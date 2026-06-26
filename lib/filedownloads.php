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

function sendFileDownloadNotFound(string $message = 'Soubor nebyl nalezen.'): void
{
    http_response_code(404);
    sendNoStoreNoIndexHeaders();
    header('Content-Type: text/plain; charset=UTF-8');
    sendNoSniffHeader();
    echo $message;
    exit;
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

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string)$fileSize);
    header('Content-Disposition: ' . storedFileContentDisposition('inline', $downloadName));
    header('Cache-Control: ' . $cacheHeader);
    sendNoSniffHeader();

    $lastModified = filemtime($path);
    if ($lastModified !== false) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
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
