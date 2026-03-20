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

$siteName     = getSetting('site_name', 'Kora CMS');
$errors       = [];
$notConfirmed = false;
$redirect     = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('public_login', 5, 300);
    verifyCsrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Vyplňte e-mail a heslo.';
    } else {
        $pdo  = db_connect();
        $stmt = $pdo->prepare(
            "SELECT id, email, password, first_name, last_name, is_superadmin, is_confirmed
             FROM cms_users
             WHERE email = ? AND role = 'public'"
        );
        $stmt->execute([$email]);
        $userRow = $stmt->fetch();

        if ($userRow && password_verify($password, $userRow['password'])) {
            if (!(int)$userRow['is_confirmed']) {
                $notConfirmed = true;
            } else {
                $displayName = trim($userRow['first_name'] . ' ' . $userRow['last_name']);
                if ($displayName === '') $displayName = $userRow['email'];
                loginUser(
                    (int)$userRow['id'],
                    $userRow['email'],
                    (bool)$userRow['is_superadmin'],
                    $displayName,
                    'public'
                );

                $target = ($redirect !== '') ? $redirect : BASE_URL . '/public_profile.php';
                header('Location: ' . $target);
                exit;
            }
        } else {
            $errors[] = 'Nesprávný e-mail nebo heslo.';
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
<?= seoMeta(['title' => 'Přihlášení – ' . $siteName]) ?>
  <title>Přihlášení – <?= h($siteName) ?></title>
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
  <h2>Přihlášení</h2>

  <?php if ($notConfirmed): ?>
    <div id="form-errors" role="alert" aria-atomic="true" style="color:#9a6700;border:2px solid #c60;padding:.6rem 1rem;border-radius:4px;margin-bottom:1rem">
      <p><strong>Váš účet dosud nebyl aktivován.</strong></p>
      <p>Zkontrolujte e-mail s potvrzovacím odkazem, nebo se
        <a href="<?= h(BASE_URL) ?>/register.php">zaregistrujte znovu</a>
        pro odeslání nového odkazu.</p>
    </div>
  <?php elseif (!empty($errors)): ?>
    <ul id="form-errors" role="alert" aria-atomic="true" style="color:#c00">
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="post" novalidate <?php if ($notConfirmed || !empty($errors)): ?>aria-describedby="form-errors"<?php endif; ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="redirect" value="<?= h($redirect) ?>">

    <fieldset>
      <legend>Přihlašovací údaje</legend>

      <div>
        <label for="email">E-mail <span aria-hidden="true">*</span></label>
        <input type="email" id="email" name="email" required aria-required="true"
               maxlength="255" value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email">
      </div>

      <div>
        <label for="password">Heslo <span aria-hidden="true">*</span></label>
        <input type="password" id="password" name="password" required aria-required="true"
               autocomplete="current-password">
      </div>

      <button type="submit" style="margin-top:1rem">Přihlásit se</button>
    </fieldset>
  </form>

  <p>
    Nemáte účet? <a href="<?= BASE_URL ?>/register.php">Zaregistrujte se</a><br>
    <a href="<?= BASE_URL ?>/reset_password.php">Zapomenuté heslo</a>
  </p>
</main>

<?= siteFooter() ?>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}});</script>
</body>
</html>
