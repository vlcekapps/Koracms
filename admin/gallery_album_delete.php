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
 * Rekurzivně soft-deletne album, všechna podalbuma a jejich fotografie.
 */
function softDeleteAlbumRecursive(PDO $pdo, int $albumId): void
{
    // Soft delete fotografií v tomto albu
    $pdo->prepare("UPDATE cms_gallery_photos SET deleted_at = NOW() WHERE album_id = ? AND deleted_at IS NULL")->execute([$albumId]);

    // Rekurzivně soft-deletnout podalbuma
    $stmt = $pdo->prepare("SELECT id FROM cms_gallery_albums WHERE parent_id = ? AND deleted_at IS NULL");
    $stmt->execute([$albumId]);
    foreach ($stmt->fetchAll() as $sub) {
        softDeleteAlbumRecursive($pdo, (int)$sub['id']);
    }

    // Soft delete tohoto alba
    $pdo->prepare("UPDATE cms_gallery_albums SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([$albumId]);
}

softDeleteAlbumRecursive($pdo, $id);
logAction('gallery_album_delete', 'id=' . $id . ' soft=true');

header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
exit;
