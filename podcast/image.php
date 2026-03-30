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

$filename = trim((string)($episode['image_file'] ?? ''));
if ($filename === '') {
    http_response_code(404);
    exit;
}

$isPublic = podcastEpisodeIsPublic($episode);

if (!$isPublic && !currentUserHasCapability('content_manage_shared')) {
    http_response_code(404);
    exit;
}

$filePath = podcastEpisodeImageFilePath($filename);
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
header('Cache-Control: ' . ($isPublic ? 'public, max-age=86400' : 'private, max-age=0, no-store'));
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
