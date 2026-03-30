<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$title = trim((string)($_POST['title'] ?? ''));
$slugInput = trim((string)($_POST['slug'] ?? ''));
$author = trim((string)($_POST['author'] ?? ''));
$subtitle = trim((string)($_POST['subtitle'] ?? ''));
$language = trim((string)($_POST['language'] ?? 'cs'));
$category = trim((string)($_POST['category'] ?? ''));
$ownerName = trim((string)($_POST['owner_name'] ?? ''));
$ownerEmailInput = trim((string)($_POST['owner_email'] ?? ''));
$explicitMode = normalizePodcastExplicitMode((string)($_POST['explicit_mode'] ?? 'no'));
$showType = normalizePodcastShowType((string)($_POST['show_type'] ?? 'episodic'));
$feedComplete = isset($_POST['feed_complete']) ? 1 : 0;
$feedEpisodeLimitInput = trim((string)($_POST['feed_episode_limit'] ?? '100'));
$websiteUrlInput = trim((string)($_POST['website_url'] ?? ''));
$description = (string)($_POST['description'] ?? '');
$deleteCover = isset($_POST['cover_image_delete']);
$isPublished = isset($_POST['is_published']) ? 1 : 0;
$backUrl = internalRedirectTarget((string)($_POST['redirect'] ?? ''), BASE_URL . '/admin/podcast_shows.php');

$redirectBase = BASE_URL . '/admin/podcast_show_form.php';
$redirectWithError = static function (string $errorCode) use ($redirectBase, $id, $backUrl): never {
    $query = $id !== null
        ? '?id=' . $id . '&err=' . rawurlencode($errorCode)
        : '?err=' . rawurlencode($errorCode);
    $query .= '&redirect=' . rawurlencode($backUrl);
    header('Location: ' . $redirectBase . $query);
    exit;
};

if ($title === '') {
    $redirectWithError('required');
}

$existing = [
    'cover_image' => '',
    'status' => 'published',
    'is_published' => 1,
];
$oldData = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingRow = $existingStmt->fetch();
    if (!$existingRow) {
        header('Location: ' . $backUrl);
        exit;
    }
    $existing = array_merge($existing, $existingRow);
    $oldData = $existingRow;
}

$resolvedSlug = podcastShowSlug($slugInput !== '' ? $slugInput : $title);
if ($resolvedSlug === '') {
    $redirectWithError('slug');
}

$uniqueSlug = uniquePodcastShowSlug($pdo, $resolvedSlug, $id);
if ($slugInput !== '' && $uniqueSlug !== $resolvedSlug) {
    $redirectWithError('slug_taken');
}

$websiteUrl = normalizePodcastWebsiteUrl($websiteUrlInput);
if ($websiteUrlInput !== '' && $websiteUrl === '') {
    $redirectWithError('url');
}

$ownerEmail = normalizePodcastOwnerEmail($ownerEmailInput);
if ($ownerEmailInput !== '' && $ownerEmail === '') {
    $redirectWithError('owner_email');
}

$feedEpisodeLimit = normalizePodcastFeedEpisodeLimit($feedEpisodeLimitInput);
if ($feedEpisodeLimitInput !== '' && (!preg_match('/^\d+$/', $feedEpisodeLimitInput) || (int)$feedEpisodeLimitInput < 1 || (int)$feedEpisodeLimitInput > 1000)) {
    $redirectWithError('feed_limit');
}

$coverFilename = (string)$existing['cover_image'];
$coverUpload = uploadPodcastCoverImage($_FILES['cover_image'] ?? [], $coverFilename);
if ($coverUpload['error'] !== '') {
    $redirectWithError('cover');
}
$coverFilename = $coverUpload['filename'];

if ($deleteCover && empty($_FILES['cover_image']['name']) && $coverFilename !== '') {
    deletePodcastCoverFile($coverFilename);
    $coverFilename = '';
}

if ($id !== null) {
    if ($oldData) {
        saveRevision(
            $pdo,
            'podcast_show',
            $id,
            podcastShowRevisionSnapshot($oldData),
            podcastShowRevisionSnapshot([
                'title' => $title,
                'slug' => $uniqueSlug,
                'description' => $description,
                'author' => $author,
                'subtitle' => $subtitle,
                'language' => $language !== '' ? $language : 'cs',
                'category' => $category,
                'owner_name' => $ownerName,
                'owner_email' => $ownerEmail,
                'explicit_mode' => $explicitMode,
                'show_type' => $showType,
                'feed_complete' => $feedComplete,
                'feed_episode_limit' => $feedEpisodeLimit,
                'website_url' => $websiteUrl,
                'status' => (string)($oldData['status'] ?? 'published'),
                'is_published' => $isPublished,
            ])
        );
    }

    $oldPath = $oldData ? podcastShowPublicPath($oldData) : '';
    $pdo->prepare(
        "UPDATE cms_podcast_shows
         SET title = ?, slug = ?, description = ?, author = ?, subtitle = ?, cover_image = ?, language = ?,
             category = ?, owner_name = ?, owner_email = ?, explicit_mode = ?, show_type = ?, feed_complete = ?,
             feed_episode_limit = ?, website_url = ?, is_published = ?, status = ?, updated_at = NOW()
         WHERE id = ?"
    )->execute([
        $title,
        $uniqueSlug,
        $description,
        $author,
        $subtitle,
        $coverFilename,
        $language !== '' ? $language : 'cs',
        $category,
        $ownerName,
        $ownerEmail,
        $explicitMode,
        $showType,
        $feedComplete,
        $feedEpisodeLimit,
        $websiteUrl,
        $isPublished,
        (string)($oldData['status'] ?? 'published'),
        $id,
    ]);
    upsertPathRedirect($pdo, $oldPath, podcastShowPublicPath(['id' => $id, 'slug' => $uniqueSlug]));
    logAction('podcast_show_edit', "id={$id} slug={$uniqueSlug}");
} else {
    $status = currentUserHasCapability('content_approve_shared') ? 'published' : 'pending';
    $pdo->prepare(
        "INSERT INTO cms_podcast_shows
         (title, slug, description, author, subtitle, cover_image, language, category, owner_name, owner_email,
          explicit_mode, show_type, feed_complete, feed_episode_limit, website_url, is_published, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $title,
        $uniqueSlug,
        $description,
        $author,
        $subtitle,
        $coverFilename,
        $language !== '' ? $language : 'cs',
        $category,
        $ownerName,
        $ownerEmail,
        $explicitMode,
        $showType,
        $feedComplete,
        $feedEpisodeLimit,
        $websiteUrl,
        $isPublished,
        $status,
    ]);
    $id = (int)$pdo->lastInsertId();
    logAction('podcast_show_add', "id={$id} slug={$uniqueSlug} status={$status}");
    if ($status === 'pending') {
        notifyPendingContent('Podcastový pořad', $title, '/admin/podcast_shows.php');
    }
}

header('Location: ' . $backUrl);
exit;
