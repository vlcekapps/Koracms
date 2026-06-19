<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$issues = [];

/**
 * @return list<string>
 */
function trackedRepositoryGuardrailFiles(string $projectRoot): array
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

function isRepositoryGuardrailPhpFile(string $relativePath): bool
{
    $normalizedPath = str_replace('\\', '/', $relativePath);
    if (preg_match('#^(?:dist|uploads|vendor)/#', $normalizedPath) === 1) {
        return false;
    }

    return strtolower((string)pathinfo($normalizedPath, PATHINFO_EXTENSION)) === 'php';
}

function isAllowedDbConnectionVariableFile(string $relativePath): bool
{
    $normalizedPath = str_replace('\\', '/', $relativePath);

    return in_array($normalizedPath, [
        'build/unit_test_bootstrap.php',
        'config.sample.php',
        'db.php',
    ], true);
}

function directlyLoadsDbOrConfig(string $source): bool
{
    return preg_match('/\b(?:require|include)(?:_once)?\b[^;]*(?:db|config)\.php/s', $source) === 1;
}

/**
 * @return list<array{name:string,line:int}>
 */
function forbiddenDbConnectionVariableMatches(string $source): array
{
    $matchCount = preg_match_all(
        '/\$(user|pass|server|database)\b/',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE
    );

    if ($matchCount === false || $matchCount === 0) {
        return [];
    }

    $results = [];
    foreach ($matches[0] as $match) {
        $variableName = (string)$match[0];
        $offset = (int)$match[1];
        $results[] = [
            'name' => $variableName,
            'line' => substr_count(substr($source, 0, $offset), "\n") + 1,
        ];
    }

    return $results;
}

foreach (trackedRepositoryGuardrailFiles($projectRoot) as $relativePath) {
    if (!isRepositoryGuardrailPhpFile($relativePath) || isAllowedDbConnectionVariableFile($relativePath)) {
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

    if (!directlyLoadsDbOrConfig($contents)) {
        continue;
    }

    foreach (forbiddenDbConnectionVariableMatches($contents) as $match) {
        $issues[] = $relativePath . ':' . $match['line'] . ': reserved DB connection variable ' . $match['name'];
    }
}

if ($issues !== []) {
    echo "Repository guardrails audit failed:\n";
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
    exit(1);
}

echo "Repository guardrails audit OK\n";
