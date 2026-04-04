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
