<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$issues = [];

/**
 * @return list<string>
 */
function trackedMojibakeAuditFiles(string $projectRoot): array
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

function shouldAuditMojibake(string $relativePath): bool
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

/**
 * @return list<string>
 */
function suspiciousMojibakeFragments(): array
{
    $hexFragments = [
        'c382',
        'c383',
        'c384',
        'c385',
        'c482',
        'c4b9',
        'c3a2',
        'efbfbd',
    ];

    $fragments = [];
    foreach ($hexFragments as $hexFragment) {
        $fragment = hex2bin($hexFragment);
        if (is_string($fragment) && $fragment !== '') {
            $fragments[] = $fragment;
        }
    }

    return $fragments;
}

function isAllowedIntentionalMojibake(string $relativePath, string $line): bool
{
    $normalizedPath = str_replace('\\', '/', $relativePath);

    if ($normalizedPath === 'migrate.php' && str_contains($line, '$legacyBrokenSocialLinksTitle')) {
        return true;
    }

    if ($normalizedPath === 'lib/presentation.php') {
        if (str_contains($line, "str_replace('") && str_contains($line, "', '…'")) {
            return true;
        }

        if (str_contains($line, "'ľ' => 'l'") || str_contains($line, "'ä' => 'ae'")) {
            return true;
        }
    }

    if (
        in_array($normalizedPath, ['build/mojibake_audit.php', 'build/runtime_audit.php', 'build/unit_tests.php'], true)
        && str_contains($line, "slugify('Ärger')")
    ) {
        return true;
    }

    return false;
}

$suspiciousFragments = suspiciousMojibakeFragments();

foreach (trackedMojibakeAuditFiles($projectRoot) as $relativePath) {
    if (!shouldAuditMojibake($relativePath)) {
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

    foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $contents)) as $lineNumber => $line) {
        foreach ($suspiciousFragments as $fragment) {
            if (!str_contains($line, $fragment)) {
                continue;
            }

            if (!isAllowedIntentionalMojibake($relativePath, $line)) {
                $issues[] = $relativePath . ':' . ($lineNumber + 1) . ': suspicious mojibake fragment';
            }

            break;
        }
    }
}

if ($issues !== []) {
    echo "Mojibake audit failed:\n";
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
    exit(1);
}

echo "Mojibake audit OK\n";
