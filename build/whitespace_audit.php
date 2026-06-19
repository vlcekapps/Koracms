<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$issues = [];

/**
 * @return list<string>
 */
function trackedWhitespaceAuditFiles(string $projectRoot): array
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

function shouldAuditWhitespace(string $relativePath): bool
{
    $normalizedPath = str_replace('\\', '/', $relativePath);
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

foreach (trackedWhitespaceAuditFiles($projectRoot) as $relativePath) {
    if (!shouldAuditWhitespace($relativePath)) {
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

    if ($contents !== '' && preg_match('/(?:\r\n|\n|\r)\z/', $contents) !== 1) {
        $issues[] = $relativePath . ': missing final newline';
    }

    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $contents));
    foreach ($lines as $lineNumber => $line) {
        if ($line !== '' && preg_match('/[ \t]\z/', $line) === 1) {
            $issues[] = $relativePath . ':' . ($lineNumber + 1) . ': trailing whitespace';
        }
    }
}

if ($issues !== []) {
    echo "Whitespace audit failed:\n";
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
    exit(1);
}

echo "Whitespace audit OK\n";
