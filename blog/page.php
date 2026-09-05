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
    renderPublicNotFoundPage([
        'body_class' => 'page-not-found',
    ]);
}

$pdo = db_connect();
$previewInput = $_GET['preview'] ?? '';
$previewToken = is_string($previewInput) ? trim($previewInput) : '__invalid_preview_token__';
if ($previewToken !== '') {
    sendNoStoreNoIndexHeaders();
    if (!isValidArticlePreviewToken($previewToken)) {
        $previewToken = '__invalid_preview_token__';
    }
}
$visibilitySql = $previewToken !== ''
    ? 'p.preview_token = ?'
    : "p.status = 'published' AND p.is_published = 1
       AND (p.publish_at IS NULL OR p.publish_at <= NOW())
       AND (p.unpublish_at IS NULL OR p.unpublish_at > NOW())";
$stmt = $pdo->prepare(
    "SELECT p.*, b.slug AS blog_slug, b.name AS blog_name
     FROM cms_pages p
     INNER JOIN cms_blogs b ON b.id = p.blog_id
     WHERE p.slug = ?
       AND p.blog_id = ?
       AND p.deleted_at IS NULL
       AND {$visibilitySql}
     LIMIT 1"
);
$pageParams = [$pageSlug, (int)$blog['id']];
if ($previewToken !== '') {
    $pageParams[] = $previewToken;
}
$stmt->execute($pageParams);
$page = $stmt->fetch() ?: null;

if (!$page) {
    renderPublicNotFoundPage([
        'meta' => [
            'url' => blogIndexPath($blog),
        ],
        'body_class' => 'page-not-found',
    ]);
}

if ($previewToken === '') {
    trackPageView('page', (int)$page['id']);
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
