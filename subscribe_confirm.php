<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$siteName = getSetting('site_name', 'Kora CMS');
$token    = trim($_GET['token'] ?? '');
$ok       = false;

if ($token !== '') {
    try {
        $stmt = db_connect()->prepare(
            "UPDATE cms_subscribers SET confirmed = 1 WHERE token = ? AND confirmed = 0"
        );
        $stmt->execute([$token]);
        $ok = $stmt->rowCount() > 0;
    } catch (\PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Potvrzení odběru – <?= h($siteName) ?></title>
</head>
<body>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav() ?>
</header>

<main id="obsah">
  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0"></div>
  <?php if ($ok): ?>
    <h2>Odběr potvrzen</h2>
    <p role="status" style="color:#060">
      Váš odběr novinek byl úspěšně potvrzen. Děkujeme!
    </p>
  <?php else: ?>
    <h2>Neplatný odkaz</h2>
    <p>Odkaz pro potvrzení je neplatný nebo již byl použit.</p>
  <?php endif; ?>
  <p><a href="<?= BASE_URL ?>/index.php"><span aria-hidden="true">←</span> Zpět na úvod</a></p>
</main>

<?= siteFooter() ?>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}});</script>
</body>
</html>
