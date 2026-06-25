<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Referrer-Policy: no-referrer');

/**
 * @param array<mixed> $source
 * @return array<string,mixed>
 */
function cspReportStringKeyArray(array $source): array
{
    $result = [];
    foreach ($source as $key => $value) {
        if (is_string($key)) {
            $result[$key] = $value;
        }
    }

    return $result;
}

/**
 * @param array<mixed> $decodedReport
 * @return array<string,mixed>
 */
function cspReportBody(array $decodedReport): array
{
    if (isset($decodedReport['csp-report']) && is_array($decodedReport['csp-report'])) {
        return cspReportStringKeyArray($decodedReport['csp-report']);
    }

    if (isset($decodedReport['body']) && is_array($decodedReport['body'])) {
        return cspReportStringKeyArray($decodedReport['body']);
    }

    foreach ($decodedReport as $reportEntry) {
        if (is_array($reportEntry) && isset($reportEntry['body']) && is_array($reportEntry['body'])) {
            return cspReportStringKeyArray($reportEntry['body']);
        }
    }

    return cspReportStringKeyArray($decodedReport);
}

function cspReportText(mixed $value, int $maxLength = 500): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim((string)$value));
    if (!is_string($text)) {
        return '';
    }

    return mb_substr($text, 0, $maxLength);
}

function cspReportUri(mixed $value): string
{
    $uri = cspReportText($value, 1000);
    if ($uri === '') {
        return '';
    }

    $withoutQuery = preg_replace('/[?#].*\z/u', '', $uri);
    if (!is_string($withoutQuery) || $withoutQuery === '') {
        return mb_substr($uri, 0, 500);
    }

    return mb_substr($withoutQuery, 0, 500);
}

/**
 * @param array<string,mixed> $body
 * @param list<string> $keys
 */
function cspReportField(array $body, array $keys, int $maxLength = 500): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $body)) {
            return cspReportText($body[$key], $maxLength);
        }
    }

    return '';
}

/**
 * @param array<string,mixed> $body
 * @return array<string,string|int>
 */
function cspReportEntry(array $body): array
{
    $lineNumber = filter_var($body['line-number'] ?? $body['lineNumber'] ?? null, FILTER_VALIDATE_INT);
    $columnNumber = filter_var($body['column-number'] ?? $body['columnNumber'] ?? null, FILTER_VALIDATE_INT);

    return [
        'time' => date(DATE_ATOM),
        'request_id' => koraRequestId(),
        'ip_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
        'user_agent' => cspReportText($_SERVER['HTTP_USER_AGENT'] ?? '', 500),
        'document_uri' => cspReportUri($body['document-uri'] ?? $body['documentURL'] ?? ''),
        'blocked_uri' => cspReportUri($body['blocked-uri'] ?? $body['blockedURL'] ?? ''),
        'violated_directive' => cspReportField($body, ['violated-directive', 'violatedDirective'], 250),
        'effective_directive' => cspReportField($body, ['effective-directive', 'effectiveDirective'], 250),
        'source_file' => cspReportUri($body['source-file'] ?? $body['sourceFile'] ?? ''),
        'line_number' => is_int($lineNumber) ? $lineNumber : 0,
        'column_number' => is_int($columnNumber) ? $columnNumber : 0,
        'disposition' => cspReportField($body, ['disposition'], 50),
    ];
}

/**
 * @param array<string,string|int> $entry
 */
function cspReportIsNoisyAllowedInlineStyle(array $entry): bool
{
    $blockedUri = strtolower((string)($entry['blocked_uri'] ?? ''));
    $effectiveDirective = strtolower((string)($entry['effective_directive'] ?? ''));

    return $blockedUri === 'inline'
        && in_array($effectiveDirective, ['style-src', 'style-src-attr', 'style-src-elem'], true);
}

/**
 * @param array<string,string|int> $extra
 */
function cspReportJsonResponse(int $statusCode, string $status, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(
        ['status' => $status, 'request_id' => koraRequestId()] + $extra,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

function cspReportRateLimitExceeded(): void
{
    header('Retry-After: 60');
    cspReportJsonResponse(429, 'rate_limited');
}

$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($requestMethod !== 'POST') {
    header('Allow: POST');
    cspReportJsonResponse(405, 'method_not_allowed');
    exit;
}

rateLimit('csp_report', 120, 60, 'cspReportRateLimitExceeded');

$rawBody = file_get_contents('php://input', false, null, 0, 65537);
if (!is_string($rawBody) || $rawBody === '') {
    cspReportJsonResponse(400, 'empty_report');
    exit;
}

if (strlen($rawBody) > 65536) {
    cspReportJsonResponse(413, 'report_too_large');
    exit;
}

$decodedReport = json_decode($rawBody, true);
if (!is_array($decodedReport)) {
    cspReportJsonResponse(400, 'invalid_json');
    exit;
}

$reportEntry = cspReportEntry(cspReportBody($decodedReport));
if (cspReportIsNoisyAllowedInlineStyle($reportEntry)) {
    http_response_code(204);
    exit;
}

$logDirectory = koraStoragePath('logs');
if (koraEnsureDirectory($logDirectory)) {
    $logLine = json_encode($reportEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($logLine !== false) {
        $logPath = $logDirectory . DIRECTORY_SEPARATOR . 'csp_reports-' . date('Y-m-d') . '.jsonl';
        if (@file_put_contents($logPath, $logLine . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            koraLog('warning', 'CSP report could not be written');
        }
    }
} else {
    koraLog('warning', 'CSP report log directory is not writable');
}

http_response_code(204);
