<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('downloads')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function groupDownloadsByCategory(array $items): array
{
    $grouped = [];
    foreach ($items as $item) {
        $grouped[$item['category_name'] ?: 'Ostatní'][] = $item;
    }
    ksort($grouped);
    return $grouped;
}

function downloadsCountLabel(int $count): string
{
    if ($count === 1) {
        return '1 položka ke stažení';
    }
    if ($count >= 2 && $count <= 4) {
        return $count . ' položky ke stažení';
    }

    return $count . ' položek ke stažení';
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage = 12;

$q = trim((string)($_GET['q'] ?? ''));
$categoryId = inputInt('get', 'kat');
$typeFilter = trim((string)($_GET['typ'] ?? 'all'));
$platformFilter = trim((string)($_GET['platform'] ?? ''));
$sourceFilter = trim((string)($_GET['source'] ?? 'all'));
$featuredOnly = (string)($_GET['featured'] ?? '0') === '1';

$allowedSourceFilters = ['all', 'local', 'external', 'hybrid'];
if (!in_array($sourceFilter, $allowedSourceFilters, true)) {
    $sourceFilter = 'all';
}
if ($typeFilter !== 'all' && !isset(downloadTypeDefinitions()[$typeFilter])) {
    $typeFilter = 'all';
}

$categories = $pdo->query(
    "SELECT c.id, c.name, COUNT(d.id) AS item_count
     FROM cms_dl_categories c
     LEFT JOIN cms_downloads d
       ON d.dl_category_id = c.id
      AND d.status = 'published'
      AND d.is_published = 1
     GROUP BY c.id, c.name
     ORDER BY c.name"
)->fetchAll();
$validCategoryIds = array_map(static fn(array $category): int => (int)$category['id'], $categories);
if ($categoryId !== null && !in_array($categoryId, $validCategoryIds, true)) {
    $categoryId = null;
}

$platformOptions = $pdo->query(
    "SELECT DISTINCT platform_label
     FROM cms_downloads
     WHERE status = 'published'
       AND is_published = 1
       AND TRIM(COALESCE(platform_label, '')) <> ''
     ORDER BY platform_label"
)->fetchAll(PDO::FETCH_COLUMN);
$platformOptions = array_values(array_filter(array_map(static fn($value): string => trim((string)$value), $platformOptions)));
if ($platformFilter !== '' && !in_array($platformFilter, $platformOptions, true)) {
    $platformFilter = '';
}

$whereParts = ["d.status = 'published'", 'd.is_published = 1'];
$params = [];

if ($q !== '') {
    $whereParts[] = "(d.title LIKE ? OR d.excerpt LIKE ? OR d.description LIKE ? OR COALESCE(c.name, '') LIKE ?
        OR d.version_label LIKE ? OR d.platform_label LIKE ? OR d.license_label LIKE ? OR d.requirements LIKE ?)";
    for ($i = 0; $i < 8; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($categoryId !== null) {
    $whereParts[] = 'd.dl_category_id = ?';
    $params[] = $categoryId;
}

if ($typeFilter !== 'all') {
    $whereParts[] = 'd.download_type = ?';
    $params[] = $typeFilter;
}

if ($platformFilter !== '') {
    $whereParts[] = 'd.platform_label = ?';
    $params[] = $platformFilter;
}

if ($sourceFilter === 'local') {
    $whereParts[] = "d.filename <> '' AND d.external_url = ''";
} elseif ($sourceFilter === 'external') {
    $whereParts[] = "d.filename = '' AND d.external_url <> ''";
} elseif ($sourceFilter === 'hybrid') {
    $whereParts[] = "d.filename <> '' AND d.external_url <> ''";
}

if ($featuredOnly) {
    $whereParts[] = 'd.is_featured = 1';
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);

$pagination = paginate(
    $pdo,
    "SELECT COUNT(*)
     FROM cms_downloads d
     LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
     {$whereSql}",
    $params,
    $perPage
);
['total' => $totalItems, 'totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pagination;

$stmt = $pdo->prepare(
    "SELECT d.id, d.title, d.slug, d.download_type, d.dl_category_id, COALESCE(c.name, '') AS category_name,
            d.excerpt, d.description, d.image_file, d.version_label, d.platform_label, d.license_label,
            d.project_url, d.release_date, d.requirements, d.checksum_sha256, d.series_key,
            d.external_url, d.filename, d.original_name, d.file_size, d.download_count, d.is_featured,
            d.created_at, d.updated_at
     FROM cms_downloads d
     LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
     {$whereSql}
     ORDER BY d.is_featured DESC, COALESCE(d.release_date, DATE(d.created_at)) DESC, d.created_at DESC, d.id DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$items = array_map(
    static fn(array $download): array => hydrateDownloadPresentation($download),
    $stmt->fetchAll()
);

$grouped = groupDownloadsByCategory($items);
$showCategoryHeadings = count($grouped) > 1;

$buildDownloadsIndexUrl = static function (array $overrides = []) use ($q, $categoryId, $typeFilter, $platformFilter, $sourceFilter, $featuredOnly): string {
    $query = [];
    $merged = array_merge([
        'q' => $q,
        'kat' => $categoryId,
        'typ' => $typeFilter,
        'platform' => $platformFilter,
        'source' => $sourceFilter,
        'featured' => $featuredOnly ? '1' : '0',
    ], $overrides);

    foreach ($merged as $key => $value) {
        if ($value === null || $value === '' || ($key === 'typ' && $value === 'all') || ($key === 'source' && $value === 'all') || ($key === 'featured' && $value === '0')) {
            continue;
        }
        $query[$key] = $value;
    }

    return BASE_URL . '/downloads/index.php' . ($query !== [] ? '?' . http_build_query($query) : '');
};

$filterSummary = [];
if ($q !== '') {
    $filterSummary[] = 'hledání: „' . $q . '“';
}
if ($categoryId !== null) {
    foreach ($categories as $category) {
        if ((int)$category['id'] === $categoryId) {
            $filterSummary[] = 'kategorie: ' . (string)$category['name'];
            break;
        }
    }
}
if ($typeFilter !== 'all') {
    $filterSummary[] = 'typ: ' . downloadTypeDefinitions()[$typeFilter]['label'];
}
if ($platformFilter !== '') {
    $filterSummary[] = 'platforma: ' . $platformFilter;
}
if ($sourceFilter === 'local') {
    $filterSummary[] = 'zdroj: lokální soubor';
} elseif ($sourceFilter === 'external') {
    $filterSummary[] = 'zdroj: externí odkaz';
} elseif ($sourceFilter === 'hybrid') {
    $filterSummary[] = 'zdroj: soubor i externí odkaz';
}
if ($featuredOnly) {
    $filterSummary[] = 'jen doporučené';
}

$metaTitle = 'Ke stažení - ' . $siteName;
if ($filterSummary !== []) {
    $metaTitle = 'Ke stažení - ' . implode(', ', $filterSummary) . ' - ' . $siteName;
}

$paginBase = $buildDownloadsIndexUrl(['strana' => null]);
$paginBase .= str_contains($paginBase, '?') ? '&' : '?';

renderPublicPage([
    'title' => $metaTitle,
    'meta' => [
        'title' => $metaTitle,
        'url' => siteUrl(str_replace(BASE_URL, '', $buildDownloadsIndexUrl(['strana' => null]))),
    ],
    'view' => 'modules/downloads-index',
    'view_data' => [
        'items' => $items,
        'grouped' => $grouped,
        'showCategoryHeadings' => $showCategoryHeadings,
        'searchQuery' => $q,
        'categories' => $categories,
        'selectedCategoryId' => $categoryId,
        'selectedType' => $typeFilter,
        'selectedPlatform' => $platformFilter,
        'selectedSource' => $sourceFilter,
        'featuredOnly' => $featuredOnly,
        'platformOptions' => $platformOptions,
        'filterSummary' => $filterSummary,
        'resultCountLabel' => downloadsCountLabel($totalItems),
        'pagerHtml' => renderPager($page, $pages, $paginBase, 'Stránkování sekce ke stažení', 'Předchozí stránka', 'Další stránka'),
        'hasActiveFilters' => $q !== '' || $categoryId !== null || $typeFilter !== 'all' || $platformFilter !== '' || $sourceFilter !== 'all' || $featuredOnly,
        'clearUrl' => BASE_URL . '/downloads/index.php',
    ],
    'current_nav' => 'downloads',
    'body_class' => 'page-downloads-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/downloads.php',
]);
