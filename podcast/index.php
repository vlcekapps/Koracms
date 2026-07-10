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
$categoryStmt = $pdo->query(
    "SELECT DISTINCT TRIM(s.category) AS category
     FROM cms_podcast_shows s
     WHERE " . podcastShowPublicVisibilitySql('s') . "
       AND TRIM(COALESCE(s.category, '')) <> ''
     ORDER BY category ASC"
);
$categories = array_values(array_filter(array_map(
    static fn (mixed $category): string => normalizePodcastCategoryFilter((string)$category),
    $categoryStmt->fetchAll(PDO::FETCH_COLUMN)
)));
$query = normalizePodcastDiscoveryQuery((string)($_GET['q'] ?? ''));
$requestedCategory = normalizePodcastCategoryFilter((string)($_GET['kategorie'] ?? ''));
$categoryFilter = in_array($requestedCategory, $categories, true) ? $requestedCategory : '';
$whereParts = [podcastShowPublicVisibilitySql('s')];
$params = [];
if ($query !== '') {
    $whereParts[] = '(s.title LIKE ? OR s.author LIKE ? OR s.description LIKE ? OR s.category LIKE ?)';
    $searchTerm = '%' . $query . '%';
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}
if ($categoryFilter !== '') {
    $whereParts[] = 'TRIM(s.category) = ?';
    $params[] = $categoryFilter;
}
$whereSql = implode(' AND ', $whereParts);

$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_podcast_shows s WHERE {$whereSql}",
    $params,
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
     WHERE {$whereSql}
     GROUP BY s.id
     ORDER BY latest_episode_at DESC, s.title ASC
     LIMIT ? OFFSET ?"
);
$stmt->execute([...$params, $perPage, $offset]);
$shows = $stmt->fetchAll();
$shows = array_map(
    static fn (array $show): array => hydratePodcastShowPresentation($show),
    $shows
);
$pagerQuery = [];
if ($query !== '') {
    $pagerQuery['q'] = $query;
}
if ($categoryFilter !== '') {
    $pagerQuery['kategorie'] = $categoryFilter;
}
$pagerBase = $pagerQuery === [] ? '?' : '?' . http_build_query($pagerQuery) . '&';
$pagerHtml = renderPager($page, $pages, $pagerBase, 'Stránkování podcastů');

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
        'query' => $query,
        'categories' => $categories,
        'categoryFilter' => $categoryFilter,
    ],
    'current_nav' => 'podcast',
    'body_class' => 'page-podcast-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/podcast_shows.php',
]);
