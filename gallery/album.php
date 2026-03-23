<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('gallery')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function galleryCountLabel(int $count, string $one, string $few, string $many): string
{
    return $count . ' ' . ($count === 1 ? $one : ($count < 5 ? $few : $many));
}

$albumId = inputInt('get', 'id');
if ($albumId === null) {
    header('Location: ' . BASE_URL . '/gallery/index.php');
    exit;
}

$pdo = db_connect();
$stmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ?");
$stmt->execute([$albumId]);
$album = $stmt->fetch();
if (!$album) {
    header('Location: ' . BASE_URL . '/gallery/index.php');
    exit;
}

$siteName = getSetting('site_name', 'Kora CMS');
$trail = gallery_breadcrumb($albumId);

$subAlbumsStmt = $pdo->prepare(
    "SELECT a.id, a.name, a.description,
            (SELECT COUNT(*) FROM cms_gallery_photos p WHERE p.album_id = a.id) AS photo_count,
            (SELECT COUNT(*) FROM cms_gallery_albums s WHERE s.parent_id = a.id) AS sub_count
     FROM cms_gallery_albums a
     WHERE a.parent_id = ?
     ORDER BY a.name"
);
$subAlbumsStmt->execute([$albumId]);
$subAlbums = $subAlbumsStmt->fetchAll();

$photosStmt = $pdo->prepare(
    "SELECT id, filename, title FROM cms_gallery_photos
     WHERE album_id = ?
     ORDER BY sort_order, id"
);
$photosStmt->execute([$albumId]);
$photos = $photosStmt->fetchAll();

foreach ($subAlbums as &$subAlbum) {
    $subAlbum['cover_url'] = gallery_cover_url((int)$subAlbum['id']);
    $subAlbum['photo_count_label'] = galleryCountLabel((int)$subAlbum['photo_count'], 'fotka', 'fotky', 'fotek');
    $subAlbum['sub_count_label'] = galleryCountLabel((int)$subAlbum['sub_count'], 'podsložka', 'podsložky', 'podsložek');
}
unset($subAlbum);

foreach ($photos as &$photo) {
    $photo['label'] = $photo['title'] !== ''
        ? $photo['title']
        : pathinfo($photo['filename'], PATHINFO_FILENAME);
}
unset($photo);

renderPublicPage([
    'title' => $album['name'] . ' – Galerie – ' . $siteName,
    'meta' => [
        'title' => $album['name'] . ' – Galerie – ' . $siteName,
        'description' => $album['description'] ?? '',
        'url' => BASE_URL . '/gallery/album.php?id=' . $albumId,
    ],
    'view' => 'modules/gallery-album',
    'view_data' => [
        'album' => $album,
        'trail' => $trail,
        'subAlbums' => $subAlbums,
        'photos' => $photos,
    ],
    'current_nav' => 'gallery',
    'body_class' => 'page-gallery-album',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/gallery_album_form.php?id=' . $albumId,
]);
