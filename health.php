<?php

require_once __DIR__ . '/db.php';

sendOperationalJsonHeaders();

$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    echo json_encode(
        [
            'status' => 'method_not_allowed',
            'request_id' => koraRequestId(),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}
$isHeadRequest = $requestMethod === 'HEAD';

$checks = [
    'database' => ['status' => 'fail'],
    'storage' => ['status' => 'fail'],
    'backup' => ['status' => 'unknown'],
    'cron' => ['status' => 'unknown'],
];

try {
    db_connect()->query('SELECT 1');
    $checks['database']['status'] = 'ok';
} catch (\Throwable $e) {
    $checks['database']['status'] = 'fail';
}

$storageDirectory = koraStorageDirectory();
if (koraEnsureDirectory($storageDirectory) && is_writable($storageDirectory)) {
    $checks['storage']['status'] = 'ok';
}

$backupDirectory = rtrim(koraStoragePath('backups'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$backupFiles = glob($backupDirectory . 'kora_backup_*.sql') ?: [];
$latestBackupTimestamp = 0;
foreach ($backupFiles as $backupFile) {
    if (!is_file($backupFile)) {
        continue;
    }

    $fileTimestamp = (int)@filemtime($backupFile);
    if ($fileTimestamp > $latestBackupTimestamp) {
        $latestBackupTimestamp = $fileTimestamp;
    }
}
if ($latestBackupTimestamp > 0) {
    $checks['backup'] = [
        'status' => $latestBackupTimestamp >= time() - (2 * 86400) ? 'ok' : 'stale',
        'last_backup' => date(DATE_ATOM, $latestBackupTimestamp),
    ];
}

$cronLastRun = getSetting('cron_last_run_at', '');
if ($cronLastRun !== '') {
    $cronLastRunTimestamp = strtotime($cronLastRun);
    if ($cronLastRunTimestamp !== false) {
        $checks['cron'] = [
            'status' => $cronLastRunTimestamp >= time() - 86400 ? 'ok' : 'stale',
            'last_run' => date(DATE_ATOM, $cronLastRunTimestamp),
        ];
    }
}

$overallStatus = 'ok';
foreach (['database', 'storage'] as $requiredCheck) {
    if ($checks[$requiredCheck]['status'] !== 'ok') {
        $overallStatus = 'fail';
    }
}

http_response_code($overallStatus === 'ok' ? 200 : 503);
if ($isHeadRequest) {
    exit;
}
echo json_encode(
    [
        'status' => $overallStatus,
        'version' => KORA_VERSION,
        'request_id' => koraRequestId(),
        'time' => date(DATE_ATOM),
        'checks' => $checks,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
