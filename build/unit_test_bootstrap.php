<?php
declare(strict_types=1);

/**
 * Bootstrap pro unit testy – nacte zdrojove soubory bez vedlejsich efektu (DB, session, headers).
 * Pouziti: require_once __DIR__ . '/unit_test_bootstrap.php';
 */

error_reporting(E_ALL & ~E_DEPRECATED);

// Definice konstant z config.php (dummy hodnoty pro testy)
if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}
if (!defined('KORA_STORAGE_DIR')) {
    define('KORA_STORAGE_DIR', sys_get_temp_dir() . '/kora_test_storage');
}
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'localhost');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 25);
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', '');
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', '');
}
if (!defined('SMTP_SECURE')) {
    define('SMTP_SECURE', '');
}
if (!defined('GITHUB_ISSUES_TOKEN')) {
    define('GITHUB_ISSUES_TOKEN', '');
}
if (!defined('CRON_TOKEN')) {
    define('CRON_TOKEN', '');
}

// Dummy DB globaly (nebudeme se pripojovat k DB)
$server   = '127.0.0.1';
$user     = 'test';
$pass     = 'test';
$database = 'kora_test';

// Dummy db_connect – auth.php IIFE vola db_connect() pred jejim definovanim v db.php.
// PDOException je odchycena, takze staci vyhodit PDOException.
if (!function_exists('db_connect')) {
    function db_connect(): PDO
    {
        throw new \PDOException('Unit test – no database');
    }
}

// Zachyceni headers a session startu
ob_start();

// Nacteni auth.php (session_start + headers; redirect IIFE catchne PDOException z dummy db_connect)
require_once dirname(__DIR__) . '/auth.php';

// Nacteni db.php – preskoci require_once auth.php (uz loaded),
// predefinuje db_connect (ale nase dummy uz existuje – require_once to preskoci),
// nacte KORA_VERSION a vsechny lib/
// Poznamka: db_connect v db.php je funkce – PHP nepovoli redefinici, ale require_once
// jen znovu nacte soubor. Protoze db.php definuje db_connect uvnitr `function db_connect()`,
// a my ji definovali jako prvni, dostaneme fatal error.
// Reseni: nacteme db.php primo, protoze nase dummy funkce slouzi jen pro IIFE v auth.php.

// Redefince db_connect neni mozna. Misto toho nacteme jen lib soubory bez db.php.
// Funkce h() a inputInt() definujeme rucne.

define('KORA_VERSION', trim((string)(file_get_contents(dirname(__DIR__) . '/VERSION') ?: '0.0.0')));

// h() a inputInt() – stejne jako v db.php, pro pripad ze db.php neni nacten
if (!function_exists('h')) {
    function h(?string $s): string
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('inputInt')) {
    function inputInt(string $source, string $key): ?int
    {
        $arr = ($source === 'get') ? $_GET : $_POST;
        $val = filter_var($arr[$key] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return ($val !== false) ? (int)$val : null;
    }
}
if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = ''): string
    {
        return $default;
    }
}
if (!function_exists('getSettings')) {
    function getSettings(): array
    {
        return [];
    }
}
if (!function_exists('saveSetting')) {
    function saveSetting(string $key, string $value): void {}
}
if (!function_exists('clearSettingsCache')) {
    function clearSettingsCache(): void {}
}
if (!function_exists('isModuleEnabled')) {
    function isModuleEnabled(string $module): bool { return false; }
}
if (!function_exists('koraStorageDirectory')) {
    function koraStorageDirectory(): string { return sys_get_temp_dir(); }
}
if (!function_exists('koraStoragePath')) {
    function koraStoragePath(string $subpath = ''): string { return sys_get_temp_dir() . '/' . $subpath; }
}
if (!function_exists('koraEnsureDirectory')) {
    function koraEnsureDirectory(string $path, int $permissions = 0755): bool { return true; }
}

// Nacteni lib souborů – kazdy ma require_once ale nemaji vedlejsi efekty pri loadu
require_once dirname(__DIR__) . '/lib/definitions.php';
require_once dirname(__DIR__) . '/lib/presentation.php';
require_once dirname(__DIR__) . '/lib/content.php';
require_once dirname(__DIR__) . '/lib/pagination.php';
require_once dirname(__DIR__) . '/lib/totp.php';
require_once dirname(__DIR__) . '/lib/mail.php';

ob_end_clean();
