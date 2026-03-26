<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu statických stránek nemáte potřebné oprávnění.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/page_positions.php');
    exit;
}

verifyCsrf();

$id = inputInt('post', 'id');
$dir = trim((string)($_POST['dir'] ?? ''));

if ($id !== null) {
    movePageNavigationOrder(db_connect(), $id, $dir);
    logAction('page_reorder', "id={$id};dir={$dir}");
}

header('Location: ' . BASE_URL . '/admin/page_positions.php?nav_saved=1');
exit;
