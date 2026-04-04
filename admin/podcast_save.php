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
$subtitle = trim((string)($_POST['subtitle'] ?? ''));
$duration = trim((string)($_POST['duration'] ?? ''));
$episodeNum = !empty($_POST['episode_num']) ? max(1, (int)$_POST['episode_num']) : null;
$seasonNum = !empty($_POST['season_num']) ? max(1, (int)$_POST['season_num']) : null;
$episodeType = normalizePodcastEpisodeType((string)($_POST['episode_type'] ?? 'full'));
$explicitMode = normalizePodcastEpisodeExplicitMode((string)($_POST['explicit_mode'] ?? 'inherit'));
$blockFromFeed = isset($_POST['block_from_feed']) ? 1 : 0;
$deleteAudioFile = isset($_POST['audio_file_delete']);
$deleteImageFile = isset($_POST['image_file_delete']);
$backUrl = internalRedirectTarget(
    (string)($_POST['redirect'] ?? ''),
    BASE_URL . '/admin/podcast.php?show_id=' . (int)($showId ?? 0)
);

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
$redirectWithError = static function (string $errorCode) use ($redirectBase, $id, $showId, $backUrl): never {
    $query = '?show_id=' . $showId . '&err=' . rawurlencode($errorCode);
    if ($id !== null) {
        $query .= '&id=' . $id;
    }
    $query .= '&redirect=' . rawurlencode($backUrl);
    header('Location: ' . $redirectBase . $query);
    exit;
};

if ($title === '') {
    $redirectWithError('required');
}

$showStmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE id = ?");
$showStmt->execute([$showId]);
$show = $showStmt->fetch() ?: null;
if (!$show) {
    header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
    exit;
}
$show = hydratePodcastShowPresentation($show);

$existing = [
    'audio_file' => '',
    'image_file' => '',
    'status' => 'published',
];
$oldData = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT * FROM cms_podcasts WHERE id = ? AND deleted_at IS NULL");
    $existingStmt->execute([$id]);
    $existingRow = $existingStmt->fetch();
    if (!$existingRow) {
        header('Location: ' . $backUrl);
        exit;
    }
    $existing = array_merge($existing, $existingRow);
    $oldData = $existingRow;
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

$imageFilename = (string)$existing['image_file'];
$imageUpload = uploadPodcastEpisodeImage($_FILES['image_file'] ?? [], $imageFilename);
if ($imageUpload['error'] !== '') {
    $redirectWithError('image');
}
$imageFilename = $imageUpload['filename'];

if ($deleteImageFile && empty($_FILES['image_file']['name']) && $imageFilename !== '') {
    deletePodcastEpisodeImageFile($imageFilename);
    $imageFilename = '';
}

if (!empty($_FILES['audio_file']['name'])) {
    $audioUrl = '';
}

$publishAt = null;
if (!empty($_POST['publish_at'])) {
    $publishAtInput = trim((string)$_POST['publish_at']);
    $publishAt = validateDateTimeLocal($publishAtInput);
    if ($publishAt === null) {
        $redirectWithError('publish_at');
    }
}

if ($id !== null) {
    $requestedStatus = trim($_POST['article_status'] ?? '');
    if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
        $requestedStatus = $oldData['status'] ?? 'published';
    }
    if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
        $requestedStatus = (($oldData['status'] ?? '') === 'published') ? 'published' : 'pending';
    }

    if ($oldData) {
        saveRevision(
            $pdo,
            'podcast_episode',
            $id,
            podcastEpisodeRevisionSnapshot($oldData),
            podcastEpisodeRevisionSnapshot([
                'title' => $title,
                'slug' => $uniqueSlug,
                'description' => $description,
                'audio_url' => $audioUrl,
                'subtitle' => $subtitle,
                'duration' => $duration,
                'episode_num' => $episodeNum,
                'season_num' => $seasonNum,
                'episode_type' => $episodeType,
                'explicit_mode' => $explicitMode,
                'block_from_feed' => $blockFromFeed,
                'publish_at' => $publishAt,
                'status' => $requestedStatus,
            ])
        );
    }

    // Při první publikaci aktualizovat created_at
    $publishingNow = $requestedStatus === 'published' && ($oldData['status'] ?? '') !== 'published';
    $createdAtClause = $publishingNow ? ', created_at = NOW()' : '';

    $oldPath = $oldData ? podcastEpisodePublicPath([
        'show_slug' => (string)($show['slug'] ?? ''),
        'slug' => (string)($oldData['slug'] ?? ''),
    ]) : '';
    $pdo->prepare(
        "UPDATE cms_podcasts
         SET show_id = ?, title = ?, slug = ?, description = ?, audio_file = ?, image_file = ?, audio_url = ?,
             subtitle = ?, duration = ?, episode_num = ?, season_num = ?, episode_type = ?, explicit_mode = ?,
             block_from_feed = ?, publish_at = ?, status = ?, updated_at = NOW(){$createdAtClause}
         WHERE id = ?"
    )->execute([
        $showId,
        $title,
        $uniqueSlug,
        $description,
        $audioFilename,
        $imageFilename,
        $audioUrl,
        $subtitle,
        $duration,
        $episodeNum,
        $seasonNum,
        $episodeType,
        $explicitMode,
        $blockFromFeed,
        $publishAt,
        $requestedStatus,
        $id,
    ]);
    upsertPathRedirect($pdo, $oldPath, podcastEpisodePublicPath([
        'show_slug' => (string)$show['slug'],
        'slug' => $uniqueSlug,
    ]));
    logAction('podcast_edit', "id={$id} show_id={$showId} slug={$uniqueSlug}");
} else {
    $requestedStatus = trim($_POST['article_status'] ?? '');
    if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
        $requestedStatus = 'draft';
    }
    if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
        $requestedStatus = 'pending';
    }
    $status = $requestedStatus;
    $pdo->prepare(
        "INSERT INTO cms_podcasts
         (show_id, title, slug, description, audio_file, image_file, audio_url, subtitle, duration, episode_num, season_num,
          episode_type, explicit_mode, block_from_feed, publish_at, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $showId,
        $title,
        $uniqueSlug,
        $description,
        $audioFilename,
        $imageFilename,
        $audioUrl,
        $subtitle,
        $duration,
        $episodeNum,
        $seasonNum,
        $episodeType,
        $explicitMode,
        $blockFromFeed,
        $publishAt,
        $status,
    ]);
    $id = (int)$pdo->lastInsertId();
    logAction('podcast_add', "id={$id} show_id={$showId} slug={$uniqueSlug} status={$status}");
    if ($status === 'pending') {
        notifyPendingContent('Podcast', $title, '/admin/podcast.php');
    }
}

header('Location: ' . $backUrl);
exit;
