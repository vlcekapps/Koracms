<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('blog')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$siteDesc = getSetting('site_description', '');
$blogId = isset($_GET['blog_id']) ? (int)$_GET['blog_id'] : null;
$blog = $blogId !== null ? getBlogById($blogId) : ($GLOBALS['current_blog'] ?? getDefaultBlog());
if (!$blog) {
    renderPublicNotFoundPage([
        'body_class' => 'page-not-found',
    ]);
}

$blogId = (int)$blog['id'];
$seriesSlug = blogSeriesSlug(trim((string)($_GET['series_slug'] ?? $_GET['slug'] ?? '')));
$seriesDetail = publicBlogSeriesDetail($pdo, $blogId, $seriesSlug);
if ($seriesDetail === null) {
    renderPublicNotFoundPage([
        'body_class' => 'page-not-found',
    ]);
}

$series = $seriesDetail['series'];
$articles = $seriesDetail['articles'];
$metaDescription = trim((string)($series['description'] ?? ''));
if ($metaDescription === '') {
    $metaDescription = $siteDesc !== '' ? $siteDesc : (string)$blog['name'];
}

renderPublicPage([
    'title' => (string)$series['title'] . ' – ' . (string)$blog['name'] . ' – ' . $siteName,
    'meta' => [
        'title' => (string)$series['title'],
        'description' => $metaDescription,
        'url' => blogSeriesUrl($blog, $series),
        'type' => 'website',
    ],
    'view' => 'modules/blog-series',
    'view_data' => [
        'series' => $series,
        'articles' => $articles,
        'blog' => $blog,
    ],
    'current_nav' => 'blog:' . $blog['slug'],
    'body_class' => 'page-blog-series',
    'page_kind' => 'listing',
]);
