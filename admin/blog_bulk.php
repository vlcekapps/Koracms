<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$ids    = array_map('intval', (array)($_POST['ids'] ?? []));
$action = $_POST['action'] ?? '';

if ($action === 'delete' && !empty($ids)) {
    $pdo = db_connect();
    $dir = __DIR__ . '/../uploads/articles/';
    foreach ($ids as $id) {
        $row = $pdo->prepare("SELECT image_file FROM cms_articles WHERE id = ?");
        $row->execute([$id]);
        $imgFile = $row->fetchColumn();
        if ($imgFile) {
            @unlink($dir . $imgFile);
            @unlink($dir . 'thumbs/' . $imgFile);
        }
        $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM cms_articles WHERE id = ?")->execute([$id]);
    }
    logAction('article_bulk_delete', 'ids=' . implode(',', $ids));
}

header('Location: ' . BASE_URL . '/admin/blog.php');
exit;
