<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$versionMetadataAuditPath = __DIR__ . DIRECTORY_SEPARATOR . 'version_metadata_audit.php';

function versionMetadataAuditSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function versionMetadataAuditSelfTestWriteTextFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        versionMetadataAuditSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        versionMetadataAuditSelfTestFail('Cannot write file: ' . $path);
    }
}

function versionMetadataAuditSelfTestRemoveTree(string $path): void
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

            versionMetadataAuditSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runVersionMetadataAuditSelfTestCommand(array $command, string $cwd): array
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
        versionMetadataAuditSelfTestFail('Cannot start command: ' . implode(' ', $command));
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
function validVersionMetadataFixture(): array
{
    return [
        'VERSION' => "1.2.3\n",
        'db.php' => <<<'PHP'
<?php
define('KORA_VERSION', trim((string)(file_get_contents(__DIR__ . '/VERSION') ?: '0.0.0')));
PHP,
        'build/unit_test_bootstrap.php' => <<<'PHP'
<?php
define('KORA_VERSION', trim((string)(file_get_contents(dirname(__DIR__) . '/VERSION') ?: '0.0.0')));
PHP,
        'build/phpstan_bootstrap.php' => <<<'PHP'
<?php
define('KORA_VERSION', '0.0.0');
PHP,
        'build/release.ps1' => <<<'POWERSHELL'
Read-VersionFile -Path $versionPath
[System.IO.File]::WriteAllText($versionPath, $newVersion, [System.Text.UTF8Encoding]::new($false))
$packageFileOverrides["VERSION"] = $newVersion
POWERSHELL,
        'build/release_smoke.php' => <<<'PHP'
<?php
$zipMessage = 'Release smoke ZIP contains an unexpected VERSION value';
$sourceMessage = 'Source archive contains an unexpected VERSION value';
PHP,
        'README.md' => "Dry-run nemění pracovní `VERSION`.\n",
        'docs/admin-guide.md' => "Dry-run nemění pracovní `VERSION`.\n",
    ];
}

/**
 * @param array<string,string> $files
 * @return array{exitCode:int, output:string}
 */
function runVersionMetadataAuditWithFixture(array $files): array
{
    global $projectRoot, $versionMetadataAuditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_version_metadata_'
        . bin2hex(random_bytes(6));

    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            versionMetadataAuditSelfTestFail('Cannot create temp directory: ' . $tempRoot);
        }

        foreach ($files as $relativePath => $contents) {
            versionMetadataAuditSelfTestWriteTextFile(
                $tempRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                $contents
            );
        }

        return runVersionMetadataAuditSelfTestCommand(
            [PHP_BINARY, $versionMetadataAuditPath, $tempRoot],
            $projectRoot
        );
    } finally {
        versionMetadataAuditSelfTestRemoveTree($tempRoot);
    }
}

/**
 * @param array<string,string> $files
 */
function assertVersionMetadataAuditPasses(string $label, array $files): void
{
    $result = runVersionMetadataAuditWithFixture($files);
    if ($result['exitCode'] !== 0) {
        versionMetadataAuditSelfTestFail($label . ' should pass version metadata audit.' . PHP_EOL . $result['output']);
    }
}

/**
 * @param array<string,string> $files
 */
function assertVersionMetadataAuditFails(string $label, array $files, string $expectedOutput): void
{
    $result = runVersionMetadataAuditWithFixture($files);
    if ($result['exitCode'] === 0) {
        versionMetadataAuditSelfTestFail($label . ' should fail version metadata audit.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        versionMetadataAuditSelfTestFail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($versionMetadataAuditPath)) {
    versionMetadataAuditSelfTestFail('Version metadata audit self-test cannot find version_metadata_audit.php.');
}

$validFiles = validVersionMetadataFixture();

assertVersionMetadataAuditPasses('Clean version metadata fixture', $validFiles);

$invalidVersionFiles = $validFiles;
$invalidVersionFiles['VERSION'] = "1.2\n";
assertVersionMetadataAuditFails(
    'Invalid SemVer guard',
    $invalidVersionFiles,
    'VERSION must use SemVer MAJOR.MINOR.PATCH or MAJOR.MINOR.PATCH-prerelease'
);

$missingRuntimeVersionFiles = $validFiles;
$missingRuntimeVersionFiles['db.php'] = "<?php\ndefine('KORA_VERSION', '1.2.3');\n";
assertVersionMetadataAuditFails(
    'Runtime version source guard',
    $missingRuntimeVersionFiles,
    'db.php reads runtime KORA_VERSION from VERSION'
);

$missingDryRunOverrideFiles = $validFiles;
$missingDryRunOverrideFiles['build/release.ps1'] = str_replace(
    '$packageFileOverrides["VERSION"] = $newVersion',
    '$packageFileOverrides = @{}',
    $missingDryRunOverrideFiles['build/release.ps1']
);
assertVersionMetadataAuditFails(
    'Dry-run ZIP override guard',
    $missingDryRunOverrideFiles,
    'release script overrides VERSION only inside dry-run ZIP'
);

$missingReleaseSmokeFiles = $validFiles;
$missingReleaseSmokeFiles['build/release_smoke.php'] = str_replace(
    'Source archive contains an unexpected VERSION value',
    'Source archive version mismatch',
    $missingReleaseSmokeFiles['build/release_smoke.php']
);
assertVersionMetadataAuditFails(
    'Source archive smoke guard',
    $missingReleaseSmokeFiles,
    'release smoke verifies source archive VERSION'
);

$missingReadmeCopyFiles = $validFiles;
$missingReadmeCopyFiles['README.md'] = "Dry-run ponechá soubor verze beze změny.\n";
assertVersionMetadataAuditFails(
    'README dry-run copy guard',
    $missingReadmeCopyFiles,
    'README documents dry-run keeping working VERSION unchanged'
);

echo "Version metadata audit self-test OK\n";
