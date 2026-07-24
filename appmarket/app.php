<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('appmarket')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$slug = appmarketAppSlug((string)($_GET['slug'] ?? ''));
$pdo = db_connect();
$app = appmarketFindPublicAppBySlug($pdo, $slug);
if ($app === null) {
    renderPublicNotFoundPage([
        'title' => 'Aplikace nenalezena',
        'meta' => ['url' => appmarketAppUrl($slug)],
        'body_class' => 'page-appmarket-not-found',
    ]);
}

$releaseStmt = $pdo->prepare(
    "SELECT r.*
     FROM cms_appmarket_releases r
     WHERE r.app_id = ?
       AND " . appmarketReleasePublicVisibilitySql('r') . "
     ORDER BY r.version_code DESC, r.id DESC"
);
$releaseStmt->execute([(int)$app['id']]);
$releases = array_map(
    static fn (array $release): array => appmarketHydrateReleasePresentation($release),
    $releaseStmt->fetchAll()
);
$latestRelease = $releases[0] ?? null;

$screenshotStmt = $pdo->prepare(
    "SELECT s.*, m.filename, m.original_name, m.mime_type, m.visibility,
            COALESCE(NULLIF(s.alt_text, ''), m.alt_text) AS resolved_alt_text
     FROM cms_appmarket_screenshots s
     INNER JOIN cms_media m ON m.id = s.media_id
     WHERE s.app_id = ?
       AND m.visibility = 'public'
       AND m.mime_type LIKE 'image/%'
     ORDER BY s.sort_order, s.id
     LIMIT 12"
);
$screenshotStmt->execute([(int)$app['id']]);
$screenshots = [];
foreach ($screenshotStmt->fetchAll() as $screenshot) {
    $media = [
        'id' => (int)$screenshot['media_id'],
        'filename' => (string)$screenshot['filename'],
        'original_name' => (string)$screenshot['original_name'],
        'mime_type' => (string)$screenshot['mime_type'],
        'visibility' => (string)$screenshot['visibility'],
    ];
    $screenshot['url'] = mediaFileUrl($media);
    $screenshot['alt'] = trim((string)$screenshot['resolved_alt_text']);
    if ($screenshot['alt'] === '') {
        continue;
    }
    $screenshots[] = $screenshot;
}

if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('appmarket_app', (int)$app['id']);
}

$siteName = getSetting('site_name', 'Kora CMS');
renderPublicPage([
    'title' => (string)$app['name'] . ' - ' . $siteName,
    'meta' => [
        'title' => (string)$app['name'] . ' - ' . $siteName,
        'description' => (string)$app['short_description'],
        'url' => appmarketAppUrl($app),
        'type' => 'article',
    ],
    'view' => 'modules/appmarket-app',
    'view_data' => [
        'app' => $app,
        'latestRelease' => $latestRelease,
        'releases' => $releases,
        'screenshots' => $screenshots,
    ],
    'current_nav' => 'appmarket',
    'body_class' => 'page-appmarket-app',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/appmarket_form.php?id=' . (int)$app['id'],
]);
