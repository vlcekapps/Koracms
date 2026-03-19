<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id          = inputInt('post', 'id') ?: null;
$name        = trim($_POST['name']        ?? '');
$description = trim($_POST['description'] ?? '');
$parentId    = inputInt('post', 'parent_id');
$coverId     = inputInt('post', 'cover_photo_id');

if ($name === '') {
    header('Location: ' . BASE_URL . '/admin/gallery_album_form.php' . ($id ? "?id={$id}" : ''));
    exit;
}

$pdo = db_connect();

if ($id) {
    $pdo->prepare(
        "UPDATE cms_gallery_albums
         SET name = ?, description = ?, parent_id = ?, cover_photo_id = ?, updated_at = NOW()
         WHERE id = ?"
    )->execute([$name, $description, $parentId, $coverId, $id]);
} else {
    $pdo->prepare(
        "INSERT INTO cms_gallery_albums (name, description, parent_id, cover_photo_id)
         VALUES (?, ?, ?, ?)"
    )->execute([$name, $description, $parentId, $coverId]);
}

header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
exit;
