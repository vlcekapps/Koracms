<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    if (canManageOwnNewsOnly()) {
        $pdo->prepare("UPDATE cms_news SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL AND author_id = ?")->execute([$id, currentUserId()]);
    } else {
        $pdo->prepare("UPDATE cms_news SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([$id]);
    }
    logAction('news_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/news.php');
exit;
