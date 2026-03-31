<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

$mediaId = inputInt('get', 'id');
if ($mediaId === null) {
    http_response_code(404);
    exit;
}

$media = mediaGetById($mediaId);
if ($media === null || !mediaCanPreviewImage($media)) {
    http_response_code(404);
    exit;
}

$isPublic = mediaIsPublic($media);
if (!$isPublic && !mediaStaffCanAccessPrivate()) {
    http_response_code(404);
    exit;
}

$thumbPath = mediaThumbPath($media);
if (($thumbPath === '' || !is_file($thumbPath)) && !mediaRebuildDerivedFiles($media)) {
    http_response_code(404);
    exit;
}

if ($thumbPath === '' || !is_file($thumbPath)) {
    http_response_code(404);
    exit;
}

$mimeType = (string)(new finfo(FILEINFO_MIME_TYPE))->file($thumbPath);
if ($mimeType === '') {
    $mimeType = 'application/octet-stream';
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string)filesize($thumbPath));
header('Content-Disposition: inline; filename="' . rawurlencode(basename($thumbPath)) . '"');
header('Cache-Control: ' . ($isPublic ? 'public, max-age=86400' : 'private, max-age=0, no-store'));
header('X-Content-Type-Options: nosniff');

$lastModified = filemtime($thumbPath);
if ($lastModified !== false) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
}

$handle = fopen($thumbPath, 'rb');
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

