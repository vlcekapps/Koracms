<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/theme.php';

define('KORA_VERSION', trim(file_get_contents(__DIR__ . '/VERSION')));

// Globální exception handler – zachytí neošetřené výjimky a zobrazí
// uživatelsky přívětivou chybovou stránku místo bílé obrazovky.
set_exception_handler(function (\Throwable $e): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $debug = (ini_get('display_errors') === '1');
    echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8"><title>Chyba serveru</title>'
       . '<style>body{font-family:system-ui,sans-serif;max-width:40em;margin:4em auto;padding:0 1em;color:#333}'
       . 'h1{color:#b00}pre{background:#f4f4f4;padding:1em;overflow:auto;font-size:.85em}</style></head><body>'
       . '<h1>Omlouváme se, došlo k chybě</h1>'
       . '<p>Stránku se nepodařilo načíst. Zkuste to prosím později.</p>';
    if ($debug) {
        echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '</body></html>';
    exit(1);
});

function db_connect(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        global $server, $user, $pass, $database;
        $dsn = "mysql:host={$server};dbname={$database};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inputInt(string $source, string $key): ?int
{
    $arr = ($source === 'get') ? $_GET : $_POST;
    $val = filter_var($arr[$key] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return ($val !== false) ? (int)$val : null;
}

// ──────────────────────────────── Nastavení (cms_settings) ────────────────────

function getSettings(): array
{
    global $_CMS_SETTINGS;
    if (!isset($_CMS_SETTINGS)) {
        try {
            $rows = db_connect()->query("SELECT `key`, value FROM cms_settings")->fetchAll();
            $_CMS_SETTINGS = array_column($rows, 'value', 'key');
        } catch (\PDOException $e) {
            $_CMS_SETTINGS = [];
        }
    }
    return $_CMS_SETTINGS;
}

function clearSettingsCache(): void
{
    global $_CMS_SETTINGS;
    unset($_CMS_SETTINGS);
}

function getSetting(string $key, string $default = ''): string
{
    return getSettings()[$key] ?? $default;
}

function saveSetting(string $key, string $value): void
{
    db_connect()
        ->prepare("INSERT INTO cms_settings (`key`, value) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE value = VALUES(value)")
        ->execute([$key, $value]);
    clearSettingsCache();
}

function isModuleEnabled(string $module): bool
{
    return getSetting('module_' . $module, '0') === '1';
}

function publicRegistrationEnabled(): bool
{
    return getSetting('public_registration_enabled', '1') === '1';
}

function koraStorageDirectory(): string
{
    $configuredPath = defined('KORA_STORAGE_DIR') ? trim((string)KORA_STORAGE_DIR) : '';
    if ($configuredPath !== '') {
        return rtrim($configuredPath, "\\/");
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'kora_storage';
}

function koraStoragePath(string $subpath = ''): string
{
    $storageRoot = koraStorageDirectory();
    $normalizedSubpath = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $subpath), DIRECTORY_SEPARATOR);
    if ($normalizedSubpath === '') {
        return $storageRoot;
    }

    return $storageRoot . DIRECTORY_SEPARATOR . $normalizedSubpath;
}

function koraEnsureDirectory(string $path, int $permissions = 0755): bool
{
    if (is_dir($path)) {
        return true;
    }

    return @mkdir($path, $permissions, true) || is_dir($path);
}

// ──────────────────────────── Moduly (lib/) ────────────────────────────────
// Funkce rozděleny do tematických souborů pro lepší přehlednost a údržbu.
require_once __DIR__ . '/lib/definitions.php';
require_once __DIR__ . '/lib/comments.php';
require_once __DIR__ . '/lib/messages.php';
require_once __DIR__ . '/lib/presentation.php';
require_once __DIR__ . '/lib/gallery.php';
require_once __DIR__ . '/lib/content.php';
require_once __DIR__ . '/lib/filedownloads.php';
require_once __DIR__ . '/lib/ui.php';
require_once __DIR__ . '/lib/github.php';
require_once __DIR__ . '/lib/webhooks.php';
require_once __DIR__ . '/lib/mail.php';
require_once __DIR__ . '/lib/stats.php';
require_once __DIR__ . '/lib/pagination.php';
require_once __DIR__ . '/lib/revisions.php';
require_once __DIR__ . '/lib/widgets.php';
require_once __DIR__ . '/lib/totp.php';

