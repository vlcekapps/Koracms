<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');
verifyCsrf();

$id      = inputInt('post', 'id');
$albumId = inputInt('post', 'album_id');

if ($id !== null) {
    $pdo  = db_connect();
    $stmt = $pdo->prepare("SELECT id, album_id FROM cms_gallery_photos WHERE id = ?");
    $stmt->execute([$id]);
    $photo = $stmt->fetch();

    if ($photo) {
        $albumId = (int)$photo['album_id'];
        $pdo->prepare("UPDATE cms_gallery_photos SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([$id]);
        logAction('gallery_photo_delete', 'id=' . $id . ' soft=true');
    }
}

header('Location: ' . BASE_URL . '/admin/gallery_photos.php?album_id=' . $albumId);
exit;
