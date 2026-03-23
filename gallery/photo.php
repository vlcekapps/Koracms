<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('gallery')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$photoId = inputInt('get', 'id');
if ($photoId === null) {
    header('Location: ' . BASE_URL . '/gallery/index.php');
    exit;
}

$pdo = db_connect();
$stmt = $pdo->prepare("SELECT * FROM cms_gallery_photos WHERE id = ?");
$stmt->execute([$photoId]);
$photo = $stmt->fetch();
if (!$photo) {
    header('Location: ' . BASE_URL . '/gallery/index.php');
    exit;
}

$albumId = (int)$photo['album_id'];
$stmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ?");
$stmt->execute([$albumId]);
$album = $stmt->fetch();

$stmtPrev = $pdo->prepare(
    "SELECT id FROM cms_gallery_photos
     WHERE album_id = ? AND (sort_order < ? OR (sort_order = ? AND id < ?))
     ORDER BY sort_order DESC, id DESC LIMIT 1"
);
$stmtPrev->execute([$albumId, $photo['sort_order'], $photo['sort_order'], $photoId]);
$prevId = $stmtPrev->fetchColumn();

$stmtNext = $pdo->prepare(
    "SELECT id FROM cms_gallery_photos
     WHERE album_id = ? AND (sort_order > ? OR (sort_order = ? AND id > ?))
     ORDER BY sort_order ASC, id ASC LIMIT 1"
);
$stmtNext->execute([$albumId, $photo['sort_order'], $photo['sort_order'], $photoId]);
$nextId = $stmtNext->fetchColumn();

$siteName = getSetting('site_name', 'Kora CMS');
$trail = gallery_breadcrumb($albumId);
$photoTitle = $photo['title'] !== '' ? $photo['title'] : pathinfo($photo['filename'], PATHINFO_FILENAME);

renderPublicPage([
    'title' => $photoTitle . ' – ' . ($album['name'] ?? 'Galerie') . ' – ' . $siteName,
    'meta' => [
        'title' => $photoTitle . ' – ' . ($album['name'] ?? 'Galerie') . ' – ' . $siteName,
        'description' => $photo['title'] !== '' ? $photo['title'] : '',
        'image' => BASE_URL . '/uploads/gallery/' . rawurlencode($photo['filename']),
        'url' => BASE_URL . '/gallery/photo.php?id=' . $photoId,
    ],
    'view' => 'modules/gallery-photo',
    'view_data' => [
        'photo' => $photo,
        'albumId' => $albumId,
        'trail' => $trail,
        'photoTitle' => $photoTitle,
        'prevId' => $prevId,
        'nextId' => $nextId,
    ],
    'current_nav' => 'gallery',
    'body_class' => 'page-gallery-photo',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/gallery_photo_form.php?id=' . $photoId,
]);
