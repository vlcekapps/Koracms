<?php

declare(strict_types=1);

$httpTestHelpersPath = __DIR__ . DIRECTORY_SEPARATOR . 'http_test_helpers.php';
$httpServerRouterPath = __DIR__ . DIRECTORY_SEPARATOR . 'http_server_router.php';

/**
 * @return never
 */
function httpServerRouterSelfTestFail(string $message): void
{
    throw new RuntimeException($message);
}

function httpServerRouterSelfTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        httpServerRouterSelfTestFail($message);
    }
}

function httpServerRouterSelfTestWriteFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        httpServerRouterSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        httpServerRouterSelfTestFail('Cannot write file: ' . $path);
    }
}

function httpServerRouterSelfTestRemoveTree(string $path): void
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

            httpServerRouterSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @return int<1, max>
 */
function httpServerRouterSelfTestAvailablePort(): int
{
    $socket = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if (!is_resource($socket)) {
        httpServerRouterSelfTestFail('Cannot reserve HTTP router self-test port: ' . $errorMessage . ' (' . $errorCode . ')');
    }

    $name = stream_socket_get_name($socket, false);
    fclose($socket);
    if (!is_string($name)) {
        httpServerRouterSelfTestFail('Cannot detect HTTP router self-test port.');
    }

    $separator = strrpos($name, ':');
    if ($separator === false) {
        httpServerRouterSelfTestFail('Cannot parse HTTP router self-test port: ' . $name);
    }

    $port = (int) substr($name, $separator + 1);
    if ($port < 1) {
        httpServerRouterSelfTestFail('HTTP router self-test port is invalid: ' . $name);
    }

    return $port;
}

/**
 * @return array{process:resource,baseUrl:string,logPath:string}
 */
function httpServerRouterSelfTestStartServer(string $documentRoot, string $routerPath, string $logPath): array
{
    $port = httpServerRouterSelfTestAvailablePort();
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['file', $logPath, 'a'],
        2 => ['file', $logPath, 'a'],
    ];

    $process = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $documentRoot, $routerPath],
        $descriptorSpec,
        $pipes,
        $documentRoot,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        httpServerRouterSelfTestFail('Cannot start HTTP router self-test server.');
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

    httpServerRouterSelfTestFail(
        'HTTP router self-test server did not start.'
        . PHP_EOL
        . 'Running: ' . ($status['running'] ? 'yes' : 'no')
        . PHP_EOL
        . ($log !== '' ? $log : '(no server log)')
    );
}

/**
 * @param resource $process
 */
function httpServerRouterSelfTestStopServer($process): void
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

function httpServerRouterSelfTestRouteFixture(string $label): string
{
    $source = <<<'PHP'
<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'label' => '__LABEL__',
    'script_name' => (string) ($_SERVER['SCRIPT_NAME'] ?? ''),
    'php_self' => (string) ($_SERVER['PHP_SELF'] ?? ''),
    'get' => $_GET,
    'request' => $_REQUEST,
], JSON_UNESCAPED_SLASHES);
PHP
    ;

    return str_replace('__LABEL__', $label, $source);
}

/**
 * @return array{label:string,script_name:string,php_self:string,get:array<string,mixed>,request:array<string,mixed>}
 */
function httpServerRouterSelfTestFetchJson(string $url): array
{
    $response = fetchUrl($url, '', 0, 'KoraRouterSelfTest/1.0');
    httpServerRouterSelfTestAssert(
        str_contains($response['status'], '200'),
        'Expected 200 response for ' . $url . ', got: ' . $response['status'] . ' body: ' . $response['body']
    );

    $decoded = json_decode($response['body'], true);
    httpServerRouterSelfTestAssert(is_array($decoded), 'Response is not JSON for ' . $url . ': ' . $response['body']);

    return [
        'label' => isset($decoded['label']) && is_string($decoded['label']) ? $decoded['label'] : '',
        'script_name' => isset($decoded['script_name']) && is_string($decoded['script_name']) ? $decoded['script_name'] : '',
        'php_self' => isset($decoded['php_self']) && is_string($decoded['php_self']) ? $decoded['php_self'] : '',
        'get' => isset($decoded['get']) && is_array($decoded['get']) ? $decoded['get'] : [],
        'request' => isset($decoded['request']) && is_array($decoded['request']) ? $decoded['request'] : [],
    ];
}

/**
 * @param array{label:string,script_name:string,php_self:string,get:array<string,mixed>,request:array<string,mixed>} $route
 */
