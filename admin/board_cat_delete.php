<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    $pdo->prepare("UPDATE cms_board SET category_id = NULL WHERE category_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_board_categories WHERE id = ?")->execute([$id]);
    logAction('board_cat_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/board_cats.php');
exit;
