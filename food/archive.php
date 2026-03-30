<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('food')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function foodCardsCountLabel(int $count): string
{
    if ($count === 1) {
        return '1 lístek';
    }
    if ($count >= 2 && $count <= 4) {
        return $count . ' lístky';
    }

    return $count . ' lístků';
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage = 12;

$q = trim((string)($_GET['q'] ?? ''));
$filterType = trim((string)($_GET['typ'] ?? 'vse'));
$scope = trim((string)($_GET['scope'] ?? 'all'));

if (!in_array($filterType, ['food', 'beverage', 'vse'], true)) {
    $filterType = 'vse';
}
if (!in_array($scope, ['current', 'upcoming', 'archive', 'all'], true)) {
    $scope = 'all';
}

$whereParts = [foodCardPublicVisibilitySql()];
$params = [];

if ($q !== '') {
    $whereParts[] = '(title LIKE ? OR description LIKE ? OR content LIKE ?)';
    for ($i = 0; $i < 3; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($filterType !== 'vse') {
    $whereParts[] = 'type = ?';
    $params[] = $filterType;
}

$countCardsForScope = static function (string $scopeKey) use ($pdo, $whereParts, $params): int {
    $scopeWhereParts = $whereParts;
    if ($scopeKey !== 'all') {
        $scopeWhereParts[] = '(' . foodCardScopeVisibilitySql($scopeKey) . ')';
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM cms_food_cards WHERE ' . implode(' AND ', $scopeWhereParts)
    );
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
};

$scopeCounts = [
    'current' => $countCardsForScope('current'),
    'upcoming' => $countCardsForScope('upcoming'),
    'archive' => $countCardsForScope('archive'),
    'all' => $countCardsForScope('all'),
];

$scopeWhereParts = $whereParts;
if ($scope !== 'all') {
    $scopeWhereParts[] = '(' . foodCardScopeVisibilitySql($scope) . ')';
}
$whereSql = 'WHERE ' . implode(' AND ', $scopeWhereParts);

$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_food_cards {$whereSql}",
    $params,
    $perPage
);
['total' => $totalItems, 'totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pagination;

$stateOrderSql = "CASE
    WHEN valid_from IS NOT NULL AND valid_from > CURDATE() THEN 1
    WHEN valid_to IS NOT NULL AND valid_to < CURDATE() THEN 2
    ELSE 0
  END";
$orderSql = match ($scope) {
    'upcoming' => 'COALESCE(valid_from, created_at) ASC, id DESC',
    'archive' => 'COALESCE(valid_to, valid_from, created_at) DESC, id DESC',
    'current' => 'is_current DESC, COALESCE(valid_from, created_at) DESC, id DESC',
    default => "{$stateOrderSql},
        CASE WHEN valid_from IS NOT NULL AND valid_from > CURDATE() THEN COALESCE(valid_from, created_at) END ASC,
        CASE WHEN valid_to IS NOT NULL AND valid_to < CURDATE() THEN COALESCE(valid_to, valid_from, created_at) END DESC,
        is_current DESC,
        COALESCE(valid_from, created_at) DESC,
        id DESC",
};

$cardsStmt = $pdo->prepare(
    "SELECT id, type, title, slug, description, content, valid_from, valid_to, is_current,
            is_published, status, created_at, updated_at
     FROM cms_food_cards
     {$whereSql}
     ORDER BY {$orderSql}
     LIMIT ? OFFSET ?"
);
$cardsStmt->execute(array_merge($params, [$perPage, $offset]));
$cards = array_map(
    static fn(array $card): array => hydrateFoodCardPresentation($card),
    $cardsStmt->fetchAll()
);

$buildFoodArchiveUrl = static function (array $overrides = []) use ($q, $filterType, $scope): string {
    $query = [];
    $merged = array_merge([
        'q' => $q,
        'typ' => $filterType,
        'scope' => $scope,
    ], $overrides);

    foreach ($merged as $key => $value) {
        if ($value === null || $value === '' || ($key === 'typ' && $value === 'vse') || ($key === 'scope' && $value === 'all')) {
            continue;
        }
        $query[$key] = $value;
    }

    return BASE_URL . '/food/archive.php' . ($query !== [] ? '?' . http_build_query($query) : '');
};

$scopeLinks = [
    [
        'key' => 'current',
        'label' => 'Platí nyní',
        'count' => $scopeCounts['current'],
        'active' => $scope === 'current',
        'url' => $buildFoodArchiveUrl(['scope' => 'current', 'strana' => null]),
    ],
    [
        'key' => 'upcoming',
        'label' => 'Připravujeme',
        'count' => $scopeCounts['upcoming'],
        'active' => $scope === 'upcoming',
        'url' => $buildFoodArchiveUrl(['scope' => 'upcoming', 'strana' => null]),
    ],
    [
        'key' => 'archive',
        'label' => 'Archivní',
        'count' => $scopeCounts['archive'],
        'active' => $scope === 'archive',
        'url' => $buildFoodArchiveUrl(['scope' => 'archive', 'strana' => null]),
    ],
    [
        'key' => 'all',
        'label' => 'Všechny lístky',
        'count' => $scopeCounts['all'],
        'active' => $scope === 'all',
        'url' => $buildFoodArchiveUrl(['scope' => 'all', 'strana' => null]),
    ],
];

$filterSummary = [];
if ($q !== '') {
    $filterSummary[] = 'hledání: „' . $q . '“';
}
if ($filterType !== 'vse') {
    $filterSummary[] = 'typ: ' . foodCardTypeLabel($filterType);
}

$pageHeading = match ($scope) {
    'current' => 'Právě platné lístky',
    'upcoming' => 'Připravované lístky',
    'archive' => 'Archiv lístků',
    default => 'Všechny jídelní a nápojové lístky',
};

$metaTitle = $pageHeading . ' - ' . $siteName;
if ($filterSummary !== []) {
    $metaTitle = $pageHeading . ' - ' . implode(', ', $filterSummary) . ' - ' . $siteName;
}

$pagerBase = $buildFoodArchiveUrl(['strana' => null]);
$pagerBase .= str_contains($pagerBase, '?') ? '&' : '?';

$listingQuery = ['scope' => $scope];
if ($q !== '') {
    $listingQuery['q'] = $q;
}
if ($filterType !== 'vse') {
    $listingQuery['typ'] = $filterType;
}
if ($page > 1) {
    $listingQuery['strana'] = (string)$page;
}

foreach ($cards as &$card) {
    if ((string)$card['validity_label'] === '') {
        $card['validity_label'] = 'Přidáno ' . formatCzechDate((string)$card['created_at']);
    }
    $card['listing_path'] = foodCardPublicPath($card, $listingQuery);
}
unset($card);

renderPublicPage([
    'title' => $metaTitle,
    'meta' => [
        'title' => $metaTitle,
        'url' => siteUrl(str_replace(BASE_URL, '', $buildFoodArchiveUrl(['strana' => null]))),
    ],
    'view' => 'modules/food-archive',
    'view_data' => [
        'cards' => $cards,
        'filterType' => $filterType,
        'searchQuery' => $q,
        'scope' => $scope,
        'scopeLinks' => $scopeLinks,
        'filterSummary' => $filterSummary,
        'resultCountLabel' => foodCardsCountLabel($totalItems),
        'pagerHtml' => renderPager($page, $pages, $pagerBase, 'Stránkování archivu lístků', 'Předchozí stránka', 'Další stránka'),
        'hasActiveFilters' => $q !== '' || $filterType !== 'vse' || $scope !== 'all',
        'clearUrl' => BASE_URL . '/food/archive.php',
        'pageHeading' => $pageHeading,
    ],
    'current_nav' => 'food',
    'body_class' => 'page-food-archive',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/food.php',
]);
