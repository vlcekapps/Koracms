<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
requireReadOnlyHttpMethod();

$id = inputInt('get', 'id');
if ($id === null) {
    sendFileDownloadNotFound();
}

$allowPrivateAccess = currentUserHasCapability('content_manage_shared') || currentUserHasCapability('content_approve_shared');
if (!$allowPrivateAccess && !isModuleEnabled('board')) {
    sendFileDownloadNotFound();
}

$stmt = db_connect()->prepare(
    "SELECT id, title, slug, filename, original_name, posted_date, publish_at, unpublish_at, deleted_at,
            is_published, COALESCE(status, 'published') AS status, NOW() AS visibility_now
     FROM cms_board
     WHERE id = ?"
);
$stmt->execute([$id]);
$document = $stmt->fetch();

if (!$document) {
    sendFileDownloadNotFound();
}

if (
    !$allowPrivateAccess
    && !boardIsPubliclyReachable($document, (string)$document['visibility_now'])
) {
    sendFileDownloadNotFound();
}

$storedName = boardStoredFilename((string)$document['filename']);
$storedPath = boardStoredFilePath($storedName);
if ($storedPath === '') {
    sendFileDownloadNotFound();
}

$downloadName = safeDownloadName((string)$document['original_name'], (string)$document['title']);
if ($downloadName === trim((string)$document['title'])) {
    $extension = pathinfo($storedName, PATHINFO_EXTENSION);
    if ($extension !== '' && !str_ends_with(strtolower($downloadName), '.' . strtolower($extension))) {
        $downloadName .= '.' . $extension;
    }
}

sendStoredFileDownload($storedPath, $downloadName);
