<?php

// Stahování souborů – extrahováno z db.php

function moduleFileUrl(string $module, int $id): string
{
    return BASE_URL . '/' . trim($module, '/') . '/file.php?id=' . $id;
}

function safeDownloadName(string $preferredName, string $fallbackName = 'soubor'): string
{
    $clean = static function (string $value): string {
        return trim(str_replace(["\r", "\n", "\0"], '', basename($value)));
    };

    $preferred = $clean($preferredName);
    if ($preferred !== '') {
        return $preferred;
    }

    $fallback = $clean($fallbackName);
    return $fallback !== '' ? $fallback : 'soubor';
}

function safeDownloadAsciiFallback(string $downloadName): string
{
    $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName);
    $fallback = trim((string)$fallback, '._');
    if ($fallback !== '') {
        return $fallback;
    }

    $extension = preg_replace('/[^A-Za-z0-9]+/', '', pathinfo($downloadName, PATHINFO_EXTENSION));
    return 'download' . ($extension !== '' ? '.' . $extension : '');
}

function storedFileContentDisposition(string $disposition, string $downloadName): string
{
    $normalizedDisposition = strtolower(trim($disposition));
    if (!in_array($normalizedDisposition, ['attachment', 'inline'], true)) {
        $normalizedDisposition = 'attachment';
    }

    $asciiFallback = safeDownloadAsciiFallback($downloadName);

    return $normalizedDisposition
        . '; filename="' . addcslashes($asciiFallback, "\\\"") . '"'
        . '; filename*=UTF-8\'\'' . rawurlencode($downloadName);
}

function storedFileEtag(string $path, int $fileSize, int $lastModified): string
{
    return 'W/"' . dechex($lastModified) . '-' . dechex($fileSize) . '-' . substr(sha1(basename($path)), 0, 12) . '"';
}

function storedFileNormalizeEtagForComparison(string $etag): string
{
    $etag = trim($etag);
    if (stripos($etag, 'W/') === 0) {
        $etag = trim(substr($etag, 2));
    }

    return $etag;
}

function storedFileIfNoneMatchMatches(string $etag, string $ifNoneMatchHeader): bool
{
    foreach (explode(',', $ifNoneMatchHeader) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '*') {
            return true;
        }

        if (storedFileNormalizeEtagForComparison($candidate) === storedFileNormalizeEtagForComparison($etag)) {
            return true;
        }
    }

    return false;
}

function storedFileRequestValidatorsMatch(string $etag, ?int $lastModified): bool
{
    $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    if (is_string($ifNoneMatch) && trim($ifNoneMatch) !== '') {
        return storedFileIfNoneMatchMatches($etag, $ifNoneMatch);
    }

    $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
    if (!is_string($ifModifiedSince) || trim($ifModifiedSince) === '' || $lastModified === null) {
        return false;
    }

    $clientTimestamp = strtotime($ifModifiedSince);
    return is_int($clientTimestamp) && $clientTimestamp >= $lastModified;
}

function storedFileHttpDate(int $timestamp): string
{
    return gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
}

function sendFileDownloadNotFound(string $message = 'Soubor nebyl nalezen.', bool $isHeadRequest = false): void
{
    http_response_code(404);
    sendNoStoreNoIndexHeaders();
    header('Content-Type: text/plain; charset=UTF-8');
    sendNoSniffHeader();

    if (!$isHeadRequest) {
        echo $message;
    }

    exit;
}

function sendAdminAttachmentHeaders(string $contentType, string $downloadName, ?int $contentLength = null): void
{
    $normalizedContentType = trim(str_replace(["\r", "\n"], '', $contentType));
    if ($normalizedContentType === '') {
        $normalizedContentType = 'application/octet-stream';
    }

    header('Content-Type: ' . $normalizedContentType);
    if ($contentLength !== null && $contentLength >= 0) {
        header('Content-Length: ' . (string)$contentLength);
    }
    header('Content-Disposition: ' . storedFileContentDisposition(
        'attachment',
        safeDownloadName($downloadName, 'download')
    ));
    sendAdminDownloadHeaders();
}

function requireReadOnlyHttpMethod(): bool
{
    $requestMethod = requireHttpMethods(['GET', 'HEAD']);

    return $requestMethod === 'HEAD';
}

/**
 * @param array<string,mixed> $context
 */
