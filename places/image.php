<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
$isHeadRequest = requireReadOnlyHttpMethod();

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

sendInlineStoredFileResponse($filePath, basename($filePath), $isPublic, $isHeadRequest);
