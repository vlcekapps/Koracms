<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu událostí nemáte potřebné oprávnění.');
requireModuleEnabled('events');
verifyCsrf();

$id = inputInt('post', 'id');
$redirectDeleteError = static function (string $error) use ($id): void {
    $target = BASE_URL . '/admin/events.php?delete_error=' . rawurlencode($error);
    if ($id !== null) {
        $target .= '&delete_error_id=' . $id;
    }
    header('Location: ' . internalRedirectTarget($target, BASE_URL . '/admin/events.php'));
    exit;
};
if ($id === null) {
    $redirectDeleteError('invalid');
}

$confirmFieldName = 'confirm_event_delete_' . $id;
if (($_POST[$confirmFieldName] ?? '') !== '1') {
    $redirectDeleteError('confirm_required');
}

$pdo = db_connect();
// Přesun do Koše musí zachovat obrázek pro obnovení i ostatní termíny série.
$deleteStmt = $pdo->prepare("UPDATE cms_events SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
$deleteStmt->execute([$id]);
if ($deleteStmt->rowCount() !== 1) {
    $redirectDeleteError('invalid');
}
logAction('event_delete', "id={$id}");

header('Location: ' . BASE_URL . '/admin/events.php');
exit;
