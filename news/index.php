<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('news')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage = max(1, (int)getSetting('news_per_page', '10'));
$q = trim((string)($_GET['q'] ?? ''));
$like = '%' . $q . '%';

$whereParts = [newsPublicVisibilitySql('n')];
$countParams = [];

if ($q !== '') {
    $whereParts[] = '(n.title LIKE ? OR n.content LIKE ?)';
    $countParams[] = $like;
    $countParams[] = $like;
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);
$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_news n {$whereSql}",
    $countParams,
    $perPage
);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pagination;

$stmt = $pdo->prepare(
    "SELECT n.id, n.title, n.slug, n.content, n.meta_title, n.meta_description, n.created_at, n.updated_at,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name,
            u.author_public_enabled, u.author_slug, u.role AS author_role
     FROM cms_news n
     LEFT JOIN cms_users u ON u.id = n.author_id
     {$whereSql}
     ORDER BY n.created_at DESC, n.id DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($countParams, [$perPage, $offset]));
$items = array_map(
    static fn(array $item): array => hydrateNewsPresentation($item),
    $stmt->fetchAll()
);

$pageTitleBase = $q !== '' ? 'Hledání v novinkách' : 'Novinky';
$fullTitle = $pageTitleBase . ' – ' . $siteName;
$metaDescription = $q !== ''
    ? 'Výsledky hledání v novinkách pro dotaz „' . $q . '“.'
    : 'Přehled nejnovějších aktualit a krátkých zpráv.';
$metaUrl = siteUrl(appendUrlQuery('/news/', $q !== '' ? ['q' => $q] : []));
$pagerBaseUrl = '?' . ($q !== '' ? http_build_query(['q' => $q]) . '&' : '');

renderPublicPage([
    'title' => $fullTitle,
    'meta' => [
        'title' => $fullTitle,
        'description' => $metaDescription,
        'url' => $metaUrl,
    ],
    'view' => 'modules/news-index',
    'view_data' => [
        'items' => $items,
        'pages' => $pages,
        'page' => $page,
        'q' => $q,
        'pager_base_url' => $pagerBaseUrl,
    ],
    'current_nav' => 'news',
    'body_class' => 'page-news-index',
    'page_kind' => 'listing',
]);
