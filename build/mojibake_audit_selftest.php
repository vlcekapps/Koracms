<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$mojibakeAuditPath = __DIR__ . DIRECTORY_SEPARATOR . 'mojibake_audit.php';

function mojibakeAuditSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function mojibakeAuditSelfTestFragment(string $hex): string
{
    $fragment = hex2bin($hex);
    if (!is_string($fragment) || $fragment === '') {
        mojibakeAuditSelfTestFail('Cannot build mojibake fixture fragment.');
    }

    return $fragment;
}

function mojibakeAuditSelfTestWriteFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        mojibakeAuditSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        mojibakeAuditSelfTestFail('Cannot write file: ' . $path);
    }
}

function mojibakeAuditSelfTestRemoveTree(string $path): void
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

            mojibakeAuditSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runMojibakeAuditSelfTestCommand(array $command, string $cwd): array
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
        mojibakeAuditSelfTestFail('Cannot start command: ' . implode(' ', $command));
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
function validMojibakeAuditFixture(): array
{
    return [
        'README.md' => "# Kora CMS\n\nČeský text zůstává čitelný.\n",
        'admin/example.php' => "<?php\necho 'Příliš žluťoučký kůň';\n",
        'migrate.php' => "<?php\n"
            . '$legacyBrokenSocialLinksTitle'
            . " = '"
            . mojibakeAuditSelfTestFragment('c385')
            . "';\n",
        'uploads/.htaccess' => "# Upload guard\nRequire all denied\n",
        'vendor/ignored.md' => "Ignored " . mojibakeAuditSelfTestFragment('c385') . "\n",
        'dist/ignored.txt' => "Ignored " . mojibakeAuditSelfTestFragment('efbfbd') . "\n",
    ];
}

/**
 * @param array<string,string> $files
 * @return array{exitCode:int, output:string}
 */
function runMojibakeAuditWithFixture(array $files): array
{
    global $projectRoot, $mojibakeAuditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_mojibake_'
        . bin2hex(random_bytes(6));

    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            mojibakeAuditSelfTestFail('Cannot create temp directory: ' . $tempRoot);
        }

        $gitInitResult = runMojibakeAuditSelfTestCommand(['git', 'init', '--quiet'], $tempRoot);
        if ($gitInitResult['exitCode'] !== 0) {
            mojibakeAuditSelfTestFail('Cannot initialize fixture git repository.' . PHP_EOL . $gitInitResult['output']);
        }

        foreach ($files as $relativePath => $contents) {
            mojibakeAuditSelfTestWriteFile(
                $tempRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                $contents
            );
        }

        $gitAddResult = runMojibakeAuditSelfTestCommand(['git', 'add', '-f', '--', '.'], $tempRoot);
        if ($gitAddResult['exitCode'] !== 0) {
            mojibakeAuditSelfTestFail('Cannot stage fixture files.' . PHP_EOL . $gitAddResult['output']);
        }

        return runMojibakeAuditSelfTestCommand(
            [PHP_BINARY, $mojibakeAuditPath, $tempRoot],
            $projectRoot
        );
    } finally {
        mojibakeAuditSelfTestRemoveTree($tempRoot);
    }
}

/**
 * @param array<string,string> $files
 */
function assertMojibakeAuditPasses(string $label, array $files): void
{
    $result = runMojibakeAuditWithFixture($files);
    if ($result['exitCode'] !== 0) {
        mojibakeAuditSelfTestFail($label . ' should pass mojibake audit.' . PHP_EOL . $result['output']);
    }
}

/**
 * @param array<string,string> $files
 */
function assertMojibakeAuditFails(string $label, array $files, string $expectedOutput): void
{
    $result = runMojibakeAuditWithFixture($files);
    if ($result['exitCode'] === 0) {
        mojibakeAuditSelfTestFail($label . ' should fail mojibake audit.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        mojibakeAuditSelfTestFail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($mojibakeAuditPath)) {
    mojibakeAuditSelfTestFail('Mojibake audit self-test cannot find mojibake_audit.php.');
}

$validFiles = validMojibakeAuditFixture();

assertMojibakeAuditPasses('Clean mojibake fixture', $validFiles);

$brokenReadmeFiles = $validFiles;
$brokenReadmeFiles['README.md'] = "Titulek " . mojibakeAuditSelfTestFragment('c385') . "\n";
assertMojibakeAuditFails(
    'README mojibake guard',
    $brokenReadmeFiles,
    'README.md:1: suspicious mojibake fragment'
);

$brokenDocsFiles = $validFiles;
$brokenDocsFiles['docs/admin-guide.md'] = "První řádek\nDruhý " . mojibakeAuditSelfTestFragment('efbfbd') . "\n";
assertMojibakeAuditFails(
    'Admin guide replacement character guard',
    $brokenDocsFiles,
    'docs/admin-guide.md:2: suspicious mojibake fragment'
);

$brokenUploadsHtaccessFiles = $validFiles;
$brokenUploadsHtaccessFiles['uploads/.htaccess'] = "Require all denied " . mojibakeAuditSelfTestFragment('c383') . "\n";
assertMojibakeAuditFails(
    'Tracked uploads htaccess mojibake guard',
    $brokenUploadsHtaccessFiles,
    'uploads/.htaccess:1: suspicious mojibake fragment'
);

echo "Mojibake audit self-test OK\n";
