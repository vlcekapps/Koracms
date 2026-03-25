<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');
verifyCsrf();

$id      = inputInt('post', 'id');
$albumId = inputInt('post', 'album_id');

if ($id !== null) {
    $pdo  = db_connect();
    $stmt = $pdo->prepare("SELECT filename, album_id FROM cms_gallery_photos WHERE id = ?");
    $stmt->execute([$id]);
    $photo = $stmt->fetch();

    if ($photo) {
        $albumId = (int)$photo['album_id'];
        $base    = __DIR__ . '/../uploads/gallery/';
        @unlink($base . $photo['filename']);
        @unlink($base . 'thumbs/' . $photo['filename']);

        // Pokud je tato fotka nastavena jako cover, odstraníme referenci
        $pdo->prepare(
            "UPDATE cms_gallery_albums SET cover_photo_id = NULL WHERE cover_photo_id = ?"
        )->execute([$id]);

        $pdo->prepare("DELETE FROM cms_gallery_photos WHERE id = ?")->execute([$id]);
        logAction('gallery_photo_delete', 'id=' . $id);
    }
}

header('Location: ' . BASE_URL . '/admin/gallery_photos.php?album_id=' . $albumId);
exit;
