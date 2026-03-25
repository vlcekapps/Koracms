<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('gallery')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$photoId = inputInt('get', 'id');
$photoSlug = galleryPhotoSlug(trim($_GET['slug'] ?? ''));
if ($photoId === null && $photoSlug === '') {
    header('Location: ' . BASE_URL . '/gallery/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

if ($photoSlug !== '') {
    $stmt = $pdo->prepare("SELECT * FROM cms_gallery_photos WHERE slug = ? LIMIT 1");
    $stmt->execute([$photoSlug]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM cms_gallery_photos WHERE id = ? LIMIT 1");
    $stmt->execute([$photoId]);
}
$photo = $stmt->fetch() ?: null;

if ($photo === null) {
    http_response_code(404);
    $missingPath = $photoSlug !== ''
        ? BASE_URL . '/gallery/photo/' . rawurlencode($photoSlug)
        : BASE_URL . '/gallery/photo.php' . ($photoId !== null ? '?id=' . urlencode((string)$photoId) : '');

    renderPublicPage([
        'title' => 'Fotografie nenalezena – ' . $siteName,
        'meta' => [
            'title' => 'Fotografie nenalezena – ' . $siteName,
            'url' => $missingPath,
        ],
        'view' => 'not-found',
        'body_class' => 'page-gallery-not-found',
    ]);
    exit;
}

$photo = hydrateGalleryPhotoPresentation($photo);
if ($photoSlug === '') {
    header('Location: ' . $photo['public_path']);
    exit;
}

$albumId = (int)$photo['album_id'];
$albumStmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ? LIMIT 1");
$albumStmt->execute([$albumId]);
$album = $albumStmt->fetch() ?: ['id' => $albumId, 'name' => 'Galerie', 'slug' => ''];
$album = hydrateGalleryAlbumPresentation($album);

$stmtPrev = $pdo->prepare(
    "SELECT id, slug, title, filename
     FROM cms_gallery_photos
     WHERE album_id = ? AND (sort_order < ? OR (sort_order = ? AND id < ?))
     ORDER BY sort_order DESC, id DESC
     LIMIT 1"
);
$stmtPrev->execute([$albumId, $photo['sort_order'], $photo['sort_order'], $photo['id']]);
$prevPhoto = $stmtPrev->fetch() ?: null;
if ($prevPhoto !== null) {
    $prevPhoto = hydrateGalleryPhotoPresentation($prevPhoto);
}

$stmtNext = $pdo->prepare(
    "SELECT id, slug, title, filename
     FROM cms_gallery_photos
     WHERE album_id = ? AND (sort_order > ? OR (sort_order = ? AND id > ?))
     ORDER BY sort_order ASC, id ASC
     LIMIT 1"
);
$stmtNext->execute([$albumId, $photo['sort_order'], $photo['sort_order'], $photo['id']]);
$nextPhoto = $stmtNext->fetch() ?: null;
if ($nextPhoto !== null) {
    $nextPhoto = hydrateGalleryPhotoPresentation($nextPhoto);
}

$trail = gallery_breadcrumb($albumId);
$photoTitle = (string)$photo['label'];

renderPublicPage([
    'title' => $photoTitle . ' – ' . $album['name'] . ' – ' . $siteName,
    'meta' => [
        'title' => $photoTitle . ' – ' . $album['name'] . ' – ' . $siteName,
        'description' => $photoTitle,
        'image' => $photo['image_url'],
        'url' => $photo['public_path'],
    ],
    'view' => 'modules/gallery-photo',
    'view_data' => [
        'photo' => $photo,
        'album' => $album,
        'trail' => $trail,
        'photoTitle' => $photoTitle,
        'prevPhoto' => $prevPhoto,
        'nextPhoto' => $nextPhoto,
    ],
    'current_nav' => 'gallery',
    'body_class' => 'page-gallery-photo',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/gallery_photo_form.php?id=' . (int)$photo['id'] . '&album_id=' . $albumId,
]);
