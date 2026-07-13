<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
$isHeadRequest = requireReadOnlyHttpMethod();

if (!isModuleEnabled('places')) {
    sendFileDownloadNotFound();
}

$placeId = inputInt('get', 'id');
if ($placeId === null) {
    sendFileDownloadNotFound();
}

$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT id, image_file, status, is_published, deleted_at
     FROM cms_places
     WHERE id = ?
     LIMIT 1"
);
$stmt->execute([$placeId]);
$place = $stmt->fetch() ?: null;

if ($place === null) {
    sendFileDownloadNotFound();
}

$filename = trim((string)($place['image_file'] ?? ''));
if ($filename === '') {
    sendFileDownloadNotFound();
}

$isPublic = ($place['deleted_at'] ?? null) === null
    && (string)($place['status'] ?? 'published') === 'published'
    && (int)($place['is_published'] ?? 1) === 1;

if (!$isPublic && !currentUserHasCapability('content_manage_shared')) {
    sendFileDownloadNotFound();
}

$filePath = dirname(__DIR__) . '/uploads/places/' . basename($filename);
if (!is_file($filePath)) {
    sendFileDownloadNotFound();
}

sendInlineStoredFileResponse($filePath, basename($filePath), $isPublic, $isHeadRequest);
