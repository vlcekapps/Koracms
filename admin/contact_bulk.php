<?php

require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu kontaktních zpráv nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/contact.php');
$action = trim((string)($_POST['action'] ?? ''));
$allowedActions = ['new', 'read', 'handled', 'delete'];
$ids = array_values(array_unique(array_filter(
    array_map('intval', (array)($_POST['ids'] ?? [])),
    static fn (int $id): bool => $id > 0
)));

if ($ids === [] || !in_array($action, $allowedActions, true)) {
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    $confirmedBulkDelete = isset($_POST['confirm_contact_bulk_delete'])
        && (string)$_POST['confirm_contact_bulk_delete'] === '1';
    if (!$confirmedBulkDelete) {
        header('Location: ' . appendUrlQuery($redirect, ['error' => 'contact_bulk_delete_confirm_required']));
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $deleteStmt = $pdo->prepare("DELETE FROM cms_contact WHERE id IN ({$placeholders})");
    $deleteStmt->execute($ids);
    $deletedCount = $deleteStmt->rowCount();
    if ($deletedCount > 0) {
        logAction('contact_bulk_delete', 'count=' . $deletedCount);
    }
    header('Location: ' . appendUrlQuery($redirect, [
        'ok' => 'bulk_deleted',
        'count' => $deletedCount,
    ]));
    exit;
}

$normalizedStatus = normalizeMessageStatus($action);
foreach ($ids as $messageId) {
    setContactMessageStatus($pdo, $messageId, $normalizedStatus);
}
logAction('contact_bulk_status', 'count=' . count($ids) . ';status=' . $normalizedStatus);

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
