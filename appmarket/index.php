<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('appmarket')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$query = trim((string)($_GET['q'] ?? ''));
$params = [];
$where = [appmarketAppPublicVisibilitySql('a')];
if ($query !== '') {
    $where[] = '(a.name LIKE ? OR a.short_description LIKE ? OR a.description LIKE ? OR a.package_id LIKE ?)';
    for ($index = 0; $index < 4; $index++) {
        $params[] = '%' . $query . '%';
    }
}

$stmt = $pdo->prepare(
    "SELECT a.*,
            m.filename AS icon_filename,
            m.original_name AS icon_original_name,
            m.mime_type AS icon_mime_type,
            m.visibility AS icon_visibility,
            m.alt_text AS icon_alt_text
     FROM cms_appmarket_apps a
     LEFT JOIN cms_media m ON m.id = a.icon_media_id
     WHERE " . implode(' AND ', $where) . "
       AND EXISTS (
         SELECT 1
         FROM cms_appmarket_releases r
         WHERE r.app_id = a.id
           AND " . appmarketReleasePublicVisibilitySql('r') . "
       )
     ORDER BY a.is_featured DESC, a.sort_order, a.name, a.id"
);
$stmt->execute($params);
$apps = array_map(
    static function (array $app) use ($pdo): array {
        $app = appmarketHydrateAppPresentation($app);
        $app['preferred_releases'] = appmarketPreferredPublicReleasesByPlatform($pdo, (int)$app['id']);
        return $app;
    },
    $stmt->fetchAll()
);

renderPublicPage([
    'title' => 'Katalog softwaru - ' . $siteName,
    'meta' => [
        'title' => 'Katalog softwaru - ' . $siteName,
        'description' => 'Software ke stažení podle platformy, systémové požadavky, historie verzí a kontrolní součty souborů.',
        'url' => siteUrl('/aplikace'),
    ],
    'view' => 'modules/appmarket-index',
    'view_data' => [
        'apps' => $apps,
        'query' => $query,
    ],
    'current_nav' => 'appmarket',
    'body_class' => 'page-appmarket-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/appmarket.php',
]);
