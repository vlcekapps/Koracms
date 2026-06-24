<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$lintPhpPath = __DIR__ . DIRECTORY_SEPARATOR . 'lint_php.php';

function lintPhpSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function lintPhpSelfTestWriteTextFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        lintPhpSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        lintPhpSelfTestFail('Cannot write file: ' . $path);
    }
}

function lintPhpSelfTestRemoveTree(string $path): void
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

            lintPhpSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runLintPhpSelfTestCommand(array $command, string $cwd): array
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
        lintPhpSelfTestFail('Cannot start command: ' . implode(' ', $command));
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
 * @param array<string,string> $files
 * @return array{exitCode:int, output:string}
 */
function runLintPhpWithFixture(array $files): array
{
    global $projectRoot, $lintPhpPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_lint_php_'
        . bin2hex(random_bytes(6));

    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            lintPhpSelfTestFail('Cannot create temp directory: ' . $tempRoot);
        }

        foreach ($files as $relativePath => $contents) {
            lintPhpSelfTestWriteTextFile(
                $tempRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                $contents
            );
        }

        return runLintPhpSelfTestCommand(
            [PHP_BINARY, $lintPhpPath, $tempRoot],
            $projectRoot
        );
    } finally {
        lintPhpSelfTestRemoveTree($tempRoot);
    }
}

/**
 * @param array<string,string> $files
 */
function assertLintPhpPasses(string $label, array $files): void
{
    $result = runLintPhpWithFixture($files);
    if ($result['exitCode'] !== 0) {
        lintPhpSelfTestFail($label . ' should pass PHP lint.' . PHP_EOL . $result['output']);
    }
}

/**
 * @param array<string,string> $files
 */
function assertLintPhpFails(string $label, array $files, string $expectedOutput): void
{
    $result = runLintPhpWithFixture($files);
    if ($result['exitCode'] === 0) {
        lintPhpSelfTestFail($label . ' should fail PHP lint.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        lintPhpSelfTestFail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($lintPhpPath)) {
    lintPhpSelfTestFail('PHP lint self-test cannot find lint_php.php.');
}

$validPhp = "<?php\nfunction lintPhpFixtureOk(): string\n{\n    return 'OK';\n}\n";
$brokenPhp = "<?php\nfunction lintPhpFixtureBroken( {\n";

assertLintPhpPasses(
    'Clean lint fixture',
    [
        'index.php' => $validPhp,
        'admin/tool.php' => $validPhp,
    ]
);

assertLintPhpPasses(
    'Excluded directories fixture',
    [
        'index.php' => $validPhp,
        'vendor/broken.php' => $brokenPhp,
        'dist/broken.php' => $brokenPhp,
        'uploads/broken.php' => $brokenPhp,
    ]
);

assertLintPhpFails(
    'Broken source fixture',
    [
        'index.php' => $validPhp,
        'admin/broken.php' => $brokenPhp,
    ],
    'PHP lint failed for 1 file(s).'
);

echo "PHP lint self-test OK\n";
