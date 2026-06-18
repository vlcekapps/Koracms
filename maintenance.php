<?php
/**
 * Stránka údržby – zobrazí se návštěvníkům, pokud je zapnut maintenance_mode.
 * Může být includována přes checkMaintenanceMode() nebo otevřena přímo.
 */
if (!function_exists('getSetting')) {
    require_once __DIR__ . '/db.php';
}

$_siteName = getSetting('site_name', 'Kora CMS');
$_msg      = getSetting(
    'maintenance_text',
    'Právě probíhá údržba webu. Brzy budeme zpět, děkujeme za trpělivost.'
);

if (!headers_sent()) {
    http_response_code(503);
    header('Retry-After: 3600');
}
?><!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Údržba – <?= h($_siteName) ?></title>
<?= standaloneStylesheetTag() ?>
</head>
<body class="standalone-page standalone-page--maintenance">
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<main id="obsah" class="maintenance-box">
  <h1>Probíhá údržba</h1>
  <p><?= h($_msg) ?></p>
</main>
</body>
</html>
