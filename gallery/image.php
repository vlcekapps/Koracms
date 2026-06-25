<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
$isHeadRequest = requireReadOnlyHttpMethod();

if (!isModuleEnabled('gallery')) {
    http_response_code(404);
    exit;
}

$photoId = inputInt('get', 'id');
$size = (string)($_GET['size'] ?? 'full');
$size = $size === 'thumb' ? 'thumb' : 'full';

if ($photoId === null) {
    http_response_code(404);
    exit;
}

$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT p.id, p.filename,
            COALESCE(p.status, 'published') AS photo_status,
            COALESCE(p.is_published, 1) AS photo_published,
            COALESCE(a.status, 'published') AS album_status,
            COALESCE(a.is_published, 1) AS album_published
     FROM cms_gallery_photos p
     INNER JOIN cms_gallery_albums a ON a.id = p.album_id
     WHERE p.id = ?
     LIMIT 1"
);
$stmt->execute([$photoId]);
$photo = $stmt->fetch() ?: null;

if ($photo === null) {
    http_response_code(404);
    exit;
}

$isPublic = (string)$photo['photo_status'] === 'published'
    && (int)$photo['photo_published'] === 1
    && (string)$photo['album_status'] === 'published'
    && (int)$photo['album_published'] === 1;

if (!$isPublic && !currentUserHasCapability('content_manage_shared')) {
    http_response_code(404);
    exit;
}

$baseDir = dirname(__DIR__) . '/uploads/gallery/';
$filePath = $baseDir . ($size === 'thumb' ? 'thumbs/' : '') . (string)$photo['filename'];
if (!is_file($filePath) && $size === 'thumb') {
    $filePath = $baseDir . (string)$photo['filename'];
}

if (!is_file($filePath)) {
    http_response_code(404);
    exit;
}

sendInlineStoredFileResponse($filePath, basename($filePath), $isPublic, $isHeadRequest);
