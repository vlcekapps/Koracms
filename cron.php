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

function cronAppendLog(array &$log, string $message): void
{
    $message = trim($message);
    if ($message !== '') {
        $log[] = $message;
    }
}

/**
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
            if ((int)@filemtime($tempFile) >= (time() - 86400)) {
                continue;
            }
            if (@unlink($tempFile)) {
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
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $fh = @fopen($todayBackup, 'w');
                if ($fh === false) {
                    cronAppendLog($log, 'Chyba zálohy: nepodařilo se otevřít soubor pro zápis');
                } else {
                    fwrite($fh, "-- Kora CMS auto-backup " . date('Y-m-d H:i:s') . "\n");
                    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

                    foreach ($tables as $tableName) {
                        if (!str_starts_with((string)$tableName, 'cms_')) {
                            continue;
                        }

                        $createTable = $pdo->query("SHOW CREATE TABLE `{$tableName}`")->fetch();
                        fwrite($fh, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                        fwrite($fh, $createTable['Create Table'] . ";\n\n");

                        $rows = $pdo->query("SELECT * FROM `{$tableName}`");
                        $firstRow = true;
                        $columnNames = null;
                        foreach ($rows as $row) {
                            if ($firstRow) {
                                $columnNames = array_keys($row);
                                $firstRow = false;
                            }
                            $values = [];
                            foreach ($columnNames as $columnName) {
                                $values[] = $row[$columnName] === null ? 'NULL' : $pdo->quote((string)$row[$columnName]);
                            }
                            fwrite($fh, "INSERT INTO `{$tableName}` (`" . implode('`, `', $columnNames) . "`) VALUES (" . implode(', ', $values) . ");\n");
                        }

                        if (!$firstRow) {
                            fwrite($fh, "\n");
                        }
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
            if ((int)@filemtime($oldBackup) < (time() - (7 * 86400))) {
                @unlink($oldBackup);
            }
        }
    } else {
        cronAppendLog($log, 'Chyba zálohy: nepodařilo se vytvořit privátní adresář pro zálohy');
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
