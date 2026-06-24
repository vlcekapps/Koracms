<?php

declare(strict_types=1);

function releasePackageAuditProjectRoot(?string $override): string
{
    if ($override !== null && trim($override) !== '') {
        return rtrim($override, DIRECTORY_SEPARATOR . '/');
    }

    $envRoot = getenv('KORA_RELEASE_PACKAGE_AUDIT_ROOT');
    if (is_string($envRoot) && trim($envRoot) !== '') {
        return rtrim($envRoot, DIRECTORY_SEPARATOR . '/');
    }

    return dirname(__DIR__);
}

$projectRootArgument = $argv[1] ?? null;
$projectRoot = releasePackageAuditProjectRoot(is_string($projectRootArgument) ? $projectRootArgument : null);
$releaseScriptPath = $projectRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release.ps1';
$releaseSmokePath = $projectRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release_smoke.php';
$gitattributesPath = $projectRoot . DIRECTORY_SEPARATOR . '.gitattributes';
$gitignorePath = $projectRoot . DIRECTORY_SEPARATOR . '.gitignore';

$issues = [];

if (!is_file($releaseScriptPath)) {
    $issues[] = 'build/release.ps1 is missing.';
} else {
    $releaseScriptSource = (string) file_get_contents($releaseScriptPath);

    $requiredExcludedEntries = [
        '.git',
        '.github',
        '.gitignore',
        '.gitattributes',
        '.claude',
        'uploads',
        'build',
        'dist',
        'docs',
        'vendor',
        'config.php',
        'aconfig.php',
        'AGENTS.md',
        'composer.json',
        'composer.lock',
        'phpstan.neon.dist',
        '.php-cs-fixer.dist.php',
        '.DS_Store',
        'Thumbs.db',
        '.vscode',
        '.idea',
    ];

    foreach ($requiredExcludedEntries as $entry) {
        if (!str_contains($releaseScriptSource, "'" . $entry . "'")) {
            $issues[] = 'build/release.ps1 does not exclude ' . $entry . '.';
        }
    }

    $requiredReleaseSnippets = [
        'Require-Command -Name php',
        'function Invoke-ReleasePackageAudit',
        'build\release_package_audit.php',
        'Invoke-ReleasePackageAudit -ProjectRoot $projectRoot',
        '[switch]$SkipCi',
        '[switch]$FullCi',
        '[switch]$DryRun',
        'Require-Command -Name composer',
        'function Invoke-ReleaseCi',
        'function Get-UpdatedChangelogContent',
        '## [Unreleased]`n`n## [$NewVersion]',
        "'ci:basic'",
        "'ci:full'",
        '& composer $scriptName',
        'Invoke-ReleaseCi -ProjectRoot $projectRoot -Full:$FullCi',
        'Přeskakuji composer CI na základě -SkipCi',
        'function Compress-ReleaseDirectory',
        'System.IO.Compression.ZipArchive',
        'Get-ChildItem -LiteralPath $sourceRoot -Recurse -Force -File',
        'CreateEntryFromFile',
        'function New-ReleaseZip',
        '[hashtable]$FileOverrides',
        '$exclude = @(',
        '$_.Name -notin $exclude',
        'Compress-ReleaseDirectory -SourceDir $tempDir -OutPath $OutPath',
        '$adminGuideSource = Join-Path $ProjectRoot "docs\admin-guide.md"',
        'Copy-Item -Path $adminGuideSource -Destination (Join-Path $docsTempDir "admin-guide.md") -Force',
        '$uploadsHtaccessSource = Join-Path $ProjectRoot "uploads\.htaccess"',
        'Release asset musí obsahovat ochranu upload adresáře.',
        'Copy-Item -Path $uploadsHtaccessSource -Destination (Join-Path $uploadsTempDir ".htaccess") -Force',
        'function Write-ReleaseChecksum',
        'Get-FileHash -Path $Path -Algorithm SHA256',
        '$checksumPath = Write-ReleaseChecksum -Path $zipPath',
        '& gh release upload $tagName $zipPath $checksumPath --clobber',
        '$expectedAssets = @("koracms-$newVersion.zip", "koracms-$newVersion.zip.sha256")',
        'if ($DryRun)',
        '$packageFileOverrides["VERSION"] = $newVersion',
        'New-ReleaseZip -ProjectRoot $projectRoot -OutPath $zipPath -FileOverrides $packageFileOverrides',
        'VERSION a CHANGELOG.md zůstávají beze změn',
        'Dry run dokončen',
        'Nebyl vytvořen commit, tag, push ani GitHub release',
    ];

    foreach ($requiredReleaseSnippets as $snippet) {
        if (!str_contains($releaseScriptSource, $snippet)) {
            $issues[] = 'build/release.ps1 is missing release packaging guard: ' . $snippet;
        }
    }

    if (str_contains($releaseScriptSource, 'Compress-Archive')) {
        $issues[] = 'build/release.ps1 must not use Compress-Archive for release ZIPs because it can drop dotfiles on Linux.';
    }
}

