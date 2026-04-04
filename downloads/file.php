<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

$id = inputInt('get', 'id');
if ($id === null) {
    sendFileDownloadNotFound();
}

$allowPrivateAccess = currentUserHasCapability('content_manage_shared') || currentUserHasCapability('content_approve_shared');
if (!$allowPrivateAccess && !isModuleEnabled('downloads')) {
    sendFileDownloadNotFound();
}

$stmt = db_connect()->prepare(
    "SELECT id, title, filename, original_name, is_published, deleted_at, COALESCE(status, 'published') AS status
     FROM cms_downloads
     WHERE id = ?"
);
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) {
    sendFileDownloadNotFound();
}

if (
    !$allowPrivateAccess
    && (
        $file['deleted_at'] !== null
        || $file['status'] !== 'published'
        || !(int)$file['is_published']
    )
) {
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

if (!$allowPrivateAccess && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    try {
        db_connect()->prepare(
            "UPDATE cms_downloads
             SET download_count = download_count + 1
             WHERE id = ?"
        )->execute([$id]);
    } catch (\PDOException $e) {
        error_log('downloads/file download_count: ' . $e->getMessage());
    }
}

sendStoredFileDownload(__DIR__ . '/../uploads/downloads/' . basename($storedName), $downloadName);
