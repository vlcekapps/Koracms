<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
$isHeadRequest = requireReadOnlyHttpMethod();

$id = inputInt('get', 'id');
if ($id === null) {
    sendFileDownloadNotFound('Externí zdroj nebyl nalezen.', $isHeadRequest);
}

$allowPrivateAccess = currentUserHasCapability('content_manage_shared') || currentUserHasCapability('content_approve_shared');
if (!$allowPrivateAccess && !isModuleEnabled('downloads')) {
    sendFileDownloadNotFound('Externí zdroj nebyl nalezen.', $isHeadRequest);
}

$stmt = db_connect()->prepare(
    "SELECT id, external_url, is_published, deleted_at, COALESCE(status, 'published') AS status
     FROM cms_downloads
     WHERE id = ?"
);
$stmt->execute([$id]);
$download = $stmt->fetch();

if (!$download) {
    sendFileDownloadNotFound('Externí zdroj nebyl nalezen.', $isHeadRequest);
}

if (
    !$allowPrivateAccess
    && (
        $download['deleted_at'] !== null
        || $download['status'] !== 'published'
        || !(int)$download['is_published']
    )
) {
    sendFileDownloadNotFound('Externí zdroj nebyl nalezen.', $isHeadRequest);
}

$target = normalizeDownloadExternalUrl((string)$download['external_url']);
if ($target === '') {
    sendFileDownloadNotFound('Externí zdroj nebyl nalezen.', $isHeadRequest);
}

if (!$allowPrivateAccess && !$isHeadRequest) {
    try {
        db_connect()->prepare(
            "UPDATE cms_downloads
             SET external_click_count = external_click_count + 1
             WHERE id = ?"
        )->execute([$id]);
    } catch (\PDOException $e) {
        koraLog('warning', 'external download click count update failed', ['download_id' => $id, 'exception' => $e]);
    }
}

sendNoStoreNoIndexHeaders();
sendNoSniffHeader();
header('Location: ' . $target, true, 302);
exit;
