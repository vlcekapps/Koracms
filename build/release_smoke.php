<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$releaseScriptPath = $projectRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release.ps1';

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, stdout:string, stderr:string}
 */
function runCommand(array $command, string $cwd): array
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
        fail('Cannot start command: ' . implode(' ', $command));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exitCode' => (int) $exitCode,
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

/**
 * @param list<string> $command
 */
function runCheckedCommand(array $command, string $cwd): string
{
    $result = runCommand($command, $cwd);
    $output = trim($result['stdout'] . ($result['stderr'] !== '' ? PHP_EOL . $result['stderr'] : ''));

    if ($result['exitCode'] !== 0) {
        fail(
            'Command failed: ' . implode(' ', $command)
            . PHP_EOL
            . ($output !== '' ? $output : '(no output)')
        );
    }

    return $output;
}

/**
 * @param list<string> $candidates
 */
function findExecutable(array $candidates): ?string
{
    $path = getenv('PATH');
    if (!is_string($path) || $path === '') {
        return null;
    }

    $directories = array_values(array_filter(explode(PATH_SEPARATOR, $path), static fn ($item): bool => $item !== ''));
    $extensions = [''];

    if (DIRECTORY_SEPARATOR === '\\') {
        $pathExt = getenv('PATHEXT');
        $extensions = [''];
        if (is_string($pathExt) && $pathExt !== '') {
            foreach (explode(';', $pathExt) as $extension) {
                if ($extension !== '') {
                    $extensions[] = $extension;
                }
            }
        } else {
            $extensions = ['', '.COM', '.EXE', '.BAT', '.CMD'];
        }
    }

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }

        if (str_contains($candidate, DIRECTORY_SEPARATOR) && is_file($candidate)) {
            return $candidate;
        }

        foreach ($directories as $directory) {
            foreach ($extensions as $extension) {
                $pathCandidate = $directory . DIRECTORY_SEPARATOR . $candidate . $extension;
                if (is_file($pathCandidate)) {
                    return $pathCandidate;
                }
            }
        }
    }

    return null;
}

function copyPath(string $source, string $destination): void
{
    if (is_dir($source)) {
        if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
            fail('Cannot create directory: ' . $destination);
        }

        $items = scandir($source);
        if ($items === false) {
            fail('Cannot read directory: ' . $source);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            copyPath(
                $source . DIRECTORY_SEPARATOR . $item,
                $destination . DIRECTORY_SEPARATOR . $item,
            );
        }

        return;
    }

    $destinationDir = dirname($destination);
    if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
        fail('Cannot create directory: ' . $destinationDir);
    }

    if (!copy($source, $destination)) {
        fail('Cannot copy file: ' . $source);
    }
}

/**
 * @param list<string> $excludedRootEntries
 */
function copyProjectSnapshot(string $source, string $destination, array $excludedRootEntries): void
{
    if (!mkdir($destination, 0777, true) && !is_dir($destination)) {
        fail('Cannot create temp directory: ' . $destination);
    }

    $items = scandir($source);
    if ($items === false) {
        fail('Cannot read project directory: ' . $source);
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || in_array($item, $excludedRootEntries, true)) {
            continue;
        }

        copyPath(
            $source . DIRECTORY_SEPARATOR . $item,
            $destination . DIRECTORY_SEPARATOR . $item,
        );
    }
}

function removeTree(string $path): void
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

            removeTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

function writeTextFile(string $path, string $contents): void
{
    if (file_put_contents($path, $contents) === false) {
        fail('Cannot write file: ' . $path);
    }
}

function writeZipInspectHelperScript(string $path): void
{
    $script = <<<'PS'
param([string]$ZipPath)

Add-Type -AssemblyName System.IO.Compression.FileSystem

$utf8 = [System.Text.UTF8Encoding]::new($false)
[Console]::OutputEncoding = $utf8
$zip = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)

