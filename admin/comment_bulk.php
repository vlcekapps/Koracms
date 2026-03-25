<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
$action = trim($_POST['action'] ?? '');

$redirect = internalRedirectTarget(
    trim($_POST['redirect'] ?? ''),
    BASE_URL . '/admin/comments.php?filter=pending'
);
if ($ids === []) {
    header('Location: ' . $redirect);
    exit;
}

$pdo = db_connect();
$placeholders = implode(',', array_fill(0, count($ids), '?'));

if ($action === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM cms_comments WHERE id IN ({$placeholders})");
    $stmt->execute($ids);
    logAction('comment_bulk_delete', 'ids=' . implode(',', $ids));
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
    foreach ($ids as $commentId) {
        setCommentModerationStatus($pdo, (int)$commentId, $status);
    }
    logAction('comment_bulk_status', 'status=' . $status . ' ids=' . implode(',', $ids));
}

header('Location: ' . $redirect);
exit;
