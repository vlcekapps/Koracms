<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$accountId = currentUserId();
$success = false;
$errors = [];

$stmt = $pdo->prepare("SELECT * FROM cms_users WHERE id = ?");
$stmt->execute([$accountId]);
$currentRow = $stmt->fetch();

if (!$currentRow) {
    $currentRow = [
        'id' => 0,
        'email' => $_SESSION['cms_user_email'] ?? '',
        'first_name' => '',
        'last_name' => '',
        'nickname' => '',
        'author_public_enabled' => 0,
        'author_slug' => '',
        'author_bio' => '',
        'author_avatar' => '',
        'author_website' => '',
        'is_superadmin' => isSuperAdmin() ? 1 : 0,
    ];
}

$currentRow = hydrateAuthorPresentation($currentRow);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newPass = $_POST['new_pass'] ?? '';
    $newPass2 = $_POST['new_pass2'] ?? '';
    $authorPublicEnabled = isset($_POST['author_public_enabled']) ? 1 : 0;
    $submittedAuthorSlug = trim($_POST['author_slug'] ?? '');
    $authorBio = trim($_POST['author_bio'] ?? '');
    $authorWebsiteInput = trim($_POST['author_website'] ?? '');
    $deleteAuthorAvatar = isset($_POST['author_avatar_delete']);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Zadejte platnou e-mailovou adresu.';
    }
    if ($newPass !== '' && strlen($newPass) < 8) {
        $errors[] = 'Nové heslo musí mít alespoň 8 znaků.';
    }
    if ($newPass !== $newPass2) {
        $errors[] = 'Hesla se neshodují.';
    }

    if (empty($errors) && $accountId) {
        $duplicateStmt = $pdo->prepare("SELECT id FROM cms_users WHERE email = ? AND id != ?");
        $duplicateStmt->execute([$email, $accountId]);
        if ($duplicateStmt->fetch()) {
            $errors[] = 'Tento e-mail již používá jiný účet.';
        }
    }

    $authorWebsite = '';
    if ($authorWebsiteInput !== '') {
        $authorWebsite = normalizeAuthorWebsite($authorWebsiteInput);
        if ($authorWebsite === '') {
            $errors[] = 'Web autora musí být platná adresa začínající na http:// nebo https://.';
        }
    }

    $authorSlugSource = $submittedAuthorSlug !== ''
        ? $submittedAuthorSlug
        : authorSlugCandidate([
            'nickname' => $nickname,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
        ]);
    $authorSlug = authorSlug($authorSlugSource);
    if ($authorSlug === '') {
        $errors[] = 'Slug veřejného autora je povinný.';
    }

    if (empty($errors) && $accountId) {
        $uniqueAuthorSlug = uniqueAuthorSlug($pdo, $authorSlug, $accountId);
        if ($submittedAuthorSlug !== '' && $uniqueAuthorSlug !== $authorSlug) {
            $errors[] = 'Zvolený slug veřejného autora už používá jiný účet.';
        } else {
            $authorSlug = $uniqueAuthorSlug;
        }
    }

    $authorAvatarFilename = (string)($currentRow['author_avatar'] ?? '');
    if (empty($errors) && $accountId) {
        $avatarUpload = storeUploadedAuthorAvatar(
            $_FILES['author_avatar'] ?? [],
            $authorAvatarFilename
        );
        if ($avatarUpload['error'] !== '') {
            $errors[] = $avatarUpload['error'];
        } else {
            $authorAvatarFilename = (string)$avatarUpload['filename'];
        }
    }

    if (empty($errors) && $accountId) {
        if ($deleteAuthorAvatar && $authorAvatarFilename !== '' && empty($_FILES['author_avatar']['name'])) {
            deleteAuthorAvatarFile($authorAvatarFilename);
            $authorAvatarFilename = '';
        }

        $setClauses = "first_name=?, last_name=?, nickname=?, email=?, author_public_enabled=?, author_slug=?, author_bio=?, author_avatar=?, author_website=?";
        $params = [
            $firstName,
            $lastName,
            $nickname,
            $email,
            $authorPublicEnabled,
            $authorSlug,
            $authorBio,
            $authorAvatarFilename,
            $authorWebsite,
        ];
        if ($newPass !== '') {
            $setClauses .= ", password=?";
            $params[] = password_hash($newPass, PASSWORD_BCRYPT);
        }
        $params[] = $accountId;
        $pdo->prepare("UPDATE cms_users SET {$setClauses} WHERE id=?")->execute($params);

        $displayName = $nickname !== '' ? $nickname : trim($firstName . ' ' . $lastName);
        if ($displayName === '') {
            $displayName = $email;
        }
        $_SESSION['cms_user_name'] = $displayName;
        $_SESSION['cms_user_email'] = $email;

        logAction('profile_update');
        $success = true;
        $currentRow = array_merge($currentRow, [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'nickname' => $nickname,
            'email' => $email,
            'author_public_enabled' => $authorPublicEnabled,
            'author_slug' => $authorSlug,
            'author_bio' => $authorBio,
            'author_avatar' => $authorAvatarFilename,
            'author_website' => $authorWebsite,
        ]);
        $currentRow = hydrateAuthorPresentation($currentRow);
    }
}

$authorProfileUrl = (string)($currentRow['author_public_path'] ?? '');

adminHeader('Můj profil');
?>

