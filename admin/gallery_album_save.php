<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id') ?: null;
$name = trim($_POST['name'] ?? '');
$slugInput = trim($_POST['slug'] ?? '');
$description = trim($_POST['description'] ?? '');
$parentId = inputInt('post', 'parent_id');
$coverId = inputInt('post', 'cover_photo_id');

$redirectBack = static function (?int $albumId, string $error = ''): never {
    $path = '/admin/gallery_album_form.php';
    $query = [];
    if ($albumId !== null) {
        $query['id'] = (string)$albumId;
    }
    if ($error !== '') {
        $query['err'] = $error;
    }
    header('Location: ' . BASE_URL . appendUrlQuery($path, $query));
    exit;
};

if ($name === '') {
    $redirectBack($id, 'required');
}

$pdo = db_connect();

if ($parentId !== null) {
    if ($id !== null && $parentId === $id) {
        $redirectBack($id, 'parent');
    }

    $allAlbums = $pdo->query("SELECT id, parent_id FROM cms_gallery_albums ORDER BY id")->fetchAll();
    $forbidden = $id !== null ? [$id] : [];
    if ($id !== null) {
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($allAlbums as $candidateAlbum) {
                if (!in_array((int)$candidateAlbum['id'], $forbidden, true) && in_array((int)$candidateAlbum['parent_id'], $forbidden, true)) {
                    $forbidden[] = (int)$candidateAlbum['id'];
                    $changed = true;
                }
            }
        }
        if (in_array($parentId, $forbidden, true)) {
            $redirectBack($id, 'parent');
        }
    }

    $parentExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_gallery_albums WHERE id = ?");
    $parentExistsStmt->execute([$parentId]);
    if ((int)$parentExistsStmt->fetchColumn() === 0) {
        $redirectBack($id, 'parent');
    }
}

$normalizedSlug = galleryAlbumSlug($slugInput);
$resolvedSlug = uniqueGalleryAlbumSlug(
    $pdo,
    $normalizedSlug !== '' ? $normalizedSlug : $name,
    $id
);
if ($normalizedSlug !== '' && $normalizedSlug !== $resolvedSlug) {
    $redirectBack($id, 'slug');
}

if ($id !== null) {
    $albumStmt = $pdo->prepare("SELECT id FROM cms_gallery_albums WHERE id = ?");
    $albumStmt->execute([$id]);
    if (!$albumStmt->fetch()) {
        $redirectBack(null, 'required');
    }
}

$coverPhotoId = null;
if ($coverId !== null && $id !== null) {
    $coverStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_gallery_photos WHERE id = ? AND album_id = ?");
    $coverStmt->execute([$coverId, $id]);
    if ((int)$coverStmt->fetchColumn() > 0) {
        $coverPhotoId = $coverId;
    }
}

$isPublished = isset($_POST['is_published']) ? 1 : 0;

if ($id !== null) {
    $pdo->prepare(
        "UPDATE cms_gallery_albums
         SET name = ?, slug = ?, description = ?, parent_id = ?, cover_photo_id = ?, is_published = ?, updated_at = NOW()
         WHERE id = ?"
    )->execute([$name, $resolvedSlug, $description, $parentId, $coverPhotoId, $isPublished, $id]);
    logAction('gallery_album_edit', 'id=' . $id);
} else {
    $status = currentUserHasCapability('content_approve_shared') ? 'published' : 'pending';
    $visible = currentUserHasCapability('content_approve_shared') ? $isPublished : 0;
    $pdo->prepare(
        "INSERT INTO cms_gallery_albums (name, slug, description, parent_id, cover_photo_id, status, is_published)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    )->execute([$name, $resolvedSlug, $description, $parentId, null, $status, $visible]);
    $id = (int)$pdo->lastInsertId();
    logAction('gallery_album_add', 'id=' . $id . ' status=' . $status);
    if ($status === 'pending') {
        notifyPendingContent('Fotoalbum', $name, '/admin/gallery_albums.php');
    }
}

header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
exit;
