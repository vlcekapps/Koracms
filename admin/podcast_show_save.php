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

$redirectBase = BASE_URL . '/admin/podcast_show_form.php';
$redirectWithError = static function (string $errorCode) use ($redirectBase, $id): never {
    $query = $id !== null
        ? '?id=' . $id . '&err=' . rawurlencode($errorCode)
        : '?err=' . rawurlencode($errorCode);
    header('Location: ' . $redirectBase . $query);
    exit;
};

if ($title === '') {
    $redirectWithError('required');
}

$existing = [
    'cover_image' => '',
];
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT cover_image FROM cms_podcast_shows WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingRow = $existingStmt->fetch();
    if (!$existingRow) {
        header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
        exit;
    }
    $existing = array_merge($existing, $existingRow);
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
    $pdo->prepare(
        "UPDATE cms_podcast_shows
         SET title = ?, slug = ?, description = ?, author = ?, subtitle = ?, cover_image = ?, language = ?,
             category = ?, owner_name = ?, owner_email = ?, explicit_mode = ?, show_type = ?, feed_complete = ?,
             feed_episode_limit = ?, website_url = ?, updated_at = NOW()
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
        $id,
    ]);
    logAction('podcast_show_edit', "id={$id} slug={$uniqueSlug}");
} else {
    $pdo->prepare(
        "INSERT INTO cms_podcast_shows
         (title, slug, description, author, subtitle, cover_image, language, category, owner_name, owner_email,
          explicit_mode, show_type, feed_complete, feed_episode_limit, website_url)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
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
    ]);
    $id = (int)$pdo->lastInsertId();
    logAction('podcast_show_add', "id={$id} slug={$uniqueSlug}");
}

header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
exit;
