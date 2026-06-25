<?php

declare(strict_types=1);

$httpTestHelpersPath = __DIR__ . DIRECTORY_SEPARATOR . 'http_test_helpers.php';

/**
 * @return never
 */
function httpTestHelpersSelfTestFail(string $message): void
{
    throw new RuntimeException($message);
}

function httpTestHelpersSelfTestWriteTextFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        httpTestHelpersSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        httpTestHelpersSelfTestFail('Cannot write file: ' . $path);
    }
}

function httpTestHelpersSelfTestRemoveTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }

    $items = scandir($path);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            httpTestHelpersSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

function httpTestHelpersSelfTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        httpTestHelpersSelfTestFail($message);
    }
}

/**
 * @param array{status:string,headers:array<int,string>,body:string} $response
 */
function assertHttpHelperStatus(array $response, string $expectedStatus, string $label): void
{
    httpTestHelpersSelfTestAssert(
        str_contains($response['status'], $expectedStatus),
        $label . ' returned unexpected status: ' . $response['status']
    );
}

/**
 * @return int<1, max>
 */
function httpTestHelpersSelfTestAvailablePort(): int
{
    $socket = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if (!is_resource($socket)) {
        httpTestHelpersSelfTestFail('Cannot reserve an HTTP self-test port: ' . $errorMessage . ' (' . $errorCode . ')');
    }

    $name = stream_socket_get_name($socket, false);
    fclose($socket);
    if (!is_string($name)) {
        httpTestHelpersSelfTestFail('Cannot detect reserved HTTP self-test port.');
    }

    $separator = strrpos($name, ':');
    if ($separator === false) {
        httpTestHelpersSelfTestFail('Cannot parse reserved HTTP self-test port: ' . $name);
    }

    $port = (int) substr($name, $separator + 1);
    if ($port < 1) {
        httpTestHelpersSelfTestFail('Reserved HTTP self-test port is invalid: ' . $name);
    }

    return $port;
}

/**
 * @return array{process:resource,baseUrl:string,logPath:string}
 */
function startHttpTestHelpersSelfTestServer(string $documentRoot, string $routerPath, string $logPath): array
{
    $port = httpTestHelpersSelfTestAvailablePort();
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['file', $logPath, 'a'],
        2 => ['file', $logPath, 'a'],
    ];

    $process = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, $routerPath],
        $descriptorSpec,
        $pipes,
        $documentRoot,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        httpTestHelpersSelfTestFail('Cannot start HTTP helper self-test server.');
    }

    if (isset($pipes[0]) && is_resource($pipes[0])) {
        fclose($pipes[0]);
    }

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errorCode, $errorMessage, 0.1);
        if (is_resource($socket)) {
            fclose($socket);

            return [
                'process' => $process,
                'baseUrl' => 'http://127.0.0.1:' . $port,
                'logPath' => $logPath,
            ];
        }

        usleep(100000);
    }

    $status = proc_get_status($process);
    $log = is_file($logPath) ? (string) file_get_contents($logPath) : '';
    proc_terminate($process);
    proc_close($process);

    httpTestHelpersSelfTestFail(
        'HTTP helper self-test server did not start.'
        . PHP_EOL
        . 'Running: ' . ($status['running'] ? 'yes' : 'no')
        . PHP_EOL
        . ($log !== '' ? $log : '(no server log)')
    );
}

/**
 * @param resource $process
 */
function stopHttpTestHelpersSelfTestServer($process): void
{
    $status = proc_get_status($process);
    $pid = $status['pid'];

    if (PHP_OS_FAMILY === 'Windows' && $pid > 0) {
        proc_terminate($process);
        usleep(100000);
        exec('taskkill /F /T /PID ' . $pid . ' >NUL 2>NUL');

        return;
    }

    if ($status['running']) {
        proc_terminate($process);
        for ($attempt = 0; $attempt < 20; $attempt++) {
            usleep(100000);
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
        }

    }

    proc_close($process);
}

function httpTestHelpersSelfTestRouterSource(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    $path = '/';
}

if ($path === '/get') {
    header('X-Helper-Fixture: get');
    echo 'GET|' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . (string) ($_SERVER['HTTP_COOKIE'] ?? '');
    return true;
}

if ($path === '/redirect') {
    header('Location: /get', true, 302);
    echo 'redirect';
    return true;
}

