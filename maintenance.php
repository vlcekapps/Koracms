<?php
/**
 * Stránka údržby – zobrazí se návštěvníkům, pokud je zapnut maintenance_mode.
 * Může být includována přes checkMaintenanceMode() nebo otevřena přímo.
 */
if (!function_exists('getSetting')) {
    require_once __DIR__ . '/db.php';
}

$_siteName = getSetting('site_name', 'Kora CMS');
$_msg      = getSetting('maintenance_text',
    'Právě probíhá údržba webu. Brzy budeme zpět, děkujeme za trpělivost.');

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
  <style nonce="<?= cspNonce() ?>">
    body { font-family: system-ui, sans-serif; display: flex; align-items: center;
           justify-content: center; min-height: 100vh; margin: 0; background: #f5f5f5; }
    .box { background: #fff; padding: 2rem 3rem; border-radius: 8px;
           box-shadow: 0 2px 12px rgba(0,0,0,.12); text-align: center; max-width: 480px; }
    h1 { margin-top: 0; font-size: 1.6rem; }
  </style>
</head>
<body>
<main class="box" role="main">
  <h1>Probíhá údržba</h1>
  <p><?= h($_msg) ?></p>
</main>
</body>
</html>
