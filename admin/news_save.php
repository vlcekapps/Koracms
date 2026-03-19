<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo     = db_connect();
$id      = inputInt('post', 'id');
$content = trim($_POST['content'] ?? '');

if ($content === '') {
    header('Location: news_form.php' . ($id ? "?id={$id}" : ''));
    exit;
}

if ($id !== null) {
    $pdo->prepare("UPDATE cms_news SET content = ?, author_id = COALESCE(author_id, ?) WHERE id = ?")
        ->execute([$content, currentUserId(), $id]);
} else {
    $status   = isSuperAdmin() ? 'published' : 'pending';
    $authorId = currentUserId();
    $pdo->prepare("INSERT INTO cms_news (content, status, author_id) VALUES (?,?,?)")->execute([$content, $status, $authorId]);
}

header('Location: ' . BASE_URL . '/admin/news.php');
exit;
