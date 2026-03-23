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

$total  = (int)$pdo->query("SELECT COUNT(*) FROM cms_news WHERE status = 'published'")->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = max(1, min($pages, (int)($_GET['strana'] ?? 1)));
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT n.id, n.content, n.created_at,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name
     FROM cms_news n
     LEFT JOIN cms_users u ON u.id = n.author_id
     WHERE n.status = 'published' ORDER BY n.created_at DESC LIMIT ? OFFSET ?"
);
$stmt->execute([$perPage, $offset]);
$items = $stmt->fetchAll();

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
