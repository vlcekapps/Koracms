<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('places')) {
    http_response_code(404);
    exit;
}

$placeId = inputInt('get', 'id');
if ($placeId === null) {
    http_response_code(404);
    exit;
}

$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT id, image_file, status, is_published
     FROM cms_places
     WHERE id = ?
     LIMIT 1"
);
$stmt->execute([$placeId]);
$place = $stmt->fetch() ?: null;

if ($place === null) {
    http_response_code(404);
    exit;
}

$filename = trim((string)($place['image_file'] ?? ''));
if ($filename === '') {
    http_response_code(404);
    exit;
}

$isPublic = (string)($place['status'] ?? 'published') === 'published'
    && (int)($place['is_published'] ?? 1) === 1;

if (!$isPublic && !currentUserHasCapability('content_manage_shared')) {
    http_response_code(404);
    exit;
}

$filePath = dirname(__DIR__) . '/uploads/places/' . basename($filename);
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
