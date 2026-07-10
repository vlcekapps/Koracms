<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
$isHeadRequest = requireReadOnlyHttpMethod();

if (!isModuleEnabled('podcast')) {
    sendReadOnlyNotFoundResponse('Kapitoly nejsou dostupné.', $isHeadRequest);
}

$episodeId = inputInt('get', 'id');
if ($episodeId === null || $episodeId < 1) {
    sendReadOnlyNotFoundResponse('Kapitoly nebyly nalezeny.', $isHeadRequest);
}

$pdo = db_connect();
$episodeStmt = $pdo->prepare(
    "SELECT p.*, s.status AS show_status, s.is_published AS show_is_published
     FROM cms_podcasts p
     INNER JOIN cms_podcast_shows s ON s.id = p.show_id
     WHERE p.id = ? LIMIT 1"
);
$episodeStmt->execute([$episodeId]);
$episode = $episodeStmt->fetch() ?: null;
if ($episode === null || !podcastEpisodeIsPublic($episode)) {
    sendReadOnlyNotFoundResponse('Kapitoly nebyly nalezeny.', $isHeadRequest);
}

$chaptersStmt = $pdo->prepare(
    "SELECT * FROM cms_podcast_chapters WHERE episode_id = ? ORDER BY start_time_seconds ASC, id ASC"
);
$chaptersStmt->execute([$episodeId]);
$chapters = $chaptersStmt->fetchAll();
$payload = podcastChaptersPayload($chapters);
if ($payload['chapters'] === []) {
    sendReadOnlyNotFoundResponse('Kapitoly nebyly nalezeny.', $isHeadRequest);
}

$latestChapterUpdate = max(array_map(
    static fn (array $chapter): int => strtotime((string)($chapter['updated_at'] ?? $chapter['created_at'] ?? '')) ?: 0,
    $chapters
));
$updatedTimestamp = max(
    strtotime((string)($episode['updated_at'] ?? $episode['created_at'] ?? 'now')) ?: time(),
    $latestChapterUpdate
);
$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
if (!is_string($json)) {
    sendReadOnlyNotFoundResponse('Kapitoly nebyly nalezeny.', $isHeadRequest);
}
$etag = '"' . hash('sha256', $json) . '"';
header('Cache-Control: public, max-age=300');
header('ETag: ' . $etag);
header('Last-Modified: ' . storedFileHttpDate($updatedTimestamp));
if (storedFileRequestValidatorsMatch($etag, $updatedTimestamp)) {
    http_response_code(304);
    exit;
}
sendReadOnlyContentHeaders('application/json+chapters; charset=UTF-8', $isHeadRequest, '', 'noindex, nofollow');
echo $json;
