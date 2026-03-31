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

function sendFileDownloadNotFound(string $message = 'Soubor nebyl nalezen.'): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function sendStoredFileResponse(string $path, string $downloadName, string $disposition = 'attachment', string $mimeTypeOverride = ''): void
{
    if (!is_file($path) || !is_readable($path)) {
        sendFileDownloadNotFound();
    }

    $downloadName = safeDownloadName($downloadName, basename($path));
    $asciiFallback = safeDownloadAsciiFallback($downloadName);
    $mimeType = 'application/octet-stream';

    try {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedType = $finfo->file($path);
        if (is_string($detectedType) && $detectedType !== '') {
            $mimeType = $detectedType;
        }
    } catch (\Throwable $e) {
        error_log('sendStoredFileDownload: ' . $e->getMessage());
    }

    $normalizedOverride = trim($mimeTypeOverride);
    if ($normalizedOverride !== '') {
        $mimeType = $normalizedOverride;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: ' . $disposition . '; filename="' . addcslashes($asciiFallback, "\\\"") . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    $written = readfile($path);
    if ($written === false) {
        error_log('sendStoredFileDownload: nepodařilo se odeslat soubor ' . $path);
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
