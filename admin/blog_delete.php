<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    if (canManageOwnBlogOnly()) {
        $row = $pdo->prepare("SELECT image_file FROM cms_articles WHERE id = ? AND author_id = ?");
        $row->execute([$id, currentUserId()]);
    } else {
        $row = $pdo->prepare("SELECT image_file FROM cms_articles WHERE id = ?");
        $row->execute([$id]);
    }
    $imageFile = $row->fetchColumn();
    if ($imageFile !== false) {
        $dir = __DIR__ . '/../uploads/articles/';
        if ($imageFile) {
            @unlink($dir . $imageFile);
            @unlink($dir . 'thumbs/' . $imageFile);
        }
        $deleteParams = canManageOwnBlogOnly() ? [$id, currentUserId()] : [$id];
        $deleteSql = canManageOwnBlogOnly()
            ? "DELETE FROM cms_article_tags WHERE article_id = ?"
            : "DELETE FROM cms_article_tags WHERE article_id = ?";
        $pdo->prepare($deleteSql)->execute([$id]);
        if (canManageOwnBlogOnly()) {
            $pdo->prepare("DELETE FROM cms_articles WHERE id = ? AND author_id = ?")->execute($deleteParams);
        } else {
            $pdo->prepare("DELETE FROM cms_articles WHERE id = ?")->execute([$id]);
        }
        logAction('article_delete', "id={$id}");
    }
}

header('Location: ' . BASE_URL . '/admin/blog.php');
exit;
