<?php
require_once __DIR__ . '/../db.php';

$redirect = adminLoginRedirectTarget(trim($_GET['redirect'] ?? $_POST['redirect'] ?? ''), BASE_URL . '/admin/index.php');
$cancel2fa = ($_GET['cancel_2fa'] ?? '') === '1';

if ($cancel2fa) {
    unset(
        $_SESSION['2fa_pending_user_id'],
        $_SESSION['2fa_pending_email'],
        $_SESSION['2fa_pending_superadmin'],
        $_SESSION['2fa_pending_role'],
        $_SESSION['2fa_pending_name'],
        $_SESSION['2fa_pending_redirect']
    );
}

if (isLoggedIn()) {
    if (isPublicUser()) {
        header('Location: ' . BASE_URL . '/public_profile.php');
    } else {
        header('Location: ' . $redirect);
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('login', 5, 300);
    verifyCsrf();

    $inputEmail = trim($_POST['email'] ?? '');
    $inputPass  = $_POST['heslo'] ?? '';

    $authenticated = false;

    // Primárně ověř přes cms_users
    try {
        $pdo  = db_connect();
        $stmt = $pdo->prepare(
            "SELECT id, password, first_name, last_name, nickname, is_superadmin, role, totp_secret
             FROM cms_users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$inputEmail]);
        $userRow = $stmt->fetch();

        if ($userRow && password_verify($inputPass, $userRow['password'])) {
            $role = $userRow['role'] ?? 'admin';
            // Veřejní uživatelé se nemohou přihlásit do administrace
            if ($role === 'public') {
                $error = 'Tento účet nemá přístup do administrace. Použijte veřejné přihlášení.';
            } elseif (!empty($userRow['totp_secret'])) {
                // 2FA aktivní – uložit do session a přesměrovat na ověření
                $_SESSION['2fa_pending_user_id'] = (int)$userRow['id'];
                $_SESSION['2fa_pending_email'] = $inputEmail;
                $_SESSION['2fa_pending_superadmin'] = (bool)$userRow['is_superadmin'];
                $_SESSION['2fa_pending_role'] = $role;
                $_SESSION['2fa_pending_redirect'] = $redirect;
                $name = $userRow['nickname'] !== '' ? $userRow['nickname']
                      : trim($userRow['first_name'] . ' ' . $userRow['last_name']);
                $_SESSION['2fa_pending_name'] = $name !== '' ? $name : $inputEmail;
                header('Location: ' . BASE_URL . '/admin/login_2fa.php');
                exit;
            } else {
                $name = $userRow['nickname'] !== '' ? $userRow['nickname']
                      : trim($userRow['first_name'] . ' ' . $userRow['last_name']);
                if ($name === '') $name = $inputEmail;
                loginUser((int)$userRow['id'], $inputEmail, (bool)$userRow['is_superadmin'], $name, $role);
                $authenticated = true;
            }
        }
    } catch (\PDOException $e) {
        // cms_users ještě neexistuje – fallback na admin_password ze settings
        $adminEmail = getSetting('admin_email', '');
        $hash       = getSetting('admin_password', '');
        if ($inputEmail === $adminEmail && $hash !== '' && password_verify($inputPass, $hash)) {
            loginUser(0, $inputEmail, true, $inputEmail);
            $authenticated = true;
        }
    }

    if ($authenticated) {
        header('Location: ' . $redirect);
        exit;
    }

    sleep(1);
    $error = 'Nesprávný e-mail nebo heslo.';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Přihlášení – Administrace</title>
  <style nonce="<?= cspNonce() ?>">
    :root {
      --login-bg: #ffffff;
      --login-text: #1a1a2e;
      --login-error: #c00;
      --login-focus: #005fcc;
      --login-input-bg: #fff;
      --login-input-border: #aaa;
      --login-btn-bg: #f8fafc;
      --login-btn-text: #102a43;
      --login-btn-border: #c6d0db;
    }
    @media (prefers-color-scheme: dark) {
      :root {
        --login-bg: #1a1a2e;
        --login-text: #e0e0e0;
        --login-error: #f08080;
        --login-focus: #4da6ff;
        --login-input-bg: #252540;
        --login-input-border: #4a4a65;
        --login-btn-bg: #252540;
        --login-btn-text: #e0e0e0;
        --login-btn-border: #4a4a65;
      }
    }
    body { font-family: system-ui, sans-serif; max-width: 380px; margin: 4rem auto; padding: 0 1rem; background: var(--login-bg); color: var(--login-text); }
    label { display: block; margin-top: 1rem; font-weight: bold; }
    input { width: 100%; padding: .4rem; margin-top: .25rem; box-sizing: border-box; background: var(--login-input-bg); color: var(--login-text); border: 1px solid var(--login-input-border); }
    button { margin-top: 1.5rem; padding: .5rem 1.5rem; background: var(--login-btn-bg); color: var(--login-btn-text); border: 1px solid var(--login-btn-border); cursor: pointer; }
    .error { color: var(--login-error); }
    :focus-visible { outline: 3px solid var(--login-focus); outline-offset: 2px; }
    .skip-link { position:absolute; left:-999px; top:auto; width:1px; height:1px; overflow:hidden; z-index:999; }
    .skip-link:focus { position:fixed; top:0; left:0; width:auto; height:auto; padding:.75rem 1.5rem; background:var(--login-focus); color:#fff; text-decoration:none; z-index:9999; }
  </style>
</head>
<body>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<main id="obsah">
  <h1>Přihlášení do administrace</h1>

  <?php if ($error !== ''): ?>
    <p class="error" role="alert"><?= h($error) ?></p>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
    <fieldset>
      <legend>Přihlašovací údaje</legend>

      <label for="email">E-mail <span aria-hidden="true">*</span></label>
      <input type="email" id="email" name="email" required aria-required="true" autocomplete="username"
             value="<?= h($_POST['email'] ?? '') ?>">

      <label for="heslo">Heslo <span aria-hidden="true">*</span></label>
      <input type="password" id="heslo" name="heslo" required aria-required="true" autocomplete="current-password">

      <button type="submit">Přihlásit se</button>
    </fieldset>
  </form>
</main>
</body>
</html>
