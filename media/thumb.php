<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
$isHeadRequest = requireReadOnlyHttpMethod();

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

sendInlineStoredFileResponse($thumbPath, basename($thumbPath), $isPublic, $isHeadRequest);
