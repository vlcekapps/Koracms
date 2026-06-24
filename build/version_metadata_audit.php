<?php

declare(strict_types=1);

$projectRootArgument = $argv[1] ?? null;
$projectRoot = versionAuditProjectRoot(is_string($projectRootArgument) ? $projectRootArgument : null);
$issues = [];

function versionAuditProjectRoot(?string $override): string
{
    $candidates = [];
    if ($override !== null && trim($override) !== '') {
        $candidates[] = $override;
    }

    $environmentOverride = getenv('KORA_VERSION_METADATA_AUDIT_ROOT');
    if (is_string($environmentOverride) && trim($environmentOverride) !== '') {
        $candidates[] = $environmentOverride;
    }

    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if (is_string($resolved) && is_dir($resolved)) {
            return $resolved;
        }
    }

    return dirname(__DIR__);
}

/**
 * @param list<string> $issues
 */
function versionAuditReadFile(string $path, string $label, array &$issues): string
{
    if (!is_file($path)) {
        $issues[] = $label . ': missing file';
        return '';
    }

    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        $issues[] = $label . ': cannot read file';
        return '';
    }

    return $contents;
}

function versionAuditIsSemver(string $value): bool
{
    return preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/', $value) === 1;
}

$versionSource = versionAuditReadFile($projectRoot . '/VERSION', 'VERSION', $issues);
$dbSource = versionAuditReadFile($projectRoot . '/db.php', 'db.php', $issues);
$unitBootstrapSource = versionAuditReadFile($projectRoot . '/build/unit_test_bootstrap.php', 'build/unit_test_bootstrap.php', $issues);
$phpstanBootstrapSource = versionAuditReadFile($projectRoot . '/build/phpstan_bootstrap.php', 'build/phpstan_bootstrap.php', $issues);
$releaseScriptSource = versionAuditReadFile($projectRoot . '/build/release.ps1', 'build/release.ps1', $issues);
$releaseSmokeSource = versionAuditReadFile($projectRoot . '/build/release_smoke.php', 'build/release_smoke.php', $issues);
$readmeSource = versionAuditReadFile($projectRoot . '/README.md', 'README.md', $issues);
$adminGuideSource = versionAuditReadFile($projectRoot . '/docs/admin-guide.md', 'docs/admin-guide.md', $issues);

$version = trim($versionSource);
if ($version === '') {
    $issues[] = 'VERSION is empty';
} elseif (!versionAuditIsSemver($version)) {
    $issues[] = 'VERSION must use SemVer MAJOR.MINOR.PATCH or MAJOR.MINOR.PATCH-prerelease';
}

foreach ([
    'db.php reads runtime KORA_VERSION from VERSION' => [
        $dbSource,
        "define('KORA_VERSION', trim((string)(file_get_contents(__DIR__ . '/VERSION') ?: '0.0.0')))",
    ],
    'unit test bootstrap reads KORA_VERSION from VERSION' => [
        $unitBootstrapSource,
        "define('KORA_VERSION', trim((string)(file_get_contents(dirname(__DIR__) . '/VERSION') ?: '0.0.0')))",
    ],
    'PHPStan bootstrap uses a static KORA_VERSION fallback only for analysis' => [
        $phpstanBootstrapSource,
        "define('KORA_VERSION', '0.0.0')",
    ],
    'release script validates VERSION before bumping' => [
        $releaseScriptSource,
        'Read-VersionFile -Path $versionPath',
    ],
    'release script updates working VERSION on real release' => [
        $releaseScriptSource,
        '[System.IO.File]::WriteAllText($versionPath, $newVersion',
    ],
    'release script overrides VERSION only inside dry-run ZIP' => [
        $releaseScriptSource,
        '$packageFileOverrides["VERSION"] = $newVersion',
    ],
    'release smoke verifies generated ZIP VERSION' => [
        $releaseSmokeSource,
        'Release smoke ZIP contains an unexpected VERSION value',
    ],
    'release smoke verifies source archive VERSION' => [
        $releaseSmokeSource,
        'Source archive contains an unexpected VERSION value',
    ],
    'README documents dry-run keeping working VERSION unchanged' => [
        $readmeSource,
        'nemění pracovní `VERSION`',
    ],
    'admin guide documents dry-run keeping working VERSION unchanged' => [
        $adminGuideSource,
        'nemění pracovní `VERSION`',
    ],
] as $label => [$source, $requiredFragment]) {
    if (!str_contains($source, $requiredFragment)) {
        $issues[] = $label . ': missing fragment ' . $requiredFragment;
    }
}

if ($issues !== []) {
    echo "Version metadata audit failed:\n";
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
    exit(1);
}

echo "Version metadata audit OK\n";
