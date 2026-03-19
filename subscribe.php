<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

if (!isModuleEnabled('newsletter')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$siteName = getSetting('site_name', 'Kora CMS');
$state    = 'form'; // form | ok | exists | error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('subscribe', 3, 300);

    if (honeypotTriggered()) {
        $state = 'ok'; // předstíráme úspěch
    } else {
        verifyCsrf();
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $state = 'error';
        } else {
            $pdo   = db_connect();
            $token = bin2hex(random_bytes(32));

            try {
                $pdo->prepare(
                    "INSERT INTO cms_subscribers (email, token, confirmed) VALUES (?, ?, 0)"
                )->execute([$email, $token]);

                // Odeslat potvrzovací email
                $confirmUrl = BASE_URL . '/subscribe_confirm.php?token=' . $token;
                $subject    = 'Potvrďte přihlášení k odběru – ' . $siteName;
                $body       = "Dobrý den,\n\n"
                            . "pro potvrzení odběru novinek webu {$siteName} klikněte na odkaz:\n"
                            . $confirmUrl . "\n\n"
                            . "Pokud jste se k odběru nepřihlásili, tento email ignorujte.\n\n"
                            . "— " . $siteName;
                $headers    = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n"
                            . "Content-Type: text/plain; charset=UTF-8\r\n";
                mail($email, $subject, $body, $headers);
                $state = 'ok';
            } catch (\PDOException $e) {
                // Duplicate entry = email již existuje
                $state = 'exists';
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
  <title>Odběr novinek – <?= h($siteName) ?></title>
</head>
<body>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav() ?>
</header>

<main id="obsah">
  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0"></div>
  <h1>Přihlášení k odběru novinek</h1>

  <?php if ($state === 'ok'): ?>
    <p role="status" style="color:#060">
      <strong>Téměř hotovo!</strong> Na vaši adresu jsme odeslali potvrzovací email.
      Klikněte prosím na odkaz v emailu.
    </p>
  <?php elseif ($state === 'exists'): ?>
    <p role="status" style="color:#060">Tato adresa je již přihlášena k odběru.</p>
  <?php elseif ($state === 'error'): ?>
    <p id="form-errors" role="alert" style="color:#c00">Zadejte platnou e-mailovou adresu.</p>
  <?php endif; ?>

  <?php if ($state === 'form' || $state === 'error'): ?>
  <form method="post" novalidate aria-describedby="form-errors">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <?= honeypotField() ?>
    <fieldset>
      <legend>Přihlášení k odběru</legend>

      <label for="email">Váš e-mail <span aria-hidden="true">*</span></label>
      <input type="email" id="email" name="email" required aria-required="true"
             maxlength="255" value="<?= h($_POST['email'] ?? '') ?>"
             style="max-width:360px">

      <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
      <input type="text" id="captcha" name="captcha" required aria-required="true"
             inputmode="numeric" autocomplete="off" style="max-width:6rem">

      <button type="submit" style="margin-top:1rem">Přihlásit k odběru</button>
    </fieldset>
  </form>
  <?php endif; ?>
</main>

<?= siteFooter() ?>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);}});</script>
</body>
</html>