if ($path === '/post') {
    echo 'POST|'
        . (string) ($_POST['name'] ?? '')
        . '|'
        . (string) ($_POST['csrf_token'] ?? '')
        . '|'
        . (string) ($_SERVER['HTTP_COOKIE'] ?? '');
    return true;
}

if ($path === '/raw') {
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $input = $length > 0 ? (string) file_get_contents('php://input', false, null, 0, $length) : '';
    echo 'RAW|' . (string) ($_SERVER['CONTENT_TYPE'] ?? '') . '|' . $input;
    return true;
}

if ($path === '/raw-method') {
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $input = $length > 0 ? (string) file_get_contents('php://input', false, null, 0, $length) : '';
    echo 'RAW-METHOD|' . (string) ($_SERVER['REQUEST_METHOD'] ?? '') . '|' . (string) ($_SERVER['CONTENT_TYPE'] ?? '') . '|' . $input;
    return true;
}

if ($path === '/multipart') {
    $upload = $_FILES['upload'] ?? null;
    $filename = is_array($upload) && isset($upload['name']) && is_string($upload['name']) ? $upload['name'] : '';
    $tmpName = is_array($upload) && isset($upload['tmp_name']) && is_string($upload['tmp_name']) ? $upload['tmp_name'] : '';
    $contents = $tmpName !== '' && is_file($tmpName) ? (string) file_get_contents($tmpName) : '';
    echo 'MULTIPART|' . (string) ($_POST['title'] ?? '') . '|' . $filename . '|' . $contents;
    return true;
}

if ($path === '/hidden') {
    echo '<form><input type="hidden" name="csrf_token" value="abc&amp;123"></form>';
    return true;
}

http_response_code(404);
echo 'missing';
return true;
PHP;
}

$tempRoot = '';
$server = null;
$selfTestError = null;

