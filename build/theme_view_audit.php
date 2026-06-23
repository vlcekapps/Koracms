<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$themeRootArgument = trim((string)($argv[1] ?? ''));
$themeRoot = $themeRootArgument !== ''
    ? $themeRootArgument
    : $projectRoot . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'default';
$issues = [];

if (!is_dir($themeRoot)) {
    fwrite(STDERR, "Theme view directory is missing.\n");
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

function themeViewAuditLineNumber(string $source, int $offset): int
{
    return substr_count(substr($source, 0, max(0, $offset)), "\n") + 1;
}

function themeViewAuditIsDynamicAttributeValue(string $value): bool
{
    return preg_match('/<\?(?:php|=)?|\$|\{|\}/', $value) === 1;
}

/**
 * @return list<array{value:string,line:int}>
 */
function themeViewAuditStaticAttributeValues(string $source, string $attribute): array
{
    $pattern = '/(?<![-:\w])' . preg_quote($attribute, '/') . '\s*=\s*([\'"])(.*?)\1/si';
    $matched = preg_match_all($pattern, $source, $rawMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    if ($matched === false || $matched === 0) {
        return [];
    }

    /** @var list<array<int, array{0:string,1:int}>> $rawMatches */
    $values = [];
    foreach ($rawMatches as $match) {
        $value = html_entity_decode($match[2][0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($value === '' || themeViewAuditIsDynamicAttributeValue($value)) {
            continue;
        }

        $values[] = [
            'value' => $value,
            'line' => themeViewAuditLineNumber($source, $match[0][1]),
        ];
    }

    return $values;
}

/**
 * @return array<string,list<int>>
 */
function themeViewAuditStaticIds(string $source): array
{
    $ids = [];
    foreach (themeViewAuditStaticAttributeValues($source, 'id') as $match) {
        $id = trim($match['value']);
        if ($id === '') {
            continue;
        }

        $ids[$id][] = $match['line'];
    }

    return $ids;
}

/**
 * @param list<string> $issues
 */
function themeViewAuditCheckStaticIdReferences(string $relativePath, string $source, array &$issues): void
{
    $ids = themeViewAuditStaticIds($source);

    foreach ($ids as $id => $lines) {
        if (count($lines) > 1) {
            $issues[] = $relativePath . ' contains duplicate static id "' . $id . '" on lines ' . implode(', ', $lines) . '.';
        }
    }

    foreach (['aria-labelledby', 'aria-describedby', 'aria-controls'] as $attribute) {
        foreach (themeViewAuditStaticAttributeValues($source, $attribute) as $match) {
            $targets = preg_split('/\s+/', trim($match['value'])) ?: [];
            foreach ($targets as $target) {
                if ($target === '' || isset($ids[$target])) {
                    continue;
                }

                $issues[] = $relativePath
                    . ':'
                    . $match['line']
                    . ' contains missing static '
                    . $attribute
                    . ' target "'
                    . $target
                    . '".';
            }
        }
    }

    foreach (themeViewAuditStaticAttributeValues($source, 'for') as $match) {
        $target = trim($match['value']);
        if ($target === '' || isset($ids[$target])) {
            continue;
        }

        $issues[] = $relativePath
            . ':'
            . $match['line']
            . ' contains missing static label target "'
            . $target
            . '".';
    }
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
        'runtime context superglobal' => '/\$_(?:SESSION|SERVER|ENV)\b/',
        'database connection or query' => '/(?:db_connect\s*\(|new\s+PDO\b|PDO::|->\s*(?:prepare|query|exec)\s*\()/',
        'response header mutation' => '/\b(?:header|setcookie|http_response_code)\s*\(/',
        'filesystem write or mutation' => '/\b(?:file_put_contents|fopen|unlink|mkdir|rename|copy|move_uploaded_file|is_uploaded_file)\s*\(/',
        'network or mail side effect' => '/\b(?:curl_[a-z_]+|mail)\s*\(/',
        'runtime clock' => '/\b(?:date|time|microtime)\s*\(/',
        'dynamic PHP include' => '/\b(?:require|require_once|include|include_once)\s*\(/',
        'eval' => '/\beval\s*\(/',
        'inline style element or attribute' => '/<style\b|style\s*=/i',
        'inline event handler' => '/\son(?:click|change|submit|input|load|error|focus|blur|mouseover)\s*=/i',
        'javascript URL' => '/javascript\s*:/i',
    ] as $label => $pattern) {
        themeViewAuditForbidPattern($label, $relativePath, $source, $pattern, $issues);
    }

    themeViewAuditCheckStaticIdReferences($relativePath, $source, $issues);

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
