<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
$filter = trim($_POST['filter'] ?? 'pending');
$action = trim($_POST['action'] ?? '');

$redirect = BASE_URL . '/admin/comments.php?filter=' . urlencode($filter);
if ($id === null) {
    header('Location: ' . $redirect);
    exit;
}

$pdo = db_connect();

if ($action === 'delete') {
    $pdo->prepare("DELETE FROM cms_comments WHERE id = ?")->execute([$id]);
    logAction('comment_delete', "id={$id}");
    header('Location: ' . $redirect);
    exit;
}

$statusMap = [
    'approve' => 'approved',
    'pending' => 'pending',
    'spam' => 'spam',
    'trash' => 'trash',
];

if (isset($statusMap[$action])) {
    $status = $statusMap[$action];
    if (setCommentModerationStatus($pdo, $id, $status)) {
        logAction('comment_status', "id={$id} status={$status}");
    }
}

header('Location: ' . $redirect);
exit;
