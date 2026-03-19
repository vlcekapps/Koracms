<?php
require_once __DIR__ . '/layout.php';
requireSuperAdmin();

$pdo  = db_connect();
$id   = inputInt('get', 'id');
$user = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_users WHERE id = ? AND is_superadmin = 0");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) { header('Location: users.php'); exit; }
}

// Chyby z user_save.php přes session
$formErrors = $_SESSION['form_errors'] ?? [];
$formData   = $_SESSION['form_data']   ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

// Předvyplnění z POST dat (po chybě)
if (!empty($formData) && !$user) {
    $user = $formData + ['email'=>'','first_name'=>'','last_name'=>'','nickname'=>''];
} elseif (!empty($formData)) {
    $user = array_merge($user, $formData);
}

adminHeader($user ? 'Upravit spolupracovníka' : 'Nový spolupracovník');
?>

<?php if (!empty($formErrors)): ?>
  <ul class="error" role="alert">
    <?php foreach ($formErrors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" action="user_save.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($user): ?>
    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Údaje účtu</legend>

    <label for="email">E-mail (pro přihlášení) <span aria-hidden="true">*</span></label>
    <input type="email" id="email" name="email" required aria-required="true"
           value="<?= h($user['email'] ?? '') ?>">

    <label for="first_name">Jméno</label>
    <input type="text" id="first_name" name="first_name" maxlength="100"
           value="<?= h($user['first_name'] ?? '') ?>">

    <label for="last_name">Příjmení</label>
    <input type="text" id="last_name" name="last_name" maxlength="100"
           value="<?= h($user['last_name'] ?? '') ?>">

    <label for="nickname">Přezdívka <small>(zobrazí se místo jména/příjmení)</small></label>
    <input type="text" id="nickname" name="nickname" maxlength="100"
           value="<?= h($user['nickname'] ?? '') ?>">
  </fieldset>

  <fieldset style="margin-top:1.5rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend><?= $user ? 'Změna hesla <small>(ponechte prázdné pro beze změny)</small>' : 'Heslo <span aria-hidden="true">*</span>' ?></legend>
    <label for="new_pass">Heslo (min. 8 znaků)</label>
    <input type="password" id="new_pass" name="new_pass" minlength="8"
           autocomplete="new-password" <?= $user ? '' : 'required aria-required="true"' ?>>

    <label for="new_pass2">Heslo znovu</label>
    <input type="password" id="new_pass2" name="new_pass2" minlength="8"
           autocomplete="new-password">
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $user ? 'Uložit' : 'Vytvořit účet' ?></button>
    <a href="users.php" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<?php adminFooter(); ?>
