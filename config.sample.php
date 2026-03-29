<?php
// Přejmenujte tento soubor na config.php a vyplňte své údaje.

// Přihlašovací údaje k databázi
$server   = 'localhost';
$user     = 'root';       // databázový uživatel
$pass     = '';           // heslo (prázdné na lokálním serveru)
$database = 'nazev_databaze';

// Základní URL webu
// Pokud je web v kořeni domény, ponechte prázdné.
// Pokud je ve složce, zadejte např. '/koracms'
define('BASE_URL', '');

// Privátní úložiště mimo webroot
// Výchozí umístění je o úroveň výš než webroot, např. ../kora_storage
// Používá se pro citlivé přílohy formulářů a denní SQL zálohy z cron.php
// Při vlastním nastavení zadejte absolutní cestu.
define('KORA_STORAGE_DIR', '');

// SMTP odesílání e-mailů
// Pokud konstanty nejsou definovány, použije se localhost:25 bez autentizace.
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');          // prázdné = bez autentizace
define('SMTP_PASS', '');
define('SMTP_SECURE', 'tls');     // '' (žádné), 'tls' (STARTTLS), 'ssl'

// GitHub issue bridge pro odpovědi formulářů
// Fine-grained token by měl mít oprávnění Issues: Read and write
define('GITHUB_ISSUES_TOKEN', '');

// Token pro volitelný HTTP přístup ke cron.php
// Doporučený způsob spouštění je CLI cron: php /cesta/k/webu/cron.php
// Pokud musíte používat webový cron, nastavte sem dlouhý náhodný řetězec
// a volejte např. https://vas-web.cz/cron.php?token=VAS_CRON_TOKEN
define('CRON_TOKEN', '');
