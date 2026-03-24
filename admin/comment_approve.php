<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id     = inputInt('post', 'id');
$filter = $_POST['filter'] ?? 'pending';

if ($id !== null) {
    $status = 'approved';
    if (setCommentModerationStatus(db_connect(), $id, $status)) {
        logAction('comment_approve', "id={$id} status={$status}");
    }
}

header('Location: ' . BASE_URL . '/admin/comments.php?filter=' . urlencode($filter));
exit;
