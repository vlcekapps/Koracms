<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('podcast')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = inputInt('get', 'id');
$showSlug = podcastShowSlug(trim((string)($_GET['show'] ?? '')));
$episodeSlug = podcastEpisodeSlug(trim((string)($_GET['slug'] ?? '')));

if ($id === null && ($showSlug === '' || $episodeSlug === '')) {
    header('Location: ' . BASE_URL . '/podcast/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

if ($showSlug !== '' && $episodeSlug !== '') {
    $stmt = $pdo->prepare(
        "SELECT p.*,
                s.id AS show_id, s.slug AS show_slug, s.title AS show_title, s.author AS show_author,
                s.cover_image AS show_cover_image, s.website_url AS show_website_url, s.description AS show_description,
                s.subtitle AS show_subtitle, s.language AS show_language, s.category AS show_category,
                s.owner_name AS show_owner_name, s.owner_email AS show_owner_email,
                s.explicit_mode AS show_explicit_mode, s.show_type AS show_show_type,
                s.feed_complete AS show_feed_complete, s.feed_episode_limit AS show_feed_episode_limit,
                s.status AS show_status, s.is_published AS show_is_published
         FROM cms_podcasts p
         INNER JOIN cms_podcast_shows s ON s.id = p.show_id
         WHERE s.slug = ?
           AND p.slug = ?
           AND " . podcastEpisodePublicVisibilitySql('p', 's') . "
         LIMIT 1"
    );
    $stmt->execute([$showSlug, $episodeSlug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT p.*,
                s.id AS show_id, s.slug AS show_slug, s.title AS show_title, s.author AS show_author,
                s.cover_image AS show_cover_image, s.website_url AS show_website_url, s.description AS show_description,
                s.subtitle AS show_subtitle, s.language AS show_language, s.category AS show_category,
                s.owner_name AS show_owner_name, s.owner_email AS show_owner_email,
                s.explicit_mode AS show_explicit_mode, s.show_type AS show_show_type,
                s.feed_complete AS show_feed_complete, s.feed_episode_limit AS show_feed_episode_limit,
                s.status AS show_status, s.is_published AS show_is_published
         FROM cms_podcasts p
         INNER JOIN cms_podcast_shows s ON s.id = p.show_id
         WHERE p.id = ?
           AND " . podcastEpisodePublicVisibilitySql('p', 's') . "
         LIMIT 1"
    );
    $stmt->execute([$id]);
}

$episode = $stmt->fetch() ?: null;
if (!$episode) {
    http_response_code(404);
    $missingUrl = $showSlug !== '' && $episodeSlug !== ''
        ? siteUrl('/podcast/' . rawurlencode($showSlug) . '/' . rawurlencode($episodeSlug))
        : siteUrl('/podcast/episode.php' . ($id !== null ? '?id=' . urlencode((string)$id) : ''));

    renderPublicPage([
        'title' => 'Epizoda nenalezena – ' . $siteName,
        'meta' => [
            'title' => 'Epizoda nenalezena – ' . $siteName,
            'url' => $missingUrl,
        ],
        'view' => 'not-found',
        'body_class' => 'page-podcast-episode-not-found',
    ]);
    exit;
}

$episode = hydratePodcastEpisodePresentation($episode);
$show = hydratePodcastShowPresentation([
    'id' => $episode['show_id'],
    'title' => $episode['show_title'],
    'slug' => $episode['show_slug'],
    'author' => $episode['show_author'] ?? '',
    'cover_image' => $episode['show_cover_image'] ?? '',
    'website_url' => $episode['show_website_url'] ?? '',
    'description' => $episode['show_description'] ?? '',
    'subtitle' => $episode['show_subtitle'] ?? '',
    'language' => $episode['show_language'] ?? 'cs',
    'category' => $episode['show_category'] ?? '',
    'owner_name' => $episode['show_owner_name'] ?? '',
    'owner_email' => $episode['show_owner_email'] ?? '',
    'explicit_mode' => $episode['show_explicit_mode'] ?? 'no',
    'show_type' => $episode['show_show_type'] ?? 'episodic',
    'feed_complete' => $episode['show_feed_complete'] ?? 0,
    'feed_episode_limit' => $episode['show_feed_episode_limit'] ?? 100,
    'status' => $episode['show_status'] ?? 'published',
    'is_published' => $episode['show_is_published'] ?? 1,
]);

$requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
if (str_contains($requestPath, '/podcast/episode.php')) {
    header('Location: ' . $episode['public_path']);
    exit;
}

if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('podcast_episode', (int)$episode['id']);
}

$feedUrl = siteUrl('/podcast/feed.php?slug=' . rawurlencode((string)$show['slug']));
$metaDescription = $episode['excerpt'] !== ''
    ? mb_strimwidth((string)$episode['excerpt'], 0, 180, '…', 'UTF-8')
    : 'Detail epizody ' . (string)$episode['title'];

renderPublicPage([
    'title' => $episode['title'] . ' – ' . $show['title'] . ' – ' . $siteName,
    'meta' => [
        'title' => $episode['title'] . ' – ' . $show['title'] . ' – ' . $siteName,
        'description' => $metaDescription,
        'url' => $episode['public_url'],
        'type' => 'article',
    ],
    'extra_head_html' => '  <link rel="alternate" type="application/rss+xml" title="'
        . h((string)$show['title']) . ' – RSS feed" href="' . h($feedUrl) . '">' . PHP_EOL
        . podcastEpisodeStructuredData($show, $episode),
    'view' => 'modules/podcast-episode',
    'view_data' => [
        'show' => $show,
        'episode' => $episode,
        'feedUrl' => $feedUrl,
    ],
    'current_nav' => 'podcast',
    'body_class' => 'page-podcast-episode',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/podcast_form.php?id=' . (int)$episode['id'] . '&show_id=' . (int)$show['id'],
]);
