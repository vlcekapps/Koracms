<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('board')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function groupBoardByCategory(array $items): array
{
    $grouped = [];
    foreach ($items as $item) {
        $grouped[$item['category_name'] ?: 'Ostatní'][] = $item;
    }
    ksort($grouped);
    return $grouped;
}

function boardCountLabel(int $count): string
{
    return $count . ' ' . ($count === 1 ? 'položka' : ($count < 5 ? 'položky' : 'položek'));
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$boardLabel = boardModulePublicLabel();
$archiveTitle = boardModuleArchiveTitle();
$allItemsLabel = boardModuleAllItemsLabel();
$emptyState = boardModuleListingEmptyState();
$perPage = max(1, (int)getSetting('board_per_page', '10'));

$q = trim((string)($_GET['q'] ?? ''));
$categoryId = inputInt('get', 'kat');
$monthFilter = trim((string)($_GET['month'] ?? ''));
$scope = trim((string)($_GET['scope'] ?? 'current'));
$allowedScopes = ['current', 'archive', 'all'];
if (!in_array($scope, $allowedScopes, true)) {
    $scope = 'current';
}
if (!preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
    $monthFilter = '';
}

$categories = $pdo->query("SELECT id, name FROM cms_board_categories ORDER BY sort_order, name")->fetchAll();
$validCategoryIds = array_map(static fn(array $category): int => (int)$category['id'], $categories);
if ($categoryId !== null && !in_array($categoryId, $validCategoryIds, true)) {
    $categoryId = null;
}

$monthOptions = [];
try {
    $monthStmt = $pdo->query(
        "SELECT DATE_FORMAT(posted_date, '%Y-%m') AS month_key, COUNT(*) AS item_count
         FROM cms_board
         WHERE " . boardPublicVisibilitySql() . "
         GROUP BY month_key
         ORDER BY month_key DESC"
    );
    foreach ($monthStmt->fetchAll() as $monthRow) {
        $monthKey = (string)($monthRow['month_key'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            continue;
        }
        $monthDate = DateTimeImmutable::createFromFormat('Y-m', $monthKey);
        $monthOptions[] = [
            'key' => $monthKey,
            'label' => $monthDate ? formatCzechMonthYear($monthDate) : $monthKey,
            'count' => (int)($monthRow['item_count'] ?? 0),
        ];
    }
} catch (\PDOException $e) {
    error_log('board/index months: ' . $e->getMessage());
}

$whereParts = [boardPublicVisibilitySql('b')];
$params = [];

if ($q !== '') {
    $whereParts[] = "(b.title LIKE ? OR b.excerpt LIKE ? OR b.description LIKE ? OR b.original_name LIKE ?
        OR b.contact_name LIKE ? OR b.contact_phone LIKE ? OR b.contact_email LIKE ? OR COALESCE(c.name, '') LIKE ?)";
    for ($i = 0; $i < 8; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($categoryId !== null) {
    $whereParts[] = 'b.category_id = ?';
    $params[] = $categoryId;
}

if ($monthFilter !== '') {
    $monthStart = $monthFilter . '-01';
    $monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month'));
    $whereParts[] = 'b.posted_date >= ? AND b.posted_date < ?';
    $params[] = $monthStart;
    $params[] = $monthEnd;
}

$countBoardItemsForScope = static function (string $scopeKey) use ($pdo, $whereParts, $params): int {
    $scopeWhereParts = $whereParts;
    if ($scopeKey !== 'all') {
        $scopeWhereParts[] = '(' . boardScopeVisibilitySql($scopeKey, 'b') . ')';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $scopeWhereParts);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM cms_board b
         LEFT JOIN cms_board_categories c ON c.id = b.category_id
         {$whereSql}"
    );
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
};

$scopeCounts = [
    'current' => $countBoardItemsForScope('current'),
    'archive' => $countBoardItemsForScope('archive'),
    'all' => $countBoardItemsForScope('all'),
];

$scopeWhereParts = $whereParts;
if ($scope !== 'all') {
    $scopeWhereParts[] = '(' . boardScopeVisibilitySql($scope, 'b') . ')';
}
$whereSql = 'WHERE ' . implode(' AND ', $scopeWhereParts);

$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_board b LEFT JOIN cms_board_categories c ON c.id = b.category_id {$whereSql}",
    $params,
    $perPage
);
['total' => $totalItems, 'totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pagination;

$stmt = $pdo->prepare(
    "SELECT b.*, COALESCE(c.name, '') AS category_name
     FROM cms_board b
     LEFT JOIN cms_board_categories c ON c.id = b.category_id
     {$whereSql}
     ORDER BY b.is_pinned DESC, b.posted_date DESC, b.created_at DESC, b.title
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$items = array_map(
    static fn(array $document): array => hydrateBoardPresentation($document),
    $stmt->fetchAll()
);

$itemsGrouped = groupBoardByCategory($items);
$showCategoryHeadings = count($itemsGrouped) > 1;

$buildBoardIndexUrl = static function (array $overrides = []) use ($q, $categoryId, $monthFilter, $scope): string {
    $query = [];
    $merged = array_merge([
        'q' => $q,
        'kat' => $categoryId,
        'month' => $monthFilter,
        'scope' => $scope,
    ], $overrides);

    foreach ($merged as $key => $value) {
        if ($value === null || $value === '' || ($key === 'scope' && $value === 'current')) {
            continue;
        }
        $query[$key] = $value;
    }

    return BASE_URL . '/board/index.php' . ($query !== [] ? '?' . http_build_query($query) : '');
};

$scopeLinks = [
    [
        'key' => 'current',
        'label' => $boardLabel,
        'count' => $scopeCounts['current'],
        'active' => $scope === 'current',
        'url' => $buildBoardIndexUrl(['scope' => 'current', 'strana' => null]),
    ],
    [
        'key' => 'archive',
        'label' => $archiveTitle,
        'count' => $scopeCounts['archive'],
        'active' => $scope === 'archive',
        'url' => $buildBoardIndexUrl(['scope' => 'archive', 'strana' => null]),
    ],
    [
        'key' => 'all',
        'label' => $allItemsLabel,
        'count' => $scopeCounts['all'],
        'active' => $scope === 'all',
        'url' => $buildBoardIndexUrl(['scope' => 'all', 'strana' => null]),
    ],
];

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
if ($monthFilter !== '') {
    foreach ($monthOptions as $monthOption) {
        if ((string)$monthOption['key'] === $monthFilter) {
            $filterSummary[] = 'období: ' . (string)$monthOption['label'];
            break;
        }
    }
}

$pageHeading = match ($scope) {
    'archive' => $archiveTitle,
    'all' => $allItemsLabel,
    default => $boardLabel,
};

$metaTitle = $pageHeading . ' - ' . $siteName;
if ($filterSummary !== []) {
    $metaTitle = $pageHeading . ' - ' . implode(', ', $filterSummary) . ' - ' . $siteName;
}

$paginBase = $buildBoardIndexUrl(['strana' => null]);
$paginBase .= str_contains($paginBase, '?') ? '&' : '?';

renderPublicPage([
    'title' => $metaTitle,
    'meta' => [
        'title' => $metaTitle,
        'url' => siteUrl(str_replace(BASE_URL, '', $buildBoardIndexUrl(['strana' => null]))),
    ],
    'view' => 'modules/board-index',
    'view_data' => [
        'boardLabel' => $boardLabel,
        'pageHeading' => $pageHeading,
        'items' => $items,
        'itemsGrouped' => $itemsGrouped,
        'showCategoryHeadings' => $showCategoryHeadings,
        'emptyState' => $emptyState,
        'scope' => $scope,
        'scopeLinks' => $scopeLinks,
        'searchQuery' => $q,
        'selectedCategoryId' => $categoryId,
        'selectedMonth' => $monthFilter,
        'categories' => $categories,
        'monthOptions' => $monthOptions,
        'filterSummary' => $filterSummary,
        'resultCountLabel' => boardCountLabel($totalItems),
        'pagerHtml' => renderPager($page, $pages, $paginBase, 'Stránkování sekce ' . $boardLabel, 'Předchozí stránka', 'Další stránka'),
        'hasActiveFilters' => $q !== '' || $categoryId !== null || $monthFilter !== '' || $scope !== 'current',
        'clearUrl' => BASE_URL . '/board/index.php',
    ],
    'current_nav' => 'board',
    'body_class' => 'page-board-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/board.php',
]);
