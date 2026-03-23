<?php
require_once __DIR__ . '/db.php';

$siteName = getSetting('site_name', 'Kora CMS');
$token    = trim($_GET['token'] ?? '');
$ok       = false;

if ($token !== '') {
    try {
        $stmt = db_connect()->prepare(
            "DELETE FROM cms_subscribers WHERE token = ?"
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
  <title>Odhlášení z odběru – <?= h($siteName) ?></title>
<?= publicA11yStyleTag() ?>
</head>
<body>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav() ?>
</header>

<main id="obsah">
  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0"></div>
  <h2>Odhlášení z odběru novinek</h2>
  <?php if ($ok): ?>
    <p role="status" style="color:#060">Váš email byl úspěšně odhlášen z odběru novinek.</p>
  <?php else: ?>
    <p>Odkaz pro odhlášení je neplatný nebo odběr již neexistuje.</p>
  <?php endif; ?>
  <p><a href="<?= BASE_URL ?>/index.php"><span aria-hidden="true">←</span> Zpět na úvod</a></p>
</main>

<?= siteFooter() ?>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}});</script>
</body>
</html>
