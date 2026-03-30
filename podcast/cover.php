<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('podcast')) {
    http_response_code(404);
    exit;
}

$showId = inputInt('get', 'id');
if ($showId === null) {
    http_response_code(404);
    exit;
}

$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT *
     FROM cms_podcast_shows
     WHERE id = ?
     LIMIT 1"
);
$stmt->execute([$showId]);
$show = $stmt->fetch() ?: null;

if ($show === null) {
    http_response_code(404);
    exit;
}

$show = hydratePodcastShowPresentation($show);
$filename = trim((string)($show['cover_image'] ?? ''));
if ($filename === '') {
    http_response_code(404);
    exit;
}

if (!podcastShowIsPublic($show) && !currentUserHasCapability('content_manage_shared')) {
    http_response_code(404);
    exit;
}

$filePath = podcastCoverFilePath($filename);
if (!is_file($filePath)) {
    http_response_code(404);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = (string)$finfo->file($filePath);
if ($mimeType === '') {
    $mimeType = 'application/octet-stream';
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string)filesize($filePath));
header('Content-Disposition: inline; filename="' . rawurlencode(basename($filePath)) . '"');
header('Cache-Control: ' . (podcastShowIsPublic($show) ? 'public, max-age=86400' : 'private, max-age=0, no-store'));
header('X-Content-Type-Options: nosniff');

$lastModified = filemtime($filePath);
if ($lastModified !== false) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
}

$handle = fopen($filePath, 'rb');
if ($handle === false) {
    http_response_code(404);
    exit;
}

while (!feof($handle)) {
    $chunk = fread($handle, 8192);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
}

fclose($handle);
exit;
