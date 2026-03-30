<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('podcast')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$slug = podcastShowSlug(trim((string)($_GET['slug'] ?? '')));
if ($slug === '') {
    header('Location: ' . BASE_URL . '/podcast/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage = 10;

$showStmt = $pdo->prepare(
    "SELECT s.*,
            COUNT(e.id) AS episode_count,
            MAX(COALESCE(e.publish_at, e.created_at)) AS latest_episode_at
     FROM cms_podcast_shows s
     LEFT JOIN cms_podcasts e ON e.show_id = s.id
         AND " . podcastEpisodePublicVisibilitySql('e') . "
     WHERE s.slug = ?
       AND " . podcastShowPublicVisibilitySql('s') . "
     GROUP BY s.id
     LIMIT 1"
);
$showStmt->execute([$slug]);
$show = $showStmt->fetch() ?: null;

if (!$show) {
    http_response_code(404);
    renderPublicPage([
        'title' => 'Podcast nenalezen – ' . $siteName,
        'meta' => [
            'title' => 'Podcast nenalezen – ' . $siteName,
            'url' => siteUrl('/podcast/' . rawurlencode($slug)),
        ],
        'view' => 'not-found',
        'body_class' => 'page-podcast-not-found',
    ]);
    exit;
}

$show = hydratePodcastShowPresentation($show);
$requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
if (str_contains($requestPath, '/podcast/show.php')) {
    header('Location: ' . $show['public_path']);
    exit;
}

$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_podcasts p WHERE p.show_id = ? AND " . podcastEpisodePublicVisibilitySql('p'),
    [(int)$show['id']],
    $perPage
);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset, 'total' => $totalEpisodes] = $pagination;

$episodesStmt = $pdo->prepare(
    "SELECT p.*, s.slug AS show_slug, s.title AS show_title, s.cover_image AS show_cover_image
     FROM cms_podcasts p
     INNER JOIN cms_podcast_shows s ON s.id = p.show_id
     WHERE p.show_id = ?
       AND " . podcastEpisodePublicVisibilitySql('p') . "
     ORDER BY COALESCE(p.publish_at, p.created_at) DESC, COALESCE(p.episode_num, 0) DESC, p.id DESC
     LIMIT ? OFFSET ?"
);
$episodesStmt->execute([(int)$show['id'], $perPage, $offset]);
$episodes = array_map(
    static fn(array $episode): array => hydratePodcastEpisodePresentation($episode),
    $episodesStmt->fetchAll()
);

if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('podcast_show', (int)$show['id']);
}

$feedUrl = siteUrl('/podcast/feed.php?slug=' . rawurlencode((string)$show['slug']));
$metaDescription = $show['description_plain'] !== ''
    ? mb_strimwidth((string)$show['description_plain'], 0, 180, '…', 'UTF-8')
    : 'Přehled epizod podcastu ' . (string)$show['title'];
$pagerHtml = renderPager($page, $pages, '?', 'Stránkování epizod podcastu');

renderPublicPage([
    'title' => $show['title'] . ' – ' . $siteName,
    'meta' => [
        'title' => $show['title'] . ' – ' . $siteName,
        'description' => $metaDescription,
        'url' => $show['public_url'],
        'type' => 'article',
    ],
    'extra_head_html' => '  <link rel="alternate" type="application/rss+xml" title="'
        . h((string)$show['title']) . ' – RSS feed" href="' . h($feedUrl) . '">' . PHP_EOL
        . podcastShowStructuredData($show),
    'view' => 'modules/podcast-show',
    'view_data' => [
        'show' => $show,
        'episodes' => $episodes,
        'feedUrl' => $feedUrl,
        'pagerHtml' => $pagerHtml,
        'resultCount' => $totalEpisodes,
    ],
    'current_nav' => 'podcast',
    'body_class' => 'page-podcast-show',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/podcast_show_form.php?id=' . (int)$show['id'],
]);
