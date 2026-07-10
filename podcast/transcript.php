<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
$isHeadRequest = requireReadOnlyHttpMethod();

if (!isModuleEnabled('podcast')) {
    sendReadOnlyNotFoundResponse('Přepis není dostupný.', $isHeadRequest);
}

$episodeId = inputInt('get', 'id');
if ($episodeId === null || $episodeId < 1) {
    sendReadOnlyNotFoundResponse('Přepis nebyl nalezen.', $isHeadRequest);
}

$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT p.*, s.title AS show_title, s.status AS show_status, s.is_published AS show_is_published
     FROM cms_podcasts p
     INNER JOIN cms_podcast_shows s ON s.id = p.show_id
     WHERE p.id = ? LIMIT 1"
);
$stmt->execute([$episodeId]);
$episode = $stmt->fetch() ?: null;
if ($episode === null || !podcastEpisodeIsPublic($episode) || trim((string)($episode['transcript'] ?? '')) === '') {
    sendReadOnlyNotFoundResponse('Přepis nebyl nalezen.', $isHeadRequest);
}

$updatedTimestamp = strtotime((string)($episode['updated_at'] ?? $episode['created_at'] ?? 'now')) ?: time();
$etag = '"' . hash('sha256', (string)$episode['id'] . '|' . (string)$episode['updated_at'] . '|' . (string)$episode['transcript']) . '"';
header('Cache-Control: public, max-age=300');
header('ETag: ' . $etag);
header('Last-Modified: ' . storedFileHttpDate($updatedTimestamp));
if (storedFileRequestValidatorsMatch($etag, $updatedTimestamp)) {
    http_response_code(304);
    exit;
}
sendReadOnlyContentHeaders('text/html; charset=UTF-8', $isHeadRequest, '', 'noindex, follow');

$language = trim((string)getSetting('site_language', 'cs')) ?: 'cs';
?>
<!doctype html>
<html lang="<?= h($language) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Přepis: <?= h((string)$episode['title']) ?></title>
</head>
<body>
  <main aria-labelledby="podcast-transcript-heading">
    <h1 id="podcast-transcript-heading">Přepis: <?= h((string)$episode['title']) ?></h1>
    <p><?= h((string)$episode['show_title']) ?></p>
    <div><?= renderContent((string)$episode['transcript']) ?></div>
  </main>
</body>
</html>
