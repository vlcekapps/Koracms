<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$configSampleAuditPath = __DIR__ . DIRECTORY_SEPARATOR . 'config_sample_audit.php';

function configSampleAuditSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function configSampleAuditSelfTestWriteTextFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        configSampleAuditSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        configSampleAuditSelfTestFail('Cannot write file: ' . $path);
    }
}

function configSampleAuditSelfTestVariable(string $name): string
{
    return '$' . $name;
}

function configSampleAuditSelfTestRemoveTree(string $path): void
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

            configSampleAuditSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runConfigSampleAuditSelfTestCommand(array $command, string $cwd): array
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
        configSampleAuditSelfTestFail('Cannot start command: ' . implode(' ', $command));
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

function validConfigSampleFixture(): string
{
    return implode('', [
        "<?php\n\n",
        "// Přejmenujte tento soubor na config.php a vyplňte své údaje.\n\n",
        "// Přihlašovací údaje k databázi\n",
        configSampleAuditSelfTestVariable('server') . " = 'localhost';\n",
        configSampleAuditSelfTestVariable('user') . " = 'root';\n",
        configSampleAuditSelfTestVariable('pass') . " = '';\n",
        configSampleAuditSelfTestVariable('database') . " = 'kora';\n\n",
        "// Základní URL webu\n",
        "define('BASE_URL', '');\n\n",
        "// Privátní úložiště mimo webroot\n",
        "define('KORA_STORAGE_DIR', '');\n\n",
        "// SMTP odesílání e-mailů\n",
        "define('SMTP_HOST', 'localhost');\n",
        "define('SMTP_PORT', 25);\n",
        "define('SMTP_USER', '');\n",
        "define('SMTP_PASS', '');\n",
        "define('SMTP_SECURE', '');\n\n",
        "// GitHub issue bridge pro odpovědi formulářů\n",
        "define('GITHUB_ISSUES_TOKEN', '');\n\n",
        "// Token pro volitelný HTTP přístup ke cron.php\n",
        "// Doporučený způsob spouštění je CLI cron: php /cesta/k/webu/cron.php\n",
        "define('CRON_TOKEN', '');\n",
    ]);
}

function validConfigSampleReadmeFixture(): string
{
    return "Instalace používá config.sample.php, BASE_URL, KORA_STORAGE_DIR, SMTP a CRON_TOKEN.\n";
}

function validConfigSampleAdminGuideFixture(): string
{
    return "Kontroly spouští composer ci:basic včetně build/config_sample_audit.php pro config.sample.php.\n";
}

/**
 * @return array{exitCode:int, output:string}
 */
function runConfigSampleAuditWithFixture(
    string $configSampleSource,
    string $readmeSource,
    string $adminGuideSource
): array {
    global $projectRoot, $configSampleAuditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_config_sample_'
        . bin2hex(random_bytes(6));

    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            configSampleAuditSelfTestFail('Cannot create temp directory: ' . $tempRoot);
        }

        configSampleAuditSelfTestWriteTextFile($tempRoot . DIRECTORY_SEPARATOR . 'config.sample.php', $configSampleSource);
        configSampleAuditSelfTestWriteTextFile($tempRoot . DIRECTORY_SEPARATOR . 'README.md', $readmeSource);
        configSampleAuditSelfTestWriteTextFile(
            $tempRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'admin-guide.md',
            $adminGuideSource
        );

        return runConfigSampleAuditSelfTestCommand(
            [PHP_BINARY, $configSampleAuditPath, $tempRoot],
            $projectRoot
        );
    } finally {
        configSampleAuditSelfTestRemoveTree($tempRoot);
    }
}

function assertConfigSampleAuditPasses(
    string $label,
    string $configSampleSource,
    string $readmeSource,
    string $adminGuideSource
): void {
    $result = runConfigSampleAuditWithFixture($configSampleSource, $readmeSource, $adminGuideSource);
    if ($result['exitCode'] !== 0) {
        configSampleAuditSelfTestFail($label . ' should pass config sample audit.' . PHP_EOL . $result['output']);
    }
}

function assertConfigSampleAuditFails(
    string $label,
    string $configSampleSource,
    string $readmeSource,
    string $adminGuideSource,
    string $expectedOutput
): void {
    $result = runConfigSampleAuditWithFixture($configSampleSource, $readmeSource, $adminGuideSource);
    if ($result['exitCode'] === 0) {
        configSampleAuditSelfTestFail($label . ' should fail config sample audit.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        configSampleAuditSelfTestFail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($configSampleAuditPath)) {
    configSampleAuditSelfTestFail('Config sample audit self-test cannot find config_sample_audit.php.');
}

$validConfigSample = validConfigSampleFixture();
$validReadme = validConfigSampleReadmeFixture();
$validAdminGuide = validConfigSampleAdminGuideFixture();

assertConfigSampleAuditPasses(
    'Clean config sample fixture',
    $validConfigSample,
    $validReadme,
    $validAdminGuide
);

assertConfigSampleAuditFails(
    'Missing cron token define',
    str_replace("define('CRON_TOKEN', '');\n", '', $validConfigSample),
    $validReadme,
    $validAdminGuide,
    'config.sample.php is missing define(CRON_TOKEN)'
);

assertConfigSampleAuditFails(
    'External SMTP placeholder guard',
    str_replace("define('SMTP_HOST', 'localhost');\n", "define('SMTP_HOST', 'smtp.example.com');\n", $validConfigSample),
    $validReadme,
    $validAdminGuide,
    "config.sample.php should use safe default for SMTP_HOST: 'localhost'"
);

assertConfigSampleAuditFails(
    'GitHub token placeholder guard',
    str_replace("define('GITHUB_ISSUES_TOKEN', '');\n", "define('GITHUB_ISSUES_TOKEN', '');\n// ghp_example\n", $validConfigSample),
    $validReadme,
    $validAdminGuide,
    'config.sample.php should not contain placeholder secret or external SMTP value: ghp_'
);

assertConfigSampleAuditFails(
    'Missing explanatory GitHub copy guard',
    str_replace('GitHub issue bridge', 'GitHub odpovědi', $validConfigSample),
    $validReadme,
    $validAdminGuide,
    'config.sample.php is missing explanatory copy: GitHub issue bridge'
);

assertConfigSampleAuditFails(
    'Missing README fragment guard',
    $validConfigSample,
    str_replace('CRON_TOKEN', 'cron token', $validReadme),
    $validAdminGuide,
    'README.md is missing configuration fragment: CRON_TOKEN'
);

assertConfigSampleAuditFails(
    'Missing admin guide audit fragment guard',
    $validConfigSample,
    $validReadme,
    str_replace('build/config_sample_audit.php', 'konfigurační audit', $validAdminGuide),
    'docs/admin-guide.md is missing configuration audit fragment: build/config_sample_audit.php'
);

echo "Config sample audit self-test OK\n";
