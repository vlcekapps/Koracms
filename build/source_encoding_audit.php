<?php

declare(strict_types=1);

$projectRootArgument = $argv[1] ?? null;
$projectRoot = sourceEncodingAuditProjectRoot(is_string($projectRootArgument) ? $projectRootArgument : null);
$issues = [];

function sourceEncodingAuditProjectRoot(?string $override): string
{
    $candidates = [];
    if ($override !== null && trim($override) !== '') {
        $candidates[] = $override;
    }

    $environmentOverride = getenv('KORA_SOURCE_ENCODING_AUDIT_ROOT');
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
 * @return list<string>
 */
function trackedSourceEncodingAuditFiles(string $projectRoot): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(
        ['git', 'ls-files', '-z'],
        $descriptorSpec,
        $pipes,
        $projectRoot,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        return [];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0 || !is_string($stdout) || $stdout === '') {
        return [];
    }

    return array_values(array_filter(explode("\0", rtrim($stdout, "\0"))));
}

function shouldAuditSourceEncoding(string $relativePath): bool
{
    $normalizedPath = str_replace('\\', '/', $relativePath);
    if ($normalizedPath === 'uploads/.htaccess') {
        return true;
    }
    if (preg_match('#^(?:dist|uploads|vendor)/#', $normalizedPath) === 1) {
        return false;
    }

    $basename = basename($normalizedPath);
    $trackedDotfiles = [
        '.gitattributes' => true,
        '.gitignore' => true,
        '.htaccess' => true,
        '.php-cs-fixer.dist.php' => true,
    ];
    if (isset($trackedDotfiles[$basename])) {
        return true;
    }

    $extension = strtolower((string)pathinfo($normalizedPath, PATHINFO_EXTENSION));
    return in_array($extension, [
        'css',
        'js',
        'json',
        'md',
        'neon',
        'php',
        'ps1',
        'txt',
        'xml',
        'yaml',
        'yml',
    ], true);
}

function allowsUtf8Bom(string $relativePath): bool
{
    return strtolower((string)pathinfo(str_replace('\\', '/', $relativePath), PATHINFO_EXTENSION)) === 'ps1';
}

foreach (trackedSourceEncodingAuditFiles($projectRoot) as $relativePath) {
    if (!shouldAuditSourceEncoding($relativePath)) {
        continue;
    }

    $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($absolutePath)) {
        continue;
    }

    $contents = file_get_contents($absolutePath);
    if (!is_string($contents)) {
        $issues[] = $relativePath . ': cannot read file';
        continue;
    }

    if (!mb_check_encoding($contents, 'UTF-8')) {
        $issues[] = $relativePath . ': invalid UTF-8 encoding';
        continue;
    }

    if (str_starts_with($contents, "\xEF\xBB\xBF") && !allowsUtf8Bom($relativePath)) {
        $issues[] = $relativePath . ': unexpected UTF-8 BOM';
    }
}

if ($issues !== []) {
    echo "Source encoding audit failed:\n";
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
    exit(1);
}

echo "Source encoding audit OK\n";
