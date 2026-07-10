<?php

require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu kontaktních zpráv nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$messageId = inputInt('post', 'id');
$action = trim((string)($_POST['action'] ?? ''));
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/contact.php');
$successRedirect = internalRedirectTarget(trim((string)($_POST['success_redirect'] ?? '')), $redirect);
$allowedActions = ['new', 'read', 'handled', 'delete'];

if ($messageId === null || !in_array($action, $allowedActions, true)) {
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    $messageStmt = $pdo->prepare("SELECT id FROM cms_contact WHERE id = ?");
    $messageStmt->execute([$messageId]);
    if (!$messageStmt->fetchColumn()) {
        header('Location: ' . $successRedirect);
        exit;
    }

    $confirmFieldName = 'confirm_contact_delete_' . $messageId;
    $confirmedDelete = isset($_POST[$confirmFieldName]) && (string)$_POST[$confirmFieldName] === '1';
    if (!$confirmedDelete) {
        header('Location: ' . appendUrlQuery($redirect, [
            'error' => 'contact_delete_confirm_required',
            'delete_id' => $messageId,
        ]));
        exit;
    }

    $deleteStmt = $pdo->prepare("DELETE FROM cms_contact WHERE id = ?");
    $deleteStmt->execute([$messageId]);
    if ($deleteStmt->rowCount() > 0) {
        logAction('contact_delete', "id={$messageId}");
    }
    header('Location: ' . appendUrlQuery($successRedirect, ['ok' => 'deleted']));
    exit;
}

$normalizedStatus = normalizeMessageStatus($action);
setContactMessageStatus($pdo, $messageId, $normalizedStatus);
logAction('contact_status', "id={$messageId};status={$normalizedStatus}");

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
