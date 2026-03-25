<?php
require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu kontaktních zpráv nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/contact.php');
$action = trim($_POST['action'] ?? '');
$allowedActions = ['new', 'read', 'handled', 'delete'];
$ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), static fn(int $id): bool => $id > 0));

if ($ids === [] || !in_array($action, $allowedActions, true)) {
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM cms_contact WHERE id IN ({$placeholders})")->execute($ids);
    logAction('contact_bulk_delete', 'count=' . count($ids));
    header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
    exit;
}

$normalizedStatus = normalizeMessageStatus($action);
foreach ($ids as $messageId) {
    setContactMessageStatus($pdo, $messageId, $normalizedStatus);
}
logAction('contact_bulk_status', 'count=' . count($ids) . ';status=' . $normalizedStatus);

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
