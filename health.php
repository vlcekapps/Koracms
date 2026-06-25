<?php

require_once __DIR__ . '/db.php';

sendOperationalJsonHeaders();

$requestMethod = requireJsonHttpMethods(['GET', 'HEAD']);
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

$statusCode = $overallStatus === 'ok' ? 200 : 503;
http_response_code($statusCode);
if ($isHeadRequest) {
    exit;
}
sendJsonResponse(
    [
        'status' => $overallStatus,
        'version' => KORA_VERSION,
        'time' => date(DATE_ATOM),
        'checks' => $checks,
    ],
    $statusCode
);
