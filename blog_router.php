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

$blogSlug = slugify(trim((string)($_GET['blog_slug'] ?? '')));
$articleSlug = articleSlug(trim((string)($_GET['slug'] ?? '')));

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
    $legacyBlog = getBlogByLegacySlug($blogSlug);
    if ($legacyBlog) {
        if ($articleSlug !== '') {
            $pdo = db_connect();
            $articleStmt = $pdo->prepare(
                "SELECT a.id, a.slug, a.blog_id, b.slug AS blog_slug
                 FROM cms_articles a
                 LEFT JOIN cms_blogs b ON b.id = a.blog_id
                 WHERE a.blog_id = ? AND a.slug = ?
                 LIMIT 1"
            );
            $articleStmt->execute([(int)$legacyBlog['id'], $articleSlug]);
            $legacyArticle = $articleStmt->fetch() ?: null;
            if ($legacyArticle) {
                header('Location: ' . articlePublicPath($legacyArticle), true, 301);
                exit;
            }
        }

        header('Location: ' . blogIndexPath($legacyBlog), true, 301);
        exit;
    }

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
