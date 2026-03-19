<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$ids    = array_map('intval', (array)($_POST['ids'] ?? []));
$action = $_POST['action'] ?? '';
$filter = $_POST['filter'] ?? 'pending';

if (!empty($ids)) {
    $pdo = db_connect();
    if ($action === 'approve') {
        foreach ($ids as $id) {
            $pdo->prepare("UPDATE cms_comments SET is_approved = 1 WHERE id = ?")->execute([$id]);
        }
        logAction('comment_bulk_approve', 'ids=' . implode(',', $ids));
    } elseif ($action === 'delete') {
        foreach ($ids as $id) {
            $pdo->prepare("DELETE FROM cms_comments WHERE id = ?")->execute([$id]);
        }
        logAction('comment_bulk_delete', 'ids=' . implode(',', $ids));
    }
}

header('Location: ' . BASE_URL . '/admin/comments.php?filter=' . urlencode($filter));
exit;
