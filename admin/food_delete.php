<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu jídelních lístků nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    db_connect()->prepare("UPDATE cms_food_cards SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([$id]);
    logAction('food_delete', "id={$id} soft=true");
}

header('Location: ' . BASE_URL . '/admin/food.php');
exit;
