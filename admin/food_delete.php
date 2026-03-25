<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu jídelních lístků nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    db_connect()->prepare("DELETE FROM cms_food_cards WHERE id = ?")->execute([$id]);
    logAction('food_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/food.php');
exit;
