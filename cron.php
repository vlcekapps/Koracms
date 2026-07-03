<?php

/**
 * Cron endpoint – plánované úlohy CMS.
 *
 * Spouštějte pravidelně (např. každých 5 minut) přes systémový cron:
 *   php /cesta/k/cron.php
 * nebo přes HTTP s tokenem:
 *   curl https://vas-web.cz/cron.php?token=VAS_CRON_TOKEN
 *
 * Token nastavte v config.php:
 *   define('CRON_TOKEN', 'nahodny-retezec');
 */
require_once __DIR__ . '/db.php';

function cronTempDirectory(): string
{
    return __DIR__ . '/uploads/tmp/';
}

function cronBackupDirectory(): string
{
    return rtrim(koraStoragePath('backups'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
}

function cronLogDirectory(): string
{
    return rtrim(koraStoragePath('logs'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
}

/**
 * @param array<string,mixed> $context
 */
function cronLogFilesystemFailure(string $operation, string $path, array $context = []): void
{
    koraLog('warning', 'cron filesystem operation failed', array_merge([
        'operation' => $operation,
        'path_hash' => hash('sha256', str_replace('\\', '/', $path)),
        'file_extension' => strtolower((string)pathinfo($path, PATHINFO_EXTENSION)),
    ], $context));
}

/**
 * @param callable(): bool $operation
 */
function cronRunFilesystemOperation(callable $operation): bool
{
    set_error_handler(static fn (): bool => true);
    try {
        return (bool)$operation();
    } finally {
        restore_error_handler();
    }
}

function cronFileMtime(string $path, string $operation): ?int
{
    set_error_handler(static fn (): bool => true);
    try {
        $mtime = filemtime($path);
    } finally {
        restore_error_handler();
    }

    if ($mtime === false) {
        cronLogFilesystemFailure($operation, $path);
        return null;
    }

    return (int)$mtime;
}

function cronDeleteFile(string $path, string $operation): bool
{
    if ($path === '' || !is_file($path)) {
        return true;
    }

    if (cronRunFilesystemOperation(static fn (): bool => unlink($path))) {
        return true;
    }

    cronLogFilesystemFailure($operation, $path);
    return false;
}

/**
 * @param list<string> $patterns
 */
function cronDeleteOldFiles(string $directory, array $patterns, int $maxAgeSeconds): int
{
    if ($maxAgeSeconds <= 0 || !is_dir($directory)) {
        return 0;
    }

    $deletedFiles = 0;
    $cutoff = time() - $maxAgeSeconds;
    foreach ($patterns as $pattern) {
        foreach (glob($directory . $pattern) ?: [] as $filePath) {
            if (!is_file($filePath)) {
                continue;
            }
            $mtime = cronFileMtime($filePath, 'retention_mtime');
            if ($mtime === null || $mtime >= $cutoff) {
                continue;
            }
            if (cronDeleteFile($filePath, 'retention_delete')) {
                $deletedFiles++;
            }
        }
    }

    return $deletedFiles;
}

/**
 * @return array{sent:int, failed:int}
 */
function cronProcessReservationReminders(PDO $pdo): array
{
    $result = ['sent' => 0, 'failed' => 0];
    if (!isModuleEnabled('reservations')) {
        return $result;
    }

    $missingBookingColumns = cronMissingColumns($pdo, 'cms_res_bookings', [
        'calendar_token',
        'reminder_sent_at',
        'reminder_last_error',
    ]);
    $missingResourceColumns = cronMissingColumns($pdo, 'cms_res_resources', [
        'reminders_enabled',
        'reminder_hours_before',
        'reminder_message',
        'calendar_invite_enabled',
    ]);
    $missingEventColumns = cronMissingColumns($pdo, 'cms_res_booking_events', [
        'booking_id',
        'event_type',
        'description',
        'created_at',
    ]);
    if ($missingBookingColumns !== [] || $missingResourceColumns !== [] || $missingEventColumns !== []) {
        return $result;
    }

    $stmt = $pdo->prepare(
        "SELECT b.id
         FROM cms_res_bookings b
         JOIN cms_res_resources r ON r.id = b.resource_id
         WHERE b.status = 'confirmed'
           AND b.reminder_sent_at IS NULL
           AND COALESCE(b.reminder_last_error, '') = ''
           AND r.reminders_enabled = 1
           AND TIMESTAMP(b.booking_date, b.start_time) > NOW()
         ORDER BY b.booking_date, b.start_time, b.id
         LIMIT 100"
    );
    $stmt->execute();
    $bookingIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($bookingIds as $bookingId) {
        $booking = reservationBookingForNotification($pdo, $bookingId);
        if ($booking === null || !reservationReminderIsDue($booking)) {
            continue;
        }

        $sent = reservationSendMail(
            $booking,
            reservationReminderSubject($booking),
            reservationReminderBody($booking),
            'reservation_reminder',
            true
        );

        if ($sent) {
            $pdo->prepare(
                "UPDATE cms_res_bookings
                 SET reminder_sent_at = NOW(), reminder_last_error = '', updated_at = NOW()
                 WHERE id = ?"
            )->execute([$bookingId]);
            reservationRecordBookingEvent($pdo, $bookingId, 'reminder_sent', 'E-mailová připomínka byla odeslána.');
            $result['sent']++;
            continue;
        }

        $errorMessage = 'E-mailovou připomínku se nepodařilo odeslat.';
        $pdo->prepare(
            "UPDATE cms_res_bookings
             SET reminder_last_error = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([$errorMessage, $bookingId]);
        reservationRecordBookingEvent($pdo, $bookingId, 'reminder_failed', $errorMessage);
        $result['failed']++;
    }

    return $result;
}

/**
 * @param list<string> $log
 */
function cronAppendLog(array &$log, string $message): void
{
    $message = trim($message);
    if ($message !== '') {
        $log[] = $message;
    }
}

/**
 * @param list<string> $requiredColumns
 * @return list<string>
 */
function cronMissingColumns(PDO $pdo, string $tableName, array $requiredColumns): array
{
    static $columnCache = [];

    if (!isset($columnCache[$tableName])) {
        try {
            $statement = $pdo->prepare(
                "SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?"
            );
            $statement->execute([$tableName]);
            $columnCache[$tableName] = array_fill_keys(
                array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)),
                true
            );
        } catch (\PDOException $e) {
            $columnCache[$tableName] = [];
        }
    }

    $missing = [];
    foreach ($requiredColumns as $columnName) {
        if (!isset($columnCache[$tableName][$columnName])) {
            $missing[] = (string)$columnName;
        }
    }

    return $missing;
}

/**
 * @return list<string>
 */
function runKoraCron(PDO $pdo): array
{
    $log = [];

    // 1. Plánované zrušení publikace
    $unpublishTables = [
        'cms_articles' => ['has_published' => false],
        'cms_news' => ['has_published' => false],
        'cms_board' => ['has_published' => true],
        'cms_pages' => ['has_published' => true],
        'cms_events' => ['has_published' => true],
    ];
    $totalUnpublished = 0;
    foreach ($unpublishTables as $tableName => $config) {
        $requiredColumns = $config['has_published']
            ? ['unpublish_at', 'is_published']
            : ['unpublish_at', 'status'];
        $missingColumns = cronMissingColumns($pdo, $tableName, $requiredColumns);
        if ($missingColumns !== []) {
            cronAppendLog(
                $log,
                "Přeskočeno zrušení publikace pro {$tableName}: chybí sloupce " . implode(', ', $missingColumns)
            );
            continue;
        }
        try {
            if ($config['has_published']) {
                $statement = $pdo->prepare(
                    "UPDATE {$tableName}
                     SET is_published = 0, unpublish_at = NULL
                     WHERE unpublish_at IS NOT NULL
                       AND unpublish_at <= NOW()
                       AND is_published = 1"
                );
            } else {
                $statement = $pdo->prepare(
                    "UPDATE {$tableName}
                     SET status = 'pending', unpublish_at = NULL
                     WHERE unpublish_at IS NOT NULL
                       AND unpublish_at <= NOW()
                       AND status = 'published'"
                );
            }
            $statement->execute();
            $totalUnpublished += $statement->rowCount();
        } catch (\PDOException $e) {
            cronAppendLog($log, 'Chyba zrušení publikace: ' . $e->getMessage());
        }
    }
    if ($totalUnpublished > 0) {
        cronAppendLog($log, "Zrušena publikace {$totalUnpublished} položek");
    }

    // 1b. Plánované publikování (publish_at)
    $publishTables = ['cms_articles', 'cms_news', 'cms_podcasts'];
    $totalPublished = 0;
    foreach ($publishTables as $tableName) {
        $missingColumns = cronMissingColumns($pdo, $tableName, ['publish_at', 'status', 'created_at']);
        if ($missingColumns !== []) {
            cronAppendLog(
                $log,
                "Přeskočeno plánované publikování pro {$tableName}: chybí sloupce " . implode(', ', $missingColumns)
            );
            continue;
        }
        try {
            $statement = $pdo->prepare(
                "UPDATE {$tableName}
                 SET status = 'published', created_at = publish_at, publish_at = NULL
                 WHERE publish_at IS NOT NULL
                   AND publish_at <= NOW()
                   AND status IN ('draft', 'pending')"
            );
            $statement->execute();
            $totalPublished += $statement->rowCount();
        } catch (\PDOException $e) {
            cronAppendLog($log, 'Chyba plánovaného publikování: ' . $e->getMessage());
        }
    }
    // Stránky a nástěnka (is_published místo status)
    $publishIsPublishedTables = ['cms_pages', 'cms_board', 'cms_events'];
    foreach ($publishIsPublishedTables as $tableName) {
        $missingColumns = cronMissingColumns($pdo, $tableName, ['publish_at', 'is_published', 'created_at']);
        if ($missingColumns !== []) {
            cronAppendLog(
                $log,
                "Přeskočeno plánované publikování pro {$tableName}: chybí sloupce " . implode(', ', $missingColumns)
            );
            continue;
        }
        try {
            $statement = $pdo->prepare(
                "UPDATE {$tableName}
                 SET is_published = 1, created_at = publish_at, publish_at = NULL
                 WHERE publish_at IS NOT NULL
                   AND publish_at <= NOW()
                   AND is_published = 0"
            );
            $statement->execute();
            $totalPublished += $statement->rowCount();
        } catch (\PDOException $e) {
            cronAppendLog($log, "Chyba plánovaného publikování ({$tableName}): " . $e->getMessage());
        }
    }
    if ($totalPublished > 0) {
        cronAppendLog($log, "Publikováno {$totalPublished} naplánovaných položek");
    }

    // 2. Čištění starých rate-limit záznamů
    try {
        $statement = $pdo->prepare(
            "DELETE FROM cms_rate_limit
             WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $statement->execute();
        $deletedCount = $statement->rowCount();
        if ($deletedCount > 0) {
            cronAppendLog($log, "Smazáno {$deletedCount} expirovaných rate-limit záznamů");
        }
    } catch (\PDOException $e) {
        cronAppendLog($log, 'Chyba čištění rate-limit: ' . $e->getMessage());
    }

    // 3. Čištění temp souborů starších než 24 hodin
    $tempDirectory = cronTempDirectory();
    if (is_dir($tempDirectory)) {
        $cleanedFiles = 0;
        foreach (glob($tempDirectory . '*') ?: [] as $tempFile) {
            if (!is_file($tempFile)) {
                continue;
            }
            $tempFileMtime = cronFileMtime($tempFile, 'temp_mtime');
            if ($tempFileMtime === null || $tempFileMtime >= (time() - 86400)) {
                continue;
            }
            if (cronDeleteFile($tempFile, 'temp_delete')) {
                $cleanedFiles++;
            }
        }
        if ($cleanedFiles > 0) {
            cronAppendLog($log, "Smazáno {$cleanedFiles} starých temp souborů");
        }
    }

    // 4. Čištění starých audit logů
    try {
        $statement = $pdo->prepare(
            "DELETE FROM cms_log
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        $statement->execute();
        $deletedCount = $statement->rowCount();
        if ($deletedCount > 0) {
            cronAppendLog($log, "Smazáno {$deletedCount} starých audit log záznamů");
        }
    } catch (\PDOException $e) {
        cronAppendLog($log, 'Chyba čištění logu: ' . $e->getMessage());
    }

    // 4b. Čištění starých CSP report logů v privátním úložišti
    $deletedCspReportFiles = cronDeleteOldFiles(cronLogDirectory(), ['csp_reports-*.jsonl'], 30 * 86400);
    if ($deletedCspReportFiles > 0) {
        cronAppendLog($log, "Smazáno {$deletedCspReportFiles} starých CSP report log souborů");
    }

    // 5. Čištění expirovaných zámků obsahu
    try {
        $statement = $pdo->prepare(
            "DELETE FROM cms_content_locks WHERE expires_at < NOW()"
        );
        $statement->execute();
        $deletedCount = $statement->rowCount();
        if ($deletedCount > 0) {
            cronAppendLog($log, "Smazáno {$deletedCount} expirovaných zámků obsahu");
        }
    } catch (\PDOException $e) {
        cronAppendLog($log, 'Chyba čištění zámků obsahu: ' . $e->getMessage());
    }

    try {
        $reservationReminders = cronProcessReservationReminders($pdo);
        if ($reservationReminders['sent'] > 0) {
            cronAppendLog($log, 'Odesláno ' . $reservationReminders['sent'] . ' připomínek rezervací');
        }
        if ($reservationReminders['failed'] > 0) {
            cronAppendLog($log, 'Nepodařilo se odeslat ' . $reservationReminders['failed'] . ' připomínek rezervací');
        }
    } catch (\PDOException $e) {
        cronAppendLog($log, 'Chyba připomínek rezervací: ' . $e->getMessage());
    }

    // 6. Automatická záloha databáze (1x denně)
    $chatRetentionDays = chatRetentionDays();
    if ($chatRetentionDays > 0) {
        try {
            $chatRetentionCutoff = date('Y-m-d H:i:s', time() - ($chatRetentionDays * 86400));
            $expiredChatIdsStmt = $pdo->prepare(
                "SELECT id
                 FROM cms_chat
                 WHERE status = 'handled'
                   AND updated_at < ?"
            );
            $expiredChatIdsStmt->execute([$chatRetentionCutoff]);
            $expiredChatIds = array_map('intval', $expiredChatIdsStmt->fetchAll(PDO::FETCH_COLUMN));

            if ($expiredChatIds !== []) {
                $placeholders = implode(',', array_fill(0, count($expiredChatIds), '?'));
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("DELETE FROM cms_chat_history WHERE chat_id IN ({$placeholders})")->execute($expiredChatIds);
                    $pdo->prepare("DELETE FROM cms_chat WHERE id IN ({$placeholders})")->execute($expiredChatIds);
                    $pdo->commit();
                } catch (\PDOException $txe) {
                    $pdo->rollBack();
                    throw $txe;
                }
                cronAppendLog($log, 'Smazáno ' . count($expiredChatIds) . ' starých vyřízených chat zpráv');
            }
        } catch (\PDOException $e) {
            cronAppendLog($log, 'Chyba čištění chat zpráv: ' . $e->getMessage());
        }
    }

    $backupDirectory = cronBackupDirectory();
    if (koraEnsureDirectory($backupDirectory)) {
        $todayBackup = $backupDirectory . 'kora_backup_' . date('Y-m-d') . '.sql';
        if (!is_file($todayBackup)) {
            try {
                $tables = koraBackupTableNames($pdo);
                $fh = @fopen($todayBackup, 'w');
                if ($fh === false) {
                    cronAppendLog($log, 'Chyba zálohy: nepodařilo se otevřít soubor pro zápis');
                } else {
                    fwrite($fh, "-- Kora CMS auto-backup " . date('Y-m-d H:i:s') . "\n");
                    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

                    foreach ($tables as $tableName) {
                        koraSqlWriteTableDump($pdo, $tableName, static function (string $sql) use ($fh): void {
                            fwrite($fh, $sql);
                        });
                    }

                    fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
                    fclose($fh);
                    cronAppendLog($log, 'Záloha databáze vytvořena: ' . basename($todayBackup));
                }
            } catch (\Throwable $e) {
                cronAppendLog($log, 'Chyba zálohy: ' . $e->getMessage());
            }
        }

        foreach (glob($backupDirectory . 'kora_backup_*.sql') ?: [] as $oldBackup) {
            if (!is_file($oldBackup)) {
                continue;
            }
            $backupMtime = cronFileMtime($oldBackup, 'backup_retention_mtime');
            if ($backupMtime !== null && $backupMtime < (time() - (7 * 86400))) {
                cronDeleteFile($oldBackup, 'backup_retention_delete');
            }
        }
    } else {
        cronAppendLog($log, 'Chyba zálohy: nepodařilo se vytvořit privátní adresář pro zálohy');
    }

    try {
        saveSetting('cron_last_run_at', date(DATE_ATOM));
    } catch (\Throwable $e) {
        cronAppendLog($log, 'Chyba uložení času cronu: ' . $e->getMessage());
    }

    return $log;
}

$currentScript = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
$isDirectRun = $currentScript === __FILE__;

if ($isDirectRun) {
    $isCli = PHP_SAPI === 'cli';

    if (!$isCli) {
        $token = trim($_GET['token'] ?? '');
        $configToken = defined('CRON_TOKEN') ? trim((string)CRON_TOKEN) : '';
        if ($configToken === '' || !hash_equals($configToken, $token)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Forbidden';
            exit;
        }
    }

    $pdo = db_connect();
    $log = runKoraCron($pdo);

    if ($log !== []) {
        $summary = implode('; ', $log);
        try {
            $pdo->prepare("INSERT INTO cms_log (action, detail) VALUES ('cron', ?)")->execute([$summary]);
        } catch (\PDOException $e) {
        }

        if ($isCli) {
            foreach ($log as $line) {
                echo $line . PHP_EOL;
            }
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo implode("\n", $log) . "\n";
        }
    } else {
        if ($isCli) {
            echo "Žádné úlohy k provedení." . PHP_EOL;
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo "OK\n";
        }
    }
}
