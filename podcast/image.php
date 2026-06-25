<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
$isHeadRequest = requireReadOnlyHttpMethod();

if (!isModuleEnabled('podcast')) {
    http_response_code(404);
    exit;
}

$episodeId = inputInt('get', 'id');
if ($episodeId === null) {
    http_response_code(404);
    exit;
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
    http_response_code(404);
    exit;
}

$filename = trim((string)($episode['image_file'] ?? ''));
if ($filename === '') {
    http_response_code(404);
    exit;
}

$isPublic = podcastEpisodeIsPublic($episode);

if (!$isPublic && !currentUserHasCapability('content_manage_shared')) {
    http_response_code(404);
    exit;
}

$filePath = podcastEpisodeImageFilePath($filename);
if (!is_file($filePath)) {
    http_response_code(404);
    exit;
}

sendInlineStoredFileResponse($filePath, basename($filePath), $isPublic, $isHeadRequest);