function storedFileLogFailure(string $reason, string $path, array $context = []): void
{
    koraLog('warning', 'stored file response failed', array_merge([
        'reason' => $reason,
        'file_extension' => strtolower((string)pathinfo($path, PATHINFO_EXTENSION)),
        'file_exists' => is_file($path),
        'file_readable' => is_readable($path),
    ], $context));
}

function storedFileMimeType(string $path, string $mimeTypeOverride = ''): string
{
    $normalizedOverride = trim($mimeTypeOverride);
    if ($normalizedOverride !== '') {
        return $normalizedOverride;
    }

    try {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedType = $finfo->file($path);
        if (is_string($detectedType) && $detectedType !== '') {
            return $detectedType;
        }
    } catch (\Throwable $e) {
        storedFileLogFailure('mime_detection', $path, ['exception' => $e]);
    }

    return 'application/octet-stream';
}

function streamStoredFile(string $path, int $chunkSize = 8192): void
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        storedFileLogFailure('fopen', $path);
        http_response_code(404);
        exit;
    }

    while (!feof($handle)) {
        $chunk = fread($handle, $chunkSize);
        if ($chunk === false) {
            storedFileLogFailure('fread', $path);
            break;
        }
        echo $chunk;
    }

    fclose($handle);
}

function sendInlineStoredFileResponse(
    string $path,
    string $downloadName,
    bool $isPublic,
    bool $isHeadRequest,
    int $publicMaxAge = 86400,
    string $mimeTypeOverride = ''
): void {
    if (!is_file($path) || !is_readable($path)) {
        sendFileDownloadNotFound();
    }

    $fileSize = filesize($path);
    if (!is_int($fileSize)) {
        storedFileLogFailure('filesize', $path);
        sendFileDownloadNotFound();
    }

    $downloadName = safeDownloadName($downloadName, basename($path));
    $mimeType = storedFileMimeType($path, $mimeTypeOverride);
    $cacheHeader = $isPublic
        ? 'public, max-age=' . max(0, $publicMaxAge)
        : 'private, max-age=0, no-store';
    $lastModified = filemtime($path);
    $lastModifiedTimestamp = is_int($lastModified) ? $lastModified : null;
    $etag = $isPublic && $lastModifiedTimestamp !== null
        ? storedFileEtag($path, $fileSize, $lastModifiedTimestamp)
        : '';

    if ($isPublic && $etag !== '' && storedFileRequestValidatorsMatch($etag, $lastModifiedTimestamp)) {
        http_response_code(304);
        header('Cache-Control: ' . $cacheHeader);
        header('ETag: ' . $etag);
        if ($lastModifiedTimestamp !== null) {
            header('Last-Modified: ' . storedFileHttpDate($lastModifiedTimestamp));
        }
        sendNoSniffHeader();
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string)$fileSize);
    header('Content-Disposition: ' . storedFileContentDisposition('inline', $downloadName));
    header('Cache-Control: ' . $cacheHeader);
    if ($etag !== '') {
        header('ETag: ' . $etag);
    }
    sendNoSniffHeader();

    if ($lastModifiedTimestamp !== null) {
        header('Last-Modified: ' . storedFileHttpDate($lastModifiedTimestamp));
    }

    if ($isHeadRequest) {
        exit;
    }

    streamStoredFile($path);
    exit;
}

function sendStoredFileResponse(string $path, string $downloadName, string $disposition = 'attachment', string $mimeTypeOverride = ''): void
{
    if (!is_file($path) || !is_readable($path)) {
        sendFileDownloadNotFound();
    }

    $downloadName = safeDownloadName($downloadName, basename($path));
    $mimeType = storedFileMimeType($path, $mimeTypeOverride);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: ' . storedFileContentDisposition($disposition, $downloadName));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    sendNoSniffHeader();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }

    $written = readfile($path);
    if ($written === false) {
        $contentLength = is_file($path) ? filesize($path) : false;
        storedFileLogFailure('readfile', $path, [
            'disposition' => $disposition,
            'content_length' => is_int($contentLength) ? $contentLength : null,
        ]);
    }
    exit;
}

function sendStoredFileDownload(string $path, string $downloadName): void
{
    sendStoredFileResponse($path, $downloadName, 'attachment');
}

function sendStoredFileInline(string $path, string $downloadName, string $mimeTypeOverride = ''): void
{
    sendStoredFileResponse($path, $downloadName, 'inline', $mimeTypeOverride);
}

