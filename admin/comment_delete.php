<?php

require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
$filter = trim((string)($_POST['filter'] ?? 'pending'));
$redirect = internalRedirectTarget(
    trim((string)($_POST['redirect'] ?? '')),
    BASE_URL . '/admin/comments.php?filter=' . urlencode($filter)
);

if ($id !== null) {
    $pdo = db_connect();
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

header('Location: ' . $redirect);
exit;