try {
    $entries = @($zip.Entries | ForEach-Object { $_.FullName })
    $version = ''
    $changelog = ''

    $versionEntry = $zip.GetEntry('VERSION')
    if ($null -ne $versionEntry) {
        $reader = [System.IO.StreamReader]::new($versionEntry.Open(), $utf8, $true)
        try {
            $version = $reader.ReadToEnd()
        } finally {
            $reader.Dispose()
        }
    }

    $changelogEntry = $zip.GetEntry('CHANGELOG.md')
    if ($null -ne $changelogEntry) {
        $reader = [System.IO.StreamReader]::new($changelogEntry.Open(), $utf8, $true)
        try {
            $changelog = $reader.ReadToEnd()
        } finally {
            $reader.Dispose()
        }
    }

    [pscustomobject]@{
        entries = $entries
        version = $version
        changelog = $changelog
    } | ConvertTo-Json -Compress -Depth 4
} finally {
    $zip.Dispose()
}
PS;

    writeTextFile($path, $script);
}

/**
 * @return array{entries:mixed, version:mixed, changelog:mixed}
 */
function inspectZipArchive(string $powerShell, string $scriptPath, string $zipPath, string $cwd): array
{
    $output = runCheckedCommand(
        [
            $powerShell,
            '-NoLogo',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            $scriptPath,
            $zipPath,
        ],
        $cwd,
    );

    $payload = json_decode($output, true);
    if (!is_array($payload)) {
        fail('Cannot parse ZIP inspection output for ' . $zipPath . '.');
    }

    return $payload;
}

/**
 * @param array{entries:mixed} $payload
 * @return list<string>
 */
function normalizedArchiveEntries(array $payload): array
{
    $entries = [];
    foreach (($payload['entries'] ?? []) as $entry) {
        if (is_string($entry)) {
            $entries[] = str_replace('\\', '/', $entry);
        }
    }

    return $entries;
}

if (!is_file($releaseScriptPath)) {
    fail('build/release.ps1 is missing.');
}

$powerShell = findExecutable(['pwsh', 'powershell']);
if ($powerShell === null) {
    fail('PowerShell executable not found. Tried pwsh and powershell.');
}

$tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR
    . 'koracms_release_smoke_'
    . bin2hex(random_bytes(6));
$zipInspectScriptPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR
    . 'koracms_release_smoke_zip_'
    . bin2hex(random_bytes(6))
    . '.ps1';

$excludedRootEntries = [
    '.git',
    '.claude',
    '.codex',
    '.cursor',
    '.idea',
    '.vscode',
    'aconfig.php',
    'config.php',
    'dist',
    'uploads',
    'vendor',
    'node_modules',
    '.php-cs-fixer.cache',
    '.DS_Store',
    'Thumbs.db',
];

