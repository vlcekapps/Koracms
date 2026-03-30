<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('podcast')) {
    http_response_code(404);
    exit;
}

$episodeId = inputInt('get', 'id');
if ($episodeId === null) {
    http_response_code(404);
    exit;
}

$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT p.*, s.status AS show_status, s.is_published AS show_is_published
     FROM cms_podcasts p
     INNER JOIN cms_podcast_shows s ON s.id = p.show_id
     WHERE p.id = ?
     LIMIT 1"
);
$stmt->execute([$episodeId]);
$episode = $stmt->fetch() ?: null;

if ($episode === null) {
    http_response_code(404);
    exit;
}

$filename = trim((string)($episode['audio_file'] ?? ''));
if ($filename === '') {
    http_response_code(404);
    exit;
}

$isPublic = podcastEpisodeIsPublic($episode);

if (!$isPublic && !currentUserHasCapability('content_manage_shared')) {
    http_response_code(404);
    exit;
}

$filePath = podcastAudioFilePath($filename);
if (!is_file($filePath)) {
    http_response_code(404);
    exit;
}

$fileSize = filesize($filePath);
if ($fileSize === false) {
    http_response_code(404);
    exit;
}

$mimeType = podcastAudioMimeType($filename);
$start = 0;
$end = $fileSize - 1;
$statusCode = 200;

header('Accept-Ranges: bytes');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . rawurlencode(basename($filePath)) . '"');
header('Cache-Control: ' . ($isPublic ? 'public, max-age=3600' : 'private, max-age=0, no-store'));
header('X-Content-Type-Options: nosniff');

$rangeHeader = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
if ($rangeHeader !== '' && preg_match('/bytes=(\d*)-(\d*)/i', $rangeHeader, $matches) === 1) {
    $rangeStart = $matches[1] !== '' ? (int)$matches[1] : null;
    $rangeEnd = $matches[2] !== '' ? (int)$matches[2] : null;

    if ($rangeStart === null && $rangeEnd !== null) {
        $start = max(0, $fileSize - $rangeEnd);
    } elseif ($rangeStart !== null) {
        $start = $rangeStart;
        if ($rangeEnd !== null) {
            $end = min($rangeEnd, $fileSize - 1);
        }
    }

    if ($start > $end || $start >= $fileSize) {
        header('Content-Range: bytes */' . $fileSize, true, 416);
        exit;
    }

    $statusCode = 206;
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
}

$contentLength = ($end - $start) + 1;
header('Content-Length: ' . $contentLength, true, $statusCode);

$lastModified = filemtime($filePath);
if ($lastModified !== false) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
}

$handle = fopen($filePath, 'rb');
if ($handle === false) {
    http_response_code(404);
    exit;
}

if ($start > 0) {
    fseek($handle, $start);
}

$remaining = $contentLength;
while (!feof($handle) && $remaining > 0) {
    $chunkSize = (int)min(8192, $remaining);
    $chunk = fread($handle, $chunkSize);
    if ($chunk === false || $chunk === '') {
        break;
    }
    $remaining -= strlen($chunk);
    echo $chunk;
}

fclose($handle);
exit;
