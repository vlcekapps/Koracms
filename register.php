<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

// Přihlášený public uživatel → profil; admin → administrace
if (isLoggedIn()) {
    if (isPublicUser()) {
        header('Location: ' . BASE_URL . '/public_profile.php');
    } else {
        header('Location: ' . BASE_URL . '/admin/index.php');
    }
    exit;
}

$siteName = getSetting('site_name', 'Kora CMS');
$errors   = [];
$success  = false;
$resent   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('register', 3, 300);

    if (honeypotTriggered()) {
        $success = true; // předstíráme úspěch
    } else {
        verifyCsrf();

        $email      = trim($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $password2  = $_POST['password2'] ?? '';
        $firstName  = trim($_POST['first_name'] ?? '');
        $lastName   = trim($_POST['last_name'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Zadejte platnou e-mailovou adresu.';
        if ($firstName === '') $errors[] = 'Jméno je povinné.';
        if ($lastName === '')  $errors[] = 'Příjmení je povinné.';
        if ($phone === '')     $errors[] = 'Telefon je povinný.';
        if (strlen($password) < 8)
            $errors[] = 'Heslo musí mít alespoň 8 znaků.';
        if ($password !== $password2)
            $errors[] = 'Hesla se neshodují.';
        if (!captchaVerify($_POST['captcha'] ?? ''))
            $errors[] = 'Chybná odpověď na ověřovací otázku.';

        if (empty($errors)) {
            $pdo = db_connect();

            // Kontrola unikátnosti emailu
            $dup = $pdo->prepare("SELECT id, is_confirmed, confirmation_token FROM cms_users WHERE email = ?");
            $dup->execute([$email]);
            $existingUser = $dup->fetch();
            if ($existingUser) {
                if (!(int)$existingUser['is_confirmed'] && $existingUser['confirmation_token']) {
                    // Neaktivovaný účet — vygenerovat nový token a poslat znovu
                    $newToken = bin2hex(random_bytes(32));
                    $pdo->prepare("UPDATE cms_users SET confirmation_token = ? WHERE id = ?")
                        ->execute([$newToken, $existingUser['id']]);
                    $confirmUrl = siteUrl('/confirm_email.php?token=' . $newToken);
                    $subject    = 'Potvrďte registraci – ' . $siteName;
                    $body       = "Dobrý den,\n\n"
                                . "pro dokončení registrace na webu {$siteName} klikněte na odkaz:\n"
                                . $confirmUrl . "\n\n"
                                . "Pokud jste se neregistrovali, tento email ignorujte.\n\n"
                                . "— " . $siteName;
                    sendMail($email, $subject, $body);
                    $resent = true;
                } else {
                    $errors[] = 'Účet s tímto e-mailem již existuje.';
                }
            }
        }

        if (empty($errors)) {
            $pdo   = db_connect();
            $token = bin2hex(random_bytes(32));
            $hash  = password_hash($password, PASSWORD_BCRYPT);

            $pdo->prepare(
                "INSERT INTO cms_users (email, password, first_name, last_name, phone, role, is_superadmin, is_confirmed, confirmation_token, created_at)
                 VALUES (?, ?, ?, ?, ?, 'public', 0, 0, ?, NOW())"
            )->execute([$email, $hash, $firstName, $lastName, $phone, $token]);

            // Odeslat potvrzovací email
            $confirmUrl = siteUrl('/confirm_email.php?token=' . $token);
            $subject    = 'Potvrďte registraci – ' . $siteName;
            $body       = "Dobrý den,\n\n"
                        . "pro dokončení registrace na webu {$siteName} klikněte na odkaz:\n"
                        . $confirmUrl . "\n\n"
                        . "Pokud jste se neregistrovali, tento email ignorujte.\n\n"
                        . "— " . $siteName;
            sendMail($email, $subject, $body);

            $success = true;
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
<?= seoMeta(['title' => 'Registrace – ' . $siteName]) ?>
  <title>Registrace – <?= h($siteName) ?></title>
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
  <h2>Registrace</h2>

  <?php if ($resent): ?>
    <p role="status" aria-atomic="true" style="color:#060">
      <strong>Váš účet dosud nebyl aktivován.</strong>
      Odeslali jsme nový potvrzovací odkaz na váš e-mail.
    </p>
  <?php elseif ($success): ?>
    <p role="status" aria-atomic="true" style="color:#060">
      <strong>Na váš e-mail jsme odeslali potvrzovací odkaz.</strong>
      Klikněte prosím na odkaz v e-mailu pro dokončení registrace.
    </p>
  <?php else: ?>

    <?php if (!empty($errors)): ?>
      <ul id="form-errors" role="alert" style="color:#c00">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" novalidate <?php if (!empty($errors)): ?>aria-describedby="form-errors"<?php endif; ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <?= honeypotField() ?>

      <fieldset>
        <legend>Registrační údaje</legend>

        <div>
          <label for="first_name">Jméno <span aria-hidden="true">*</span></label>
          <input type="text" id="first_name" name="first_name" required aria-required="true"
                 maxlength="100" value="<?= h($_POST['first_name'] ?? '') ?>">
        </div>

        <div>
          <label for="last_name">Příjmení <span aria-hidden="true">*</span></label>
          <input type="text" id="last_name" name="last_name" required aria-required="true"
                 maxlength="100" value="<?= h($_POST['last_name'] ?? '') ?>">
        </div>

        <div>
          <label for="email">E-mail <span aria-hidden="true">*</span></label>
          <input type="email" id="email" name="email" required aria-required="true"
                 maxlength="255" value="<?= h($_POST['email'] ?? '') ?>">
        </div>

        <div>
          <label for="phone">Telefon <span aria-hidden="true">*</span></label>
          <input type="tel" id="phone" name="phone" required aria-required="true"
                 maxlength="20" value="<?= h($_POST['phone'] ?? '') ?>" aria-describedby="phone-hint">
          <small id="phone-hint">Nutné pro rezervace</small>
        </div>

        <div>
          <label for="password">Heslo (min. 8 znaků) <span aria-hidden="true">*</span></label>
          <input type="password" id="password" name="password" required aria-required="true"
                 minlength="8" autocomplete="new-password">
        </div>

        <div>
          <label for="password2">Heslo znovu <span aria-hidden="true">*</span></label>
          <input type="password" id="password2" name="password2" required aria-required="true"
                 minlength="8" autocomplete="new-password">
        </div>

        <div>
          <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
          <input type="text" id="captcha" name="captcha" required aria-required="true"
                 inputmode="numeric" autocomplete="off" style="width:6rem">
        </div>

        <button type="submit" style="margin-top:1rem">Zaregistrovat se</button>
      </fieldset>
    </form>

    <p>Již máte účet? <a href="<?= BASE_URL ?>/public_login.php">Přihlaste se</a></p>

  <?php endif; ?>
</main>

<?= siteFooter() ?>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}});</script>
</body>
</html>