try {
    copyProjectSnapshot($projectRoot, $tempRoot, $excludedRootEntries);
    $sourceUploadsHtaccess = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($sourceUploadsHtaccess)) {
        fail('Source uploads/.htaccess is missing.');
    }
    $snapshotUploadsDir = $tempRoot . DIRECTORY_SEPARATOR . 'uploads';
    if (!mkdir($snapshotUploadsDir, 0777, true) && !is_dir($snapshotUploadsDir)) {
        fail('Cannot create snapshot uploads directory.');
    }
    copyPath($sourceUploadsHtaccess, $snapshotUploadsDir . DIRECTORY_SEPARATOR . '.htaccess');

    runCheckedCommand(['git', 'init', '--quiet'], $tempRoot);
    runCheckedCommand(['git', 'config', 'user.email', 'release-smoke@example.invalid'], $tempRoot);
    runCheckedCommand(['git', 'config', 'user.name', 'Kora CMS Release Smoke'], $tempRoot);
    runCheckedCommand(['git', 'config', 'commit.gpgsign', 'false'], $tempRoot);
    runCheckedCommand(['git', 'add', '--all'], $tempRoot);
    runCheckedCommand(['git', 'commit', '--quiet', '-m', 'Release smoke snapshot'], $tempRoot);

    $snapshotVersion = trim((string) file_get_contents($tempRoot . DIRECTORY_SEPARATOR . 'VERSION'));
    if ($snapshotVersion === '') {
        fail('Cannot read VERSION from the release smoke snapshot.');
    }
    $snapshotChangelogPath = $tempRoot . DIRECTORY_SEPARATOR . 'CHANGELOG.md';
    $snapshotChangelog = is_file($snapshotChangelogPath)
        ? (string) file_get_contents($snapshotChangelogPath)
        : '';

    writeZipInspectHelperScript($zipInspectScriptPath);

    $releaseResult = runCommand(
        [
            $powerShell,
            '-NoLogo',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            'build/release.ps1',
            '-Bump',
            'patch',
            '-DryRun',
            '-SkipCi',
        ],
        $tempRoot,
    );

    $releaseOutput = trim(
        $releaseResult['stdout']
        . ($releaseResult['stderr'] !== '' ? PHP_EOL . $releaseResult['stderr'] : '')
    );

    if ($releaseResult['exitCode'] !== 0) {
        fail(
            'Release dry-run failed.'
            . PHP_EOL
            . ($releaseOutput !== '' ? $releaseOutput : '(no output)')
        );
    }

    if (!str_contains($releaseOutput, 'Release package audit OK')) {
        fail('Release dry-run output is missing the release package audit confirmation.');
    }

    if (!str_contains($releaseOutput, 'Dry run')) {
        fail('Release dry-run output is missing the dry-run marker.');
    }
    if (!str_contains($releaseOutput, 'Dry run: ACR Version evaluated bude při ostrém vydání nastavena na')) {
        fail('Release dry-run output is missing the ACR version preflight marker.');
    }

    if (!preg_match('/^Verze:\s*(.+)$/mu', $releaseOutput, $versionLineMatch)) {
        fail('Release dry-run output is missing the version line.');
    }

    if (!preg_match('/^(\S+)\s+\S+\s+(\S+)$/u', trim($versionLineMatch[1]), $versionPartsMatch)) {
        fail('Cannot parse the release version transition from dry-run output.');
    }

    $expectedVersion = trim($versionPartsMatch[2]);

    if (!preg_match('/^Zip:\s*(.+)$/m', $releaseOutput, $zipPathMatch)) {
        fail('Release dry-run output is missing the ZIP path.');
    }
    if (!preg_match('/^SHA-256:\s*(.+)$/m', $releaseOutput, $checksumPathMatch)) {
        fail('Release dry-run output is missing the checksum path.');
    }

    $zipPath = trim($zipPathMatch[1]);
    $checksumPath = trim($checksumPathMatch[1]);

    if (!is_file($zipPath)) {
        fail('Release smoke ZIP was not created: ' . $zipPath);
    }
    if (!is_file($checksumPath)) {
        fail('Release smoke checksum was not created: ' . $checksumPath);
    }

    $gitStatus = runCheckedCommand(['git', 'status', '--short', '--untracked-files=all'], $tempRoot);
    if ($gitStatus !== '') {
        fail('Release dry-run left the snapshot repository dirty:' . PHP_EOL . $gitStatus);
    }

    $gitTags = runCheckedCommand(['git', 'tag', '--list'], $tempRoot);
    if ($gitTags !== '') {
        fail('Release dry-run created a git tag in the snapshot repository.');
    }

    $expectedChecksum = hash_file('sha256', $zipPath);
    $checksumLine = trim((string) file_get_contents($checksumPath));
    $expectedChecksumLine = $expectedChecksum . '  ' . basename($zipPath);
    if ($checksumLine !== $expectedChecksumLine) {
        fail('Release smoke checksum file does not match the generated ZIP.');
    }

    $zipPayload = inspectZipArchive($powerShell, $zipInspectScriptPath, $zipPath, $tempRoot);
    $entries = normalizedArchiveEntries($zipPayload);

    foreach ([
        '.htaccess',
        'VERSION',
        'CHANGELOG.md',
        'README.md',
        'LICENSE',
        'NOTICE.md',
        'config.sample.php',
        'install.php',
        'migrate.php',
        'admin/login.php',
        'assets/error.css',
        'themes/default/theme.json',
        'themes/default/assets/public.css',
        'tools/appmarket-publish.ps1',
        'docs/admin-guide.md',
        'uploads/.htaccess',
    ] as $requiredEntry) {
        if (!in_array($requiredEntry, $entries, true)) {
            fail('Release smoke ZIP is missing required file: ' . $requiredEntry);
        }
    }

    foreach ($entries as $entry) {
        if (str_starts_with($entry, '.claude/')) {
            fail('Release smoke ZIP unexpectedly contains local Claude metadata.');
        }
        if (str_starts_with($entry, '.codex/')) {
            fail('Release smoke ZIP unexpectedly contains local Codex metadata.');
        }
        if (str_starts_with($entry, '.cursor/')) {
            fail('Release smoke ZIP unexpectedly contains local Cursor metadata.');
        }
        if (str_starts_with($entry, '.github/')) {
            fail('Release smoke ZIP unexpectedly contains .github metadata.');
        }
        if (str_starts_with($entry, '.idea/')) {
            fail('Release smoke ZIP unexpectedly contains IDE metadata: ' . $entry);
        }
        if (str_starts_with($entry, '.vscode/')) {
            fail('Release smoke ZIP unexpectedly contains IDE metadata: ' . $entry);
        }
        if (str_starts_with($entry, 'vendor/')) {
            fail('Release smoke ZIP unexpectedly contains vendor files.');
        }
        if (str_starts_with($entry, 'node_modules/')) {
            fail('Release smoke ZIP unexpectedly contains Node dependencies.');
        }
        if (str_starts_with($entry, 'build/')) {
            fail('Release smoke ZIP unexpectedly contains build tooling files.');
        }
        if (str_starts_with($entry, 'dist/')) {
            fail('Release smoke ZIP unexpectedly contains generated dist files.');
        }
        if (str_starts_with($entry, 'docs/') && $entry !== 'docs/' && $entry !== 'docs/admin-guide.md') {
            fail('Release smoke ZIP unexpectedly contains extra docs content: ' . $entry);
        }
        if (str_starts_with($entry, 'uploads/') && $entry !== 'uploads/' && $entry !== 'uploads/.htaccess') {
            fail('Release smoke ZIP unexpectedly contains user upload content: ' . $entry);
        }
    }

    foreach ([
        '.gitattributes',
        '.gitignore',
        '.php-cs-fixer.dist.php',
        '.codex',
        '.cursor',
        'AGENTS.md',
        'aconfig.php',
        'composer.json',
        'composer.lock',
        'config.php',
        'node_modules',
        'phpstan.neon.dist',
    ] as $excludedFile) {
        if (in_array($excludedFile, $entries, true)) {
            fail('Release smoke ZIP unexpectedly contains dev metadata: ' . $excludedFile);
        }
    }

    $versionContents = $zipPayload['version'] ?? null;
    if (!is_string($versionContents) || trim($versionContents) !== $expectedVersion) {
        fail('Release smoke ZIP contains an unexpected VERSION value.');
    }

    $changelogContents = $zipPayload['changelog'] ?? null;
    if (!is_string($changelogContents)) {
        fail('Release smoke ZIP does not contain CHANGELOG.md contents.');
    }
    $normalizedChangelogContents = str_replace(["\r\n", "\r"], "\n", $changelogContents);
    if (str_contains($releaseOutput, 'Dry run: ZIP použije náhled aktualizovaného CHANGELOG.md.')) {
        if (!str_contains($changelogContents, '## [' . $expectedVersion . ']')) {
            fail('Release smoke ZIP does not contain the expected CHANGELOG preview.');
        }
        if (!str_contains($normalizedChangelogContents, "## [Unreleased]\n\n## [" . $expectedVersion . ']')) {
            fail('Release smoke ZIP does not keep a fresh Unreleased section above the released version.');
        }
    } elseif ($changelogContents !== $snapshotChangelog) {
        fail('Release smoke ZIP changed CHANGELOG.md even though dry-run reported no changelog preview.');
    }

    $sourceArchivePath = $tempRoot . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'source-archive.zip';
    runCheckedCommand(['git', 'archive', '--format=zip', '--output', $sourceArchivePath, 'HEAD'], $tempRoot);

    if (!is_file($sourceArchivePath)) {
        fail('Source archive ZIP was not created: ' . $sourceArchivePath);
    }

    $sourcePayload = inspectZipArchive($powerShell, $zipInspectScriptPath, $sourceArchivePath, $tempRoot);
    $sourceEntries = normalizedArchiveEntries($sourcePayload);

    foreach ([
        '.htaccess',
        'VERSION',
        'CHANGELOG.md',
        'README.md',
        'LICENSE',
        'NOTICE.md',
        'auth.php',
        'config.sample.php',
        'install.php',
        'migrate.php',
        'admin/login.php',
        'assets/error.css',
        'themes/default/theme.json',
        'themes/default/assets/public.css',
        'uploads/.htaccess',
    ] as $requiredEntry) {
        if (!in_array($requiredEntry, $sourceEntries, true)) {
            fail('Source archive is missing required file: ' . $requiredEntry);
        }
    }

    foreach ($sourceEntries as $entry) {
        if (str_starts_with($entry, '.claude/')) {
            fail('Source archive unexpectedly contains local Claude metadata: ' . $entry);
        }
        if (str_starts_with($entry, '.codex/')) {
            fail('Source archive unexpectedly contains local Codex metadata: ' . $entry);
        }
        if (str_starts_with($entry, '.cursor/')) {
            fail('Source archive unexpectedly contains local Cursor metadata: ' . $entry);
        }
        if (str_starts_with($entry, '.github/')) {
            fail('Source archive unexpectedly contains .github metadata.');
        }
        if (str_starts_with($entry, '.idea/')) {
            fail('Source archive unexpectedly contains IDE metadata: ' . $entry);
        }
        if (str_starts_with($entry, '.vscode/')) {
            fail('Source archive unexpectedly contains IDE metadata: ' . $entry);
        }
        if (str_starts_with($entry, 'build/')) {
            fail('Source archive unexpectedly contains build tooling: ' . $entry);
        }
        if (str_starts_with($entry, 'dist/')) {
            fail('Source archive unexpectedly contains generated dist files: ' . $entry);
        }
        if (str_starts_with($entry, 'docs/')) {
            fail('Source archive unexpectedly contains docs content: ' . $entry);
        }
        if (str_starts_with($entry, 'uploads/') && $entry !== 'uploads/' && $entry !== 'uploads/.htaccess') {
            fail('Source archive unexpectedly contains user upload content: ' . $entry);
        }
        if (str_starts_with($entry, 'vendor/')) {
            fail('Source archive unexpectedly contains vendor files: ' . $entry);
        }
        if (str_starts_with($entry, 'node_modules/')) {
            fail('Source archive unexpectedly contains Node dependencies: ' . $entry);
        }
    }

    foreach ([
        '.gitattributes',
        '.gitignore',
        '.php-cs-fixer.dist.php',
        '.codex',
        '.cursor',
        'AGENTS.md',
        'aconfig.php',
        'composer.json',
        'composer.lock',
        'config.php',
        'node_modules',
        'phpstan.neon.dist',
    ] as $excludedFile) {
        if (in_array($excludedFile, $sourceEntries, true)) {
            fail('Source archive unexpectedly contains export-ignored file: ' . $excludedFile);
        }
    }

    $sourceVersionContents = $sourcePayload['version'] ?? null;
    if (!is_string($sourceVersionContents) || trim($sourceVersionContents) !== $snapshotVersion) {
        fail('Source archive contains an unexpected VERSION value.');
    }
} finally {
    if (is_file($zipInspectScriptPath)) {
        @unlink($zipInspectScriptPath);
    }
    removeTree($tempRoot);
}

echo "Release smoke OK\n";
