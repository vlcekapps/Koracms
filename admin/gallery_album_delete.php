<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');
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
    $albumStmt = $pdo->prepare("SELECT id, slug FROM cms_gallery_albums WHERE id = ?");
    $albumStmt->execute([$albumId]);
    $album = $albumStmt->fetch() ?: null;

    // Smazat fotografie v tomto albu
    $stmt = $pdo->prepare("SELECT id, filename, slug FROM cms_gallery_photos WHERE album_id = ?");
    $stmt->execute([$albumId]);
    foreach ($stmt->fetchAll() as $photo) {
        $base = __DIR__ . '/../uploads/gallery/';
        @unlink($base . $photo['filename']);
        @unlink($base . 'thumbs/' . $photo['filename']);
        $photoPath = galleryPhotoPublicPath($photo);
        $pdo->prepare("DELETE FROM cms_redirects WHERE new_path = ?")->execute([$photoPath]);
        $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'gallery_photo' AND entity_id = ?")->execute([(int)$photo['id']]);
    }
    $pdo->prepare("DELETE FROM cms_gallery_photos WHERE album_id = ?")->execute([$albumId]);

    // Rekurzivně smazat podalbuma
    $stmt = $pdo->prepare("SELECT id FROM cms_gallery_albums WHERE parent_id = ?");
    $stmt->execute([$albumId]);
    foreach ($stmt->fetchAll() as $sub) {
        deleteAlbumRecursive($pdo, (int)$sub['id']);
    }

    // Smazat toto album
    if ($album) {
        $albumPath = galleryAlbumPublicPath($album);
        $pdo->prepare("DELETE FROM cms_redirects WHERE new_path = ?")->execute([$albumPath]);
    }
    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'gallery_album' AND entity_id = ?")->execute([$albumId]);
    $pdo->prepare("DELETE FROM cms_gallery_albums WHERE id = ?")->execute([$albumId]);
}

deleteAlbumRecursive($pdo, $id);
logAction('gallery_album_delete', 'id=' . $id);

header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
exit;
