<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$sourceEncodingAuditPath = __DIR__ . DIRECTORY_SEPARATOR . 'source_encoding_audit.php';

function sourceEncodingAuditSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function sourceEncodingAuditSelfTestWriteFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        sourceEncodingAuditSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        sourceEncodingAuditSelfTestFail('Cannot write file: ' . $path);
    }
}

function sourceEncodingAuditSelfTestRemoveTree(string $path): void
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

            sourceEncodingAuditSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runSourceEncodingAuditSelfTestCommand(array $command, string $cwd): array
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
        sourceEncodingAuditSelfTestFail('Cannot start command: ' . implode(' ', $command));
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
function validSourceEncodingFixture(): array
{
    return [
        'README.md' => "# Kora CMS\n\nČeský text zůstává v UTF-8.\n",
        'admin/example.php' => "<?php\necho 'Příliš žluťoučký kůň';\n",
        'assets/app.js' => "const label = 'Živý filtr';\n",
        'build/tool.ps1' => "\xEF\xBB\xBFWrite-Output 'PowerShell BOM je povolený'\n",
        'uploads/.htaccess' => "# Upload guard\nRequire all denied\n",
        'vendor/ignored.php' => pack('C*', 0xC3, 0x28),
        'dist/ignored.txt' => pack('C*', 0xC3, 0x28),
    ];
}

/**
 * @param array<string,string> $files
 * @return array{exitCode:int, output:string}
 */
function runSourceEncodingAuditWithFixture(array $files): array
{
    global $projectRoot, $sourceEncodingAuditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_source_encoding_'
        . bin2hex(random_bytes(6));

    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            sourceEncodingAuditSelfTestFail('Cannot create temp directory: ' . $tempRoot);
        }

        $gitInitResult = runSourceEncodingAuditSelfTestCommand(['git', 'init', '--quiet'], $tempRoot);
        if ($gitInitResult['exitCode'] !== 0) {
            sourceEncodingAuditSelfTestFail('Cannot initialize fixture git repository.' . PHP_EOL . $gitInitResult['output']);
        }

        foreach ($files as $relativePath => $contents) {
            sourceEncodingAuditSelfTestWriteFile(
                $tempRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                $contents
            );
        }

        $gitAddResult = runSourceEncodingAuditSelfTestCommand(['git', 'add', '-f', '--', '.'], $tempRoot);
        if ($gitAddResult['exitCode'] !== 0) {
            sourceEncodingAuditSelfTestFail('Cannot stage fixture files.' . PHP_EOL . $gitAddResult['output']);
        }

        return runSourceEncodingAuditSelfTestCommand(
            [PHP_BINARY, $sourceEncodingAuditPath, $tempRoot],
            $projectRoot
        );
    } finally {
        sourceEncodingAuditSelfTestRemoveTree($tempRoot);
    }
}

/**
 * @param array<string,string> $files
 */
function assertSourceEncodingAuditPasses(string $label, array $files): void
{
    $result = runSourceEncodingAuditWithFixture($files);
    if ($result['exitCode'] !== 0) {
        sourceEncodingAuditSelfTestFail($label . ' should pass source encoding audit.' . PHP_EOL . $result['output']);
    }
}

/**
 * @param array<string,string> $files
 */
function assertSourceEncodingAuditFails(string $label, array $files, string $expectedOutput): void
{
    $result = runSourceEncodingAuditWithFixture($files);
    if ($result['exitCode'] === 0) {
        sourceEncodingAuditSelfTestFail($label . ' should fail source encoding audit.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        sourceEncodingAuditSelfTestFail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($sourceEncodingAuditPath)) {
    sourceEncodingAuditSelfTestFail('Source encoding audit self-test cannot find source_encoding_audit.php.');
}

$validFiles = validSourceEncodingFixture();

assertSourceEncodingAuditPasses('Clean UTF-8 fixture', $validFiles);

$invalidUtf8Files = $validFiles;
$invalidUtf8Files['README.md'] = "Broken UTF-8: " . pack('C*', 0xC3, 0x28) . "\n";
assertSourceEncodingAuditFails(
    'Invalid UTF-8 guard',
    $invalidUtf8Files,
    'README.md: invalid UTF-8 encoding'
);

$unexpectedBomFiles = $validFiles;
$unexpectedBomFiles['docs/admin-guide.md'] = "\xEF\xBB\xBF# Nápověda\n";
assertSourceEncodingAuditFails(
    'Unexpected BOM guard',
    $unexpectedBomFiles,
    'docs/admin-guide.md: unexpected UTF-8 BOM'
);

$uploadsHtaccessFiles = $validFiles;
$uploadsHtaccessFiles['uploads/.htaccess'] = pack('C*', 0xC3, 0x28);
assertSourceEncodingAuditFails(
    'Tracked uploads htaccess guard',
    $uploadsHtaccessFiles,
    'uploads/.htaccess: invalid UTF-8 encoding'
);

echo "Source encoding audit self-test OK\n";
