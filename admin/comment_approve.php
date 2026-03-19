<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id     = inputInt('post', 'id');
$filter = $_POST['filter'] ?? 'pending';

if ($id !== null) {
    db_connect()->prepare("UPDATE cms_comments SET is_approved = 1 WHERE id = ?")->execute([$id]);
    logAction('comment_approve', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/comments.php?filter=' . urlencode($filter));
exit;
