<?php
declare(strict_types=1);

/**
 * Bootstrap pro PHPStan.
 *
 * Definuje jen bezpečné globální helpery a konstanty, které statická analýza
 * potřebuje pro samostatné lib soubory. Nenačítá databázi, session ani config.
 */

if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

if (!function_exists('h')) {
    function h(?string $s): string
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('inputInt')) {
    function inputInt(string $source, string $key): ?int
    {
        $sourceData = $source === 'get' ? $_GET : $_POST;
        $value = filter_var($sourceData[$key] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $value !== false ? (int) $value : null;
    }
}
