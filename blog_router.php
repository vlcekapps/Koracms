<?php
/**
 * Front-controller pro dynamické blog slugy (multiblog).
 * Volán z .htaccess catch-all pravidel pro URL jako /cooking/ nebo /cooking/recipe-slug.
 */
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

if (!isModuleEnabled('blog')) {
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

$blogSlug = slugify(trim($_GET['blog_slug'] ?? ''));
$articleSlug = articleSlug(trim($_GET['slug'] ?? ''));

if ($blogSlug === '') {
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

$blog = getBlogBySlug($blogSlug);
if (!$blog) {
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

$GLOBALS['current_blog'] = $blog;
$_GET['blog_id'] = (int)$blog['id'];

if ($articleSlug !== '') {
    $_GET['slug'] = $articleSlug;
    require __DIR__ . '/blog/article.php';
} else {
    require __DIR__ . '/blog/index.php';
}
