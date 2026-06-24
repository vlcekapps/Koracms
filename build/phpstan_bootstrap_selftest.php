<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$phpstanBootstrapPath = __DIR__ . DIRECTORY_SEPARATOR . 'phpstan_bootstrap.php';

function phpstanBootstrapSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function phpstanBootstrapSelfTestWriteTextFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        phpstanBootstrapSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        phpstanBootstrapSelfTestFail('Cannot write file: ' . $path);
    }
}

/**
 * @param list<string> $command
 * @return array{exitCode:int,output:string}
 */
function runPhpstanBootstrapSelfTestCommand(array $command, string $cwd): array
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
        phpstanBootstrapSelfTestFail('Cannot start command: ' . implode(' ', $command));
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

function runPhpstanBootstrapProbe(string $probeSource): string
{
    global $projectRoot;

    $probePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_phpstan_bootstrap_'
        . bin2hex(random_bytes(6))
        . '.php';

    try {
        phpstanBootstrapSelfTestWriteTextFile($probePath, $probeSource);

        $result = runPhpstanBootstrapSelfTestCommand([PHP_BINARY, $probePath], $projectRoot);
        if ($result['exitCode'] !== 0) {
            phpstanBootstrapSelfTestFail(
                'PHPStan bootstrap probe failed.'
                . PHP_EOL
                . ($result['output'] !== '' ? $result['output'] : '(no output)')
            );
        }

        return $result['output'];
    } finally {
        if (is_file($probePath)) {
            @unlink($probePath);
        }
    }
}

function phpstanBootstrapProbeSource(string $bootstrapPath, string $body): string
{
    return "<?php\n"
        . "declare(strict_types=1);\n"
        . '$bootstrapPath = ' . var_export($bootstrapPath, true) . ";\n"
        . $body;
}

if (!is_file($phpstanBootstrapPath)) {
    phpstanBootstrapSelfTestFail('PHPStan bootstrap self-test cannot find phpstan_bootstrap.php.');
}

$defaultProbeOutput = runPhpstanBootstrapProbe(phpstanBootstrapProbeSource(
    $phpstanBootstrapPath,
    <<<'PHP'
putenv('KORA_PHPSTAN_BASE_URL=https://static.example.invalid/cms');
$_GET['id'] = '42';
$_GET['invalid'] = '0';
$_POST['id'] = '7';

require $bootstrapPath;

$checks = [
    'BASE_URL follows KORA_PHPSTAN_BASE_URL' => defined('BASE_URL') && BASE_URL === 'https://static.example.invalid/cms',
    'KORA_VERSION fallback is stable' => defined('KORA_VERSION') && KORA_VERSION === '0.0.0',
    'h helper escapes HTML' => h('<strong>"Text"</strong>') === htmlspecialchars('<strong>"Text"</strong>', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    'inputInt reads GET integers' => inputInt('get', 'id') === 42,
    'inputInt reads POST integers' => inputInt('post', 'id') === 7,
    'inputInt rejects invalid integers' => inputInt('get', 'invalid') === null,
    'bootstrap does not load DB helper' => !function_exists('db_connect'),
    'bootstrap does not load auth helper' => !function_exists('requireLogin'),
    'bootstrap does not load runtime config' => !defined('SMTP_HOST'),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, implode("\n", $failed) . "\n");
    exit(1);
}

echo "default probe OK\n";
PHP
));

if ($defaultProbeOutput !== 'default probe OK') {
    phpstanBootstrapSelfTestFail('PHPStan bootstrap default probe returned unexpected output: ' . $defaultProbeOutput);
}

$predefinedBaseUrlOutput = runPhpstanBootstrapProbe(phpstanBootstrapProbeSource(
    $phpstanBootstrapPath,
    <<<'PHP'
define('BASE_URL', 'https://predefined.example.invalid');
putenv('KORA_PHPSTAN_BASE_URL=https://ignored.example.invalid');

require $bootstrapPath;

if (BASE_URL !== 'https://predefined.example.invalid') {
    fwrite(STDERR, 'BASE_URL was overwritten by phpstan bootstrap.' . "\n");
    exit(1);
}

echo "predefined BASE_URL probe OK\n";
PHP
));

if ($predefinedBaseUrlOutput !== 'predefined BASE_URL probe OK') {
    phpstanBootstrapSelfTestFail('PHPStan bootstrap predefined BASE_URL probe returned unexpected output: ' . $predefinedBaseUrlOutput);
}

echo "PHPStan bootstrap self-test OK\n";
