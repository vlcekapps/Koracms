<?php
require_once __DIR__ . '/../db.php';

// Musí mít pending 2FA session
if (empty($_SESSION['2fa_pending_user_id'])) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

$error = '';
$userId = (int)$_SESSION['2fa_pending_user_id'];
$redirect = adminLoginRedirectTarget((string)($_SESSION['2fa_pending_redirect'] ?? ''), BASE_URL . '/admin/index.php');
$backToLoginUrl = BASE_URL . '/admin/login.php?cancel_2fa=1&redirect=' . urlencode($redirect);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('login_2fa', 5, 300);
    verifyCsrf();
    rateLimitSubject('login_2fa_user', (string)$userId, 5, 300);

    $code = trim($_POST['totp_code'] ?? '');
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT totp_secret FROM cms_users WHERE id = ?");
    $stmt->execute([$userId]);
    $secret = $stmt->fetchColumn();

    if ($secret && totpVerify($secret, $code)) {
        loginUser(
            $userId,
            $_SESSION['2fa_pending_email'],
            $_SESSION['2fa_pending_superadmin'],
            $_SESSION['2fa_pending_name'],
            $_SESSION['2fa_pending_role']
        );
        unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_email'],
            $_SESSION['2fa_pending_superadmin'], $_SESSION['2fa_pending_name'],
            $_SESSION['2fa_pending_role'], $_SESSION['2fa_pending_redirect']);
        header('Location: ' . $redirect);
        exit;
    }

    $error = 'Nesprávný ověřovací kód. Zkuste to znovu.';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dvoufázové ověření – Administrace</title>
<?= adminLoginStylesheetTag() ?>
</head>
<body>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<main id="obsah">
  <h1>Dvoufázové ověření</h1>
  <p>Zadejte 6místný kód z vaší autentizační aplikace (FreeOTP, Authy, Google Authenticator).</p>

  <?php if ($error !== ''): ?>
    <p class="error" role="alert"><?= h($error) ?></p>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <fieldset>
      <legend>Ověřovací kód</legend>

      <label for="totp_code">Kód <span aria-hidden="true">*</span></label>
      <input type="text" id="totp_code" name="totp_code" required aria-required="true"
             autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}"
             maxlength="6" placeholder="000000" class="totp-code-input">

      <button type="submit">Ověřit</button>
    </fieldset>
  </form>

  <p class="login-secondary-action"><a href="<?= h($backToLoginUrl) ?>">Zpět na přihlášení</a></p>
</main>
</body>
</html>
