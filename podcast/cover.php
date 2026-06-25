<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
$isHeadRequest = requireReadOnlyHttpMethod();

if (!isModuleEnabled('podcast')) {
    http_response_code(404);
    exit;
}

$showId = inputInt('get', 'id');
if ($showId === null) {
    http_response_code(404);
    exit;
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
    http_response_code(404);
    exit;
}

$show = hydratePodcastShowPresentation($show);
$filename = trim((string)($show['cover_image'] ?? ''));
if ($filename === '') {
    http_response_code(404);
    exit;
}

$isPublic = podcastShowIsPublic($show);

if (!$isPublic && !currentUserHasCapability('content_manage_shared')) {
    http_response_code(404);
    exit;
}

$filePath = podcastCoverFilePath($filename);
if (!is_file($filePath)) {
    http_response_code(404);
    exit;
}

sendInlineStoredFileResponse($filePath, basename($filePath), $isPublic, $isHeadRequest);
