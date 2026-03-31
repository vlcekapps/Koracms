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
$pageSlug = pageSlug(trim((string)($_GET['page_slug'] ?? '')));

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
        $pdo = db_connect();
        if ($pageSlug !== '') {
            $pageStmt = $pdo->prepare(
                "SELECT p.id, p.slug, p.blog_id, b.slug AS blog_slug
                 FROM cms_pages p
                 INNER JOIN cms_blogs b ON b.id = p.blog_id
                 WHERE p.blog_id = ?
                   AND p.slug = ?
                   AND p.deleted_at IS NULL
                   AND p.status = 'published'
                   AND p.is_published = 1
                 LIMIT 1"
            );
            $pageStmt->execute([(int)$legacyBlog['id'], $pageSlug]);
            $legacyPage = $pageStmt->fetch() ?: null;
            if ($legacyPage) {
                header('Location: ' . pagePublicPath($legacyPage), true, 301);
                exit;
            }
        }

        if ($articleSlug !== '') {
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

if ($pageSlug !== '') {
    $_GET['page_slug'] = $pageSlug;
    require __DIR__ . '/blog/page.php';
} elseif ($articleSlug !== '') {
    $_GET['slug'] = $articleSlug;
    require __DIR__ . '/blog/article.php';
} else {
    require __DIR__ . '/blog/index.php';
}
