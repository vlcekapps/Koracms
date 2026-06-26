<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
$isHeadRequest = requireReadOnlyHttpMethod();

if (!isModuleEnabled('podcast')) {
    sendFileDownloadNotFound();
}

$episodeId = inputInt('get', 'id');
if ($episodeId === null) {
    sendFileDownloadNotFound();
}

$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT p.*, s.status AS show_status, s.is_published AS show_is_published
     FROM cms_podcasts p
     INNER JOIN cms_podcast_shows s ON s.id = p.show_id
     WHERE p.id = ?
     LIMIT 1"
);
$stmt->execute([$episodeId]);
$episode = $stmt->fetch() ?: null;

if ($episode === null) {
    sendFileDownloadNotFound();
}

$filename = trim((string)($episode['image_file'] ?? ''));
if ($filename === '') {
    sendFileDownloadNotFound();
}

$isPublic = podcastEpisodeIsPublic($episode);

if (!$isPublic && !currentUserHasCapability('content_manage_shared')) {
    sendFileDownloadNotFound();
}

$filePath = podcastEpisodeImageFilePath($filename);
if (!is_file($filePath)) {
    sendFileDownloadNotFound();
}

sendInlineStoredFileResponse($filePath, basename($filePath), $isPublic, $isHeadRequest);