try {
    if (!is_file($httpTestHelpersPath)) {
        httpTestHelpersSelfTestFail('HTTP test helpers self-test cannot find http_test_helpers.php.');
    }

    require_once $httpTestHelpersPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_http_helpers_'
        . bin2hex(random_bytes(6));

    if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
        httpTestHelpersSelfTestFail('Cannot create temp directory: ' . $tempRoot);
    }

    $sessionDir = $tempRoot . DIRECTORY_SEPARATOR . 'sessions';
    if (!mkdir($sessionDir, 0777, true) && !is_dir($sessionDir)) {
        httpTestHelpersSelfTestFail('Cannot create session directory: ' . $sessionDir);
    }
    session_save_path($sessionDir);

    $routerPath = $tempRoot . DIRECTORY_SEPARATOR . 'router.php';
    $uploadPath = $tempRoot . DIRECTORY_SEPARATOR . 'upload.txt';
    $logPath = $tempRoot . DIRECTORY_SEPARATOR . 'server.log';
    httpTestHelpersSelfTestWriteTextFile($routerPath, httpTestHelpersSelfTestRouterSource());
    httpTestHelpersSelfTestWriteTextFile($uploadPath, 'uploaded content');

    $server = startHttpTestHelpersSelfTestServer($tempRoot, $routerPath, $logPath);
    $baseUrl = $server['baseUrl'];

    $getResponse = fetchUrl($baseUrl . '/get', 'first=1', 0, 'KoraHelperSelfTest/1.0');
    assertHttpHelperStatus($getResponse, '200', 'GET helper fixture');
    httpTestHelpersSelfTestAssert(
        $getResponse['body'] === 'GET|KoraHelperSelfTest/1.0|first=1',
        'GET helper did not send expected user agent and cookie.'
    );

    $redirectResponse = fetchUrl($baseUrl . '/redirect', '', 0);
    assertHttpHelperStatus($redirectResponse, '302', 'Redirect helper fixture');
    httpTestHelpersSelfTestAssert(
        responseLocationHeaderValue($redirectResponse['headers']) === '/get',
        'responseLocationHeaderValue did not extract redirect target.'
    );
    httpTestHelpersSelfTestAssert(
        responseHasLocationHeader($redirectResponse['headers'], '/get', $baseUrl),
        'responseHasLocationHeader did not match relative redirect target.'
    );

    $followedRedirectResponse = fetchUrl($baseUrl . '/get', '', 2);
    assertHttpHelperStatus($followedRedirectResponse, '200', 'Second GET helper fixture');
    httpTestHelpersSelfTestAssert(
        str_starts_with($followedRedirectResponse['body'], 'GET|'),
        'fetchUrl did not return the expected body for a second request.'
    );

    $sessionId = 'koracmshelper' . bin2hex(random_bytes(4));
    session_id($sessionId);
    session_start();
    $_SESSION['csrf_token'] = 'fresh-csrf-token';
    session_write_close();

    $postResponse = postUrl(
        $baseUrl . '/post',
        [
            'name' => 'Pavel Vlcek',
            'csrf_token' => 'stale-csrf-token',
        ],
        'PHPSESSID=' . $sessionId,
        0
    );
    assertHttpHelperStatus($postResponse, '200', 'POST helper fixture');
    httpTestHelpersSelfTestAssert(
        $postResponse['body'] === 'POST|Pavel Vlcek|fresh-csrf-token|PHPSESSID=' . $sessionId,
        'postUrl did not send form fields, cookie or refreshed CSRF token. Body: ' . $postResponse['body']
    );

    $rawResponse = postRawUrl(
        $baseUrl . '/raw',
        '{"ok":true}',
        'application/json; charset=UTF-8',
        '',
        0
    );
    assertHttpHelperStatus($rawResponse, '200', 'Raw POST helper fixture');
    httpTestHelpersSelfTestAssert(
        $rawResponse['body'] === 'RAW|application/json; charset=UTF-8|{"ok":true}',
        'postRawUrl did not send raw body and content type. Body: ' . $rawResponse['body']
    );

    $customMethodResponse = requestRawUrl(
        'PUT',
        $baseUrl . '/raw-method',
        'custom-body',
        'text/plain; charset=UTF-8',
        '',
        0
    );
    assertHttpHelperStatus($customMethodResponse, '200', 'Custom raw method helper fixture');
    httpTestHelpersSelfTestAssert(
        $customMethodResponse['body'] === 'RAW-METHOD|PUT|text/plain; charset=UTF-8|custom-body',
        'requestRawUrl did not send custom method, raw body and content type. Body: ' . $customMethodResponse['body']
    );

    $multipartResponse = postMultipartUrl(
        $baseUrl . '/multipart',
        ['title' => 'Attachment'],
        [
            'upload' => [
                'path' => $uploadPath,
                'filename' => 'file.txt',
                'type' => 'text/plain',
            ],
        ],
        '',
        0
    );
    assertHttpHelperStatus($multipartResponse, '200', 'Multipart helper fixture');
    httpTestHelpersSelfTestAssert(
        $multipartResponse['body'] === 'MULTIPART|Attachment|file.txt|uploaded content',
        'postMultipartUrl did not send multipart field and file payload. Body: ' . $multipartResponse['body']
    );

    $hiddenResponse = fetchUrl($baseUrl . '/hidden', '', 0);
    assertHttpHelperStatus($hiddenResponse, '200', 'Hidden input helper fixture');
    httpTestHelpersSelfTestAssert(
        extractHiddenInputValue($hiddenResponse['body'], 'csrf_token') === 'abc&123',
        'extractHiddenInputValue did not decode hidden input value.'
    );

    httpTestHelpersSelfTestAssert(
        responseMergeCookies(
            [
                'HTTP/1.1 200 OK',
                'Set-Cookie: b=2; Path=/',
                'Set-Cookie: a=3; Path=/',
            ],
            'a=1'
        ) === 'a=3; b=2',
        'responseMergeCookies did not merge and replace cookies predictably.'
    );

    $primedSession = koraPrimeTestSession(['user_id' => 123], 'koraprime' . bin2hex(random_bytes(4)));
    httpTestHelpersSelfTestAssert(
        str_starts_with($primedSession['cookie'], 'PHPSESSID=')
        && $primedSession['id'] !== ''
        && $primedSession['csrf'] === '',
        'koraPrimeTestSession did not return expected session metadata.'
    );
} catch (Throwable $exception) {
    $selfTestError = $exception;
} finally {
    if ($server !== null) {
        stopHttpTestHelpersSelfTestServer($server['process']);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if ($tempRoot !== '') {
        httpTestHelpersSelfTestRemoveTree($tempRoot);
    }
}

if ($selfTestError !== null) {
    fwrite(STDERR, $selfTestError->getMessage() . PHP_EOL);
    exit(1);
}

echo "HTTP test helpers self-test OK\n";