/**
 * @return array{start:int,end:int}|false|null
 */
function storedFileParseSingleRange(string $rangeHeader, int $fileSize): array|false|null
{
    $rangeHeader = trim($rangeHeader);
    if ($rangeHeader === '') {
        return null;
    }
    if ($fileSize <= 0
        || preg_match('/\Abytes=(\d*)-(\d*)\z/', $rangeHeader, $match) !== 1
        || ($match[1] === '' && $match[2] === '')
    ) {
        return false;
    }

    if ($match[1] === '') {
        $suffixLength = (int)$match[2];
        if ($suffixLength <= 0) {
            return false;
        }
        $suffixLength = min($suffixLength, $fileSize);
        return [
            'start' => $fileSize - $suffixLength,
            'end' => $fileSize - 1,
        ];
    }

    $start = (int)$match[1];
    $end = $match[2] === '' ? $fileSize - 1 : (int)$match[2];
    if ($start < 0 || $start >= $fileSize || $end < $start) {
        return false;
    }

    return [
        'start' => $start,
        'end' => min($end, $fileSize - 1),
    ];
}

function streamStoredFileRange(string $path, int $start, int $length, int $chunkSize = 1048576): void
{
    $handle = fopen($path, 'rb');
    if ($handle === false || fseek($handle, $start) !== 0) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        storedFileLogFailure('range_open', $path);
        return;
    }

    $remaining = max(0, $length);
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min($chunkSize, $remaining));
        if ($chunk === false || $chunk === '') {
            if ($chunk === false) {
                storedFileLogFailure('range_read', $path);
            }
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
        if (function_exists('flush')) {
            flush();
        }
    }

    fclose($handle);
}

function sendStoredFileRangeDownload(
    string $path,
    string $downloadName,
    bool $isHeadRequest,
    string $mimeTypeOverride = '',
    string $sha256 = ''
): void {
    if (!is_file($path) || !is_readable($path)) {
        sendFileDownloadNotFound('Soubor nebyl nalezen.', $isHeadRequest);
    }

    $fileSize = filesize($path);
    $lastModified = filemtime($path);
    if (!is_int($fileSize) || $fileSize <= 0) {
        storedFileLogFailure('range_filesize', $path);
        sendFileDownloadNotFound('Soubor nebyl nalezen.', $isHeadRequest);
    }

    $normalizedSha256 = strtolower(trim($sha256));
    $etag = preg_match('/\A[a-f0-9]{64}\z/', $normalizedSha256) === 1
        ? '"' . $normalizedSha256 . '"'
        : storedFileEtag($path, $fileSize, is_int($lastModified) ? $lastModified : 0);
    $rangeHeader = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
    $ifRange = trim((string)($_SERVER['HTTP_IF_RANGE'] ?? ''));
    if ($ifRange !== '' && $ifRange !== $etag) {
        $rangeHeader = '';
    }

    if ($rangeHeader === '' && storedFileRequestValidatorsMatch($etag, is_int($lastModified) ? $lastModified : null)) {
        http_response_code(304);
        header('Cache-Control: public, no-cache, must-revalidate');
        header('ETag: ' . $etag);
        header('Accept-Ranges: bytes');
        sendNoSniffHeader();
        exit;
    }

    $range = storedFileParseSingleRange($rangeHeader, $fileSize);
    if ($range === false) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-store, max-age=0');
        sendNoSniffHeader();
        exit;
    }

    $start = is_array($range) ? $range['start'] : 0;
    $end = is_array($range) ? $range['end'] : $fileSize - 1;
    $length = $end - $start + 1;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (is_array($range)) {
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    }
    header('Content-Type: ' . storedFileMimeType($path, $mimeTypeOverride));
    header('Content-Length: ' . $length);
    header('Content-Disposition: ' . storedFileContentDisposition(
        'attachment',
        safeDownloadName($downloadName, 'app-release.apk')
    ));
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, no-cache, must-revalidate');
    header('ETag: ' . $etag);
    if (is_int($lastModified)) {
        header('Last-Modified: ' . storedFileHttpDate($lastModified));
    }
    sendNoSniffHeader();

    if ($isHeadRequest) {
        exit;
    }

    streamStoredFileRange($path, $start, $length);
    exit;
}
