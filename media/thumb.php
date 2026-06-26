<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
$isHeadRequest = requireReadOnlyHttpMethod();

$mediaId = inputInt('get', 'id');
if ($mediaId === null) {
    sendFileDownloadNotFound();
}

$media = mediaGetById($mediaId);
if ($media === null || !mediaCanPreviewImage($media)) {
    sendFileDownloadNotFound();
}

$isPublic = mediaIsPublic($media);
if (!$isPublic && !mediaStaffCanAccessPrivate()) {
    sendFileDownloadNotFound();
}

$thumbPath = mediaThumbPath($media);
if (($thumbPath === '' || !is_file($thumbPath)) && !mediaRebuildDerivedFiles($media)) {
    sendFileDownloadNotFound();
}

if ($thumbPath === '' || !is_file($thumbPath)) {
    sendFileDownloadNotFound();
}

sendInlineStoredFileResponse($thumbPath, basename($thumbPath), $isPublic, $isHeadRequest);
