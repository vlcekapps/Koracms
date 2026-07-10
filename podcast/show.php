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
    renderPublicNotFoundPage([
        'title' => 'Podcast nenalezen',
        'meta' => [
            'url' => siteUrl('/podcast/' . rawurlencode($slug)),
        ],
        'body_class' => 'page-podcast-not-found',
    ]);
}

$show = hydratePodcastShowPresentation($show);
$requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
if (str_contains($requestPath, '/podcast/show.php')) {
    header('Location: ' . $show['public_path']);
    exit;
}

$seasonsStmt = $pdo->prepare(
    "SELECT DISTINCT p.season_num
     FROM cms_podcasts p
     WHERE p.show_id = ?
       AND p.season_num IS NOT NULL
       AND p.season_num > 0
       AND " . podcastEpisodePublicVisibilitySql('p') . "
     ORDER BY p.season_num DESC"
);
$seasonsStmt->execute([(int)$show['id']]);
$seasons = array_map('intval', $seasonsStmt->fetchAll(PDO::FETCH_COLUMN));
$requestedSeason = inputInt('get', 'sezona');
$seasonFilter = $requestedSeason !== null && in_array($requestedSeason, $seasons, true)
    ? $requestedSeason
    : null;
$episodeWhere = "p.show_id = ? AND " . podcastEpisodePublicVisibilitySql('p');
$episodeParams = [(int)$show['id']];
if ($seasonFilter !== null) {
    $episodeWhere .= " AND p.season_num = ?";
    $episodeParams[] = $seasonFilter;
}

$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_podcasts p WHERE {$episodeWhere}",
    $episodeParams,
    $perPage
);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset, 'total' => $totalEpisodes] = $pagination;

$episodesStmt = $pdo->prepare(
    "SELECT p.*, s.slug AS show_slug, s.title AS show_title, s.cover_image AS show_cover_image
     FROM cms_podcasts p
     INNER JOIN cms_podcast_shows s ON s.id = p.show_id
     WHERE {$episodeWhere}
     ORDER BY COALESCE(p.publish_at, p.created_at) DESC, COALESCE(p.episode_num, 0) DESC, p.id DESC
     LIMIT ? OFFSET ?"
);
$episodesStmt->execute([...$episodeParams, $perPage, $offset]);
$episodes = array_map(
    static fn (array $episode): array => hydratePodcastEpisodePresentation($episode),
    $episodesStmt->fetchAll()
);
$peopleStmt = $pdo->prepare(
    "SELECT * FROM cms_podcast_people
     WHERE show_id = ? AND episode_id IS NULL
     ORDER BY sort_order ASC, name ASC, id ASC"
);
$peopleStmt->execute([(int)$show['id']]);
$people = $peopleStmt->fetchAll();
$platformStmt = $pdo->prepare(
    "SELECT platform_key, label, url
     FROM cms_podcast_platform_links
     WHERE show_id = ?
     ORDER BY sort_order ASC, id ASC"
);
$platformStmt->execute([(int)$show['id']]);
$platformLinks = $platformStmt->fetchAll();

if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('podcast_show', (int)$show['id']);
}

$feedUrl = siteUrl('/podcast/feed.php?slug=' . rawurlencode((string)$show['slug']));
$metaDescription = $show['description_plain'] !== ''
    ? mb_strimwidth((string)$show['description_plain'], 0, 180, '…', 'UTF-8')
    : 'Přehled epizod podcastu ' . (string)$show['title'];
$pagerBase = $seasonFilter !== null ? '?sezona=' . $seasonFilter . '&' : '?';
$pagerHtml = renderPager($page, $pages, $pagerBase, 'Stránkování epizod podcastu');

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
        'people' => $people,
        'platformLinks' => $platformLinks,
        'seasons' => $seasons,
        'seasonFilter' => $seasonFilter,
    ],
    'current_nav' => 'podcast',
    'body_class' => 'page-podcast-show',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/podcast_show_form.php?id=' . (int)$show['id'],
]);
