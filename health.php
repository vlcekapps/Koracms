<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

$checks = [
    'database' => ['status' => 'fail'],
    'storage' => ['status' => 'fail'],
    'backup' => ['status' => 'unknown'],
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
    $checks['backup']['status'] = $latestBackupTimestamp >= time() - (2 * 86400) ? 'ok' : 'stale';
}

$overallStatus = 'ok';
foreach (['database', 'storage'] as $requiredCheck) {
    if (($checks[$requiredCheck]['status'] ?? 'fail') !== 'ok') {
        $overallStatus = 'fail';
    }
}

http_response_code($overallStatus === 'ok' ? 200 : 503);
echo json_encode(
    [
        'status' => $overallStatus,
        'version' => KORA_VERSION,
        'time' => date(DATE_ATOM),
        'checks' => $checks,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
