<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('appmarket')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$slug = appmarketAppSlug((string)($_GET['slug'] ?? ''));
$versionCode = filter_var($_GET['version_code'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$pdo = db_connect();
$app = appmarketFindPublicAppBySlug($pdo, $slug);
$release = $app !== null && $versionCode !== false
    ? appmarketFindPublicRelease($pdo, (int)$app['id'], (int)$versionCode)
    : null;
if ($app === null || $release === null) {
    renderPublicNotFoundPage([
        'title' => 'Vydání nenalezeno',
        'meta' => [
            'url' => $versionCode !== false
                ? siteUrl(str_replace(BASE_URL, '', appmarketReleasePath($slug, (int)$versionCode)))
                : appmarketAppUrl($slug),
        ],
        'body_class' => 'page-appmarket-release-not-found',
    ]);
}

$latestRelease = appmarketLatestPublishedRelease($pdo, (int)$app['id']);
if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('appmarket_release', (int)$release['id']);
}
$siteName = getSetting('site_name', 'Kora CMS');
renderPublicPage([
    'title' => (string)$app['name'] . ' ' . (string)$release['version_name'] . ' - ' . $siteName,
    'meta' => [
        'title' => (string)$app['name'] . ' ' . (string)$release['version_name'] . ' - ' . $siteName,
        'description' => 'Vydání Android aplikace ' . (string)$app['name'] . ', SHA-256 a seznam změn.',
        'url' => siteUrl(str_replace(
            BASE_URL,
            '',
            appmarketReleasePath($app, (int)$release['version_code'])
        )),
        'type' => 'article',
    ],
    'view' => 'modules/appmarket-release',
    'view_data' => [
        'app' => $app,
        'release' => $release,
        'latestRelease' => $latestRelease,
    ],
    'current_nav' => 'appmarket',
    'body_class' => 'page-appmarket-release',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/appmarket.php?app_id=' . (int)$app['id'],
]);
