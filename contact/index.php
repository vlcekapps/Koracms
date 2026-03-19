<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('contact')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo       = db_connect();
$siteName  = getSetting('site_name', 'Kora CMS');
$destEmail = getSetting('contact_email', '');

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('contact', 3, 120);

    if (honeypotTriggered()) {
        $success = true;
        // Předstíráme úspěch, ale nic neukládáme
    } else {
    verifyCsrf();

    $from    = trim($_POST['from']    ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Zadejte platnou e-mailovou adresu odesílatele.';
    if ($subject === '') $errors[] = 'Předmět je povinný.';
    if ($message === '') $errors[] = 'Zpráva je povinná.';
    if (!captchaVerify($_POST['captcha'] ?? ''))
        $errors[] = 'Chybná odpověď na ověřovací otázku.';

    if (empty($errors)) {
        // Uložit do databáze
        $pdo->prepare(
            "INSERT INTO cms_contact (sender_email, subject, message) VALUES (?,?,?)"
        )->execute([$from, $subject, $message]);

        // Odeslat e-mail, pokud je nastaven cílový e-mail
        if ($destEmail !== '') {
            $safeSubject = preg_replace('/[\r\n]/', '', $subject);
            $safeFrom    = preg_replace('/[\r\n]/', '', $from);
            $headers     = "Content-Type: text/plain; charset=UTF-8\r\n"
                         . "Reply-To: {$safeFrom}\r\n"
                         . "From: {$safeFrom}\r\n";
            $body = "Zpráva z kontaktního formuláře.\n\nOd: {$safeFrom}\nPředmět: {$safeSubject}\n\n{$message}";
            mail($destEmail, $safeSubject, $body, $headers);
        }

        $success = true;
    }
    } // end honeypot else
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kontakt – <?= h($siteName) ?></title>
</head>
<body>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('contact') ?>
</header>

<main id="obsah">
  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0"></div>
  <h2>Kontakt</h2>

  <?php $captchaExpr = captchaGenerate(); ?>
  <?php if ($success): ?>
    <p role="status" style="color:#060">
      Zpráva byla odeslána. Děkujeme!
    </p>
  <?php else: ?>

    <?php if (!empty($errors)): ?>
      <ul id="form-errors" role="alert" style="color:#c00">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" novalidate aria-describedby="form-errors">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <?= honeypotField() ?>

      <fieldset>
        <legend>Kontaktní formulář</legend>
        <div>
          <label for="from">Váš e-mail <span aria-hidden="true">*</span></label>
          <input type="email" id="from" name="from" required aria-required="true"
                 maxlength="255" value="<?= h($_POST['from'] ?? '') ?>">
        </div>
        <div>
          <label for="subject">Předmět <span aria-hidden="true">*</span></label>
          <input type="text" id="subject" name="subject" required aria-required="true"
                 maxlength="255" value="<?= h($_POST['subject'] ?? '') ?>">
        </div>
        <div>
          <label for="message">Zpráva <span aria-hidden="true">*</span></label>
          <textarea id="message" name="message" rows="8" required
                    aria-required="true"><?= h($_POST['message'] ?? '') ?></textarea>
        </div>
        <div>
          <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
          <input type="text" id="captcha" name="captcha" required aria-required="true"
                 inputmode="numeric" autocomplete="off" style="width:6rem">
        </div>
        <button type="submit">Odeslat zprávu</button>
      </fieldset>
    </form>

  <?php endif; ?>
</main>

<footer>
  <p>© <?= date('Y') ?> <?= h($siteName) ?></p>
</footer>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);}});</script>
</body>
</html>
