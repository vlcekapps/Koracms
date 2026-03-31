<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

$mediaId = inputInt('get', 'id');
if ($mediaId === null) {
    sendFileDownloadNotFound();
}

$media = mediaGetById($mediaId);
if ($media === null) {
    sendFileDownloadNotFound();
}

$isPublic = mediaIsPublic($media);
if (!$isPublic && !mediaStaffCanAccessPrivate()) {
    sendFileDownloadNotFound();
}

$filePath = mediaOriginalPath($media);
if ($filePath === '' || !is_file($filePath)) {
    sendFileDownloadNotFound();
}

sendStoredFileDownload($filePath, mediaDownloadName($media));

