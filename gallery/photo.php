<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('gallery')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$photoId = inputInt('get', 'id');
$photoSlug = galleryPhotoSlug(trim((string)($_GET['slug'] ?? '')));
if ($photoId === null && $photoSlug === '') {
    header('Location: ' . BASE_URL . '/gallery/index.php');
    exit;
}

$searchQuery = trim((string)($_GET['q'] ?? ''));
$listingPage = max(1, (int)($_GET['strana'] ?? 1));

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

if ($photoSlug !== '') {
    $stmt = $pdo->prepare(
        "SELECT p.*,
                a.id AS album_id,
                a.name AS album_name,
                a.slug AS album_slug,
                a.description AS album_description
         FROM cms_gallery_photos p
         INNER JOIN cms_gallery_albums a ON a.id = p.album_id
         WHERE p.slug = ?
           AND " . galleryPhotoPublicVisibilitySql('p', 'a') . "
         LIMIT 1"
    );
    $stmt->execute([$photoSlug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT p.*,
                a.id AS album_id,
                a.name AS album_name,
                a.slug AS album_slug,
                a.description AS album_description
         FROM cms_gallery_photos p
         INNER JOIN cms_gallery_albums a ON a.id = p.album_id
         WHERE p.id = ?
           AND " . galleryPhotoPublicVisibilitySql('p', 'a') . "
         LIMIT 1"
    );
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

$album = hydrateGalleryAlbumPresentation([
    'id' => $photo['album_id'],
    'name' => $photo['album_name'],
    'slug' => $photo['album_slug'],
    'description' => $photo['album_description'],
]);

$listingQuery = array_filter([
    'q' => $searchQuery !== '' ? $searchQuery : null,
    'strana' => $listingPage > 1 ? (string)$listingPage : null,
], static fn($value): bool => $value !== null && $value !== '');

$photo = hydrateGalleryPhotoPresentation($photo);
if ($photoSlug === '') {
    header('Location: ' . galleryPhotoPublicPath($photo, $listingQuery));
    exit;
}

$albumId = (int)$album['id'];

$sequenceStmt = $pdo->prepare(
    "SELECT id
     FROM cms_gallery_photos p
     WHERE p.album_id = ?
       AND " . galleryPhotoPublicVisibilitySql('p') . "
     ORDER BY p.sort_order, p.id"
);
$sequenceStmt->execute([$albumId]);
$orderedIds = array_map(static fn($value): int => (int)$value, $sequenceStmt->fetchAll(PDO::FETCH_COLUMN));
$photoIndex = array_search((int)$photo['id'], $orderedIds, true);
$photoPosition = $photoIndex === false ? null : $photoIndex + 1;
$photoCount = count($orderedIds);

$stmtPrev = $pdo->prepare(
    "SELECT id, album_id, filename, title, slug, sort_order, created_at
     FROM cms_gallery_photos p
     WHERE p.album_id = ?
       AND (p.sort_order < ? OR (p.sort_order = ? AND p.id < ?))
       AND " . galleryPhotoPublicVisibilitySql('p') . "
     ORDER BY p.sort_order DESC, p.id DESC
     LIMIT 1"
);
$stmtPrev->execute([$albumId, $photo['sort_order'], $photo['sort_order'], $photo['id']]);
$prevPhoto = $stmtPrev->fetch() ?: null;
if ($prevPhoto !== null) {
    $prevPhoto = hydrateGalleryPhotoPresentation($prevPhoto);
    $prevPhoto['public_path'] = galleryPhotoPublicPath($prevPhoto, $listingQuery);
}

$stmtNext = $pdo->prepare(
    "SELECT id, album_id, filename, title, slug, sort_order, created_at
     FROM cms_gallery_photos p
     WHERE p.album_id = ?
       AND (p.sort_order > ? OR (p.sort_order = ? AND p.id > ?))
       AND " . galleryPhotoPublicVisibilitySql('p') . "
     ORDER BY p.sort_order ASC, p.id ASC
     LIMIT 1"
);
$stmtNext->execute([$albumId, $photo['sort_order'], $photo['sort_order'], $photo['id']]);
$nextPhoto = $stmtNext->fetch() ?: null;
if ($nextPhoto !== null) {
    $nextPhoto = hydrateGalleryPhotoPresentation($nextPhoto);
    $nextPhoto['public_path'] = galleryPhotoPublicPath($nextPhoto, $listingQuery);
}

$relatedStmt = $pdo->prepare(
    "SELECT id, album_id, filename, title, slug, sort_order, created_at
     FROM cms_gallery_photos p
     WHERE p.album_id = ?
       AND p.id <> ?
       AND " . galleryPhotoPublicVisibilitySql('p') . "
     ORDER BY ABS(p.sort_order - ?) ASC, ABS(p.id - ?) ASC
     LIMIT 6"
);
$relatedStmt->execute([$albumId, $photo['id'], $photo['sort_order'], $photo['id']]);
$relatedPhotos = array_map(
    static function (array $relatedPhoto) use ($listingQuery): array {
        $relatedPhoto = hydrateGalleryPhotoPresentation($relatedPhoto);
        $relatedPhoto['public_path'] = galleryPhotoPublicPath($relatedPhoto, $listingQuery);
        return $relatedPhoto;
    },
    $relatedStmt->fetchAll()
);

$trail = gallery_breadcrumb($albumId);
$photoTitle = (string)$photo['label'];
$copyUrl = galleryPhotoPublicUrl($photo);
$backPath = galleryAlbumPublicPath($album, $listingQuery);
$metaTitle = $photoTitle . ' – ' . $album['name'] . ' – ' . $siteName;

renderPublicPage([
    'title' => $metaTitle,
    'meta' => [
        'title' => $metaTitle,
        'description' => $photoTitle,
        'image' => $photo['image_url'],
        'url' => galleryPhotoPublicUrl($photo),
    ],
    'view' => 'modules/gallery-photo',
    'view_data' => [
        'photo' => $photo,
        'album' => $album,
        'trail' => $trail,
        'photoTitle' => $photoTitle,
        'prevPhoto' => $prevPhoto,
        'nextPhoto' => $nextPhoto,
        'relatedPhotos' => $relatedPhotos,
        'copyUrl' => $copyUrl,
        'backPath' => $backPath,
        'photoPosition' => $photoPosition,
        'photoCount' => $photoCount,
    ],
    'current_nav' => 'gallery',
    'body_class' => 'page-gallery-photo',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/gallery_photo_form.php?id=' . (int)$photo['id'] . '&album_id=' . $albumId,
    'extra_head_html' => galleryPhotoStructuredData($photo, $album),
]);
