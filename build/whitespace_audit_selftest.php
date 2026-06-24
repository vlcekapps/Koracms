<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$whitespaceAuditPath = __DIR__ . DIRECTORY_SEPARATOR . 'whitespace_audit.php';

function whitespaceAuditSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function whitespaceAuditSelfTestWriteFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        whitespaceAuditSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        whitespaceAuditSelfTestFail('Cannot write file: ' . $path);
    }
}

function whitespaceAuditSelfTestRemoveTree(string $path): void
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

            whitespaceAuditSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runWhitespaceAuditSelfTestCommand(array $command, string $cwd): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(
        $command,
        $descriptorSpec,
        $pipes,
        $cwd,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        whitespaceAuditSelfTestFail('Cannot start command: ' . implode(' ', $command));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exitCode' => (int)$exitCode,
        'output' => trim(
            (is_string($stdout) ? $stdout : '')
            . (is_string($stderr) && $stderr !== '' ? PHP_EOL . $stderr : '')
        ),
    ];
}

/**
 * @return array<string,string>
 */
function validWhitespaceAuditFixture(): array
{
    return [
        'README.md' => "# Kora CMS\n\nČistý text.\n",
        'admin/example.php' => "<?php\necho 'OK';\n",
        '.htaccess' => "Options -Indexes\n",
        'assets/app.js' => "const label = 'OK';\n",
        'vendor/ignored.php' => "<?php\necho 'ignored'; " . "\n",
        'dist/ignored.txt' => "ignored without final newline",
        'uploads/.htaccess' => "ignored upload guard " . "\n",
    ];
}

/**
 * @param array<string,string> $files
 * @return array{exitCode:int, output:string}
 */
function runWhitespaceAuditWithFixture(array $files): array
{
    global $projectRoot, $whitespaceAuditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_whitespace_'
        . bin2hex(random_bytes(6));

    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            whitespaceAuditSelfTestFail('Cannot create temp directory: ' . $tempRoot);
        }

        $gitInitResult = runWhitespaceAuditSelfTestCommand(['git', 'init', '--quiet'], $tempRoot);
        if ($gitInitResult['exitCode'] !== 0) {
            whitespaceAuditSelfTestFail('Cannot initialize fixture git repository.' . PHP_EOL . $gitInitResult['output']);
        }

        foreach ($files as $relativePath => $contents) {
            whitespaceAuditSelfTestWriteFile(
                $tempRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                $contents
            );
        }

        $gitAddResult = runWhitespaceAuditSelfTestCommand(['git', 'add', '-f', '--', '.'], $tempRoot);
        if ($gitAddResult['exitCode'] !== 0) {
            whitespaceAuditSelfTestFail('Cannot stage fixture files.' . PHP_EOL . $gitAddResult['output']);
        }

        return runWhitespaceAuditSelfTestCommand(
            [PHP_BINARY, $whitespaceAuditPath, $tempRoot],
            $projectRoot
        );
    } finally {
        whitespaceAuditSelfTestRemoveTree($tempRoot);
    }
}

/**
 * @param array<string,string> $files
 */
function assertWhitespaceAuditPasses(string $label, array $files): void
{
    $result = runWhitespaceAuditWithFixture($files);
    if ($result['exitCode'] !== 0) {
        whitespaceAuditSelfTestFail($label . ' should pass whitespace audit.' . PHP_EOL . $result['output']);
    }
}

/**
 * @param array<string,string> $files
 */
function assertWhitespaceAuditFails(string $label, array $files, string $expectedOutput): void
{
    $result = runWhitespaceAuditWithFixture($files);
    if ($result['exitCode'] === 0) {
        whitespaceAuditSelfTestFail($label . ' should fail whitespace audit.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        whitespaceAuditSelfTestFail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($whitespaceAuditPath)) {
    whitespaceAuditSelfTestFail('Whitespace audit self-test cannot find whitespace_audit.php.');
}

$validFiles = validWhitespaceAuditFixture();

assertWhitespaceAuditPasses('Clean whitespace fixture', $validFiles);

$trailingSpaceFiles = $validFiles;
$trailingSpaceFiles['README.md'] = "# Kora CMS\n\nŘádek s mezerou" . " \n";
assertWhitespaceAuditFails(
    'Trailing space guard',
    $trailingSpaceFiles,
    'README.md:3: trailing whitespace'
);

$trailingTabFiles = $validFiles;
$trailingTabFiles['assets/app.js'] = "const label = 'OK';" . "\t\n";
assertWhitespaceAuditFails(
    'Trailing tab guard',
    $trailingTabFiles,
    'assets/app.js:1: trailing whitespace'
);

$missingFinalNewlineFiles = $validFiles;
$missingFinalNewlineFiles['docs/admin-guide.md'] = '# Admin guide';
assertWhitespaceAuditFails(
    'Missing final newline guard',
    $missingFinalNewlineFiles,
    'docs/admin-guide.md: missing final newline'
);

$trackedDotfileFiles = $validFiles;
$trackedDotfileFiles['.gitignore'] = "vendor/\nnode_modules/" . " \n";
assertWhitespaceAuditFails(
    'Tracked dotfile guard',
    $trackedDotfileFiles,
    '.gitignore:2: trailing whitespace'
);

echo "Whitespace audit self-test OK\n";
