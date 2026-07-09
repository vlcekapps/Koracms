<?php

require_once __DIR__ . '/../db.php';
requireCapability('blog_taxonomies_manage', 'Přístup odepřen.');
requireModuleEnabled('blog');

$redirectToBlogs = static function (array $params = []): void {
    $target = BASE_URL . '/admin/blogs.php';
    if ($params !== []) {
        $target .= '?' . http_build_query($params);
    }
    header('Location: ' . $target);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirectToBlogs();
}

verifyCsrf();

$id = inputInt('post', 'id');
if ($id === null) {
    $redirectToBlogs();
}

$pdo = db_connect();
$blogStmt = $pdo->prepare("SELECT id, logo_file FROM cms_blogs WHERE id = ?");
$blogStmt->execute([$id]);
$blog = $blogStmt->fetch() ?: null;
if (!$blog) {
    $redirectToBlogs();
}

$confirmFieldName = 'confirm_blog_delete_' . $id;
$confirmedBlogDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedBlogDelete) {
    $redirectToBlogs(['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$logoFile = trim((string)($blog['logo_file'] ?? ''));
$fallback = $pdo->prepare("SELECT id FROM cms_blogs WHERE id != ? ORDER BY sort_order, id LIMIT 1");
$fallback->execute([$id]);
$fallbackValue = $fallback->fetchColumn();
$fallbackId = $fallbackValue !== false ? (int)$fallbackValue : null;

$articleCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_articles WHERE blog_id = ?');
$articleCountStmt->execute([$id]);
$articleCount = (int)$articleCountStmt->fetchColumn();

$categoryCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_categories WHERE blog_id = ?');
$categoryCountStmt->execute([$id]);
$categoryCount = (int)$categoryCountStmt->fetchColumn();

$tagCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_tags WHERE blog_id = ?');
$tagCountStmt->execute([$id]);
$tagCount = (int)$tagCountStmt->fetchColumn();

$seriesCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_blog_series WHERE blog_id = ?');
$seriesCountStmt->execute([$id]);
$seriesCount = (int)$seriesCountStmt->fetchColumn();

$memberCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_blog_members WHERE blog_id = ?');
$memberCountStmt->execute([$id]);
$memberCount = (int)$memberCountStmt->fetchColumn();

$pdo->beginTransaction();
try {
    $pdo->prepare(
        "DELETE si
         FROM cms_blog_series_items si
         INNER JOIN cms_blog_series s ON s.id = si.series_id
         WHERE s.blog_id = ?"
    )->execute([$id]);
    $pdo->prepare("DELETE FROM cms_blog_series WHERE blog_id = ?")->execute([$id]);

    if ($fallbackId !== null) {
        $articleRedirectStmt = $pdo->prepare(
            "SELECT id, slug, blog_id, status, publish_at, unpublish_at, deleted_at
             FROM cms_articles
             WHERE blog_id = ?"
        );
        $articleRedirectStmt->execute([$id]);
        $articlesForRedirects = $articleRedirectStmt->fetchAll() ?: [];

        // Přesunout články, kategorie a tagy do zbývajícího blogu
        $pdo->prepare("UPDATE cms_articles SET blog_id = ? WHERE blog_id = ?")->execute([$fallbackId, $id]);
        foreach ($articlesForRedirects as $articleForRedirect) {
            $updatedArticleForRedirect = $articleForRedirect;
            $updatedArticleForRedirect['blog_id'] = $fallbackId;
            if (blogArticleIsPubliclyReachable($articleForRedirect) && blogArticleIsPubliclyReachable($updatedArticleForRedirect)) {
                upsertPathRedirect(
                    $pdo,
                    articlePublicPath($articleForRedirect),
                    articlePublicPath($updatedArticleForRedirect),
                    301
                );
            }
        }
        $pdo->prepare("UPDATE cms_categories SET blog_id = ? WHERE blog_id = ?")->execute([$fallbackId, $id]);
        $pdo->prepare("UPDATE cms_tags SET blog_id = ? WHERE blog_id = ?")->execute([$fallbackId, $id]);
        logAction('blog_delete', "id={$id};article_count={$articleCount};category_count={$categoryCount};tag_count={$tagCount};series_count={$seriesCount};member_count={$memberCount};content_moved_to={$fallbackId}");
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
        logAction('blog_delete', "id={$id};article_count={$articleCount};category_count={$categoryCount};tag_count={$tagCount};series_count={$seriesCount};member_count={$memberCount};all_content_deleted");
    }

    $pdo->prepare("DELETE FROM cms_blog_members WHERE blog_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_blogs WHERE id = ?")->execute([$id]);
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

if ($logoFile !== '') {
    deleteBlogLogoFile($logoFile);
}

resetAutoIncrementIfEmpty($pdo, 'cms_blogs');
if ($fallbackId === null) {
    resetAutoIncrementIfEmpty($pdo, 'cms_articles');
    resetAutoIncrementIfEmpty($pdo, 'cms_categories');
    resetAutoIncrementIfEmpty($pdo, 'cms_tags');
    resetAutoIncrementIfEmpty($pdo, 'cms_comments');
}

$redirectToBlogs(['deleted' => '1']);
