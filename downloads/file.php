<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

$id = inputInt('get', 'id');
if ($id === null) {
    sendFileDownloadNotFound();
}

$allowPrivateAccess = isLoggedIn() && !isPublicUser();
if (!$allowPrivateAccess && !isModuleEnabled('downloads')) {
    sendFileDownloadNotFound();
}

$stmt = db_connect()->prepare(
    "SELECT id, title, filename, original_name, is_published, COALESCE(status, 'published') AS status
     FROM cms_downloads
     WHERE id = ?"
);
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) {
    sendFileDownloadNotFound();
}

if (!$allowPrivateAccess && ($file['status'] !== 'published' || !(int)$file['is_published'])) {
    sendFileDownloadNotFound();
}

$storedName = trim((string)$file['filename']);
if ($storedName === '') {
    sendFileDownloadNotFound();
}

$downloadName = safeDownloadName((string)$file['original_name'], (string)$file['title']);
if ($downloadName === trim((string)$file['title'])) {
    $extension = pathinfo($storedName, PATHINFO_EXTENSION);
    if ($extension !== '' && !str_ends_with(strtolower($downloadName), '.' . strtolower($extension))) {
        $downloadName .= '.' . $extension;
    }
}

sendStoredFileDownload(__DIR__ . '/../uploads/downloads/' . basename($storedName), $downloadName);
