<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('places')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$places = $pdo->query(
    "SELECT * FROM cms_places WHERE status = 'published' AND is_published = 1 ORDER BY sort_order, name"
)->fetchAll();

$grouped = [];
foreach ($places as $place) {
    $grouped[$place['category'] ?: 'Ostatní'][] = $place;
}
ksort($grouped);

renderPublicPage([
    'title' => 'Zajímavá místa – ' . $siteName,
    'meta' => [
        'title' => 'Zajímavá místa – ' . $siteName,
        'url' => BASE_URL . '/places/index.php',
    ],
    'view' => 'modules/places-index',
    'view_data' => [
        'places' => $places,
        'grouped' => $grouped,
    ],
    'current_nav' => 'places',
    'body_class' => 'page-places-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/places.php',
]);
