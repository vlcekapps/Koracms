<?php

require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
requireModuleEnabled('blog');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    $articleForRedirectCleanup = null;

    // Soft delete – přesun do koše
    if (canManageOwnBlogOnly()) {
        $articleStmt = $pdo->prepare(
            "SELECT id, slug, blog_id
             FROM cms_articles
             WHERE id = ? AND author_id = ? AND deleted_at IS NULL
             LIMIT 1"
        );
        $articleStmt->execute([$id, currentUserId()]);
        $articleForRedirectCleanup = $articleStmt->fetch() ?: null;
        $pdo->prepare("UPDATE cms_articles SET deleted_at = NOW() WHERE id = ? AND author_id = ? AND deleted_at IS NULL")
            ->execute([$id, currentUserId()]);
    } else {
        $articleStmt = $pdo->prepare(
            "SELECT id, slug, blog_id
             FROM cms_articles
             WHERE id = ? AND deleted_at IS NULL
             LIMIT 1"
        );
        $articleStmt->execute([$id]);
        $articleForRedirectCleanup = $articleStmt->fetch() ?: null;
        $pdo->prepare("UPDATE cms_articles SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")
            ->execute([$id]);
    }
    if ($articleForRedirectCleanup) {
        deleteRedirectsTargetingPath($pdo, articlePublicPath($articleForRedirectCleanup));
    }
    $pdo->prepare("DELETE FROM cms_blog_series_items WHERE article_id = ?")->execute([$id]);
    logAction('article_delete', "id={$id} soft=true");
}

header('Location: ' . BASE_URL . '/admin/blog.php');
exit;
