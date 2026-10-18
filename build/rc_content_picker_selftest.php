<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Capture projection only. The production date helper and final sort/limit pipeline run unchanged.
// Actual JSON projection, canonical URLs and database visibility are tested over HTTP.
function contentReferenceResult(array $row): array
{
    $GLOBALS['pickerProjected'][] = $row;
    return ['title' => (string)$row['title'], 'type' => (string)$row['type']];
}

function pickerSame(mixed $actual, mixed $expected, string $label): void
{
    $GLOBALS['pickerChecks']++;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function pickerPipeline(array $input): array
{
    $GLOBALS['pickerProjected'] = [];
    $results = array_map('contentReferenceSearchResult', $input);
    eval($GLOBALS['pickerPipelineCode']);
    return $results;
}

$GLOBALS['pickerChecks'] = 0;
try {
    $source = (string)file_get_contents(dirname(__DIR__) . '/admin/content_reference_search.php');
    if (preg_match('/^function contentReferenceTimestamp\b.*?^\}/ms', $source, $match) !== 1) {
        throw new RuntimeException('Missing production timestamp helper');
    }
    eval($match[0]);
    if (preg_match('/^function contentReferenceSearchResult\b.*?^\}/ms', $source, $match) !== 1) {
        throw new RuntimeException('Missing production candidate helper');
    }
    eval($match[0]);
    $start = strrpos($source, "\nusort(");
    $end = $start !== false ? strpos($source, "\ncontentReferenceJsonResponse([", $start) : false;
    if ($start === false || $end === false) {
        throw new RuntimeException('Missing production picker pipeline');
    }
    $GLOBALS['pickerPipelineCode'] = substr($source, $start, $end - $start);
    pickerSame(contentReferenceTimestamp([]), 0, 'Missing date');
    pickerSame(contentReferenceTimestamp(['created_at' => 'invalid-date']), 0, 'Invalid date');
    pickerSame(contentReferenceTimestamp(['created_at' => '2024-01-01 12:00:00']), strtotime('2024-01-01 12:00:00'), 'Valid raw date');
    pickerSame(pickerPipeline([]), [], 'Empty results');
    pickerSame($GLOBALS['pickerProjected'], [], 'Empty results do not project');
    $rows = [];
    foreach (range(1, 25) as $number) {
        $rows[] = ['title' => 'Older ' . $number, 'type' => 'blog', 'created_at' => sprintf('2000-01-%02d 00:00:00', $number)];
    }
    $rows[] = ['title' => 'Newer board', 'type' => 'board', 'created_at' => '2024-01-01 00:00:00', 'private_field' => 'not a JSON field'];
    $rows[] = ['title' => 'Newest poll', 'type' => 'poll', 'created_at' => '2025-01-01 00:00:00'];
    $result = pickerPipeline($rows);
    pickerSame(count($result), 20, 'Global limit');
    pickerSame($result[0]['title'], 'Newest poll', 'Date survives projection');
    pickerSame($result[1]['title'], 'Newer board', 'Later source survives truncation');
    pickerSame($result[19]['title'], 'Older 8', 'Date ordering across full result window');
    pickerSame(count($GLOBALS['pickerProjected']), 27, 'Candidates are projected exactly once');
    pickerSame(array_keys(contentReferenceSearchResult($rows[25])), ['title', 'type', 'created_at'], 'Candidates do not retain full bodies/private fields');
    pickerSame(array_keys($result[1]), ['title', 'type'], 'Projected shape is used');
    pickerSame(count($rows), 27, 'Input remains unchanged');
    pickerSame(pickerPipeline([
        ['title' => 'No date', 'type' => 'page'], ['title' => 'Valid', 'type' => 'board', 'created_at' => '2020-01-01'],
        ['title' => 'Invalid', 'type' => 'page', 'created_at' => 'invalid-date'],
    ]), [['title' => 'Valid', 'type' => 'board'], ['title' => 'No date', 'type' => 'page'], ['title' => 'Invalid', 'type' => 'page']], 'Invalid dates sort last, ties remain stable');
    echo 'Content picker pipeline OK (' . $GLOBALS['pickerChecks'] . ' checks)' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Content picker pipeline FAILED: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
