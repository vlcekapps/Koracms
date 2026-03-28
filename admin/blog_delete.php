<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();

    // Soft delete – přesun do koše
    if (canManageOwnBlogOnly()) {
        $pdo->prepare("UPDATE cms_articles SET deleted_at = NOW() WHERE id = ? AND author_id = ? AND deleted_at IS NULL")
            ->execute([$id, currentUserId()]);
    } else {
        $pdo->prepare("UPDATE cms_articles SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")
            ->execute([$id]);
    }
    logAction('article_delete', "id={$id} soft=true");
}

header('Location: ' . BASE_URL . '/admin/blog.php');
exit;
