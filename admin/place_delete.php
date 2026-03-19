<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    db_connect()->prepare("DELETE FROM cms_places WHERE id = ?")->execute([$id]);
    logAction('place_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/places.php');
exit;
