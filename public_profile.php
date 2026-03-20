<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();
requirePublicLogin(BASE_URL . '/public_profile.php');

$pdo     = db_connect();
$userId  = currentUserId();
$siteName = getSetting('site_name', 'Kora CMS');
$success = false;
$passSuccess = false;
$errors  = [];

// Načteme aktuálního uživatele
$stmt = $pdo->prepare("SELECT * FROM cms_users WHERE id = ?");
$stmt->execute([$userId]);
$me = $stmt->fetch();

if (!$me) {
    header('Location: ' . BASE_URL . '/public_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? 'profile';

    if ($action === 'password') {
        // Změna hesla
        $currentPass = $_POST['current_pass'] ?? '';
        $newPass     = $_POST['new_pass'] ?? '';
        $newPass2    = $_POST['new_pass2'] ?? '';

        if (!password_verify($currentPass, $me['password']))
            $errors[] = 'Současné heslo není správné.';
        if (strlen($newPass) < 8)
            $errors[] = 'Nové heslo musí mít alespoň 8 znaků.';
        if ($newPass !== $newPass2)
            $errors[] = 'Nová hesla se neshodují.';

        if (empty($errors)) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE cms_users SET password = ? WHERE id = ?")
                ->execute([$hash, $userId]);
            $passSuccess = true;
        }
    } else {
        // Úprava profilu
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');

        if ($firstName === '') $errors[] = 'Jméno je povinné.';
        if ($lastName === '')  $errors[] = 'Příjmení je povinné.';
        if ($phone === '')     $errors[] = 'Telefon je povinný.';

        if (empty($errors)) {
            $pdo->prepare(
                "UPDATE cms_users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?"
            )->execute([$firstName, $lastName, $phone, $userId]);

            // Aktualizuj session
            $displayName = trim($firstName . ' ' . $lastName);
            if ($displayName === '') $displayName = $me['email'];
            $_SESSION['cms_user_name'] = $displayName;

            $success = true;
            $me = array_merge($me, [
                'first_name' => $firstName, 'last_name' => $lastName, 'phone' => $phone,
            ]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Můj profil – ' . $siteName]) ?>
  <title>Můj profil – <?= h($siteName) ?></title>
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
  <h2>Můj profil</h2>

  <nav aria-label="Uživatelské odkazy" style="margin-bottom:1.5rem">
    <ul style="list-style:none;padding:0;display:flex;gap:1rem;flex-wrap:wrap">
      <li><a href="<?= BASE_URL ?>/reservations/my.php">Moje rezervace</a></li>
      <li><a href="<?= BASE_URL ?>/public_logout.php">Odhlásit se</a></li>
    </ul>
  </nav>

  <?php if ($success): ?>
    <p role="status" aria-atomic="true" style="color:#060"><strong>Profil byl uložen.</strong></p>
  <?php endif; ?>

  <?php if ($passSuccess): ?>
    <p role="status" aria-atomic="true" style="color:#060"><strong>Heslo bylo změněno.</strong></p>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <ul id="form-errors" role="alert" aria-atomic="true" style="color:#c00">
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="post" novalidate <?php if (!empty($errors)): ?>aria-describedby="form-errors"<?php endif; ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="profile">

    <fieldset>
      <legend>Osobní údaje</legend>

      <div>
        <label for="first_name">Jméno <span aria-hidden="true">*</span></label>
        <input type="text" id="first_name" name="first_name" required aria-required="true"
               maxlength="100" value="<?= h($me['first_name']) ?>">
      </div>

      <div>
        <label for="last_name">Příjmení <span aria-hidden="true">*</span></label>
        <input type="text" id="last_name" name="last_name" required aria-required="true"
               maxlength="100" value="<?= h($me['last_name']) ?>">
      </div>

      <div>
        <label for="phone">Telefon <span aria-hidden="true">*</span></label>
        <input type="tel" id="phone" name="phone" required aria-required="true"
               maxlength="20" value="<?= h($me['phone'] ?? '') ?>" aria-describedby="phone-hint">
        <small id="phone-hint">Nutné pro rezervace</small>
      </div>

      <div>
        <label for="email_display">E-mail</label>
        <input type="email" id="email_display" value="<?= h($me['email']) ?>" readonly
               aria-readonly="true" style="background:#eee;cursor:not-allowed">
      </div>

      <button type="submit" style="margin-top:1rem">Uložit profil</button>
    </fieldset>
  </form>

  <form method="post" novalidate style="margin-top:2rem">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="password">

    <fieldset style="border:1px solid #ccc;padding:.5rem 1rem">
      <legend>Změna hesla</legend>

      <div>
        <label for="current_pass">Současné heslo <span aria-hidden="true">*</span></label>
        <input type="password" id="current_pass" name="current_pass" required aria-required="true"
               autocomplete="current-password">
      </div>

      <div>
        <label for="new_pass">Nové heslo (min. 8 znaků) <span aria-hidden="true">*</span></label>
        <input type="password" id="new_pass" name="new_pass" required aria-required="true"
               minlength="8" autocomplete="new-password">
      </div>

      <div>
        <label for="new_pass2">Nové heslo znovu <span aria-hidden="true">*</span></label>
        <input type="password" id="new_pass2" name="new_pass2" required aria-required="true"
               minlength="8" autocomplete="new-password">
      </div>

      <button type="submit" style="margin-top:1rem">Změnit heslo</button>
    </fieldset>
  </form>

</main>

<?= siteFooter() ?>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}});</script>
</body>
</html>
