<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$accountId = currentUserId();
$success = false;
$errors = [];
$fieldErrors = [];
$enableTwoFactorRequested = false;
$totpSetupSecret = '';

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
    $enableTwoFactorRequested = isset($_POST['enable_2fa']) && $_POST['enable_2fa'] === '1';
    $totpSetupSecret = trim($_POST['totp_secret'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Zadejte platnou e-mailovou adresu.';
        $fieldErrors[] = 'email';
    }
    if ($newPass !== '' && strlen($newPass) < 8) {
        $errors[] = 'Nové heslo musí mít alespoň 8 znaků.';
        $fieldErrors[] = 'new_pass';
    }
    if ($newPass !== $newPass2) {
        $errors[] = 'Hesla se neshodují.';
        $fieldErrors[] = 'new_pass';
        $fieldErrors[] = 'new_pass2';
    }

    if (empty($errors) && $accountId) {
        $duplicateStmt = $pdo->prepare("SELECT id FROM cms_users WHERE email = ? AND id != ?");
        $duplicateStmt->execute([$email, $accountId]);
        if ($duplicateStmt->fetch()) {
            $errors[] = 'Tento e-mail již používá jiný účet.';
            $fieldErrors[] = 'email';
        }
    }

    $authorWebsite = '';
    if ($authorWebsiteInput !== '') {
        $authorWebsite = normalizeAuthorWebsite($authorWebsiteInput);
        if ($authorWebsite === '') {
            $errors[] = 'Web autora musí být platná veřejná adresa. Lze zadat i doménu bez schématu, CMS ji uloží jako https://.';
            $fieldErrors[] = 'author_website';
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
        $fieldErrors[] = 'author_slug';
    }

    if (empty($errors) && $accountId) {
        $uniqueAuthorSlug = uniqueAuthorSlug($pdo, $authorSlug, $accountId);
        if ($submittedAuthorSlug !== '' && $uniqueAuthorSlug !== $authorSlug) {
            $errors[] = 'Zvolený slug veřejného autora už používá jiný účet.';
            $fieldErrors[] = 'author_slug';
        } else {
            $authorSlug = $uniqueAuthorSlug;
        }
    }

    $authorAvatarFilename = (string)($currentRow['author_avatar'] ?? '');

    if (empty($errors) && $accountId) {
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

        $updatedTotpSecret = (string)($currentRow['totp_secret'] ?? '');

        // 2FA TOTP
        if ($enableTwoFactorRequested) {
            $totpSecret = $totpSetupSecret;
            $totpVerifyCode = trim($_POST['totp_verify'] ?? '');
            if ($totpSecret !== '' && $totpVerifyCode !== '' && totpVerify($totpSecret, $totpVerifyCode)) {
                $setClauses .= ", totp_secret=?";
                $params[] = $totpSecret;
                $updatedTotpSecret = $totpSecret;
            } else {
                $errors[] = 'Ověřovací kód nesouhlasí. 2FA nebylo aktivováno.';
                $fieldErrors[] = 'totp_verify';
            }
        }
        if (isset($_POST['disable_2fa']) && $_POST['disable_2fa'] === '1') {
            $setClauses .= ", totp_secret=NULL";
            $updatedTotpSecret = '';
        }

        if (empty($errors)) {
            $avatarUpload = storeUploadedAuthorAvatar(
                $_FILES['author_avatar'] ?? [],
                $authorAvatarFilename
            );
            if ($avatarUpload['error'] !== '') {
                $errors[] = $avatarUpload['error'];
                $fieldErrors[] = 'author_avatar';
            } else {
                $authorAvatarFilename = (string)$avatarUpload['filename'];
                $params[7] = $authorAvatarFilename;
            }
        }

        if (empty($errors)) {
            if ($deleteAuthorAvatar && $authorAvatarFilename !== '' && empty($_FILES['author_avatar']['name'])) {
                deleteAuthorAvatarFile($authorAvatarFilename);
                $authorAvatarFilename = '';
                $params[7] = $authorAvatarFilename;
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
            $currentRow['totp_secret'] = $updatedTotpSecret;
        }
    }

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

$authorProfileUrl = (string)($currentRow['author_public_path'] ?? '');
$fieldErrors = array_values(array_unique($fieldErrors));
$fieldErrorMessages = [
    'email' => 'Zadejte platnou a jedinečnou e-mailovou adresu.',
    'new_pass' => 'Nové heslo musí mít alespoň 8 znaků.',
    'new_pass2' => 'Kontrolní heslo se musí shodovat s novým heslem.',
    'author_slug' => 'Zadejte jedinečný slug veřejného autora.',
    'author_website' => 'Zadejte platnou veřejnou webovou adresu, například https://example.com.',
    'author_avatar' => 'Nahrajte avatar ve formátu JPEG, PNG, GIF nebo WebP.',
    'totp_verify' => 'Zadejte platný šestimístný ověřovací kód.',
];

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
           autocomplete="email" value="<?= h($currentRow['email']) ?>"<?= adminFieldAttributes('email', $fieldErrors) ?>>
    <?php adminRenderFieldError('email', $fieldErrors, [], $fieldErrorMessages['email']); ?>
  </fieldset>

  <fieldset class="admin-fieldset-card admin-fieldset-spaced">
    <legend>Změna hesla</legend>
    <small id="profile-password-help" class="field-help field-help--flush">Vyplňte jen pokud chcete změnit.</small>
    <label for="new_pass">Nové heslo (min. 8 znaků)</label>
    <input type="password" id="new_pass" name="new_pass" minlength="8" autocomplete="new-password"<?= adminFieldAttributes('new_pass', $fieldErrors, [], ['profile-password-help']) ?>>
    <?php adminRenderFieldError('new_pass', $fieldErrors, [], $fieldErrorMessages['new_pass']); ?>

    <label for="new_pass2">Nové heslo znovu</label>
    <input type="password" id="new_pass2" name="new_pass2" minlength="8" autocomplete="new-password"<?= adminFieldAttributes('new_pass2', $fieldErrors, [], ['profile-password-help']) ?>>
    <?php adminRenderFieldError('new_pass2', $fieldErrors, [], $fieldErrorMessages['new_pass2']); ?>
  </fieldset>

  <fieldset class="admin-fieldset-card admin-fieldset-spaced">
    <legend>Dvoufázové ověření (2FA)</legend>
    <?php $hasTOTP = !empty($currentRow['totp_secret']); ?>
    <?php if ($hasTOTP): ?>
      <p class="success"><strong>✓ TOTP ověření je aktivní.</strong></p>
      <p class="field-help">Při přihlášení budete vyzváni k zadání kódu z autentizační aplikace.</p>
      <label class="admin-checkbox-label">
        <input type="checkbox" name="disable_2fa" value="1"> Deaktivovat dvoufázové ověření
      </label>
    <?php else: ?>
      <p class="field-help">Zvyšte zabezpečení svého účtu. Použijte FreeOTP, Authy, Google Authenticator nebo jinou TOTP aplikaci.</p>
      <label class="admin-checkbox-label">
        <input type="checkbox" name="enable_2fa" value="1" id="enable_2fa_check"<?= $enableTwoFactorRequested ? ' checked' : '' ?>> Aktivovat dvoufázové ověření
      </label>
      <div id="totp-setup" class="admin-profile-totp-panel"<?= $enableTwoFactorRequested ? '' : ' hidden' ?>>
        <?php
          $totpSetupSecret = $totpSetupSecret !== '' ? $totpSetupSecret : totpGenerateSecret();
        $totpUri = totpUri($totpSetupSecret, $currentRow['email'], getSetting('site_name', 'Kora CMS'));
        $qrUrl = totpQrUrl($totpUri);
        ?>
        <input type="hidden" name="totp_secret" value="<?= h($totpSetupSecret) ?>">
        <p><strong>1.</strong> Naskenujte QR kód v autentizační aplikaci:</p>
        <img src="<?= h($qrUrl) ?>" alt="QR kód pro TOTP nastavení" class="admin-totp-qr">
        <p><strong>2.</strong> Nebo ručně zadejte klíč: <code class="admin-code-break"><?= h($totpSetupSecret) ?></code></p>
        <p><strong>3.</strong> Zadejte ověřovací kód z aplikace pro potvrzení:</p>
        <label for="totp_verify">Ověřovací kód <span aria-hidden="true">*</span></label>
        <input type="text" id="totp_verify" name="totp_verify" inputmode="numeric" pattern="[0-9]{6}"
               maxlength="6" placeholder="000000" autocomplete="one-time-code"
               class="admin-totp-code-input" value="<?= h((string)($_POST['totp_verify'] ?? '')) ?>"<?= adminFieldAttributes('totp_verify', $fieldErrors) ?>>
        <?php adminRenderFieldError('totp_verify', $fieldErrors, [], $fieldErrorMessages['totp_verify']); ?>
      </div>
      <script nonce="<?= cspNonce() ?>">
      document.getElementById('enable_2fa_check')?.addEventListener('change', function(){
        const setup = document.getElementById('totp-setup');
        if (setup) {
          setup.hidden = !this.checked;
        }
      });
      </script>
    <?php endif; ?>
  </fieldset>

  <fieldset class="admin-fieldset-card admin-fieldset-spaced">
    <legend>Veřejný autor</legend>

    <div>
      <input type="checkbox" id="author_public_enabled" name="author_public_enabled" value="1"
             <?= (int)($currentRow['author_public_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
      <label for="author_public_enabled" class="admin-checkbox-label">
        Zpřístupnit veřejný autorský profil
      </label>
    </div>

    <label for="author_slug">Slug veřejného autora <span aria-hidden="true">*</span></label>
    <input type="text" id="author_slug" name="author_slug" maxlength="255" pattern="[a-z0-9\-]+"
           value="<?= h((string)($currentRow['author_slug'] ?? '')) ?>"<?= adminFieldAttributes('author_slug', $fieldErrors, [], ['profile-author-slug-help']) ?>>
    <small id="profile-author-slug-help" class="field-help">Používejte malá písmena, číslice a pomlčky.</small>
    <?php adminRenderFieldError('author_slug', $fieldErrors, [], $fieldErrorMessages['author_slug']); ?>

    <label for="author_bio">Krátké bio / medailonek</label>
    <textarea id="author_bio" name="author_bio" rows="6" aria-describedby="profile-author-bio-help"><?= h((string)($currentRow['author_bio'] ?? '')) ?></textarea>
    <small id="profile-author-bio-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
    <?php renderAdminContentReferencePicker('author_bio'); ?>

    <label for="author_website">Web autora</label>
    <input type="url" id="author_website" name="author_website" maxlength="255"
           value="<?= h((string)($currentRow['author_website'] ?? '')) ?>"<?= adminFieldAttributes('author_website', $fieldErrors, [], ['profile-author-website-help']) ?>>
    <small id="profile-author-website-help" class="field-help">Nepovinné pole pro osobní web nebo profil autora.</small>
    <?php adminRenderFieldError('author_website', $fieldErrors, [], $fieldErrorMessages['author_website']); ?>

    <label for="author_avatar">Avatar autora</label>
    <input type="file" id="author_avatar" name="author_avatar" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
           <?= adminFieldAttributes('author_avatar', $fieldErrors, [], !empty($currentRow['author_avatar']) ? ['profile-author-avatar-help', 'profile-author-avatar-current'] : ['profile-author-avatar-help']) ?>>
    <small id="profile-author-avatar-help" class="field-help">Povolené formáty: JPEG, PNG, GIF nebo WebP.</small>
    <?php adminRenderFieldError('author_avatar', $fieldErrors, [], $fieldErrorMessages['author_avatar']); ?>
    <?php if (!empty($currentRow['author_avatar'])): ?>
      <div id="profile-author-avatar-current" class="field-help">
        Aktuální avatar:
        <img src="<?= BASE_URL ?>/uploads/authors/<?= rawurlencode($currentRow['author_avatar']) ?>"
             alt="Aktuální avatar autora" class="admin-avatar-preview">
      </div>
    <?php endif; ?>

    <?php if (!empty($currentRow['author_avatar'])): ?>
      <div class="admin-check-row">
        <label for="author_avatar_delete" class="admin-checkbox-label">
          <input type="checkbox" id="author_avatar_delete" name="author_avatar_delete" value="1">
          Smazat stávající avatar
        </label>
      </div>
    <?php endif; ?>

    <?php if ((int)($currentRow['author_public_enabled'] ?? 0) === 1 && $authorProfileUrl !== ''): ?>
      <p class="admin-action-row">
        Veřejný profil:
        <a href="<?= h($authorProfileUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= h(newWindowLinkLabel($authorProfileUrl, 'veřejný profil')) ?>"><?= h($authorProfileUrl) ?></a>
      </p>
    <?php endif; ?>
  </fieldset>

  <div class="admin-action-row">
    <button type="submit">Uložit profil</button>
  </div>
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
