<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo = db_connect();
$accountId = inputInt('post', 'id');
$email = trim($_POST['email'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$nickname = trim($_POST['nickname'] ?? '');
$newPass = $_POST['new_pass'] ?? '';
$newPass2 = $_POST['new_pass2'] ?? '';
$submittedRole = normalizeUserRole($_POST['role'] ?? 'author');

$existingAccount = null;
if ($accountId === null && !publicRegistrationEnabled()) {
    requireSuperAdmin();
}

if ($accountId !== null) {
    $existingStmt = $pdo->prepare("SELECT * FROM cms_users WHERE id = ? AND is_superadmin = 0");
    $existingStmt->execute([$accountId]);
    $existingAccount = $existingStmt->fetch() ?: null;
    if (!$existingAccount) {
        header('Location: ' . BASE_URL . '/admin/users.php');
        exit;
    }
}

$accountRole = $submittedRole;
$authorFieldsAllowed = $accountRole !== 'public';
$errors = [];
$errorFields = [];

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Zadejte platnou e-mailovou adresu.';
    $errorFields[] = 'email';
}
if ($accountId === null && ($newPass === '' || strlen($newPass) < 8)) {
    $errors[] = 'Heslo je povinné a musí mít alespoň 8 znaků.';
    $errorFields[] = 'new_pass';
}
if ($newPass !== '' && strlen($newPass) < 8) {
    $errors[] = 'Heslo musí mít alespoň 8 znaků.';
    $errorFields[] = 'new_pass';
}
if ($newPass !== $newPass2) {
    $errors[] = 'Hesla se neshodují.';
    $errorFields[] = 'new_pass';
    $errorFields[] = 'new_pass2';
}

if (empty($errors)) {
    if ($accountId !== null) {
        $duplicateStmt = $pdo->prepare("SELECT id FROM cms_users WHERE email = ? AND id != ?");
        $duplicateStmt->execute([$email, $accountId]);
    } else {
        $duplicateStmt = $pdo->prepare("SELECT id FROM cms_users WHERE email = ?");
        $duplicateStmt->execute([$email]);
    }
    if ($duplicateStmt->fetch()) {
        $errors[] = 'Tento e-mail již používá jiný účet.';
        $errorFields[] = 'email';
    }
}

$authorPublicEnabled = (int)($existingAccount['author_public_enabled'] ?? 0);
$authorSlug = (string)($existingAccount['author_slug'] ?? '');
$authorBio = (string)($existingAccount['author_bio'] ?? '');
$authorAvatarFilename = (string)($existingAccount['author_avatar'] ?? '');
$authorWebsite = (string)($existingAccount['author_website'] ?? '');

if ($authorFieldsAllowed) {
    $authorPublicEnabled = isset($_POST['author_public_enabled']) ? 1 : 0;
    $submittedAuthorSlug = trim($_POST['author_slug'] ?? '');
    $authorBio = trim($_POST['author_bio'] ?? '');
    $authorWebsiteInput = trim($_POST['author_website'] ?? '');
    $deleteAuthorAvatar = isset($_POST['author_avatar_delete']);

    if ($authorWebsiteInput !== '') {
        $authorWebsite = normalizeAuthorWebsite($authorWebsiteInput);
        if ($authorWebsite === '') {
            $errors[] = 'Web autora musí být platná adresa začínající na http:// nebo https://.';
            $errorFields[] = 'author_website';
        }
    } else {
        $authorWebsite = '';
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
        $errorFields[] = 'author_slug';
    }

    if (empty($errors)) {
        $uniqueAuthorSlug = uniqueAuthorSlug($pdo, $authorSlug, $accountId);
        if ($submittedAuthorSlug !== '' && $uniqueAuthorSlug !== $authorSlug) {
            $errors[] = 'Zvolený slug veřejného autora už používá jiný účet.';
            $errorFields[] = 'author_slug';
        } else {
            $authorSlug = $uniqueAuthorSlug;
        }
    }

    if (empty($errors)) {
        $avatarUpload = storeUploadedAuthorAvatar(
            $_FILES['author_avatar'] ?? [],
            $authorAvatarFilename
        );
        if ($avatarUpload['error'] !== '') {
            $errors[] = $avatarUpload['error'];
            $errorFields[] = 'author_avatar';
        } else {
            $authorAvatarFilename = (string)$avatarUpload['filename'];
        }
    }

    if (empty($errors) && $deleteAuthorAvatar && $authorAvatarFilename !== '' && empty($_FILES['author_avatar']['name'])) {
        deleteAuthorAvatarFile($authorAvatarFilename);
        $authorAvatarFilename = '';
    }
} else {
    $authorPublicEnabled = 0;
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_error_fields'] = array_values(array_unique($errorFields));
    $_SESSION['form_data'] = $_POST;
    header('Location: user_form.php' . ($accountId ? '?id=' . $accountId : ''));
    exit;
}

if ($accountId !== null) {
    $setClauses = "email=?, first_name=?, last_name=?, nickname=?, role=?, author_public_enabled=?, author_slug=?, author_bio=?, author_avatar=?, author_website=?";
    $params = [
        $email,
        $firstName,
        $lastName,
        $nickname,
        $accountRole,
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
    $pdo->prepare("UPDATE cms_users SET {$setClauses} WHERE id=? AND is_superadmin=0")->execute($params);
    logAction('user_edit', "id={$accountId} email={$email} role={$accountRole}");
} else {
    $pdo->prepare(
        "INSERT INTO cms_users (
            email, password, first_name, last_name, nickname, role, is_superadmin,
            author_public_enabled, author_slug, author_bio, author_avatar, author_website
         ) VALUES (?,?,?,?,?,?,0,?,?,?,?,?)"
    )->execute([
        $email,
        password_hash($newPass, PASSWORD_BCRYPT),
        $firstName,
        $lastName,
        $nickname,
        $accountRole,
        $authorPublicEnabled,
        $authorSlug,
        $authorBio,
        $authorAvatarFilename,
        $authorWebsite,
    ]);
    logAction('user_add', "email={$email} role={$accountRole}");
}

header('Location: ' . BASE_URL . '/admin/users.php');
exit;
