<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
$isHeadRequest = requireReadOnlyHttpMethod();

if (!isModuleEnabled('podcast')) {
    sendFileDownloadNotFound();
}

$showId = inputInt('get', 'id');
if ($showId === null) {
    sendFileDownloadNotFound();
}

$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT *
     FROM cms_podcast_shows
     WHERE id = ?
     LIMIT 1"
);
$stmt->execute([$showId]);
$show = $stmt->fetch() ?: null;

if ($show === null) {
    sendFileDownloadNotFound();
}

$show = hydratePodcastShowPresentation($show);
$filename = trim((string)($show['cover_image'] ?? ''));
if ($filename === '') {
    sendFileDownloadNotFound();
}

$isPublic = podcastShowIsPublic($show);

if (!$isPublic && !currentUserHasCapability('content_manage_shared')) {
    sendFileDownloadNotFound();
}

$filePath = podcastCoverFilePath($filename);
if (!is_file($filePath)) {
    sendFileDownloadNotFound();
}

sendInlineStoredFileResponse($filePath, basename($filePath), $isPublic, $isHeadRequest);
