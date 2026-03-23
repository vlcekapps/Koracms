<?php
require_once __DIR__ . '/../db.php';
header('Cache-Control: no-cache, no-store, must-revalidate');
checkMaintenanceMode();

if (!isModuleEnabled('reservations')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$slotModeLabels = [
    'slots' => 'Pevné časy',
    'range' => 'Volný rozsah',
    'duration' => 'Pevná délka',
];

$resources = $pdo->query(
    "SELECT r.*, c.name AS category_name, c.sort_order AS cat_sort
     FROM cms_res_resources r
     LEFT JOIN cms_res_categories c ON c.id = r.category_id
     WHERE r.is_active = 1
     ORDER BY c.sort_order IS NULL, c.sort_order, c.name, r.name"
)->fetchAll();

$locationsByResource = [];
$resourceIds = array_map(static fn(array $resource): int => (int)$resource['id'], $resources);
if (!empty($resourceIds)) {
    $placeholders = implode(',', array_fill(0, count($resourceIds), '?'));
    $locStmt = $pdo->prepare(
        "SELECT rl.resource_id, l.name
         FROM cms_res_resource_locations rl
         JOIN cms_res_locations l ON l.id = rl.location_id
         WHERE rl.resource_id IN ({$placeholders})
         ORDER BY l.name"
    );
    $locStmt->execute($resourceIds);
    foreach ($locStmt->fetchAll() as $locationRow) {
        $locationsByResource[(int)$locationRow['resource_id']][] = $locationRow['name'];
    }
}

$grouped = [];
foreach ($resources as $resource) {
    $resource['excerpt'] = mb_strlen($resource['description'] ?? '', 'UTF-8') > 200
        ? mb_substr($resource['description'], 0, 200, 'UTF-8') . '...'
        : ($resource['description'] ?? '');
    $resource['mode_label'] = $slotModeLabels[$resource['slot_mode']] ?? $resource['slot_mode'];
    $resource['location_names'] = $locationsByResource[(int)$resource['id']] ?? [];

    $categoryName = $resource['category_name'] ?? null;
    $groupKey = $categoryName !== null ? $categoryName : '__uncategorized__';
    $grouped[$groupKey][] = $resource;
}

$sections = [];
$sectionIndex = 0;
foreach ($grouped as $groupKey => $items) {
    $label = $groupKey === '__uncategorized__' ? 'Ostatní' : $groupKey;
    $sections[] = [
        'label' => $label,
        'heading_id' => 'reservations-group-' . $sectionIndex,
        'show_heading' => count($grouped) > 1 || $groupKey !== '__uncategorized__',
        'items' => $items,
    ];
    $sectionIndex++;
}

renderPublicPage([
    'title' => 'Rezervace – ' . $siteName,
    'meta' => [
        'title' => 'Rezervace – ' . $siteName,
        'url' => BASE_URL . '/reservations/index.php',
    ],
    'view' => 'modules/reservations-index',
    'view_data' => [
        'sections' => $sections,
    ],
    'current_nav' => 'reservations',
    'body_class' => 'page-reservations-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/res_resources.php',
]);
