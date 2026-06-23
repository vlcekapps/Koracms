<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$themeRoot = $projectRoot . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'default';
$issues = [];

if (!is_dir($themeRoot)) {
    fwrite(STDERR, "Default theme directory is missing.\n");
    exit(1);
}

/**
 * @return list<string>
 */
function themeViewAuditFiles(string $themeRoot): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($themeRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if ($item instanceof SplFileInfo && $item->isFile() && $item->getExtension() === 'php') {
            $files[] = $item->getPathname();
        }
    }

    sort($files);
    return $files;
}

function themeViewAuditRelativePath(string $path, string $themeRoot): string
{
    return str_replace('\\', '/', substr($path, strlen($themeRoot) + 1));
}

/**
 * @param list<string> $issues
 */
function themeViewAuditForbidPattern(string $label, string $relativePath, string $source, string $pattern, array &$issues): void
{
    if (preg_match($pattern, $source) === 1) {
        $issues[] = $relativePath . ' contains forbidden theme view pattern: ' . $label;
    }
}

foreach (themeViewAuditFiles($themeRoot) as $path) {
    $relativePath = themeViewAuditRelativePath($path, $themeRoot);
    $source = file_get_contents($path);
    if (!is_string($source) || $source === '') {
        $issues[] = $relativePath . ' cannot be read.';
        continue;
    }

    foreach ([
        'request input superglobal' => '/\$_(?:GET|POST|REQUEST|FILES|COOKIE)\b/',
        'database connection or query' => '/(?:db_connect\s*\(|new\s+PDO\b|PDO::|->\s*(?:prepare|query|exec)\s*\()/',
        'response header mutation' => '/\b(?:header|setcookie|http_response_code)\s*\(/',
        'filesystem write or mutation' => '/\b(?:file_put_contents|fopen|unlink|mkdir|rename|copy|move_uploaded_file|is_uploaded_file)\s*\(/',
        'network or mail side effect' => '/\b(?:curl_[a-z_]+|mail)\s*\(/',
        'dynamic PHP include' => '/\b(?:require|require_once|include|include_once)\s*\(/',
        'eval' => '/\beval\s*\(/',
        'inline style element or attribute' => '/<style\b|style\s*=/i',
        'inline event handler' => '/\son(?:click|change|submit|input|load|error|focus|blur|mouseover)\s*=/i',
        'javascript URL' => '/javascript\s*:/i',
    ] as $label => $pattern) {
        themeViewAuditForbidPattern($label, $relativePath, $source, $pattern, $issues);
    }

    if (preg_match_all('/<script\b[^>]*>/i', $source, $scriptMatches) !== false) {
        foreach ($scriptMatches[0] as $scriptTag) {
            if (stripos($scriptTag, ' nonce=') === false) {
                $issues[] = $relativePath . ' contains a script tag without CSP nonce.';
            }
        }
    }
}

if ($issues === []) {
    echo "Theme view audit OK\n";
    exit(0);
}

echo "Theme view audit failed:\n";
foreach ($issues as $issue) {
    echo '- ' . $issue . "\n";
}
exit(1);
