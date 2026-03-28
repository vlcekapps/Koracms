<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('news')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage  = max(1, (int)getSetting('news_per_page', '10'));

$pag = paginate($pdo, "SELECT COUNT(*) FROM cms_news WHERE status = 'published'", [], $perPage);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pag;

$stmt = $pdo->prepare(
    "SELECT n.id, n.title, n.slug, n.content, n.created_at,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name,
            u.author_public_enabled, u.author_slug, u.role AS author_role
     FROM cms_news n
     LEFT JOIN cms_users u ON u.id = n.author_id
     WHERE n.status = 'published'
     ORDER BY n.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute([$perPage, $offset]);
$items = array_map(
    static fn(array $item): array => hydrateNewsPresentation($item),
    $stmt->fetchAll()
);

renderPublicPage([
    'title' => 'Novinky – ' . $siteName,
    'meta' => [
        'title' => 'Novinky – ' . $siteName,
        'url' => BASE_URL . '/news/index.php',
    ],
    'view' => 'modules/news-index',
    'view_data' => [
        'items' => $items,
        'pages' => $pages,
        'page' => $page,
    ],
    'current_nav' => 'news',
    'body_class' => 'page-news-index',
    'page_kind' => 'listing',
]);
