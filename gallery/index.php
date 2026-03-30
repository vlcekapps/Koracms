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

function galleryAlbumsCountLabel(int $count): string
{
    if ($count === 1) {
        return '1 album';
    }
    if ($count >= 2 && $count <= 4) {
        return $count . ' alba';
    }

    return $count . ' alb';
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$searchQuery = trim((string)($_GET['q'] ?? ''));
$perPage = 12;

$whereParts = [galleryAlbumPublicVisibilitySql('a')];
$params = [];
if ($searchQuery === '') {
    $whereParts[] = 'a.parent_id IS NULL';
} else {
    $whereParts[] = '(a.name LIKE ? OR a.slug LIKE ? OR a.description LIKE ?)';
    $params = [
        '%' . $searchQuery . '%',
        '%' . $searchQuery . '%',
        '%' . $searchQuery . '%',
    ];
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);
$pagination = paginate(
    $pdo,
    "SELECT COUNT(*)
     FROM cms_gallery_albums a
     {$whereSql}",
    $params,
    $perPage
);
['total' => $totalAlbums, 'totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pagination;

$orderSql = $searchQuery === ''
    ? 'ORDER BY a.name'
    : 'ORDER BY COALESCE(a.updated_at, a.created_at) DESC, a.name';

$stmt = $pdo->prepare(
    "SELECT a.id, a.name, a.slug, a.description, a.parent_id,
            COALESCE(a.updated_at, a.created_at) AS updated_at,
            (SELECT COUNT(*)
             FROM cms_gallery_photos p
             WHERE p.album_id = a.id
               AND " . galleryPhotoPublicVisibilitySql('p') . ") AS photo_count,
            (SELECT COUNT(*)
             FROM cms_gallery_albums s
             WHERE s.parent_id = a.id
               AND " . galleryAlbumPublicVisibilitySql('s') . ") AS sub_count
     FROM cms_gallery_albums a
     {$whereSql}
     {$orderSql}
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$albums = $stmt->fetchAll();

foreach ($albums as &$album) {
    $album = hydrateGalleryAlbumPresentation($album);
    $album['photo_count_label'] = galleryCountLabel((int)$album['photo_count'], 'fotka', 'fotky', 'fotek');
    $album['sub_count_label'] = galleryCountLabel((int)$album['sub_count'], 'podsložka', 'podsložky', 'podsložek');
}
unset($album);

$buildGalleryIndexUrl = static function (array $overrides = []) use ($searchQuery): string {
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

    return BASE_URL . '/gallery/index.php' . ($query !== [] ? '?' . http_build_query($query) : '');
};

$pagerBase = $buildGalleryIndexUrl(['strana' => null]);
$pagerBase .= str_contains($pagerBase, '?') ? '&' : '?';

$metaTitle = $searchQuery !== ''
    ? 'Galerie – hledání „' . $searchQuery . '“ – ' . $siteName
    : 'Galerie – ' . $siteName;

renderPublicPage([
    'title' => $metaTitle,
    'meta' => [
        'title' => $metaTitle,
        'url' => siteUrl(str_replace(BASE_URL, '', $buildGalleryIndexUrl(['strana' => null]))),
    ],
    'view' => 'modules/gallery-index',
    'view_data' => [
        'albums' => $albums,
        'searchQuery' => $searchQuery,
        'resultCountLabel' => galleryAlbumsCountLabel($totalAlbums),
        'pagerHtml' => renderPager($page, $pages, $pagerBase, 'Stránkování galerie', 'Předchozí stránka', 'Další stránka'),
        'hasActiveFilters' => $searchQuery !== '',
        'clearUrl' => BASE_URL . '/gallery/index.php',
    ],
    'current_nav' => 'gallery',
    'body_class' => 'page-gallery-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/gallery_albums.php',
]);
