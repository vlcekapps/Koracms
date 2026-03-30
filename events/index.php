<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('events')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function eventsCountLabel(int $count): string
{
    if ($count === 1) {
        return '1 akce';
    }
    if ($count >= 2 && $count <= 4) {
        return $count . ' akce';
    }

    return $count . ' akcí';
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage = max(1, (int)getSetting('events_per_page', '10'));

$q = trim((string)($_GET['q'] ?? ''));
$locationFilter = trim((string)($_GET['misto'] ?? ''));
$typeFilter = trim((string)($_GET['typ'] ?? 'all'));
$periodFilter = trim((string)($_GET['period'] ?? 'all'));
$scope = trim((string)($_GET['scope'] ?? 'upcoming'));

$allowedScopes = ['upcoming', 'ongoing', 'past', 'all'];
if (!in_array($scope, $allowedScopes, true)) {
    $scope = 'upcoming';
}

$periodOptions = [
    'all' => 'Všechna období',
    'today' => 'Dnes',
    'this_week' => 'Tento týden',
    'this_month' => 'Tento měsíc',
    'next_30_days' => 'Následujících 30 dní',
    'this_year' => 'Tento rok',
];
if (!isset($periodOptions[$periodFilter])) {
    $periodFilter = 'all';
}
if ($typeFilter !== 'all' && !isset(eventKindDefinitions()[$typeFilter])) {
    $typeFilter = 'all';
}

$locationOptions = $pdo->query(
    "SELECT DISTINCT TRIM(location) AS location_label
     FROM cms_events
     WHERE " . eventPublicVisibilitySql() . "
       AND TRIM(COALESCE(location, '')) <> ''
     ORDER BY location_label"
)->fetchAll(PDO::FETCH_COLUMN);
$locationOptions = array_values(array_filter(array_map(static fn($value): string => trim((string)$value), $locationOptions)));
if ($locationFilter !== '' && !in_array($locationFilter, $locationOptions, true)) {
    $locationFilter = '';
}

$whereParts = [eventPublicVisibilitySql('e')];
$params = [];

if ($q !== '') {
    $whereParts[] = "(e.title LIKE ? OR e.excerpt LIKE ? OR e.description LIKE ? OR e.program_note LIKE ? OR e.location LIKE ? OR e.organizer_name LIKE ? OR e.price_note LIKE ?)";
    for ($i = 0; $i < 7; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($locationFilter !== '') {
    $whereParts[] = 'TRIM(e.location) = ?';
    $params[] = $locationFilter;
}

if ($typeFilter !== 'all') {
    $whereParts[] = 'e.event_kind = ?';
    $params[] = $typeFilter;
}

if ($periodFilter === 'today') {
    $whereParts[] = 'DATE(e.event_date) = CURDATE()';
} elseif ($periodFilter === 'this_week') {
    $whereParts[] = 'YEARWEEK(e.event_date, 3) = YEARWEEK(CURDATE(), 3)';
} elseif ($periodFilter === 'this_month') {
    $whereParts[] = "DATE_FORMAT(e.event_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
} elseif ($periodFilter === 'next_30_days') {
    $whereParts[] = 'e.event_date >= CURDATE() AND e.event_date < DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
} elseif ($periodFilter === 'this_year') {
    $whereParts[] = 'YEAR(e.event_date) = YEAR(CURDATE())';
}

$countEventsForScope = static function (string $scopeKey) use ($pdo, $whereParts, $params): int {
    $scopeWhereParts = $whereParts;
    if ($scopeKey !== 'all') {
        $scopeWhereParts[] = '(' . eventScopeVisibilitySql($scopeKey, 'e') . ')';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $scopeWhereParts);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cms_events e {$whereSql}");
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
};

$scopeCounts = [
    'upcoming' => $countEventsForScope('upcoming'),
    'ongoing' => $countEventsForScope('ongoing'),
    'past' => $countEventsForScope('past'),
    'all' => $countEventsForScope('all'),
];

$scopeWhereParts = $whereParts;
if ($scope !== 'all') {
    $scopeWhereParts[] = '(' . eventScopeVisibilitySql($scope, 'e') . ')';
}
$whereSql = 'WHERE ' . implode(' AND ', $scopeWhereParts);

$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_events e {$whereSql}",
    $params,
    $perPage
);
['total' => $totalItems, 'totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pagination;

$effectiveEndSql = eventEffectiveEndSql('e');
$orderSql = match ($scope) {
    'past' => "{$effectiveEndSql} DESC, e.event_date DESC, e.id DESC",
    'all' => "CASE
                WHEN e.event_date <= NOW() AND {$effectiveEndSql} >= NOW() THEN 0
                WHEN e.event_date > NOW() THEN 1
                ELSE 2
              END,
              CASE WHEN e.event_date > NOW() THEN e.event_date END ASC,
              CASE WHEN {$effectiveEndSql} < NOW() THEN {$effectiveEndSql} END DESC,
              e.id DESC",
    default => "e.event_date ASC, e.id DESC",
};

$stmt = $pdo->prepare(
    "SELECT e.*
     FROM cms_events e
     {$whereSql}
     ORDER BY {$orderSql}
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$items = array_map(
    static fn(array $event): array => hydrateEventPresentation($event),
    $stmt->fetchAll()
);

$buildEventsIndexUrl = static function (array $overrides = []) use ($q, $locationFilter, $typeFilter, $periodFilter, $scope): string {
    $query = [];
    $merged = array_merge([
        'q' => $q,
        'misto' => $locationFilter,
        'typ' => $typeFilter,
        'period' => $periodFilter,
        'scope' => $scope,
    ], $overrides);

    foreach ($merged as $key => $value) {
        if ($value === null || $value === '' || ($key === 'typ' && $value === 'all') || ($key === 'period' && $value === 'all') || ($key === 'scope' && $value === 'upcoming')) {
            continue;
        }
        $query[$key] = $value;
    }

    return BASE_URL . '/events/index.php' . ($query !== [] ? '?' . http_build_query($query) : '');
};

$scopeLinks = [
    [
        'key' => 'ongoing',
        'label' => 'Právě probíhá',
        'count' => $scopeCounts['ongoing'],
        'active' => $scope === 'ongoing',
        'url' => $buildEventsIndexUrl(['scope' => 'ongoing', 'strana' => null]),
    ],
    [
        'key' => 'upcoming',
        'label' => 'Připravované',
        'count' => $scopeCounts['upcoming'],
        'active' => $scope === 'upcoming',
        'url' => $buildEventsIndexUrl(['scope' => 'upcoming', 'strana' => null]),
    ],
    [
        'key' => 'past',
        'label' => 'Archiv akcí',
        'count' => $scopeCounts['past'],
        'active' => $scope === 'past',
        'url' => $buildEventsIndexUrl(['scope' => 'past', 'strana' => null]),
    ],
    [
        'key' => 'all',
        'label' => 'Všechny akce',
        'count' => $scopeCounts['all'],
        'active' => $scope === 'all',
        'url' => $buildEventsIndexUrl(['scope' => 'all', 'strana' => null]),
    ],
];

$filterSummary = [];
if ($q !== '') {
    $filterSummary[] = 'hledání: „' . $q . '“';
}
if ($locationFilter !== '') {
    $filterSummary[] = 'místo: ' . $locationFilter;
}
if ($typeFilter !== 'all') {
    $filterSummary[] = 'typ: ' . (string)(eventKindDefinitions()[$typeFilter]['label'] ?? $typeFilter);
}
if ($periodFilter !== 'all') {
    $filterSummary[] = 'období: ' . (string)$periodOptions[$periodFilter];
}

$pageHeading = match ($scope) {
    'ongoing' => 'Právě probíhající akce',
    'past' => 'Archiv akcí',
    'all' => 'Všechny akce',
    default => 'Připravované akce',
};

$metaTitle = $pageHeading . ' - ' . $siteName;
if ($filterSummary !== []) {
    $metaTitle = $pageHeading . ' - ' . implode(', ', $filterSummary) . ' - ' . $siteName;
}

$pagerBase = $buildEventsIndexUrl(['strana' => null]);
$pagerBase .= str_contains($pagerBase, '?') ? '&' : '?';

$listingQuery = [];
foreach ([
    'q' => $q,
    'misto' => $locationFilter,
    'typ' => $typeFilter,
    'period' => $periodFilter,
    'scope' => $scope,
    'strana' => $page > 1 ? (string)$page : '',
] as $key => $value) {
    if ($value === '' || ($key === 'typ' && $value === 'all') || ($key === 'period' && $value === 'all') || ($key === 'scope' && $value === 'upcoming')) {
        continue;
    }
    $listingQuery[$key] = $value;
}

renderPublicPage([
    'title' => $metaTitle,
    'meta' => [
        'title' => $metaTitle,
        'url' => siteUrl(str_replace(BASE_URL, '', $buildEventsIndexUrl(['strana' => null]))),
    ],
    'view' => 'modules/events-index',
    'view_data' => [
        'items' => $items,
        'scope' => $scope,
        'scopeLinks' => $scopeLinks,
        'searchQuery' => $q,
        'locationOptions' => $locationOptions,
        'selectedLocation' => $locationFilter,
        'selectedType' => $typeFilter,
        'selectedPeriod' => $periodFilter,
        'periodOptions' => $periodOptions,
        'filterSummary' => $filterSummary,
        'resultCountLabel' => eventsCountLabel($totalItems),
        'pagerHtml' => renderPager($page, $pages, $pagerBase, 'Stránkování akcí', 'Předchozí stránka', 'Další stránka'),
        'hasActiveFilters' => $q !== '' || $locationFilter !== '' || $typeFilter !== 'all' || $periodFilter !== 'all' || $scope !== 'upcoming',
        'clearUrl' => BASE_URL . '/events/index.php',
        'pageHeading' => $pageHeading,
        'listingQuery' => $listingQuery,
    ],
    'current_nav' => 'events',
    'body_class' => 'page-events-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/events.php',
]);
