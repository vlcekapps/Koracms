<?php
require_once __DIR__ . '/../db.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/index.php');
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
            "SELECT id, password, first_name, last_name, nickname, is_superadmin
             FROM cms_users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$inputEmail]);
        $user = $stmt->fetch();

        if ($user && password_verify($inputPass, $user['password'])) {
            $name = $user['nickname'] !== '' ? $user['nickname']
                  : trim($user['first_name'] . ' ' . $user['last_name']);
            if ($name === '') $name = $inputEmail;
            loginUser((int)$user['id'], $inputEmail, (bool)$user['is_superadmin'], $name);
            $authenticated = true;
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
        header('Location: ' . BASE_URL . '/admin/index.php');
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
  <style>
    body { font-family: system-ui, sans-serif; max-width: 380px; margin: 4rem auto; padding: 0 1rem; }
    label { display: block; margin-top: 1rem; font-weight: bold; }
    input { width: 100%; padding: .4rem; margin-top: .25rem; box-sizing: border-box; }
    button { margin-top: 1.5rem; padding: .5rem 1.5rem; }
    .error { color: #c00; }
  </style>
</head>
<body>
<main>
  <h1>Přihlášení do administrace</h1>

  <?php if ($error !== ''): ?>
    <p class="error" role="alert"><?= h($error) ?></p>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

    <label for="email">E-mail <span aria-hidden="true">*</span></label>
    <input type="email" id="email" name="email" required aria-required="true" autocomplete="username"
           value="<?= h($_POST['email'] ?? '') ?>">

    <label for="heslo">Heslo <span aria-hidden="true">*</span></label>
    <input type="password" id="heslo" name="heslo" required aria-required="true" autocomplete="current-password">

    <button type="submit">Přihlásit se</button>
  </form>
</main>
</body>
</html>
