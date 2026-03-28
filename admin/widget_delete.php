<?php
require_once __DIR__ . '/../db.php';
requireCapability('settings_manage', 'Přístup odepřen.');
verifyCsrf();

$id = inputInt('post', 'widget_id');
if ($id !== null) {
    $pdo = db_connect();
    $pdo->prepare("DELETE FROM cms_widgets WHERE id = ?")->execute([$id]);
    logAction('widget_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/widgets.php');
exit;
