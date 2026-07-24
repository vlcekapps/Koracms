<?php

require_once __DIR__ . '/../db.php';

$isHeadRequest = requireReadOnlyHttpMethod();
session_write_close();
header_remove('Set-Cookie');
if (!isModuleEnabled('appmarket')) {
    sendFileDownloadNotFound('APK nebyl nalezen.', $isHeadRequest);
}

$slug = appmarketAppSlug((string)($_GET['slug'] ?? ''));
$versionCode = filter_var($_GET['version_code'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$pdo = db_connect();
$app = appmarketFindPublicAppBySlug($pdo, $slug);
$release = $app !== null && $versionCode !== false
    ? appmarketFindPublicRelease($pdo, (int)$app['id'], (int)$versionCode)
    : null;
if ($app === null || $release === null) {
    sendFileDownloadNotFound('APK nebyl nalezen.', $isHeadRequest);
}

$path = appmarketPrivateApkPath((string)$release['apk_storage_name']);
$actualSize = $path !== '' && is_file($path) ? filesize($path) : false;
$actualHash = $path !== '' && is_file($path) ? hash_file('sha256', $path) : false;
if (!appmarketPrivateStorageIsSafe()
    || $path === ''
    || !is_file($path)
    || !is_readable($path)
    || !is_int($actualSize)
    || $actualSize !== (int)$release['apk_size']
    || !is_string($actualHash)
    || !hash_equals((string)$release['apk_sha256'], strtolower($actualHash))
) {
    koraLog('warning', 'appmarket public APK is missing', [
        'app_id' => (int)$app['id'],
        'release_id' => (int)$release['id'],
    ]);
    sendFileDownloadNotFound('APK nebyl nalezen.', $isHeadRequest);
}

$rangeHeader = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
if (!$isHeadRequest && ($rangeHeader === '' || str_starts_with($rangeHeader, 'bytes=0-'))) {
    $pdo->prepare(
        "UPDATE cms_appmarket_releases
         SET download_count = download_count + 1
         WHERE id = ? AND status = 'published'"
    )->execute([(int)$release['id']]);
}

$downloadName = appmarketAppSlug((string)$app['slug'])
    . '-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$release['version_name']) . '.apk';
sendStoredFileRangeDownload(
    $path,
    $downloadName,
    $isHeadRequest,
    'application/vnd.android.package-archive',
    (string)$release['apk_sha256']
);
