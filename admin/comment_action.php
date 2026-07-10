<?php

require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
$filter = trim($_POST['filter'] ?? 'pending');
$action = trim($_POST['action'] ?? '');

$redirect = internalRedirectTarget(
    trim($_POST['redirect'] ?? ''),
    BASE_URL . '/admin/comments.php?filter=' . urlencode($filter)
);
if ($id === null) {
    header('Location: ' . $redirect);
    exit;
}

$pdo = db_connect();

if ($action === 'delete') {
    $commentStmt = $pdo->prepare("SELECT id FROM cms_comments WHERE id = ?");
    $commentStmt->execute([$id]);
    if (!$commentStmt->fetchColumn()) {
        header('Location: ' . $redirect);
        exit;
    }

    $confirmFieldName = 'confirm_comment_delete_' . $id;
    $confirmedDelete = isset($_POST[$confirmFieldName]) && (string)$_POST[$confirmFieldName] === '1';
    if (!$confirmedDelete) {
        header('Location: ' . appendUrlQuery($redirect, [
            'error' => 'delete_confirm_required',
            'delete_id' => $id,
        ]));
        exit;
    }

    $deleteStmt = $pdo->prepare("DELETE FROM cms_comments WHERE id = ?");
    $deleteStmt->execute([$id]);
    if ($deleteStmt->rowCount() > 0) {
        logAction('comment_delete', "id={$id}");
    }
    header('Location: ' . appendUrlQuery($redirect, ['ok' => 'deleted']));
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
