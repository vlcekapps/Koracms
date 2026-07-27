<?php

require_once __DIR__ . '/../db.php';

$isHeadRequest = requireReadOnlyHttpMethod();
session_write_close();
header_remove('Set-Cookie');
if (!isModuleEnabled('appmarket')) {
    appmarketSendJson(404, ['error' => 'appmarket_disabled'], false, $isHeadRequest);
}

$packageId = appmarketNormalizePackageId((string)($_GET['package_id'] ?? ''));
$versionCode = appmarketNormalizeVersionCode($_GET['version_code'] ?? null);
$sdkLevel = appmarketNormalizeSdkLevel($_GET['sdk_int'] ?? null);
$rawChannel = strtolower(trim((string)($_GET['channel'] ?? 'stable')));
$channel = appmarketNormalizeReleaseChannel($rawChannel);
$rawDeviceAbi = trim((string)($_GET['abi'] ?? ''));
$deviceAbi = appmarketNormalizeAbi($rawDeviceAbi);
$rawRolloutBucket = trim((string)($_GET['rollout_bucket'] ?? ''));
$rolloutBucket = appmarketNormalizeRolloutBucket($rawRolloutBucket);
if ($packageId === ''
    || $versionCode === null
    || $sdkLevel === null
    || !array_key_exists($rawChannel, appmarketReleaseChannelDefinitions())
    || ($rawDeviceAbi !== '' && $deviceAbi === '')
    || ($rawRolloutBucket !== '' && $rolloutBucket === null)
) {
    appmarketSendJson(400, [
        'error' => 'invalid_request',
        'message' => 'Zadejte platné package_id, version_code, sdk_int, channel, ABI a rollout_bucket 0 až 99.',
    ], false, $isHeadRequest);
}

$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT *
     FROM cms_appmarket_apps
     WHERE package_id = ?
       AND " . appmarketAppPublicVisibilitySql('cms_appmarket_apps') . '
     LIMIT 1'
);
$stmt->execute([$packageId]);
$app = $stmt->fetch();
if (!is_array($app)) {
    appmarketSendJson(404, ['error' => 'app_not_found'], false, $isHeadRequest);
}

$release = appmarketLatestV3UpdateRelease(
    $pdo,
    (int)$app['id'],
    $sdkLevel,
    $versionCode,
    $channel,
    $deviceAbi,
    $rolloutBucket
);
$currentRelease = appmarketFindPublishedReleaseByVersionCode(
    $pdo,
    (int)$app['id'],
    $versionCode
);
$payload = appmarketUpdatePayloadV3(
    $app,
    $release,
    $versionCode,
    $sdkLevel,
    $channel,
    $deviceAbi,
    $rolloutBucket,
    $currentRelease
);
$encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$etag = '"' . hash('sha256', is_string($encoded) ? $encoded : '') . '"';
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    header('Cache-Control: public, max-age=300');
    header('ETag: ' . $etag);
    sendNoSniffHeader();
    exit;
}
header('ETag: ' . $etag);
appmarketSendJson(200, $payload, true, $isHeadRequest);
