<?php

require_once __DIR__ . '/../db.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu míst rezervací nemáte potřebné oprávnění.');
requireModuleEnabled('reservations');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/res_locations.php');
    exit;
}
verifyCsrf();

$id = inputInt('post', 'id');
if ($id === null) {
    header('Location: ' . BASE_URL . '/admin/res_locations.php');
    exit;
}

$confirmFieldName = 'confirm_res_location_delete_' . $id;
$confirmedLocationDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedLocationDelete) {
    header('Location: ' . BASE_URL . '/admin/res_locations.php?delete_error=confirm_required&delete_error_id=' . $id);
    exit;
}

$pdo = db_connect();
$resourceCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_res_resource_locations WHERE location_id = ?');
$resourceCountStmt->execute([$id]);
$resourceCount = (int)$resourceCountStmt->fetchColumn();

// Odebrat vazby na zdroje
$pdo->prepare("DELETE FROM cms_res_resource_locations WHERE location_id = ?")->execute([$id]);

// Smazat místo
$pdo->prepare("DELETE FROM cms_res_locations WHERE id = ?")->execute([$id]);

logAction('res_location_delete', "id={$id};resource_count={$resourceCount}");
header('Location: ' . BASE_URL . '/admin/res_locations.php?deleted=1');
exit;
