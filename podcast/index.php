<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('podcast')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage = 12;

$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_podcast_shows s WHERE " . podcastShowPublicVisibilitySql('s'),
    [],
    $perPage
);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset, 'total' => $totalShows] = $pagination;

$stmt = $pdo->prepare(
    "SELECT s.*,
            COUNT(e.id) AS episode_count,
            MAX(COALESCE(e.publish_at, e.created_at)) AS latest_episode_at
     FROM cms_podcast_shows s
     LEFT JOIN cms_podcasts e ON e.show_id = s.id
         AND " . podcastEpisodePublicVisibilitySql('e') . "
     WHERE " . podcastShowPublicVisibilitySql('s') . "
     GROUP BY s.id
     ORDER BY latest_episode_at DESC, s.title ASC
     LIMIT ? OFFSET ?"
);
$stmt->execute([$perPage, $offset]);
$shows = $stmt->fetchAll();
$shows = array_map(
    static fn(array $show): array => hydratePodcastShowPresentation($show),
    $shows
);
$pagerHtml = renderPager($page, $pages, '?', 'Stránkování podcastů');

renderPublicPage([
    'title' => 'Podcasty – ' . $siteName,
    'meta' => [
        'title' => 'Podcasty – ' . $siteName,
        'url' => siteUrl('/podcast/index.php'),
    ],
    'view' => 'modules/podcast-index',
    'view_data' => [
        'shows' => $shows,
        'pagerHtml' => $pagerHtml,
        'resultCount' => $totalShows,
    ],
    'current_nav' => 'podcast',
    'body_class' => 'page-podcast-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/podcast_shows.php',
]);
