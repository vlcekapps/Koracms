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

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $token = trim($_GET['token'] ?? '');
    $configToken = defined('CRON_TOKEN') ? CRON_TOKEN : '';
    if ($configToken === '' || !hash_equals($configToken, $token)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$pdo = db_connect();
$log = [];

// ── 1. Plánované publikování článků ──────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        "UPDATE cms_articles SET status = 'published'
         WHERE status = 'published' AND publish_at IS NOT NULL AND publish_at <= NOW()"
    );
    $stmt->execute();
    $count = $stmt->rowCount();
    if ($count > 0) {
        $log[] = "Publikováno {$count} naplánovaných článků";
    }
} catch (\PDOException $e) {
    $log[] = 'Chyba plánované publikace: ' . $e->getMessage();
}

// ── 2. Plánované zrušení publikace ────────────────────────────────────────────
$unpublishTables = [
    'cms_articles'  => ['has_published' => false],
    'cms_news'      => ['has_published' => false],
    'cms_pages'     => ['has_published' => true],
    'cms_events'    => ['has_published' => true],
];
$totalUnpublished = 0;
foreach ($unpublishTables as $uTable => $uCfg) {
    try {
        if ($uCfg['has_published']) {
            $stmt = $pdo->prepare("UPDATE {$uTable} SET is_published = 0, unpublish_at = NULL WHERE unpublish_at IS NOT NULL AND unpublish_at <= NOW() AND is_published = 1");
        } else {
            $stmt = $pdo->prepare("UPDATE {$uTable} SET status = 'pending', unpublish_at = NULL WHERE unpublish_at IS NOT NULL AND unpublish_at <= NOW() AND status = 'published'");
        }
        $stmt->execute();
        $totalUnpublished += $stmt->rowCount();
    } catch (\PDOException $e) {}
}
if ($totalUnpublished > 0) {
    $log[] = "Zrušena publikace {$totalUnpublished} položek (unpublish_at)";
}

// ── 3. Čištění starých rate-limit záznamů (starší než 1 hodina) ─────────────
try {
    $stmt = $pdo->prepare("DELETE FROM cms_rate_limit WHERE expires_at < NOW() - INTERVAL 1 HOUR");
    $stmt->execute();
    $count = $stmt->rowCount();
    if ($count > 0) {
        $log[] = "Smazáno {$count} expirovaných rate-limit záznamů";
    }
} catch (\PDOException $e) {
    $log[] = 'Chyba čištění rate-limit: ' . $e->getMessage();
}

// ── 3. Čištění temp souborů starších než 24 hodin ────────────────────────────
$tmpDir = __DIR__ . '/uploads/tmp/';
if (is_dir($tmpDir)) {
    $cleaned = 0;
    foreach (glob($tmpDir . '*') as $file) {
        if (is_file($file) && filemtime($file) < time() - 86400) {
            if (@unlink($file)) {
                $cleaned++;
            }
        }
    }
    if ($cleaned > 0) {
        $log[] = "Smazáno {$cleaned} starých temp souborů";
    }
}

// ── 4. Čištění starých audit logů (starší než 90 dní) ───────────────────────
try {
    $stmt = $pdo->prepare("DELETE FROM cms_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt->execute();
    $count = $stmt->rowCount();
    if ($count > 0) {
        $log[] = "Smazáno {$count} starých audit log záznamů (90+ dní)";
    }
} catch (\PDOException $e) {
    $log[] = 'Chyba čištění logu: ' . $e->getMessage();
}

// ── 5. Automatická záloha databáze (1x denně) ────────────────────────────────
$backupDir = __DIR__ . '/uploads/backups/';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}
$todayBackup = $backupDir . 'kora_backup_' . date('Y-m-d') . '.sql';
if (!is_file($todayBackup)) {
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $sql = "-- Kora CMS auto-backup " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
        foreach ($tables as $table) {
            if (!str_starts_with($table, 'cms_')) continue;
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n";
            $rows = $pdo->query("SELECT * FROM `{$table}`");
            $first = true;
            $cols = null;
            foreach ($rows as $row) {
                if ($first) { $cols = array_keys($row); $first = false; }
                $vals = [];
                foreach ($cols as $c) { $vals[] = $row[$c] === null ? 'NULL' : $pdo->quote($row[$c]); }
                $sql .= "INSERT INTO `{$table}` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $vals) . ");\n";
            }
            if (!$first) $sql .= "\n";
        }
        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        file_put_contents($todayBackup, $sql);
        $log[] = 'Záloha databáze vytvořena: ' . basename($todayBackup);
    } catch (\PDOException $e) {
        $log[] = 'Chyba zálohy: ' . $e->getMessage();
    }

    // Rotace: smazat zálohy starší než 7 dní
    foreach (glob($backupDir . 'kora_backup_*.sql') as $oldBackup) {
        if (filemtime($oldBackup) < time() - 7 * 86400) {
            @unlink($oldBackup);
        }
    }
}

// ── Výstup ───────────────────────────────────────────────────────────────────
if ($log !== []) {
    $summary = implode('; ', $log);
    try {
        $pdo->prepare("INSERT INTO cms_log (action, detail) VALUES ('cron', ?)")->execute([$summary]);
    } catch (\PDOException $e) {}

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
        echo "Žádné úlohy k provedení.\n";
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo "OK\n";
    }
}
