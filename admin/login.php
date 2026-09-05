<?php
require_once __DIR__ . '/../db.php';

$redirect = adminLoginRedirectTarget(trim($_GET['redirect'] ?? $_POST['redirect'] ?? ''), BASE_URL . '/admin/index.php');
$cancel2fa = ($_GET['cancel_2fa'] ?? '') === '1';

if ($cancel2fa) {
    clearPendingTwoFactorSession();
}

if (isLoggedIn()) {
    if (isPublicUser()) {
        header('Location: ' . BASE_URL . '/public_profile.php');
    } else {
        header('Location: ' . $redirect);
    }
    exit;
}

$error = (string)($_SESSION['auth_session_notice'] ?? '');
unset($_SESSION['auth_session_notice']);
$showReturnNotice = $redirect !== BASE_URL . '/admin/index.php';
$loginDescriptionIds = [];
if ($showReturnNotice) {
    $loginDescriptionIds[] = 'admin-login-return-info';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('login', 5, 300);
    verifyCsrf();

    $inputEmail = trim($_POST['email'] ?? '');
    $inputPass  = $_POST['heslo'] ?? '';
    rateLimitSubject('login_email', $inputEmail, 5, 900);

    $authenticated = false;

    // Primárně ověř přes cms_users
    try {
        $pdo  = db_connect();
        $stmt = $pdo->prepare(
            "SELECT *
             FROM cms_users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$inputEmail]);
        $userRow = $stmt->fetch();

        if ($userRow && userSessionAccountIsConfirmed($userRow) && password_verify($inputPass, $userRow['password'])) {
            $role = userSessionAccountRole($userRow);
            // Veřejní uživatelé se nemohou přihlásit do administrace
            if ($role === 'public') {
                $error = 'Tento účet nemá přístup do administrace. Použijte veřejné přihlášení.';
            } elseif (!empty($userRow['totp_secret'])) {
                // 2FA aktivní – uložit do session a přesměrovat na ověření
                session_regenerate_id(true);
                clearPendingTwoFactorSession();
                $_SESSION['2fa_pending_user_id'] = (int)$userRow['id'];
                $_SESSION['2fa_pending_issued_at'] = time();
                $_SESSION['2fa_pending_fingerprint'] = userSessionFingerprint($userRow);
                $_SESSION['2fa_pending_email'] = $inputEmail;
                $_SESSION['2fa_pending_superadmin'] = (bool)$userRow['is_superadmin'];
                $_SESSION['2fa_pending_role'] = $role;
                $_SESSION['2fa_pending_redirect'] = $redirect;
                $_SESSION['2fa_pending_name'] = userSessionDisplayName($userRow);
                header('Location: ' . BASE_URL . '/admin/login_2fa.php');
                exit;
            } else {
                $name = userSessionDisplayName($userRow);
                loginUser((int)$userRow['id'], (string)$userRow['email'], (bool)$userRow['is_superadmin'], $name, $role, userSessionFingerprint($userRow));
                $authenticated = true;
            }
        }
    } catch (\PDOException $e) {
        // Do not fall back to legacy credentials on unrelated schema/connection failures.
        if ((string)$e->getCode() === '42S02') {
            $adminEmail = getSetting('admin_email', '');
            $hash       = getSetting('admin_password', '');
            if ($inputEmail === $adminEmail && $hash !== '' && password_verify($inputPass, $hash)) {
                loginUser(0, $inputEmail, true, $inputEmail, 'admin', legacyUserSessionFingerprint($adminEmail, $hash));
                $authenticated = true;
            }
        }
    }

    if ($authenticated) {
        header('Location: ' . $redirect);
        exit;
    }

    sleep(1);
    $error = 'Nesprávný e-mail nebo heslo.';
}
if ($error !== '') {
    $loginDescriptionIds[] = 'admin-login-errors';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Přihlášení – Administrace</title>
<?= adminLoginStylesheetTag() ?>
</head>
<body>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<main id="obsah">
  <h1>Přihlášení do administrace</h1>

  <?php if ($error !== ''): ?>
    <div id="admin-login-errors" class="error" role="alert" aria-atomic="true" aria-labelledby="admin-login-errors-heading">
      <p id="admin-login-errors-heading"><?= h($error) ?></p>
    </div>
  <?php endif; ?>

  <?php if ($showReturnNotice): ?>
    <div id="admin-login-return-info" class="login-info" role="status" aria-atomic="true">
      <p><strong>Po přihlášení vás vrátíme na původní administrační stránku.</strong></p>
      <p>Pokud se přihlašujete po vypršení session, rozepsaný formulář může po návratu nabídnout lokální záložní koncept.</p>
    </div>
  <?php endif; ?>

  <form method="post" novalidate<?php if ($loginDescriptionIds !== []): ?> aria-describedby="<?= h(implode(' ', $loginDescriptionIds)) ?>"<?php endif; ?>>
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