if (!is_file($releaseSmokePath)) {
    $issues[] = 'build/release_smoke.php is missing.';
} else {
    $releaseSmokeSource = (string) file_get_contents($releaseSmokePath);
    $requiredReleaseSmokeSnippets = [
        "'.htaccess',",
        "'config.sample.php',",
        "'install.php',",
        "'migrate.php',",
        "'assets/error.css',",
        "'themes/default/assets/public.css',",
        "str_starts_with(\$entry, 'uploads/') && \$entry !== 'uploads/' && \$entry !== 'uploads/.htaccess'",
        "str_starts_with(\$entry, 'vendor/')",
        "str_starts_with(\$entry, 'dist/')",
        "Source archive unexpectedly contains user upload content",
        "Release smoke ZIP unexpectedly contains dev metadata",
    ];

    foreach ($requiredReleaseSmokeSnippets as $snippet) {
        if (!str_contains($releaseSmokeSource, $snippet)) {
            $issues[] = 'build/release_smoke.php is missing release artifact guard: ' . $snippet;
        }
    }
}

if (!is_file($gitattributesPath)) {
    $issues[] = '.gitattributes is missing.';
} else {
    $gitattributesSource = (string) file_get_contents($gitattributesPath);

    $requiredExportIgnores = [
        '.github export-ignore',
        '.github/** export-ignore',
        '.claude export-ignore',
        '.claude/** export-ignore',
        '.idea export-ignore',
        '.idea/** export-ignore',
        '.vscode export-ignore',
        '.vscode/** export-ignore',
        '.gitignore export-ignore',
        '.gitattributes export-ignore',
        '.php-cs-fixer.dist.php export-ignore',
        'AGENTS.md export-ignore',
        'aconfig.php export-ignore',
        'build export-ignore',
        'build/** export-ignore',
        'build/runtime_audit.php export-ignore',
        'build/release_package_audit.php export-ignore',
        'build/release_smoke.php export-ignore',
        'composer.json export-ignore',
        'composer.lock export-ignore',
        'config.php export-ignore',
        'dist export-ignore',
        'dist/** export-ignore',
        'docs export-ignore',
        'docs/** export-ignore',
        'phpstan.neon.dist export-ignore',
        'vendor export-ignore',
        'vendor/** export-ignore',
    ];

    foreach ($requiredExportIgnores as $rule) {
        if (!str_contains($gitattributesSource, $rule)) {
            $issues[] = '.gitattributes is missing source archive rule: ' . $rule;
        }
    }
}

if (!is_file($gitignorePath)) {
    $issues[] = '.gitignore is missing.';
} else {
    $gitignoreSource = (string) file_get_contents($gitignorePath);

    $requiredIgnoreRules = [
        'config.php',
        'aconfig.php',
        'uploads/*',
        '!uploads/.htaccess',
        '!uploads/backups/',
        'uploads/backups/*',
        '!uploads/backups/.htaccess',
        '.integrity_snapshot.json',
        'dist/',
        'vendor/',
        '.php-cs-fixer.cache',
        '.DS_Store',
        'Thumbs.db',
        '.vscode/',
        '.idea/',
        '.claude/',
        '!docs/admin-guide.md',
    ];

    foreach ($requiredIgnoreRules as $rule) {
        if (!str_contains($gitignoreSource, $rule)) {
            $issues[] = '.gitignore is missing local/generated file rule: ' . $rule;
        }
    }
}

if ($issues !== []) {
    echo "Release package audit failed:\n";
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
    exit(1);
}

echo "Release package audit OK\n";
