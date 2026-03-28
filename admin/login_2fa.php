<?php
require_once __DIR__ . '/../db.php';

// Musí mít pending 2FA session
if (empty($_SESSION['2fa_pending_user_id'])) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}

$error = '';
$userId = (int)$_SESSION['2fa_pending_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('login_2fa', 5, 300);
    verifyCsrf();

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
              $_SESSION['2fa_pending_role']);
        header('Location: ' . BASE_URL . '/admin/index.php');
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
  <style nonce="<?= cspNonce() ?>">
    body { font-family: system-ui, sans-serif; max-width: 380px; margin: 4rem auto; padding: 0 1rem; }
    label { display: block; margin-top: 1rem; font-weight: bold; }
    input { width: 100%; padding: .4rem; margin-top: .25rem; box-sizing: border-box; }
    button { margin-top: 1.5rem; padding: .5rem 1.5rem; }
    .error { color: #c00; }
  </style>
</head>
<body>
<main>
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
             maxlength="6" placeholder="000000" style="font-size:1.5rem;text-align:center;letter-spacing:.3rem">

      <button type="submit">Ověřit</button>
    </fieldset>
  </form>

  <p style="margin-top:2rem"><a href="login.php">Zpět na přihlášení</a></p>
</main>
</body>
</html>
