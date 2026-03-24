<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('news')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = inputInt('get', 'id');
$slug = newsSlug(trim($_GET['slug'] ?? ''));
if ($id === null && $slug === '') {
    header('Location: ' . BASE_URL . '/news/index.php');
    exit;
}

$pdo = db_connect();

if ($slug !== '') {
    $stmt = $pdo->prepare(
        "SELECT n.id, n.title, n.slug, n.content, n.created_at, n.updated_at,
                COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name,
                u.author_public_enabled, u.author_slug, u.role AS author_role
         FROM cms_news n
         LEFT JOIN cms_users u ON u.id = n.author_id
         WHERE n.slug = ? AND n.status = 'published'
         LIMIT 1"
    );
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT n.id, n.title, n.slug, n.content, n.created_at, n.updated_at,
                COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name,
                u.author_public_enabled, u.author_slug, u.role AS author_role
         FROM cms_news n
         LEFT JOIN cms_users u ON u.id = n.author_id
         WHERE n.id = ? AND n.status = 'published'
         LIMIT 1"
    );
    $stmt->execute([$id]);
}

$news = $stmt->fetch() ?: null;
if (!$news) {
    http_response_code(404);
    $siteName = getSetting('site_name', 'Kora CMS');
    renderPublicPage([
        'title' => 'Novinka nenalezena – ' . $siteName,
        'meta' => [
            'title' => 'Novinka nenalezena – ' . $siteName,
            'url' => $slug !== ''
                ? BASE_URL . '/news/' . rawurlencode($slug)
                : BASE_URL . '/news/article.php' . ($id !== null ? '?id=' . urlencode((string)$id) : ''),
        ],
        'view' => 'not-found',
        'body_class' => 'page-news-not-found',
    ]);
    exit;
}

$news = hydrateNewsPresentation($news);

if ($slug === '' && !empty($news['slug'])) {
    header('Location: ' . newsPublicPath($news));
    exit;
}

if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('news', (int)$news['id']);
}

$siteName = getSetting('site_name', 'Kora CMS');

renderPublicPage([
    'title' => $news['title'] . ' – ' . $siteName,
    'meta' => [
        'title' => $news['title'] . ' – ' . $siteName,
        'description' => newsExcerpt((string)$news['content'], 180),
        'url' => newsPublicUrl($news),
        'type' => 'article',
    ],
    'view' => 'modules/news-article',
    'view_data' => [
        'news' => $news,
    ],
    'current_nav' => 'news',
    'body_class' => 'page-news-article',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/news_form.php?id=' . (int)$news['id'],
]);
