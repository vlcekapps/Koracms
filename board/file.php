<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

$id = inputInt('get', 'id');
if ($id === null) {
    sendFileDownloadNotFound();
}

$allowPrivateAccess = isLoggedIn() && !isPublicUser();
if (!$allowPrivateAccess && !isModuleEnabled('board')) {
    sendFileDownloadNotFound();
}

$stmt = db_connect()->prepare(
    "SELECT id, title, filename, original_name, is_published, COALESCE(status, 'published') AS status
     FROM cms_board
     WHERE id = ?"
);
$stmt->execute([$id]);
$document = $stmt->fetch();

if (!$document) {
    sendFileDownloadNotFound();
}

if (!$allowPrivateAccess && ($document['status'] !== 'published' || !(int)$document['is_published'])) {
    sendFileDownloadNotFound();
}

$storedName = trim((string)$document['filename']);
if ($storedName === '') {
    sendFileDownloadNotFound();
}

$downloadName = safeDownloadName((string)$document['original_name'], (string)$document['title']);
if ($downloadName === trim((string)$document['title'])) {
    $extension = pathinfo($storedName, PATHINFO_EXTENSION);
    if ($extension !== '' && !str_ends_with(strtolower($downloadName), '.' . strtolower($extension))) {
        $downloadName .= '.' . $extension;
    }
}

sendStoredFileDownload(__DIR__ . '/../uploads/board/' . basename($storedName), $downloadName);
