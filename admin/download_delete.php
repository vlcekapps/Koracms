<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu souborů ke stažení nemáte potřebné oprávnění.');
requireModuleEnabled('downloads');
verifyCsrf();

$id = inputInt('post', 'id');
$redirectDeleteError = static function (string $error) use ($id): void {
    $target = BASE_URL . '/admin/downloads.php?delete_error=' . rawurlencode($error);
    if ($id !== null) {
        $target .= '&delete_error_id=' . $id;
    }
    header('Location: ' . internalRedirectTarget($target, BASE_URL . '/admin/downloads.php'));
    exit;
};
if ($id === null) {
    $redirectDeleteError('invalid');
}

$confirmFieldName = 'confirm_download_delete_' . $id;
if (($_POST[$confirmFieldName] ?? '') !== '1') {
    $redirectDeleteError('confirm_required');
}

$pdo = db_connect();
$deleteStmt = $pdo->prepare("UPDATE cms_downloads SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
$deleteStmt->execute([$id]);
if ($deleteStmt->rowCount() !== 1) {
    $redirectDeleteError('invalid');
}
logAction('download_delete', "id={$id} soft=true");

header('Location: ' . BASE_URL . '/admin/downloads.php?msg=deleted');
exit;
