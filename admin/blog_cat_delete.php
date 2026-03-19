<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    // Zruší vazbu článků na tuto kategorii
    db_connect()->prepare("UPDATE cms_articles SET category_id = NULL WHERE category_id = ?")->execute([$id]);
    db_connect()->prepare("DELETE FROM cms_categories WHERE id = ?")->execute([$id]);
}

header('Location: ' . BASE_URL . '/admin/blog_cats.php');
exit;
