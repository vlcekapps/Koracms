<?php
require_once __DIR__ . '/../db.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu kategorií rezervací nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    $pdo->prepare("UPDATE cms_res_resources SET category_id = NULL WHERE category_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_res_categories WHERE id = ?")->execute([$id]);
    logAction('res_cat_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/res_categories.php');
exit;
