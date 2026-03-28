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

// ── 2. Čištění starých rate-limit záznamů (starší než 1 hodina) ─────────────
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
