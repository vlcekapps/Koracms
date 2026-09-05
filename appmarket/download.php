<?php

require_once __DIR__ . '/../db.php';

$isHeadRequest = requireReadOnlyHttpMethod();
session_write_close();
header_remove('Set-Cookie');
if (!isModuleEnabled('appmarket')) {
    sendFileDownloadNotFound('Soubor nebyl nalezen.', $isHeadRequest);
}

$slug = appmarketAppSlug((string)($_GET['slug'] ?? ''));
$versionCode = filter_var($_GET['version_code'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$pdo = db_connect();
$app = appmarketFindPublicAppBySlug($pdo, $slug);
$release = $app !== null && $versionCode !== false
    ? appmarketFindPublicRelease($pdo, (int)$app['id'], (int)$versionCode)
    : null;
if ($app === null || $release === null) {
    sendFileDownloadNotFound('Soubor nebyl nalezen.', $isHeadRequest);
}

$path = appmarketReleaseFilePath($release);
$fileReadable = appmarketPrivateStorageIsSafe() && $path !== '' && is_file($path) && is_readable($path);
$actualSize = $fileReadable ? filesize($path) : false;
$actualHash = $fileReadable ? hash_file('sha256', $path) : false;
if (!$fileReadable
    || !is_int($actualSize)
    || $actualSize <= 0
    || $actualSize !== (int)$release['file_size']
    || !is_string($actualHash)
    || !hash_equals((string)$release['file_sha256'], strtolower($actualHash))
) {
    koraLog('warning', 'appmarket public release file is missing or invalid', [
        'app_id' => (int)$app['id'],
        'release_id' => (int)$release['id'],
    ]);
    sendFileDownloadNotFound('Soubor nebyl nalezen.', $isHeadRequest);
}

$rangeHeader = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
if (!$isHeadRequest && ($rangeHeader === '' || str_starts_with($rangeHeader, 'bytes=0-'))) {
    $pdo->prepare(
        "UPDATE cms_appmarket_releases
         SET download_count = download_count + 1
         WHERE id = ? AND status = 'published'"
    )->execute([(int)$release['id']]);
}

$downloadName = appmarketReleaseDownloadName($app, $release);
$mimeType = strtolower((string)$release['file_extension']) === 'apk'
    ? 'application/vnd.android.package-archive'
    : 'application/octet-stream';
sendStoredFileRangeDownload(
    $path,
    $downloadName,
    $isHeadRequest,
    $mimeType,
    (string)$release['file_sha256']
);
