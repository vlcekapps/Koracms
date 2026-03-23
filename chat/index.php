<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('chat')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('chat', 5, 120);

    if (honeypotTriggered()) {
        header('Location: ' . BASE_URL . '/chat/index.php');
        exit;
    }

    verifyCsrf();

    if (!captchaVerify($_POST['captcha'] ?? ''))
        $errors[] = 'Nesprávná odpověď na ověřovací příklad.';

    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $web     = trim($_POST['web']     ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '')    $errors[] = 'Jméno je povinný údaj.';
    if ($message === '') $errors[] = 'Zpráva je povinný údaj.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Neplatná e-mailová adresa.';
    if ($web !== '' && !filter_var($web, FILTER_VALIDATE_URL))
        $web = '';

    if (empty($errors)) {
        $pdo->prepare(
            "INSERT INTO cms_chat (name, email, web, message) VALUES (?,?,?,?)"
        )->execute([$name, $email, $web, $message]);

        header('Location: ' . BASE_URL . '/chat/index.php');
        exit;
    }
}

// Vždy vygenerujeme čerstvý příklad pro formulář
$captchaExpr = captchaGenerate();

$messages = $pdo->query(
    "SELECT name, email, web, message, created_at FROM cms_chat ORDER BY created_at DESC LIMIT 50"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chat – <?= h($siteName) ?></title>
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
  <?= siteNav('chat') ?>
</header>

<main id="obsah">
  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0"></div>
  <h2>Chat</h2>

  <section aria-labelledby="zpravy-nadpis">
    <h3 id="zpravy-nadpis">Zprávy</h3>
    <?php if (empty($messages)): ?>
      <p>Zatím žádné zprávy. Buďte první!</p>
    <?php else: ?>
      <?php foreach ($messages as $m): ?>
        <article>
          <header>
            <strong><?= h($m['name']) ?></strong>
            <?php if ($m['email'] !== ''): ?>
              – <a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a>
            <?php endif; ?>
            <?php if ($m['web'] !== ''): ?>
              – <a href="<?= h($m['web']) ?>" rel="nofollow noopener"><?= h($m['web']) ?></a>
            <?php endif; ?>
            <time datetime="<?= h(str_replace(' ', 'T', $m['created_at'])) ?>"
                  style="margin-left:1rem; font-size:.85em">
              <?= formatCzechDate($m['created_at']) ?>
            </time>
          </header>
          <p><?= nl2br(h($m['message'])) ?></p>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section aria-labelledby="pridat-nadpis">
    <h3 id="pridat-nadpis">Přidat zprávu</h3>

    <?php if (!empty($errors)): ?>
      <ul id="form-errors" role="alert">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" novalidate<?php if (!empty($errors)): ?> aria-describedby="form-errors"<?php endif; ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <?= honeypotField() ?>
      <fieldset>
        <legend>Přidat zprávu</legend>
        <div>
          <label for="name">Jméno <span aria-hidden="true">*</span></label>
          <input type="text" id="name" name="name" required maxlength="100"
                 aria-required="true" value="<?= h($_POST['name'] ?? '') ?>">
        </div>
        <div>
          <label for="email">E-mail <small>(nepovinný)</small></label>
          <input type="email" id="email" name="email" maxlength="255"
                 value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <div>
          <label for="web">Web <small>(nepovinný)</small></label>
          <input type="url" id="web" name="web" maxlength="255"
                 value="<?= h($_POST['web'] ?? '') ?>">
        </div>
        <div>
          <label for="message">Zpráva <span aria-hidden="true">*</span></label>
          <textarea id="message" name="message" rows="5" required
                    aria-required="true"><?= h($_POST['message'] ?? '') ?></textarea>
        </div>
        <div>
          <label for="captcha">
            Ověření: kolik je <?= h($captchaExpr) ?>?
            <span aria-hidden="true">*</span>
          </label>
          <input type="text" id="captcha" name="captcha" required
                 aria-required="true" inputmode="numeric"
                 autocomplete="off" style="max-width:6rem">
        </div>
        <button type="submit">Odeslat zprávu</button>
      </fieldset>
    </form>
  </section>
</main>

<footer>
  <p>© <?= date('Y') ?> <?= h($siteName) ?></p>
</footer>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}});</script>
</body>
</html>
