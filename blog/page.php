<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('blog')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$blog = $GLOBALS['current_blog'] ?? null;
$pageSlug = pageSlug(trim((string)($_GET['page_slug'] ?? '')));

if (!$blog || $pageSlug === '') {
    http_response_code(404);
    $siteName = getSetting('site_name', 'Kora CMS');
    renderPublicPage([
        'title' => 'Stránka nenalezena – ' . $siteName,
        'meta' => ['title' => 'Stránka nenalezena – ' . $siteName],
        'view' => 'not-found',
        'body_class' => 'page-not-found',
    ]);
    exit;
}

$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT p.*, b.slug AS blog_slug, b.name AS blog_name
     FROM cms_pages p
     INNER JOIN cms_blogs b ON b.id = p.blog_id
     WHERE p.slug = ?
       AND p.blog_id = ?
       AND p.deleted_at IS NULL
       AND p.status = 'published'
       AND p.is_published = 1
     LIMIT 1"
);
$stmt->execute([$pageSlug, (int)$blog['id']]);
$page = $stmt->fetch() ?: null;

if (!$page) {
    http_response_code(404);
    $siteName = getSetting('site_name', 'Kora CMS');
    renderPublicPage([
        'title' => 'Stránka nenalezena – ' . $siteName,
        'meta' => [
            'title' => 'Stránka nenalezena – ' . $siteName,
            'url' => blogIndexPath($blog),
        ],
        'view' => 'not-found',
        'body_class' => 'page-not-found',
    ]);
    exit;
}

$siteName = getSetting('site_name', 'Kora CMS');
$metaTitle = trim((string)($page['title'] ?? ''));
$metaDescription = trim((string)($blog['description'] ?? ''));

renderPublicPage([
    'title' => $metaTitle . ' – ' . $siteName,
    'meta' => [
        'title' => $metaTitle . ' – ' . $siteName,
        'description' => $metaDescription !== '' ? $metaDescription : $metaTitle,
        'url' => pagePublicPath($page),
        'type' => 'article',
    ],
    'view' => 'page',
    'view_data' => [
        'page' => $page,
        'pageKicker' => 'Stránka blogu',
        'backLinkHref' => blogIndexPath($blog),
        'backLinkLabel' => 'Zpět na blog ' . (string)$blog['name'],
    ],
    'current_nav' => 'blog:' . (string)$blog['slug'],
    'page_kind' => 'page',
    'body_class' => 'page-blog-static',
    'admin_edit_url' => BASE_URL . '/admin/page_form.php?id=' . (int)$page['id'],
]);
