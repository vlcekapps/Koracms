<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo     = db_connect();
$userId  = currentUserId();
$success = false;
$errors  = [];

// Načteme aktuálního uživatele
$stmt = $pdo->prepare("SELECT * FROM cms_users WHERE id = ?");
$stmt->execute([$userId]);
$me = $stmt->fetch();

if (!$me) {
    // Fallback: superadmin přihlášen bez cms_users (před migrací)
    $me = [
        'id' => 0, 'email' => $_SESSION['cms_user_email'] ?? '',
        'first_name' => '', 'last_name' => '', 'nickname' => '',
        'is_superadmin' => isSuperAdmin() ? 1 : 0,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $nickname  = trim($_POST['nickname']   ?? '');
    $email     = trim($_POST['email']      ?? '');
    $newPass   = $_POST['new_pass']        ?? '';
    $newPass2  = $_POST['new_pass2']       ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Zadejte platnou e-mailovou adresu.';
    if ($newPass !== '' && strlen($newPass) < 8)
        $errors[] = 'Nové heslo musí mít alespoň 8 znaků.';
    if ($newPass !== $newPass2)
        $errors[] = 'Hesla se neshodují.';

    // Zkontroluj unikátnost emailu (kromě vlastního)
    if (empty($errors) && $userId) {
        $dup = $pdo->prepare("SELECT id FROM cms_users WHERE email = ? AND id != ?");
        $dup->execute([$email, $userId]);
        if ($dup->fetch()) $errors[] = 'Tento e-mail již používá jiný účet.';
    }

    if (empty($errors) && $userId) {
        $setClauses = "first_name=?, last_name=?, nickname=?, email=?";
        $params     = [$firstName, $lastName, $nickname, $email];
        if ($newPass !== '') {
            $setClauses .= ", password=?";
            $params[]    = password_hash($newPass, PASSWORD_BCRYPT);
        }
        $params[] = $userId;
        $pdo->prepare("UPDATE cms_users SET {$setClauses} WHERE id=?")->execute($params);

        // Aktualizuj session
        $displayName = $nickname !== '' ? $nickname : trim($firstName . ' ' . $lastName);
        if ($displayName === '') $displayName = $email;
        $_SESSION['cms_user_name']  = $displayName;
        $_SESSION['cms_user_email'] = $email;

        logAction('profile_update');
        $success = true;
        $me = array_merge($me, [
            'first_name' => $firstName, 'last_name' => $lastName,
            'nickname' => $nickname, 'email' => $email,
        ]);
    }
}

adminHeader('Můj profil');
?>

<?php if ($success): ?>
  <p class="success" role="status">Profil byl uložen.</p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <ul class="error" role="alert">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($me['is_superadmin']): ?>
  <p><strong>Hlavní administrátor</strong></p>
<?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <label for="first_name">Jméno</label>
  <input type="text" id="first_name" name="first_name" maxlength="100"
         value="<?= h($me['first_name']) ?>">

  <label for="last_name">Příjmení</label>
  <input type="text" id="last_name" name="last_name" maxlength="100"
         value="<?= h($me['last_name']) ?>">

  <label for="nickname">Přezdívka <small>(zobrazí se místo jména/příjmení)</small></label>
  <input type="text" id="nickname" name="nickname" maxlength="100"
         value="<?= h($me['nickname']) ?>">

  <label for="email">E-mail (pro přihlášení) <span aria-hidden="true">*</span></label>
  <input type="email" id="email" name="email" required aria-required="true"
         value="<?= h($me['email']) ?>">

  <fieldset style="margin-top:1.5rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Změna hesla <small>(vyplňte jen pokud chcete změnit)</small></legend>
    <label for="new_pass">Nové heslo (min. 8 znaků)</label>
    <input type="password" id="new_pass" name="new_pass" minlength="8" autocomplete="new-password">

    <label for="new_pass2">Nové heslo znovu</label>
    <input type="password" id="new_pass2" name="new_pass2" minlength="8" autocomplete="new-password">
  </fieldset>

  <button type="submit" style="margin-top:1.5rem">Uložit profil</button>
</form>

<?php adminFooter(); ?>
