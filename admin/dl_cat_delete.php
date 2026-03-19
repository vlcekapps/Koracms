<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    // Zruší vazbu souborů na tuto kategorii
    $pdo->prepare("UPDATE cms_downloads SET dl_category_id = NULL WHERE dl_category_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_dl_categories WHERE id = ?")->execute([$id]);
    logAction('dl_cat_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/dl_cats.php');
exit;
