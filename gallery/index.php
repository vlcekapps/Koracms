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

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$albums = $pdo->query(
    "SELECT a.id, a.name, a.description,
            (SELECT COUNT(*) FROM cms_gallery_photos p WHERE p.album_id = a.id) AS photo_count,
            (SELECT COUNT(*) FROM cms_gallery_albums s WHERE s.parent_id = a.id) AS sub_count
     FROM cms_gallery_albums a
     WHERE a.parent_id IS NULL
     ORDER BY a.name"
)->fetchAll();

foreach ($albums as &$album) {
    $album['cover_url'] = gallery_cover_url((int)$album['id']);
    $album['photo_count_label'] = galleryCountLabel((int)$album['photo_count'], 'fotka', 'fotky', 'fotek');
    $album['sub_count_label'] = galleryCountLabel((int)$album['sub_count'], 'podsložka', 'podsložky', 'podsložek');
}
unset($album);

renderPublicPage([
    'title' => 'Galerie – ' . $siteName,
    'meta' => [
        'title' => 'Galerie – ' . $siteName,
        'url' => BASE_URL . '/gallery/index.php',
    ],
    'view' => 'modules/gallery-index',
    'view_data' => [
        'albums' => $albums,
    ],
    'current_nav' => 'gallery',
    'body_class' => 'page-gallery-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/gallery_albums.php',
]);
