<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$accountId = inputInt('get', 'id');
$account = null;

if ($accountId !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_users WHERE id = ? AND is_superadmin = 0");
    $stmt->execute([$accountId]);
    $account = $stmt->fetch();
    if (!$account) {
        header('Location: users.php');
        exit;
    }
}

$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

$defaults = [
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'nickname' => '',
    'role' => 'author',
    'author_public_enabled' => 0,
    'author_slug' => '',
    'author_bio' => '',
    'author_avatar' => '',
    'author_website' => '',
];

if ($account === null) {
    $account = $defaults;
} else {
    $account = array_merge($defaults, $account);
}

if (!empty($formData)) {
    $account = array_merge($account, $formData);
}

$accountRole = normalizeUserRole((string)($account['role'] ?? 'author'));
$account['role'] = $accountRole;
$account = hydrateAuthorPresentation($account);
$authorFieldsetAvailable = $accountRole !== 'public';
$roleOptions = staffRoleOptions($accountRole);

adminHeader($accountId !== null ? 'Upravit uživatele' : 'Nový uživatel');
?>

<?php if (!empty($formErrors)): ?>
  <ul class="error" role="alert">
    <?php foreach ($formErrors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" action="user_save.php" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($accountId !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Údaje účtu</legend>

    <label for="email">E-mail (pro přihlášení) <span aria-hidden="true">*</span></label>
    <input type="email" id="email" name="email" required aria-required="true"
           value="<?= h((string)$account['email']) ?>">

    <label for="first_name">Jméno</label>
    <input type="text" id="first_name" name="first_name" maxlength="100"
           value="<?= h((string)$account['first_name']) ?>">

    <label for="last_name">Příjmení</label>
    <input type="text" id="last_name" name="last_name" maxlength="100"
           value="<?= h((string)$account['last_name']) ?>">

    <label for="nickname">Přezdívka</label>
    <input type="text" id="nickname" name="nickname" maxlength="100" aria-describedby="nickname-help"
           value="<?= h((string)$account['nickname']) ?>">
    <small id="nickname-help" class="field-help">Zobrazí se místo jména a příjmení.</small>

    <label for="role">Role</label>
    <select id="role" name="role" aria-describedby="role-help">
      <?php foreach ($roleOptions as $roleKey => $roleLabel): ?>
        <option value="<?= h($roleKey) ?>" <?= $accountRole === $roleKey ? 'selected' : '' ?>>
          <?= h($roleLabel) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <small id="role-help" class="field-help">
      Autor pracuje s blogem a novinkami, editor navíc schvaluje obsah, moderátor řeší komentáře a zprávy,
      správce rezervací řeší rezervace a admin spravuje i nastavení a uživatele.
    </small>
  </fieldset>

  <fieldset style="margin-top:1.5rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend><?= $accountId !== null ? 'Změna hesla' : 'Heslo <span aria-hidden="true">*</span>' ?></legend>
    <?php if ($accountId !== null): ?>
      <small id="password-help" class="field-help" style="margin-top:0">Ponechte prázdné pro beze změny.</small>
    <?php endif; ?>
    <label for="new_pass">Heslo (min. 8 znaků)</label>
    <input type="password" id="new_pass" name="new_pass" minlength="8"
           autocomplete="new-password"<?= $accountId !== null ? ' aria-describedby="password-help"' : '' ?> <?= $accountId !== null ? '' : 'required aria-required="true"' ?>>

    <label for="new_pass2">Heslo znovu</label>
    <input type="password" id="new_pass2" name="new_pass2" minlength="8"
           autocomplete="new-password"<?= $accountId !== null ? ' aria-describedby="password-help"' : '' ?>>
  </fieldset>

  <fieldset id="author-fieldset" style="margin-top:1.5rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Veřejný autor</legend>

    <p id="author-role-note" style="margin-top:.35rem;color:#555;<?= $authorFieldsetAvailable ? 'display:none' : '' ?>">
      Tento účet patří mezi veřejné uživatele, proto nemůže mít veřejný autorský profil.
    </p>

    <div id="author-role-fields"<?= $authorFieldsetAvailable ? '' : ' hidden' ?>>
      <div>
        <input type="checkbox" id="author_public_enabled" name="author_public_enabled" value="1"
               <?= (int)($account['author_public_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
        <label for="author_public_enabled" style="display:inline;font-weight:normal">
          Zpřístupnit veřejný autorský profil
        </label>
      </div>

      <label for="author_slug">Slug veřejného autora <span aria-hidden="true">*</span></label>
      <input type="text" id="author_slug" name="author_slug" maxlength="255" pattern="[a-z0-9\-]+"
             aria-describedby="author-slug-help"
             value="<?= h((string)($account['author_slug'] ?? '')) ?>">
      <small id="author-slug-help" class="field-help">Používejte malá písmena, číslice a pomlčky.</small>

      <label for="author_bio">Krátké bio / medailonek</label>
      <textarea id="author_bio" name="author_bio" rows="6" aria-describedby="author-bio-help"><?= h((string)($account['author_bio'] ?? '')) ?></textarea>
      <small id="author-bio-help" class="field-help">Podporuje HTML i Markdown syntaxi.</small>

      <label for="author_website">Web autora</label>
      <input type="url" id="author_website" name="author_website" maxlength="255"
             aria-describedby="author-website-help"
             value="<?= h((string)($account['author_website'] ?? '')) ?>">
      <small id="author-website-help" class="field-help">Nepovinné pole pro osobní web nebo profil autora.</small>

      <label for="author_avatar">Avatar autora</label>
      <input type="file" id="author_avatar" name="author_avatar" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*"
             aria-describedby="author-avatar-help<?= !empty($account['author_avatar']) ? ' author-avatar-current' : '' ?>">
      <small id="author-avatar-help" class="field-help">Povolené formáty: JPEG, PNG, GIF, WebP nebo SVG.</small>
      <?php if (!empty($account['author_avatar'])): ?>
        <div id="author-avatar-current" class="field-help">
          Aktuální avatar:
          <img src="<?= BASE_URL ?>/uploads/authors/<?= rawurlencode((string)$account['author_avatar']) ?>"
               alt="Aktuální avatar autora" style="height:48px;width:48px;object-fit:cover;border-radius:999px;vertical-align:middle">
        </div>
      <?php endif; ?>

      <?php if (!empty($account['author_avatar'])): ?>
        <label for="author_avatar_delete" style="font-weight:normal;margin-top:.35rem">
          <input type="checkbox" id="author_avatar_delete" name="author_avatar_delete" value="1">
          Smazat stávající avatar
        </label>
      <?php endif; ?>

      <?php if ((int)($account['author_public_enabled'] ?? 0) === 1 && !empty($account['author_public_path'])): ?>
        <p style="margin-top:1rem">
          Veřejný profil:
          <a href="<?= h((string)$account['author_public_path']) ?>" target="_blank" rel="noopener noreferrer">
            <?= h((string)$account['author_public_path']) ?>
          </a>
        </p>
      <?php endif; ?>
    </div>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $accountId !== null ? 'Uložit' : 'Vytvořit účet' ?></button>
    <a href="users.php" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<script>
(function () {
    const roleInput = document.getElementById('role');
    const authorFieldsWrap = document.getElementById('author-role-fields');
    const authorRoleNote = document.getElementById('author-role-note');
    const authorInputs = authorFieldsWrap
        ? authorFieldsWrap.querySelectorAll('input, textarea')
        : [];
    const nicknameInput = document.getElementById('nickname');
    const firstNameInput = document.getElementById('first_name');
    const lastNameInput = document.getElementById('last_name');
    const emailInput = document.getElementById('email');
    const slugInput = document.getElementById('author_slug');
    let slugManual = <?= !empty($account['author_slug']) ? 'true' : 'false' ?>;

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const sourceValue = () => {
        const nickname = nicknameInput?.value.trim() ?? '';
        if (nickname !== '') {
            return nickname;
        }

        const firstName = firstNameInput?.value.trim() ?? '';
        const lastName = lastNameInput?.value.trim() ?? '';
        const fullName = [firstName, lastName].filter(Boolean).join(' ').trim();
        if (fullName !== '') {
            return fullName;
        }

        const email = emailInput?.value.trim() ?? '';
        return email.includes('@') ? email.split('@')[0] : email;
    };

    const syncAuthorRole = () => {
        const isPublicRole = roleInput?.value === 'public';
        if (authorRoleNote) {
            authorRoleNote.style.display = isPublicRole ? '' : 'none';
        }
        if (authorFieldsWrap) {
            authorFieldsWrap.hidden = isPublicRole;
        }
        authorInputs.forEach((input) => {
            input.disabled = isPublicRole;
        });
    };

    slugInput?.addEventListener('input', function () {
        slugManual = this.value.trim() !== '';
    });

    [nicknameInput, firstNameInput, lastNameInput, emailInput].forEach((input) => {
        input?.addEventListener('input', function () {
            if (slugManual || !slugInput) {
                return;
            }
            slugInput.value = slugify(sourceValue());
        });
    });

    roleInput?.addEventListener('change', syncAuthorRole);
    syncAuthorRole();
})();
</script>

<?php adminFooter(); ?>
