<?php
require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu kontaktních zpráv nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$messageId = inputInt('post', 'id');
$action = trim($_POST['action'] ?? '');
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/contact.php');
$allowedActions = ['new', 'read', 'handled', 'delete'];

if ($messageId === null || !in_array($action, $allowedActions, true)) {
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    $pdo->prepare("DELETE FROM cms_contact WHERE id = ?")->execute([$messageId]);
    logAction('contact_delete', "id={$messageId}");
    header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
    exit;
}

$normalizedStatus = normalizeMessageStatus($action);
setContactMessageStatus($pdo, $messageId, $normalizedStatus);
logAction('contact_status', "id={$messageId};status={$normalizedStatus}");

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