<?php if ($success): ?>
  <p class="success" role="status">Profil byl uložen.</p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <ul class="error" role="alert">
    <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($currentRow['is_superadmin']): ?>
  <p><strong>Hlavní administrátor</strong></p>
<?php else: ?>
  <p><strong>Role:</strong> <?= h(userRoleLabel((string)($currentRow['role'] ?? currentUserRole()))) ?></p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Osobní údaje</legend>

    <label for="first_name">Jméno</label>
    <input type="text" id="first_name" name="first_name" maxlength="100"
           value="<?= h($currentRow['first_name']) ?>">

    <label for="last_name">Příjmení</label>
    <input type="text" id="last_name" name="last_name" maxlength="100"
           value="<?= h($currentRow['last_name']) ?>">

    <label for="nickname">Přezdívka</label>
    <input type="text" id="nickname" name="nickname" maxlength="100" aria-describedby="profile-nickname-help"
           value="<?= h($currentRow['nickname']) ?>">
    <small id="profile-nickname-help" class="field-help">Zobrazí se místo jména a příjmení.</small>

    <label for="email">E-mail (pro přihlášení) <span aria-hidden="true">*</span></label>
    <input type="email" id="email" name="email" required aria-required="true"
           autocomplete="email" value="<?= h($currentRow['email']) ?>">
  </fieldset>

  <fieldset style="margin-top:1.5rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Změna hesla</legend>
    <small id="profile-password-help" class="field-help" style="margin-top:0">Vyplňte jen pokud chcete změnit.</small>
    <label for="new_pass">Nové heslo (min. 8 znaků)</label>
    <input type="password" id="new_pass" name="new_pass" minlength="8" autocomplete="new-password" aria-describedby="profile-password-help">

    <label for="new_pass2">Nové heslo znovu</label>
    <input type="password" id="new_pass2" name="new_pass2" minlength="8" autocomplete="new-password" aria-describedby="profile-password-help">
  </fieldset>

  <fieldset style="margin-top:1.5rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Veřejný autor</legend>

    <div>
      <input type="checkbox" id="author_public_enabled" name="author_public_enabled" value="1"
             <?= (int)($currentRow['author_public_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
      <label for="author_public_enabled" style="display:inline;font-weight:normal">
        Zpřístupnit veřejný autorský profil
      </label>
    </div>

    <label for="author_slug">Slug veřejného autora <span aria-hidden="true">*</span></label>
    <input type="text" id="author_slug" name="author_slug" maxlength="255" pattern="[a-z0-9\-]+"
           aria-describedby="profile-author-slug-help"
           value="<?= h((string)($currentRow['author_slug'] ?? '')) ?>">
    <small id="profile-author-slug-help" class="field-help">Používejte malá písmena, číslice a pomlčky.</small>

    <label for="author_bio">Krátké bio / medailonek</label>
    <textarea id="author_bio" name="author_bio" rows="6" aria-describedby="profile-author-bio-help"><?= h((string)($currentRow['author_bio'] ?? '')) ?></textarea>
    <small id="profile-author-bio-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
    <?php renderAdminContentReferencePicker('author_bio'); ?>

    <label for="author_website">Web autora</label>
    <input type="url" id="author_website" name="author_website" maxlength="255"
           aria-describedby="profile-author-website-help"
           value="<?= h((string)($currentRow['author_website'] ?? '')) ?>">
    <small id="profile-author-website-help" class="field-help">Nepovinné pole pro osobní web nebo profil autora.</small>

    <label for="author_avatar">Avatar autora</label>
    <input type="file" id="author_avatar" name="author_avatar" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*"
           aria-describedby="profile-author-avatar-help<?= !empty($currentRow['author_avatar']) ? ' profile-author-avatar-current' : '' ?>">
    <small id="profile-author-avatar-help" class="field-help">Povolené formáty: JPEG, PNG, GIF, WebP nebo SVG.</small>
    <?php if (!empty($currentRow['author_avatar'])): ?>
      <div id="profile-author-avatar-current" class="field-help">
        Aktuální avatar:
        <img src="<?= BASE_URL ?>/uploads/authors/<?= rawurlencode($currentRow['author_avatar']) ?>"
             alt="Aktuální avatar autora" style="height:48px;width:48px;object-fit:cover;border-radius:999px;vertical-align:middle">
      </div>
    <?php endif; ?>

    <?php if (!empty($currentRow['author_avatar'])): ?>
      <label for="author_avatar_delete" style="font-weight:normal;margin-top:.35rem">
        <input type="checkbox" id="author_avatar_delete" name="author_avatar_delete" value="1">
        Smazat stávající avatar
      </label>
    <?php endif; ?>

    <?php if ((int)($currentRow['author_public_enabled'] ?? 0) === 1 && $authorProfileUrl !== ''): ?>
      <p style="margin-top:1rem">
        Veřejný profil:
        <a href="<?= h($authorProfileUrl) ?>" target="_blank" rel="noopener noreferrer"><?= h($authorProfileUrl) ?></a>
      </p>
    <?php endif; ?>
  </fieldset>

  <button type="submit" style="margin-top:1.5rem">Uložit profil</button>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
    const nicknameInput = document.getElementById('nickname');
    const firstNameInput = document.getElementById('first_name');
    const lastNameInput = document.getElementById('last_name');
    const emailInput = document.getElementById('email');
    const slugInput = document.getElementById('author_slug');
    let slugManual = <?= !empty($currentRow['author_slug']) ? 'true' : 'false' ?>;

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
})();
</script>

<?php adminFooter(); ?>
