<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    if (canManageOwnNewsOnly()) {
        $pdo->prepare("DELETE FROM cms_news WHERE id = ? AND author_id = ?")->execute([$id, currentUserId()]);
    } else {
        $pdo->prepare("DELETE FROM cms_news WHERE id = ?")->execute([$id]);
    }
    logAction('news_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/news.php');
exit;
