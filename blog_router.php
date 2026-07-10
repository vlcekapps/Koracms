<?php

/**
 * Front-controller pro dynamické blog slugy (multiblog).
 * Volán z .htaccess catch-all pravidel pro URL jako /cooking/ nebo /cooking/recipe-slug.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/theme.php';
checkMaintenanceMode();

if (!isModuleEnabled('blog')) {
    renderPublicNotFoundPage([
        'body_class' => 'page-not-found',
    ]);
}

$blogSlug = slugify(trim((string)($_GET['blog_slug'] ?? '')));
$articleSlug = articleSlug(trim((string)($_GET['slug'] ?? '')));
$pageSlug = pageSlug(trim((string)($_GET['page_slug'] ?? '')));
$seriesSlug = blogSeriesSlug(trim((string)($_GET['series_slug'] ?? '')));
$categorySlug = blogCategorySlug(trim((string)($_GET['category_slug'] ?? '')));
$tagSlug = blogTagSlug(trim((string)($_GET['tag_slug'] ?? '')));
$archiveRequested = isset($_GET['archive_year']) || isset($_GET['archive_month']);
$archiveKey = normalizeBlogArchiveKey(
    trim((string)($_GET['archive_year'] ?? '')) . '-' . trim((string)($_GET['archive_month'] ?? ''))
);

if ($archiveRequested && $archiveKey === '') {
    renderPublicNotFoundPage([
        'body_class' => 'page-not-found',
    ]);
}

if ($blogSlug === '') {
    renderPublicNotFoundPage([
        'body_class' => 'page-not-found',
    ]);
}

$blog = getBlogBySlug($blogSlug);
if (!$blog) {
    $legacyBlog = getBlogByLegacySlug($blogSlug);
    if ($legacyBlog) {
        $pdo = db_connect();
        if ($archiveKey !== '') {
            header('Location: ' . blogArchivePath($legacyBlog, $archiveKey), true, 301);
            exit;
        }
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

        if ($categorySlug !== '') {
            try {
                $categoryStmt = $pdo->prepare(
                    "SELECT id, name, slug, blog_id
                     FROM cms_categories
                     WHERE blog_id = ? AND slug = ?
                     LIMIT 1"
                );
                $categoryStmt->execute([(int)$legacyBlog['id'], $categorySlug]);
                $legacyCategory = $categoryStmt->fetch() ?: null;
                if ($legacyCategory) {
                    header('Location: ' . blogCategoryPath($legacyBlog, $legacyCategory), true, 301);
                    exit;
                }
            } catch (\PDOException $e) {
                // Při postupném nasazení může být kód novější než DB migrace.
            }
        }

        if ($tagSlug !== '') {
            try {
                $tagStmt = $pdo->prepare(
                    "SELECT id, name, slug, blog_id
                     FROM cms_tags
                     WHERE blog_id = ? AND slug = ?
                     LIMIT 1"
                );
                $tagStmt->execute([(int)$legacyBlog['id'], $tagSlug]);
                $legacyTag = $tagStmt->fetch() ?: null;
                if ($legacyTag) {
                    header('Location: ' . blogTagPath($legacyBlog, $legacyTag), true, 301);
                    exit;
                }
            } catch (\PDOException $e) {
                // Při postupném nasazení může být kód novější než DB migrace.
            }
        }

        if ($seriesSlug !== '') {
            try {
                $seriesStmt = $pdo->prepare(
                    "SELECT s.id, s.slug, s.blog_id, b.slug AS blog_slug
                     FROM cms_blog_series s
                     INNER JOIN cms_blogs b ON b.id = s.blog_id
                     WHERE s.blog_id = ?
                       AND s.slug = ?
                       AND s.is_active = 1
                     LIMIT 1"
                );
                $seriesStmt->execute([(int)$legacyBlog['id'], $seriesSlug]);
                $legacySeries = $seriesStmt->fetch() ?: null;
                if ($legacySeries) {
                    header('Location: ' . blogSeriesPath($legacyBlog, $legacySeries), true, 301);
                    exit;
                }
            } catch (\PDOException $e) {
                // Při postupném nasazení může být kód novější než DB migrace.
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

    renderPublicNotFoundPage([
        'body_class' => 'page-not-found',
    ]);
}

$GLOBALS['current_blog'] = $blog;
$_GET['blog_id'] = (int)$blog['id'];

if ($archiveKey !== '') {
    $_GET['archiv'] = $archiveKey;
    require __DIR__ . '/blog/index.php';
} elseif ($categorySlug !== '') {
    $_GET['category_slug'] = $categorySlug;
    require __DIR__ . '/blog/index.php';
} elseif ($tagSlug !== '') {
    $_GET['tag_slug'] = $tagSlug;
    require __DIR__ . '/blog/index.php';
} elseif ($seriesSlug !== '') {
    $_GET['series_slug'] = $seriesSlug;
    require __DIR__ . '/blog/series.php';
} elseif ($pageSlug !== '') {
    $_GET['page_slug'] = $pageSlug;
    require __DIR__ . '/blog/page.php';
} elseif ($articleSlug !== '') {
    $_GET['slug'] = $articleSlug;
    require __DIR__ . '/blog/article.php';
} else {
    require __DIR__ . '/blog/index.php';
}
