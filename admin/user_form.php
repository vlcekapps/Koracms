<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$accountId = inputInt('get', 'id');
$account = null;
$publicRegistrationEnabled = publicRegistrationEnabled();

if ($accountId === null && !$publicRegistrationEnabled) {
    requireSuperAdmin();
}

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
$formErrorFields = $_SESSION['form_error_fields'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_error_fields'], $_SESSION['form_data']);

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
$fieldErrorMessages = [
    'email' => 'Zadejte úplnou e-mailovou adresu ve tvaru jmeno@example.cz. Adresa musí být jedinečná, protože slouží k přihlášení.',
    'new_pass' => $accountId !== null
        ? 'Zadejte nové heslo dlouhé alespoň 8 znaků, nebo pole ponechte prázdné, pokud heslo nechcete měnit.'
        : 'Zadejte heslo dlouhé alespoň 8 znaků.',
    'new_pass2' => 'Zopakujte stejné heslo jako v poli Heslo.',
    'author_slug' => 'Použijte jedinečný slug z malých písmen, číslic a pomlček, nebo upravte jméno či přezdívku pro automatické vytvoření.',
    'author_website' => 'Zadejte veřejnou http/https adresu, například https://example.com, nebo doménu bez schématu.',
    'author_avatar' => 'Nahrajte avatar ve formátu JPEG, PNG, GIF nebo WebP; SVG a jiné formáty CMS nepřijímá. Pokud avatar nechcete měnit, nechte pole prázdné.',
];

adminHeader($accountId !== null ? 'Upravit uživatelský účet' : 'Nový uživatelský účet');
?>

<?php if (!empty($formErrors)): ?>
  <ul class="error" role="alert" id="user-form-errors" aria-atomic="true">
    <?php foreach ($formErrors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<p><a href="users.php"><span aria-hidden="true">←</span> Zpět na uživatele a role</a></p>

<?php if ($accountId === null && !$publicRegistrationEnabled): ?>
  <p class="field-help">Veřejná registrace je vypnutá. Nový účet proto může ručně přidávat jen hlavní administrátor.</p>
<?php endif; ?>

<form method="post" action="user_save.php" enctype="multipart/form-data" novalidate<?= !empty($formErrors) ? ' aria-describedby="user-form-errors"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($accountId !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Údaje účtu</legend>

    <label for="email">E-mail (pro přihlášení) <span aria-hidden="true">*</span></label>
    <input type="email" id="email" name="email" required aria-required="true"
           autocomplete="email" value="<?= h((string)$account['email']) ?>"<?= adminFieldAttributes('email', $formErrorFields) ?>>
    <?php adminRenderFieldError('email', $formErrorFields, [], $fieldErrorMessages['email']); ?>

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

  <fieldset class="admin-fieldset-card admin-fieldset-spaced">
    <legend><?= $accountId !== null ? 'Změna hesla' : 'Heslo <span aria-hidden="true">*</span>' ?></legend>
    <?php if ($accountId !== null): ?>
      <small id="password-help" class="field-help field-help--flush">Ponechte prázdné pro beze změny.</small>
    <?php endif; ?>
    <label for="new_pass">Heslo (min. 8 znaků)</label>
    <input type="password" id="new_pass" name="new_pass" minlength="8"
           autocomplete="new-password"<?= adminFieldAttributes('new_pass', $formErrorFields, [], $accountId !== null ? ['password-help'] : []) ?> <?= $accountId !== null ? '' : 'required aria-required="true"' ?>>
    <?php adminRenderFieldError('new_pass', $formErrorFields, [], $fieldErrorMessages['new_pass']); ?>

    <label for="new_pass2">Heslo znovu</label>
    <input type="password" id="new_pass2" name="new_pass2" minlength="8"
           autocomplete="new-password"<?= adminFieldAttributes('new_pass2', $formErrorFields, [], $accountId !== null ? ['password-help'] : []) ?>>
    <?php adminRenderFieldError('new_pass2', $formErrorFields, [], $fieldErrorMessages['new_pass2']); ?>
  </fieldset>

  <fieldset id="author-fieldset" class="admin-fieldset-card admin-fieldset-spaced">
    <legend>Veřejný autor</legend>

    <p id="author-role-note" class="admin-description admin-description--muted admin-field-row"<?= $authorFieldsetAvailable ? ' hidden' : '' ?>>
      Tento účet patří mezi veřejné uživatele, proto nemůže mít veřejný autorský profil.
    </p>

    <div id="author-role-fields"<?= $authorFieldsetAvailable ? '' : ' hidden' ?>>
      <div>
        <input type="checkbox" id="author_public_enabled" name="author_public_enabled" value="1"
               <?= (int)($account['author_public_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
        <label for="author_public_enabled" class="admin-checkbox-label">
          Zpřístupnit veřejný autorský profil
        </label>
      </div>

      <label for="author_slug">Slug veřejného autora <span aria-hidden="true">*</span></label>
      <input type="text" id="author_slug" name="author_slug" maxlength="255" pattern="[a-z0-9\-]+"
             value="<?= h((string)($account['author_slug'] ?? '')) ?>"<?= adminFieldAttributes('author_slug', $formErrorFields, [], ['author-slug-help']) ?>>
      <small id="author-slug-help" class="field-help">Adresa autora se vyplní automaticky podle jména nebo přezdívky. Použijte malá písmena, číslice a pomlčky.</small>
      <?php adminRenderFieldError('author_slug', $formErrorFields, [], $fieldErrorMessages['author_slug']); ?>

      <label for="author_bio">Krátké bio / medailonek</label>
      <textarea id="author_bio" name="author_bio" rows="6" aria-describedby="author-bio-help"><?= h((string)($account['author_bio'] ?? '')) ?></textarea>
      <small id="author-bio-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
      <?php renderAdminContentReferencePicker('author_bio'); ?>

      <label for="author_website">Web autora</label>
      <input type="url" id="author_website" name="author_website" maxlength="255"
             value="<?= h((string)($account['author_website'] ?? '')) ?>"<?= adminFieldAttributes('author_website', $formErrorFields, [], ['author-website-help']) ?>>
      <small id="author-website-help" class="field-help">Nepovinné pole pro osobní web nebo profil autora.</small>
      <?php adminRenderFieldError('author_website', $formErrorFields, [], $fieldErrorMessages['author_website']); ?>

      <label for="author_avatar">Avatar autora</label>
      <input type="file" id="author_avatar" name="author_avatar" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
             <?= adminFieldAttributes('author_avatar', $formErrorFields, [], !empty($account['author_avatar']) ? ['author-avatar-help', 'author-avatar-current'] : ['author-avatar-help']) ?>>
      <small id="author-avatar-help" class="field-help">Povolené formáty: JPEG, PNG, GIF nebo WebP.</small>
      <?php adminRenderFieldError('author_avatar', $formErrorFields, [], $fieldErrorMessages['author_avatar']); ?>
      <?php if (!empty($account['author_avatar'])): ?>
        <div id="author-avatar-current" class="field-help">
          Aktuální avatar:
          <img src="<?= BASE_URL ?>/uploads/authors/<?= rawurlencode((string)$account['author_avatar']) ?>"
               alt="Aktuální avatar autora" class="admin-avatar-preview">
        </div>
      <?php endif; ?>

      <?php if (!empty($account['author_avatar'])): ?>
        <div class="admin-field-row">
          <label for="author_avatar_delete" class="admin-checkbox-label">
            <input type="checkbox" id="author_avatar_delete" name="author_avatar_delete" value="1">
            Smazat stávající avatar
          </label>
        </div>
      <?php endif; ?>

      <?php if ((int)($account['author_public_enabled'] ?? 0) === 1 && !empty($account['author_public_path'])): ?>
        <p class="admin-action-row">
          Veřejný profil:
          <a href="<?= h((string)$account['author_public_path']) ?>" target="_blank" rel="noopener noreferrer">
            <?= h((string)$account['author_public_path']) ?>
            <?= newWindowLinkSrOnlySuffix() ?>
          </a>
        </p>
      <?php endif; ?>
    </div>
  </fieldset>

  <div class="button-row admin-fieldset-spaced">
    <button type="submit" class="btn"><?= $accountId !== null ? 'Uložit změny' : 'Vytvořit účet' ?></button>
    <a href="users.php">Zrušit</a>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
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
            authorRoleNote.hidden = !isPublicRole;
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
