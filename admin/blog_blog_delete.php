<?php

require_once __DIR__ . '/../db.php';
requireCapability('blog_taxonomies_manage', 'Přístup odepřen.');
requireModuleEnabled('blog');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    $blogStmt = $pdo->prepare("SELECT id, logo_file FROM cms_blogs WHERE id = ?");
    $blogStmt->execute([$id]);
    $blog = $blogStmt->fetch() ?: null;

    if ($blog) {
        $logoFile = trim((string)($blog['logo_file'] ?? ''));

        // Najít zbývající blog pro přesun obsahu
        $fallback = $pdo->prepare("SELECT id FROM cms_blogs WHERE id != ? ORDER BY sort_order, id LIMIT 1");
        $fallback->execute([$id]);
        $fallbackId = $fallback->fetchColumn();

        $pdo->prepare(
            "DELETE si
             FROM cms_blog_series_items si
             INNER JOIN cms_blog_series s ON s.id = si.series_id
             WHERE s.blog_id = ?"
        )->execute([$id]);
        $pdo->prepare("DELETE FROM cms_blog_series WHERE blog_id = ?")->execute([$id]);

        if ($fallbackId) {
            $articleRedirectStmt = $pdo->prepare(
                "SELECT id, slug, blog_id, status, publish_at, unpublish_at, deleted_at
                 FROM cms_articles
                 WHERE blog_id = ?"
            );
            $articleRedirectStmt->execute([$id]);
            $articlesForRedirects = $articleRedirectStmt->fetchAll() ?: [];

            // Přesunout články, kategorie a tagy do zbývajícího blogu
            $pdo->prepare("UPDATE cms_articles SET blog_id = ? WHERE blog_id = ?")->execute([(int)$fallbackId, $id]);
            foreach ($articlesForRedirects as $articleForRedirect) {
                $updatedArticleForRedirect = $articleForRedirect;
                $updatedArticleForRedirect['blog_id'] = (int)$fallbackId;
                if (blogArticleIsPubliclyReachable($articleForRedirect) && blogArticleIsPubliclyReachable($updatedArticleForRedirect)) {
                    upsertPathRedirect(
                        $pdo,
                        articlePublicPath($articleForRedirect),
                        articlePublicPath($updatedArticleForRedirect),
                        301
                    );
                }
            }
            $pdo->prepare("UPDATE cms_categories SET blog_id = ? WHERE blog_id = ?")->execute([(int)$fallbackId, $id]);
            $pdo->prepare("UPDATE cms_tags SET blog_id = ? WHERE blog_id = ?")->execute([(int)$fallbackId, $id]);
            logAction('blog_delete', "id={$id} content_moved_to={$fallbackId}");
        } else {
            // Poslední blog – smazat veškerý obsah nenávratně
            $articleRows = $pdo->prepare("SELECT id, slug, blog_id FROM cms_articles WHERE blog_id = ?");
            $articleRows->execute([$id]);
            foreach ($articleRows->fetchAll() as $articleRow) {
                $artId = (int)$articleRow['id'];
                deleteRedirectsTargetingPath($pdo, articlePublicPath($articleRow));
                $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?")->execute([$artId]);
                $pdo->prepare("DELETE FROM cms_article_related WHERE article_id = ? OR related_article_id = ?")->execute([$artId, $artId]);
                $pdo->prepare("DELETE FROM cms_blog_series_items WHERE article_id = ?")->execute([$artId]);
                $pdo->prepare("DELETE FROM cms_comments WHERE article_id = ?")->execute([$artId]);
                $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'article' AND entity_id = ?")->execute([$artId]);
            }
            $pdo->prepare("DELETE FROM cms_articles WHERE blog_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM cms_categories WHERE blog_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM cms_tags WHERE blog_id = ?")->execute([$id]);
            logAction('blog_delete', "id={$id} all_content_deleted");
        }

        $pdo->prepare("DELETE FROM cms_blogs WHERE id = ?")->execute([$id]);
        if ($logoFile !== '') {
            deleteBlogLogoFile($logoFile);
        }

        resetAutoIncrementIfEmpty($pdo, 'cms_blogs');
        if (!$fallbackId) {
            resetAutoIncrementIfEmpty($pdo, 'cms_articles');
            resetAutoIncrementIfEmpty($pdo, 'cms_categories');
            resetAutoIncrementIfEmpty($pdo, 'cms_tags');
            resetAutoIncrementIfEmpty($pdo, 'cms_comments');
        }
    }
}

header('Location: ' . BASE_URL . '/admin/blogs.php');
exit;
