<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu souborů ke stažení nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    $pdo->prepare("UPDATE cms_downloads SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([$id]);
    logAction('download_delete', "id={$id} soft=true");
}

header('Location: ' . BASE_URL . '/admin/downloads.php');
exit;
