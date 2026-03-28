<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$mode = trim($_POST['mode'] ?? '');
$albumId = inputInt('post', 'album_id');

$redirectToAlbumPhotos = static function (?int $targetAlbumId): never {
    $location = BASE_URL . '/admin/gallery_albums.php';
    if ($targetAlbumId !== null) {
        $location = BASE_URL . '/admin/gallery_photos.php?album_id=' . $targetAlbumId;
    }
    header('Location: ' . $location);
    exit;
};

if ($albumId === null) {
    $redirectToAlbumPhotos(null);
}

$albumStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_gallery_albums WHERE id = ?");
$albumStmt->execute([$albumId]);
if ((int)$albumStmt->fetchColumn() === 0) {
    $redirectToAlbumPhotos(null);
}

if ($mode === 'edit') {
    $id = inputInt('post', 'id');
    $title = trim($_POST['title'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));

    if ($id === null) {
        $redirectToAlbumPhotos($albumId);
    }

    $photoStmt = $pdo->prepare("SELECT filename FROM cms_gallery_photos WHERE id = ? AND album_id = ?");
    $photoStmt->execute([$id, $albumId]);
    $existingPhoto = $photoStmt->fetch();
    if (!$existingPhoto) {
        $redirectToAlbumPhotos($albumId);
    }

    $slugCandidate = $slugInput !== ''
        ? $slugInput
        : ($title !== '' ? $title : pathinfo((string)$existingPhoto['filename'], PATHINFO_FILENAME));
    $normalizedSlug = galleryPhotoSlug($slugInput);
    $resolvedSlug = uniqueGalleryPhotoSlug($pdo, $slugCandidate, $id);
    if ($normalizedSlug !== '' && $normalizedSlug !== $resolvedSlug) {
        header('Location: ' . BASE_URL . appendUrlQuery('/admin/gallery_photo_form.php', [
            'id' => (string)$id,
            'album_id' => (string)$albumId,
            'err' => 'slug',
        ]));
        exit;
    }

    $pdo->prepare(
        "UPDATE cms_gallery_photos SET title = ?, slug = ?, sort_order = ? WHERE id = ?"
    )->execute([$title, $resolvedSlug, $sortOrder, $id]);

    logAction('gallery_photo_edit', 'id=' . $id);
    $redirectToAlbumPhotos($albumId);
}

if ($mode !== 'upload') {
    $redirectToAlbumPhotos(null);
}

$uploadDir = __DIR__ . '/../uploads/gallery/';
$thumbDir = $uploadDir . 'thumbs/';

$allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxBytes = 10 * 1024 * 1024;
$files = $_FILES['photos'] ?? [];
$uploadedCount = 0;

if (!empty($files['name'])) {
    $count = count($files['name']);
    for ($index = 0; $index < $count; $index++) {
        $error = $files['error'][$index];
        $tmpName = $files['tmp_name'][$index];
        $origName = $files['name'][$index];
        $size = $files['size'][$index];

        if ($error !== UPLOAD_ERR_OK || $size > $maxBytes) {
            continue;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpName);
        if (!in_array($mime, $allowedMime, true)) {
            continue;
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        };
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        if (!move_uploaded_file($tmpName, $uploadDir . $filename)) {
            continue;
        }

        gallery_make_thumb($uploadDir . $filename, $thumbDir . $filename, 300);
        generateWebp($uploadDir . $filename);
        generateWebp($thumbDir . $filename);

        $slugCandidate = pathinfo((string)$origName, PATHINFO_FILENAME);
        $resolvedSlug = uniqueGalleryPhotoSlug($pdo, $slugCandidate);
        $pdo->prepare(
            "INSERT INTO cms_gallery_photos (album_id, filename, title, slug) VALUES (?, ?, ?, ?)"
        )->execute([$albumId, $filename, '', $resolvedSlug]);
        $uploadedCount++;
    }
}

if ($uploadedCount > 0) {
    logAction('gallery_photo_upload', 'album_id=' . $albumId . ';count=' . $uploadedCount);
}

$redirectToAlbumPhotos($albumId);
