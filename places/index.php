<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('places')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function placesCountLabel(int $count): string
{
    if ($count === 1) {
        return '1 místo';
    }
    if ($count >= 2 && $count <= 4) {
        return $count . ' místa';
    }

    return $count . ' míst';
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage = 12;

$q = trim((string)($_GET['q'] ?? ''));
$kindFilter = trim((string)($_GET['kind'] ?? 'all'));
$categoryFilter = trim((string)($_GET['category'] ?? ''));
$localityFilter = trim((string)($_GET['locality'] ?? ''));

$kindOptions = placeKindOptions();
if ($kindFilter !== 'all' && !isset($kindOptions[$kindFilter])) {
    $kindFilter = 'all';
}

$categoryOptions = $pdo->query(
    "SELECT DISTINCT TRIM(category) AS category_label
     FROM cms_places
     WHERE " . placePublicVisibilitySql() . "
       AND TRIM(COALESCE(category, '')) <> ''
     ORDER BY category_label"
)->fetchAll(PDO::FETCH_COLUMN);
$categoryOptions = array_values(array_filter(array_map(static fn($value): string => trim((string)$value), $categoryOptions)));
if ($categoryFilter !== '' && !in_array($categoryFilter, $categoryOptions, true)) {
    $categoryFilter = '';
}

$localityOptions = $pdo->query(
    "SELECT DISTINCT TRIM(locality) AS locality_label
     FROM cms_places
     WHERE " . placePublicVisibilitySql() . "
       AND TRIM(COALESCE(locality, '')) <> ''
     ORDER BY locality_label"
)->fetchAll(PDO::FETCH_COLUMN);
$localityOptions = array_values(array_filter(array_map(static fn($value): string => trim((string)$value), $localityOptions)));
if ($localityFilter !== '' && !in_array($localityFilter, $localityOptions, true)) {
    $localityFilter = '';
}

$whereParts = [placePublicVisibilitySql('p')];
$params = [];

if ($q !== '') {
    $whereParts[] = '(p.name LIKE ? OR p.excerpt LIKE ? OR p.description LIKE ? OR p.address LIKE ? OR p.locality LIKE ? OR p.category LIKE ?)';
    for ($i = 0; $i < 6; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($kindFilter !== 'all') {
    $whereParts[] = 'p.place_kind = ?';
    $params[] = $kindFilter;
}

if ($categoryFilter !== '') {
    $whereParts[] = 'TRIM(p.category) = ?';
    $params[] = $categoryFilter;
}

if ($localityFilter !== '') {
    $whereParts[] = 'TRIM(p.locality) = ?';
    $params[] = $localityFilter;
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);
$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_places p {$whereSql}",
    $params,
    $perPage
);
['total' => $totalItems, 'totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pagination;

$stmt = $pdo->prepare(
    "SELECT *
     FROM cms_places p
     {$whereSql}
     ORDER BY p.name ASC, p.id ASC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$places = array_map(
    static fn(array $place): array => hydratePlacePresentation($place),
    $stmt->fetchAll()
);

$buildPlacesIndexUrl = static function (array $overrides = []) use ($q, $kindFilter, $categoryFilter, $localityFilter): string {
    $query = [];
    $merged = array_merge([
        'q' => $q,
        'kind' => $kindFilter,
        'category' => $categoryFilter,
        'locality' => $localityFilter,
    ], $overrides);

    foreach ($merged as $key => $value) {
        if ($value === null || $value === '' || ($key === 'kind' && $value === 'all')) {
            continue;
        }
        $query[$key] = $value;
    }

    return BASE_URL . '/places/index.php' . ($query !== [] ? '?' . http_build_query($query) : '');
};

$filterSummary = [];
if ($q !== '') {
    $filterSummary[] = 'hledání: „' . $q . '“';
}
if ($kindFilter !== 'all') {
    $filterSummary[] = 'typ: ' . (string)($kindOptions[$kindFilter]['label'] ?? $kindFilter);
}
if ($categoryFilter !== '') {
    $filterSummary[] = 'kategorie: ' . $categoryFilter;
}
if ($localityFilter !== '') {
    $filterSummary[] = 'lokalita: ' . $localityFilter;
}

$pagerBase = $buildPlacesIndexUrl(['strana' => null]);
$pagerBase .= str_contains($pagerBase, '?') ? '&' : '?';
$metaTitle = 'Zajímavá místa - ' . $siteName;
if ($filterSummary !== []) {
    $metaTitle = 'Zajímavá místa - ' . implode(', ', $filterSummary) . ' - ' . $siteName;
}

renderPublicPage([
    'title' => $metaTitle,
    'meta' => [
        'title' => $metaTitle,
        'url' => siteUrl(str_replace(BASE_URL, '', $buildPlacesIndexUrl(['strana' => null]))),
    ],
    'view' => 'modules/places-index',
    'view_data' => [
        'places' => $places,
        'searchQuery' => $q,
        'kindOptions' => $kindOptions,
        'selectedKind' => $kindFilter,
        'categoryOptions' => $categoryOptions,
        'selectedCategory' => $categoryFilter,
        'localityOptions' => $localityOptions,
        'selectedLocality' => $localityFilter,
        'filterSummary' => $filterSummary,
        'resultCountLabel' => placesCountLabel($totalItems),
        'pagerHtml' => renderPager($page, $pages, $pagerBase, 'Stránkování míst', 'Předchozí stránka', 'Další stránka'),
        'hasActiveFilters' => $q !== '' || $kindFilter !== 'all' || $categoryFilter !== '' || $localityFilter !== '',
        'clearUrl' => BASE_URL . '/places/index.php',
        'listingQuery' => array_filter([
            'q' => $q !== '' ? $q : null,
            'kind' => $kindFilter !== 'all' ? $kindFilter : null,
            'category' => $categoryFilter !== '' ? $categoryFilter : null,
            'locality' => $localityFilter !== '' ? $localityFilter : null,
            'strana' => $page > 1 ? (string)$page : null,
        ], static fn($value): bool => $value !== null && $value !== ''),
    ],
    'current_nav' => 'places',
    'body_class' => 'page-places-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/places.php',
]);