function httpServerRouterSelfTestAssertRoute(array $route, string $label, string $scriptName): void
{
    httpServerRouterSelfTestAssert($route['label'] === $label, 'Route label mismatch for ' . $label);
    httpServerRouterSelfTestAssert($route['script_name'] === $scriptName, 'SCRIPT_NAME mismatch for ' . $label . ': ' . $route['script_name']);
    httpServerRouterSelfTestAssert($route['php_self'] === $scriptName, 'PHP_SELF mismatch for ' . $label . ': ' . $route['php_self']);
}

if (!is_file($httpTestHelpersPath)) {
    httpServerRouterSelfTestFail('HTTP server router self-test cannot find http_test_helpers.php.');
}

if (!is_file($httpServerRouterPath)) {
    httpServerRouterSelfTestFail('HTTP server router self-test cannot find http_server_router.php.');
}

require_once $httpTestHelpersPath;

$tempRoot = '';
$server = null;
$selfTestError = null;

try {
    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_http_router_'
        . bin2hex(random_bytes(6));
    $buildDir = $tempRoot . DIRECTORY_SEPARATOR . 'build';

    if (!mkdir($buildDir, 0777, true) && !is_dir($buildDir)) {
        httpServerRouterSelfTestFail('Cannot create temp build directory: ' . $buildDir);
    }

    $routerCopyPath = $buildDir . DIRECTORY_SEPARATOR . 'http_server_router.php';
    $routerSource = file_get_contents($httpServerRouterPath);
    if (!is_string($routerSource)) {
        httpServerRouterSelfTestFail('Cannot read http_server_router.php.');
    }
    httpServerRouterSelfTestWriteFile($routerCopyPath, $routerSource);

    $fixtures = [
        'index.php' => 'home',
        'robots.php' => 'robots',
        'sitemap.php' => 'sitemap',
        'authors/index.php' => 'authors',
        'changelog.php' => 'changelog',
        'author.php' => 'author',
        'blog/article.php' => 'blog-article',
        'chat/index.php' => 'chat-index',
        'chat/message.php' => 'chat-message',
        'events/index.php' => 'events-index',
        'events/ics.php' => 'event-ics',
        'faq/index.php' => 'faq-index',
        'forms/index.php' => 'form',
        'podcast/episode.php' => 'podcast-episode',
        'blog_router.php' => 'blog-router',
    ];

    foreach ($fixtures as $script => $label) {
        httpServerRouterSelfTestWriteFile(
            $tempRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $script),
            httpServerRouterSelfTestRouteFixture($label)
        );
    }

    httpServerRouterSelfTestWriteFile(
        $tempRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'app.css',
        "static ok\n"
    );

    $logPath = $tempRoot . DIRECTORY_SEPARATOR . 'server.log';
    $server = httpServerRouterSelfTestStartServer($tempRoot, $routerCopyPath, $logPath);
    $baseUrl = $server['baseUrl'];

    httpServerRouterSelfTestAssertRoute(httpServerRouterSelfTestFetchJson($baseUrl . '/'), 'home', '/index.php');
    httpServerRouterSelfTestAssertRoute(httpServerRouterSelfTestFetchJson($baseUrl . '/robots.txt'), 'robots', '/robots.php');
    httpServerRouterSelfTestAssertRoute(httpServerRouterSelfTestFetchJson($baseUrl . '/sitemap.xml'), 'sitemap', '/sitemap.php');
    httpServerRouterSelfTestAssertRoute(httpServerRouterSelfTestFetchJson($baseUrl . '/authors'), 'authors', '/authors/index.php');
    httpServerRouterSelfTestAssertRoute(httpServerRouterSelfTestFetchJson($baseUrl . '/changelog'), 'changelog', '/changelog.php');

    $authorRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/author/pavel-vlcek?source=test');
    httpServerRouterSelfTestAssertRoute($authorRoute, 'author', '/author.php');
    httpServerRouterSelfTestAssert(($authorRoute['get']['slug'] ?? '') === 'pavel-vlcek', 'Author slug was not routed.');
    httpServerRouterSelfTestAssert(($authorRoute['get']['source'] ?? '') === 'test', 'Author query string was not preserved.');

    $blogArticleRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/blog/testovaci-clanek');
    httpServerRouterSelfTestAssertRoute($blogArticleRoute, 'blog-article', '/blog/article.php');
    httpServerRouterSelfTestAssert(($blogArticleRoute['get']['slug'] ?? '') === 'testovaci-clanek', 'Blog article slug was not routed.');

    $blogPageRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/snd/stranka/o-projektu?preview=1');
    httpServerRouterSelfTestAssertRoute($blogPageRoute, 'blog-router', '/blog_router.php');
    httpServerRouterSelfTestAssert(($blogPageRoute['get']['blog_slug'] ?? '') === 'snd', 'Blog page blog_slug was not routed.');
    httpServerRouterSelfTestAssert(($blogPageRoute['get']['page_slug'] ?? '') === 'o-projektu', 'Blog page page_slug was not routed.');
    httpServerRouterSelfTestAssert(($blogPageRoute['get']['preview'] ?? '') === '1', 'Blog page query string was not preserved.');

    $blogSeriesRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/snd/serie/vikend-s-veterany?preview=1');
    httpServerRouterSelfTestAssertRoute($blogSeriesRoute, 'blog-router', '/blog_router.php');
    httpServerRouterSelfTestAssert(($blogSeriesRoute['get']['blog_slug'] ?? '') === 'snd', 'Blog series blog_slug was not routed.');
    httpServerRouterSelfTestAssert(($blogSeriesRoute['get']['series_slug'] ?? '') === 'vikend-s-veterany', 'Blog series series_slug was not routed.');
    httpServerRouterSelfTestAssert(($blogSeriesRoute['get']['preview'] ?? '') === '1', 'Blog series query string was not preserved.');

    $blogCategoryRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/snd/kategorie/linuxovy-koutek?preview=1');
    httpServerRouterSelfTestAssertRoute($blogCategoryRoute, 'blog-router', '/blog_router.php');
    httpServerRouterSelfTestAssert(($blogCategoryRoute['get']['blog_slug'] ?? '') === 'snd', 'Blog category blog_slug was not routed.');
    httpServerRouterSelfTestAssert(($blogCategoryRoute['get']['category_slug'] ?? '') === 'linuxovy-koutek', 'Blog category slug was not routed.');
    httpServerRouterSelfTestAssert(($blogCategoryRoute['get']['preview'] ?? '') === '1', 'Blog category query string was not preserved.');

    $blogTagRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/snd/stitky/nvda-tip?preview=1');
    httpServerRouterSelfTestAssertRoute($blogTagRoute, 'blog-router', '/blog_router.php');
    httpServerRouterSelfTestAssert(($blogTagRoute['get']['blog_slug'] ?? '') === 'snd', 'Blog tag blog_slug was not routed.');
    httpServerRouterSelfTestAssert(($blogTagRoute['get']['tag_slug'] ?? '') === 'nvda-tip', 'Blog tag slug was not routed.');
    httpServerRouterSelfTestAssert(($blogTagRoute['get']['preview'] ?? '') === '1', 'Blog tag query string was not preserved.');

    $blogArchiveRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/snd/archiv/2026/07?preview=1');
    httpServerRouterSelfTestAssertRoute($blogArchiveRoute, 'blog-router', '/blog_router.php');
    httpServerRouterSelfTestAssert(($blogArchiveRoute['get']['blog_slug'] ?? '') === 'snd', 'Blog archive blog_slug was not routed.');
    httpServerRouterSelfTestAssert(($blogArchiveRoute['get']['archive_year'] ?? '') === '2026', 'Blog archive year was not routed.');
    httpServerRouterSelfTestAssert(($blogArchiveRoute['get']['archive_month'] ?? '') === '07', 'Blog archive month was not routed.');
    httpServerRouterSelfTestAssert(($blogArchiveRoute['get']['preview'] ?? '') === '1', 'Blog archive query string was not preserved.');

    $blogRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/snd/vikend-s-veterany');
    httpServerRouterSelfTestAssertRoute($blogRoute, 'blog-router', '/blog_router.php');
    httpServerRouterSelfTestAssert(($blogRoute['get']['blog_slug'] ?? '') === 'snd', 'Blog route blog_slug was not routed.');
    httpServerRouterSelfTestAssert(($blogRoute['get']['slug'] ?? '') === 'vikend-s-veterany', 'Blog route slug was not routed.');

    $eventIcsRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/events/setkani.ics');
    httpServerRouterSelfTestAssertRoute($eventIcsRoute, 'event-ics', '/events/ics.php');
    httpServerRouterSelfTestAssert(($eventIcsRoute['get']['slug'] ?? '') === 'setkani', 'Event ICS slug was not routed.');

    $eventTypeRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/events/typ/prednasky?scope=all');
    httpServerRouterSelfTestAssertRoute($eventTypeRoute, 'events-index', '/events/index.php');
    httpServerRouterSelfTestAssert(($eventTypeRoute['get']['type_slug'] ?? '') === 'prednasky', 'Event type slug was not routed.');
    httpServerRouterSelfTestAssert(($eventTypeRoute['get']['scope'] ?? '') === 'all', 'Event type query string was not preserved.');

    $faqCategoryRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/faq/kategorie/instalace?zobrazeni=inline');
    httpServerRouterSelfTestAssertRoute($faqCategoryRoute, 'faq-index', '/faq/index.php');
    httpServerRouterSelfTestAssert(($faqCategoryRoute['get']['category_slug'] ?? '') === 'instalace', 'FAQ category slug was not routed.');
    httpServerRouterSelfTestAssert(($faqCategoryRoute['get']['zobrazeni'] ?? '') === 'inline', 'FAQ category query string was not preserved.');

    $chatTopicRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/chat/tema/obecne?preview=1');
    httpServerRouterSelfTestAssertRoute($chatTopicRoute, 'chat-index', '/chat/index.php');
    httpServerRouterSelfTestAssert(($chatTopicRoute['get']['topic_slug'] ?? '') === 'obecne', 'Chat topic slug was not routed.');
    httpServerRouterSelfTestAssert(($chatTopicRoute['get']['preview'] ?? '') === '1', 'Chat topic query string was not preserved.');

    $chatMessageRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/chat/zprava/42?preview=1');
    httpServerRouterSelfTestAssertRoute($chatMessageRoute, 'chat-message', '/chat/message.php');
    httpServerRouterSelfTestAssert(($chatMessageRoute['get']['id'] ?? '') === '42', 'Chat message id was not routed.');
    httpServerRouterSelfTestAssert(($chatMessageRoute['get']['preview'] ?? '') === '1', 'Chat message query string was not preserved.');

    $podcastEpisodeRoute = httpServerRouterSelfTestFetchJson($baseUrl . '/podcast/porad/epizoda');
    httpServerRouterSelfTestAssertRoute($podcastEpisodeRoute, 'podcast-episode', '/podcast/episode.php');
    httpServerRouterSelfTestAssert(($podcastEpisodeRoute['get']['show'] ?? '') === 'porad', 'Podcast show was not routed.');
    httpServerRouterSelfTestAssert(($podcastEpisodeRoute['get']['slug'] ?? '') === 'epizoda', 'Podcast episode slug was not routed.');

    $staticResponse = fetchUrl($baseUrl . '/assets/app.css', '', 0, 'KoraRouterSelfTest/1.0');
    httpServerRouterSelfTestAssert(str_contains($staticResponse['status'], '200'), 'Static file did not return 200.');
    httpServerRouterSelfTestAssert($staticResponse['body'] === "static ok\n", 'Static file was not served by built-in server.');

    foreach (['/.env', '/composer.json', '/.claude/local.json', '/.codex/settings.json', '/.cursor/rules.md', '/node_modules/package/index.js', '/uploads/media/logo.svg', '/uploads/forms/private.txt', '/foo/%2e%2e/config.php'] as $protectedPath) {
        $protectedResponse = fetchUrl($baseUrl . $protectedPath, '', 0, 'KoraRouterSelfTest/1.0');
        httpServerRouterSelfTestAssert(
            str_contains($protectedResponse['status'], '403'),
            'Protected path did not return 403: ' . $protectedPath . ' status: ' . $protectedResponse['status']
        );
    }

    $notFoundResponse = fetchUrl($baseUrl . '/unknown/path/that/does/not/match', '', 0, 'KoraRouterSelfTest/1.0');
    httpServerRouterSelfTestAssert(
        str_contains($notFoundResponse['status'], '404'),
        'Unknown clean URL did not return 404: ' . $notFoundResponse['status']
    );
} catch (Throwable $exception) {
    $selfTestError = $exception;
} finally {
    if ($server !== null) {
        httpServerRouterSelfTestStopServer($server['process']);
    }

    if ($tempRoot !== '') {
        httpServerRouterSelfTestRemoveTree($tempRoot);
    }
}

if ($selfTestError !== null) {
    fwrite(STDERR, $selfTestError->getMessage() . PHP_EOL);
    exit(1);
}

echo "HTTP server router self-test OK\n";
