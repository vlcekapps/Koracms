<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$siteName = getSetting('site_name', 'Kora CMS');
$success  = false;
$error    = false;

$token = trim($_GET['token'] ?? '');

if ($token !== '') {
    $pdo  = db_connect();
    $stmt = $pdo->prepare(
        "SELECT id FROM cms_users WHERE confirmation_token = ? AND is_confirmed = 0"
    );
    $stmt->execute([$token]);
    $userRow = $stmt->fetch();

    if ($userRow) {
        $pdo->prepare(
            "UPDATE cms_users SET is_confirmed = 1, confirmation_token = '' WHERE id = ?"
        )->execute([$userRow['id']]);
        $success = true;
    } else {
        $error = true;
    }
} else {
    $error = true;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Potvrzení e-mailu – ' . $siteName]) ?>
  <title>Potvrzení e-mailu – <?= h($siteName) ?></title>
  <style>
    .skip-link { position: absolute; left: -9999px; }
    .skip-link:focus { left: 1rem; top: 1rem; z-index: 9999;
      background: #fff; padding: .5rem 1rem; border: 2px solid #000; }
  </style>
</head>
<body>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>

<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav() ?>
</header>

<main id="obsah">
  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0"></div>
  <h2>Potvrzení e-mailu</h2>

  <?php if ($success): ?>
    <p role="status" style="color:#060">
      <strong>Váš e-mail byl úspěšně ověřen.</strong>
      Nyní se můžete <a href="<?= BASE_URL ?>/public_login.php">přihlásit</a>.
    </p>
  <?php else: ?>
    <p role="alert" style="color:#c00">
      Neplatný nebo již použitý potvrzovací odkaz.
    </p>
    <p><a href="<?= BASE_URL ?>/register.php">Zaregistrovat se znovu</a></p>
  <?php endif; ?>
</main>

<?= siteFooter() ?>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}});</script>
</body>
</html>
