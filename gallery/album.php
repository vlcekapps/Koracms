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
$albumSlug = galleryAlbumSlug(trim((string)($_GET['slug'] ?? '')));
if ($albumId === null && $albumSlug === '') {
    header('Location: ' . BASE_URL . '/gallery/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$searchQuery = trim((string)($_GET['q'] ?? ''));
$perPage = 18;

if ($albumSlug !== '') {
    $stmt = $pdo->prepare(
        "SELECT a.*
         FROM cms_gallery_albums a
         WHERE a.slug = ?
           AND " . galleryAlbumPublicVisibilitySql('a') . "
         LIMIT 1"
    );
    $stmt->execute([$albumSlug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT a.*
         FROM cms_gallery_albums a
         WHERE a.id = ?
           AND " . galleryAlbumPublicVisibilitySql('a') . "
         LIMIT 1"
    );
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
$listingQuery = array_filter([
    'q' => $searchQuery !== '' ? $searchQuery : null,
    'strana' => null,
], static fn($value): bool => $value !== null && $value !== '');

if ($albumSlug === '') {
    header('Location: ' . galleryAlbumPublicPath($album, $listingQuery));
    exit;
}

$trail = gallery_breadcrumb((int)$album['id']);

$subAlbumWhereParts = [
    'a.parent_id = ?',
    galleryAlbumPublicVisibilitySql('a'),
];
$subAlbumParams = [(int)$album['id']];
if ($searchQuery !== '') {
    $subAlbumWhereParts[] = '(a.name LIKE ? OR a.description LIKE ?)';
    $subAlbumParams[] = '%' . $searchQuery . '%';
    $subAlbumParams[] = '%' . $searchQuery . '%';
}
$subAlbumsStmt = $pdo->prepare(
    "SELECT a.id, a.name, a.slug, a.description, a.parent_id,
            (SELECT COUNT(*)
             FROM cms_gallery_photos p
             WHERE p.album_id = a.id
               AND " . galleryPhotoPublicVisibilitySql('p') . ") AS photo_count,
            (SELECT COUNT(*)
             FROM cms_gallery_albums s
             WHERE s.parent_id = a.id
               AND " . galleryAlbumPublicVisibilitySql('s') . ") AS sub_count
     FROM cms_gallery_albums a
     WHERE " . implode(' AND ', $subAlbumWhereParts) . "
     ORDER BY a.name"
);
$subAlbumsStmt->execute($subAlbumParams);
$subAlbums = $subAlbumsStmt->fetchAll();

foreach ($subAlbums as &$subAlbum) {
    $subAlbum = hydrateGalleryAlbumPresentation($subAlbum);
    $subAlbum['photo_count_label'] = galleryCountLabel((int)$subAlbum['photo_count'], 'fotka', 'fotky', 'fotek');
    $subAlbum['sub_count_label'] = galleryCountLabel((int)$subAlbum['sub_count'], 'podsložka', 'podsložky', 'podsložek');
}
unset($subAlbum);

$photoWhereParts = [
    'album_id = ?',
    galleryPhotoPublicVisibilitySql(),
];
$photoParams = [(int)$album['id']];
if ($searchQuery !== '') {
    $photoWhereParts[] = '(title LIKE ? OR slug LIKE ? OR filename LIKE ?)';
    $photoParams[] = '%' . $searchQuery . '%';
    $photoParams[] = '%' . $searchQuery . '%';
    $photoParams[] = '%' . $searchQuery . '%';
}
$photoWhereSql = 'WHERE ' . implode(' AND ', $photoWhereParts);
$photoPagination = paginate(
    $pdo,
    "SELECT COUNT(*)
     FROM cms_gallery_photos
     {$photoWhereSql}",
    $photoParams,
    $perPage
);
['total' => $totalPhotos, 'totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $photoPagination;

$photosStmt = $pdo->prepare(
    "SELECT id, album_id, filename, title, slug, sort_order, created_at
     FROM cms_gallery_photos
     {$photoWhereSql}
     ORDER BY sort_order, id
     LIMIT ? OFFSET ?"
);
$photosStmt->execute(array_merge($photoParams, [$perPage, $offset]));
$photos = $photosStmt->fetchAll();

$albumListingQuery = array_filter([
    'q' => $searchQuery !== '' ? $searchQuery : null,
    'strana' => $page > 1 ? (string)$page : null,
], static fn($value): bool => $value !== null && $value !== '');

foreach ($photos as &$photo) {
    $photo = hydrateGalleryPhotoPresentation($photo);
    $photo['public_path'] = galleryPhotoPublicPath($photo, $albumListingQuery);
}
unset($photo);

$buildAlbumUrl = static function (array $overrides = []) use ($album, $searchQuery): string {
    $query = [];
    $merged = array_merge([
        'q' => $searchQuery,
    ], $overrides);

    foreach ($merged as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $query[$key] = $value;
    }

    return galleryAlbumPublicPath($album, $query);
};

$pagerBase = $buildAlbumUrl(['strana' => null]);
$pagerBase .= str_contains($pagerBase, '?') ? '&' : '?';
$filterSummary = [];
if ($searchQuery !== '') {
    $filterSummary[] = 'hledání: „' . $searchQuery . '“';
}

$resultParts = [];
if (!empty($subAlbums)) {
    $resultParts[] = galleryCountLabel(count($subAlbums), 'podalbum', 'podalba', 'podalb');
}
if ($totalPhotos > 0) {
    $resultParts[] = galleryCountLabel($totalPhotos, 'fotografie', 'fotografie', 'fotografií');
}
$resultCountLabel = $resultParts !== [] ? implode(' · ', $resultParts) : 'Žádné položky';

$metaTitle = $album['name'] . ' – Galerie – ' . $siteName;
if ($searchQuery !== '') {
    $metaTitle = $album['name'] . ' – hledání „' . $searchQuery . '“ – ' . $siteName;
}

renderPublicPage([
    'title' => $metaTitle,
    'meta' => [
        'title' => $metaTitle,
        'description' => $album['excerpt'] !== '' ? $album['excerpt'] : '',
        'url' => galleryAlbumPublicUrl($album, ['q' => $searchQuery !== '' ? $searchQuery : null]),
    ],
    'view' => 'modules/gallery-album',
    'view_data' => [
        'album' => $album,
        'trail' => $trail,
        'subAlbums' => $subAlbums,
        'photos' => $photos,
        'searchQuery' => $searchQuery,
        'filterSummary' => $filterSummary,
        'resultCountLabel' => $resultCountLabel,
        'pagerHtml' => renderPager($page, $pages, $pagerBase, 'Stránkování alba galerie', 'Předchozí stránka', 'Další stránka'),
        'hasActiveFilters' => $searchQuery !== '',
        'clearUrl' => galleryAlbumPublicPath($album),
    ],
    'current_nav' => 'gallery',
    'body_class' => 'page-gallery-album',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/gallery_album_form.php?id=' . (int)$album['id'],
    'extra_head_html' => galleryAlbumStructuredData($album, $photos),
]);
