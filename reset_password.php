<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$siteName = getSetting('site_name', 'Kora CMS');
$token    = trim($_GET['token'] ?? $_POST['token'] ?? '');
$mode     = ($token !== '') ? 'reset' : 'request';
$errors   = [];
$success  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('password_reset', 3, 300);
    verifyCsrf();

    $pdo = db_connect();

    if ($mode === 'request') {
        // Požadavek na reset hesla
        $email = trim($_POST['email'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Zadejte platnou e-mailovou adresu.';
        if (!captchaVerify($_POST['captcha'] ?? ''))
            $errors[] = 'Chybná odpověď na ověřovací otázku.';

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                "SELECT id, email FROM cms_users WHERE email = ? AND role = 'public' AND is_confirmed = 1"
            );
            $stmt->execute([$email]);
            $userRow = $stmt->fetch();

            if ($userRow) {
                $resetToken = bin2hex(random_bytes(32));
                $pdo->prepare(
                    "UPDATE cms_users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?"
                )->execute([$resetToken, $userRow['id']]);

                $resetUrl = siteUrl('/reset_password.php?token=' . $resetToken);
                $subject  = 'Obnovení hesla – ' . $siteName;
                $body     = "Dobrý den,\n\n"
                          . "obdrželi jsme žádost o obnovení hesla na webu {$siteName}.\n"
                          . "Pro nastavení nového hesla klikněte na odkaz:\n"
                          . $resetUrl . "\n\n"
                          . "Odkaz je platný 1 hodinu.\n\n"
                          . "Pokud jste o obnovení nežádali, tento email ignorujte.\n\n"
                          . "— " . $siteName;
                sendMail($userRow['email'], $subject, $body);
            }

            // Vždy zobrazíme úspěch (ochrana před enumerací)
            $success = true;
        }
    } else {
        // Reset hesla s tokenem
        $newPass  = $_POST['new_pass'] ?? '';
        $newPass2 = $_POST['new_pass2'] ?? '';

        if (strlen($newPass) < 8)
            $errors[] = 'Heslo musí mít alespoň 8 znaků.';
        if ($newPass !== $newPass2)
            $errors[] = 'Hesla se neshodují.';

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                "SELECT id FROM cms_users WHERE reset_token = ? AND reset_expires > NOW()"
            );
            $stmt->execute([$token]);
            $userRow = $stmt->fetch();

            if ($userRow) {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $pdo->prepare(
                    "UPDATE cms_users SET password = ?, reset_token = '', reset_expires = NULL WHERE id = ?"
                )->execute([$hash, $userRow['id']]);
                $success = true;
            } else {
                $errors[] = 'Neplatný nebo vypršený odkaz pro obnovení hesla.';
            }
        }
    }
}

$captchaExpr = captchaGenerate();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Obnovení hesla – ' . $siteName]) ?>
  <title>Obnovení hesla – <?= h($siteName) ?></title>
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
  <h2>Obnovení hesla</h2>

  <?php if (!empty($errors)): ?>
    <ul id="form-errors" role="alert" style="color:#c00">
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($mode === 'request'): ?>

    <?php if ($success): ?>
      <p role="status" style="color:#060">
        <strong>Pokud účet s tímto e-mailem existuje, odeslali jsme odkaz pro obnovení hesla.</strong>
        Zkontrolujte svou e-mailovou schránku.
      </p>
    <?php else: ?>
      <form method="post" novalidate aria-describedby="form-errors">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

        <fieldset>
          <legend>Žádost o obnovení hesla</legend>

          <div>
            <label for="email">Váš e-mail <span aria-hidden="true">*</span></label>
            <input type="email" id="email" name="email" required aria-required="true"
                   maxlength="255" value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email">
          </div>

          <div>
            <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
            <input type="text" id="captcha" name="captcha" required aria-required="true"
                   inputmode="numeric" autocomplete="off" style="width:6rem">
          </div>

          <button type="submit" style="margin-top:1rem">Odeslat odkaz</button>
        </fieldset>
      </form>
    <?php endif; ?>

  <?php else: ?>

    <?php if ($success): ?>
      <p role="status" style="color:#060">
        <strong>Heslo bylo úspěšně změněno.</strong>
        Nyní se můžete <a href="<?= BASE_URL ?>/public_login.php">přihlásit</a>.
      </p>
    <?php else: ?>
      <form method="post" novalidate aria-describedby="form-errors">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">

        <fieldset>
          <legend>Nastavení nového hesla</legend>

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

          <button type="submit" style="margin-top:1rem">Nastavit nové heslo</button>
        </fieldset>
      </form>
    <?php endif; ?>

  <?php endif; ?>

  <p><a href="<?= BASE_URL ?>/public_login.php">Zpět na přihlášení</a></p>
</main>

<?= siteFooter() ?>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}});</script>
</body>
</html>
