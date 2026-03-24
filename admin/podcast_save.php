<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$showId = inputInt('post', 'show_id');
$title = trim((string)($_POST['title'] ?? ''));
$slugInput = trim((string)($_POST['slug'] ?? ''));
$description = (string)($_POST['description'] ?? '');
$audioUrlInput = trim((string)($_POST['audio_url'] ?? ''));
$duration = trim((string)($_POST['duration'] ?? ''));
$episodeNum = !empty($_POST['episode_num']) ? max(1, (int)$_POST['episode_num']) : null;
$deleteAudioFile = isset($_POST['audio_file_delete']);

if ($showId === null && $id !== null) {
    $showLookup = $pdo->prepare("SELECT show_id FROM cms_podcasts WHERE id = ?");
    $showLookup->execute([$id]);
    $showId = (int)($showLookup->fetchColumn() ?: 0);
}

if ($showId === null || $showId < 1) {
    header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
    exit;
}

$redirectBase = BASE_URL . '/admin/podcast_form.php';
$redirectWithError = static function (string $errorCode) use ($redirectBase, $id, $showId): never {
    $query = '?show_id=' . $showId . '&err=' . rawurlencode($errorCode);
    if ($id !== null) {
        $query .= '&id=' . $id;
    }
    header('Location: ' . $redirectBase . $query);
    exit;
};

if ($title === '') {
    $redirectWithError('required');
}

$showStmt = $pdo->prepare("SELECT id FROM cms_podcast_shows WHERE id = ?");
$showStmt->execute([$showId]);
if (!$showStmt->fetchColumn()) {
    header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
    exit;
}

$existing = [
    'audio_file' => '',
];
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT audio_file FROM cms_podcasts WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingRow = $existingStmt->fetch();
    if (!$existingRow) {
        header('Location: ' . BASE_URL . '/admin/podcast.php?show_id=' . (int)$showId);
        exit;
    }
    $existing = array_merge($existing, $existingRow);
}

$resolvedSlug = podcastEpisodeSlug($slugInput !== '' ? $slugInput : $title);
if ($resolvedSlug === '') {
    $redirectWithError('slug');
}

$uniqueSlug = uniquePodcastEpisodeSlug($pdo, $showId, $resolvedSlug, $id);
if ($slugInput !== '' && $uniqueSlug !== $resolvedSlug) {
    $redirectWithError('slug_taken');
}

$audioUrl = normalizePodcastEpisodeAudioUrl($audioUrlInput);
if ($audioUrlInput !== '' && $audioUrl === '') {
    $redirectWithError('url');
}

$audioFilename = (string)$existing['audio_file'];
$audioUpload = uploadPodcastAudioFile($_FILES['audio_file'] ?? [], $audioFilename);
if ($audioUpload['error'] !== '') {
    $redirectWithError('audio');
}
$audioFilename = $audioUpload['filename'];

if ($deleteAudioFile && empty($_FILES['audio_file']['name']) && $audioFilename !== '') {
    deletePodcastAudioFile($audioFilename);
    $audioFilename = '';
}

if (!empty($_FILES['audio_file']['name'])) {
    $audioUrl = '';
}

$publishAt = null;
if (!empty($_POST['publish_at'])) {
    $dateTime = \DateTime::createFromFormat('Y-m-d\TH:i', (string)$_POST['publish_at']);
    if ($dateTime instanceof \DateTime) {
        $publishAt = $dateTime->format('Y-m-d H:i:s');
    }
}

if ($id !== null) {
    $pdo->prepare(
        "UPDATE cms_podcasts
         SET show_id = ?, title = ?, slug = ?, description = ?, audio_file = ?, audio_url = ?,
             duration = ?, episode_num = ?, publish_at = ?, updated_at = NOW()
         WHERE id = ?"
    )->execute([
        $showId,
        $title,
        $uniqueSlug,
        $description,
        $audioFilename,
        $audioUrl,
        $duration,
        $episodeNum,
        $publishAt,
        $id,
    ]);
    logAction('podcast_edit', "id={$id} show_id={$showId} slug={$uniqueSlug}");
} else {
    $status = currentUserHasCapability('content_approve_shared') ? 'published' : 'pending';
    $pdo->prepare(
        "INSERT INTO cms_podcasts
         (show_id, title, slug, description, audio_file, audio_url, duration, episode_num, publish_at, status)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $showId,
        $title,
        $uniqueSlug,
        $description,
        $audioFilename,
        $audioUrl,
        $duration,
        $episodeNum,
        $publishAt,
        $status,
    ]);
    $id = (int)$pdo->lastInsertId();
    logAction('podcast_add', "id={$id} show_id={$showId} slug={$uniqueSlug} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/podcast.php?show_id=' . (int)$showId);
exit;
