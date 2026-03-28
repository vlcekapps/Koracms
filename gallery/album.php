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
$albumSlug = galleryAlbumSlug(trim($_GET['slug'] ?? ''));
if ($albumId === null && $albumSlug === '') {
    header('Location: ' . BASE_URL . '/gallery/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

if ($albumSlug !== '') {
    $stmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE slug = ? LIMIT 1");
    $stmt->execute([$albumSlug]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ? LIMIT 1");
    $stmt->execute([$albumId]);
}
$album = $stmt->fetch() ?: null;

if ($album === null) {
    http_response_code(404);
    $missingPath = $albumSlug !== ''
        ? BASE_URL . '/gallery/album/' . rawurlencode($albumSlug)
        : BASE_URL . '/gallery/album.php' . ($albumId !== null ? '?id=' . urlencode((string)$albumId) : '');

    renderPublicPage([
        'title' => 'Album nenalezeno – ' . $siteName,
        'meta' => [
            'title' => 'Album nenalezeno – ' . $siteName,
            'url' => $missingPath,
        ],
        'view' => 'not-found',
        'body_class' => 'page-gallery-not-found',
    ]);
    exit;
}

$album = hydrateGalleryAlbumPresentation($album);
if ($albumSlug === '') {
    header('Location: ' . $album['public_path']);
    exit;
}

$trail = gallery_breadcrumb((int)$album['id']);

$subAlbumsStmt = $pdo->prepare(
    "SELECT a.id, a.name, a.slug, a.description,
            (SELECT COUNT(*) FROM cms_gallery_photos p WHERE p.album_id = a.id) AS photo_count,
            (SELECT COUNT(*) FROM cms_gallery_albums s WHERE s.parent_id = a.id) AS sub_count
     FROM cms_gallery_albums a
     WHERE a.parent_id = ?
       AND COALESCE(a.status, 'published') = 'published'
       AND COALESCE(a.is_published, 1) = 1
     ORDER BY a.name"
);
$subAlbumsStmt->execute([(int)$album['id']]);
$subAlbums = $subAlbumsStmt->fetchAll();

$photosStmt = $pdo->prepare(
    "SELECT id, filename, title, slug
     FROM cms_gallery_photos
     WHERE album_id = ?
       AND COALESCE(status, 'published') = 'published'
       AND COALESCE(is_published, 1) = 1
     ORDER BY sort_order, id"
);
$photosStmt->execute([(int)$album['id']]);
$photos = $photosStmt->fetchAll();

foreach ($subAlbums as &$subAlbum) {
    $subAlbum = hydrateGalleryAlbumPresentation($subAlbum);
    $subAlbum['photo_count_label'] = galleryCountLabel((int)$subAlbum['photo_count'], 'fotka', 'fotky', 'fotek');
    $subAlbum['sub_count_label'] = galleryCountLabel((int)$subAlbum['sub_count'], 'podsložka', 'podsložky', 'podsložek');
}
unset($subAlbum);

foreach ($photos as &$photo) {
    $photo = hydrateGalleryPhotoPresentation($photo);
}
unset($photo);

renderPublicPage([
    'title' => $album['name'] . ' – Galerie – ' . $siteName,
    'meta' => [
        'title' => $album['name'] . ' – Galerie – ' . $siteName,
        'description' => $album['excerpt'] !== '' ? $album['excerpt'] : '',
        'url' => $album['public_path'],
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
    'admin_edit_url' => BASE_URL . '/admin/gallery_album_form.php?id=' . (int)$album['id'],
]);
