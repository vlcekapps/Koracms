<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('downloads')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$items = $pdo->query(
    "SELECT d.id, d.title, d.slug, d.download_type, d.dl_category_id, COALESCE(c.name, '') AS category_name,
            d.excerpt, d.description, d.image_file, d.version_label, d.platform_label, d.license_label,
            d.external_url, d.filename, d.original_name, d.file_size, d.created_at, d.updated_at
     FROM cms_downloads d
     LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
     WHERE d.status = 'published' AND d.is_published = 1
     ORDER BY c.name, d.created_at DESC, d.id DESC"
)->fetchAll();

$items = array_map(
    static fn(array $download): array => hydrateDownloadPresentation($download),
    $items
);

$grouped = [];
foreach ($items as $item) {
    $grouped[$item['category_name'] ?: 'Ostatní'][] = $item;
}
ksort($grouped);

renderPublicPage([
    'title' => 'Ke stažení – ' . $siteName,
    'meta' => [
        'title' => 'Ke stažení – ' . $siteName,
        'url' => BASE_URL . '/downloads/index.php',
    ],
    'view' => 'modules/downloads-index',
    'view_data' => [
        'items' => $items,
        'grouped' => $grouped,
        'showCategoryHeadings' => count($grouped) > 1,
    ],
    'current_nav' => 'downloads',
    'body_class' => 'page-downloads-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/downloads.php',
]);
