<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id === null) {
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}

$pdo = db_connect();

/**
 * Rekurzivně smaže album, všechna podalbuma a jejich fotografie (včetně souborů).
 */
function deleteAlbumRecursive(PDO $pdo, int $albumId): void
{
    // Smazat fotografie v tomto albu
    $stmt = $pdo->prepare("SELECT filename FROM cms_gallery_photos WHERE album_id = ?");
    $stmt->execute([$albumId]);
    foreach ($stmt->fetchAll() as $photo) {
        $base = __DIR__ . '/../uploads/gallery/';
        @unlink($base . $photo['filename']);
        @unlink($base . 'thumbs/' . $photo['filename']);
    }
    $pdo->prepare("DELETE FROM cms_gallery_photos WHERE album_id = ?")->execute([$albumId]);

    // Rekurzivně smazat podalbuma
    $stmt = $pdo->prepare("SELECT id FROM cms_gallery_albums WHERE parent_id = ?");
    $stmt->execute([$albumId]);
    foreach ($stmt->fetchAll() as $sub) {
        deleteAlbumRecursive($pdo, (int)$sub['id']);
    }

    // Smazat toto album
    $pdo->prepare("DELETE FROM cms_gallery_albums WHERE id = ?")->execute([$albumId]);
}

deleteAlbumRecursive($pdo, $id);

header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
exit;
