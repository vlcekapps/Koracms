<?php
require_once __DIR__ . '/../db.php';
requireSuperAdmin();
verifyCsrf();

$pdo       = db_connect();
$id        = inputInt('post', 'id');
$email     = trim($_POST['email']      ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name']  ?? '');
$nickname  = trim($_POST['nickname']   ?? '');
$newPass   = $_POST['new_pass']        ?? '';
$newPass2  = $_POST['new_pass2']       ?? '';

$errors = [];

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors[] = 'Zadejte platnou e-mailovou adresu.';
if ($id === null && ($newPass === '' || strlen($newPass) < 8))
    $errors[] = 'Heslo je povinné a musí mít alespoň 8 znaků.';
if ($newPass !== '' && strlen($newPass) < 8)
    $errors[] = 'Heslo musí mít alespoň 8 znaků.';
if ($newPass !== $newPass2)
    $errors[] = 'Hesla se neshodují.';

// Unikátnost emailu
if (empty($errors)) {
    $dup = $pdo->prepare("SELECT id FROM cms_users WHERE email = ?" . ($id ? " AND id != {$id}" : ''));
    $dup->execute([$email]);
    if ($dup->fetch()) $errors[] = 'Tento e-mail již používá jiný účet.';
}

if (!empty($errors)) {
    // Vrátíme se na formulář s chybami přes session (jednoduché řešení)
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data']   = $_POST;
    header('Location: user_form.php' . ($id ? "?id={$id}" : ''));
    exit;
}

if ($id !== null) {
    // UPDATE – nesmíme upravovat superadmina
    $setClauses = "email=?, first_name=?, last_name=?, nickname=?";
    $params     = [$email, $firstName, $lastName, $nickname];
    if ($newPass !== '') {
        $setClauses .= ", password=?";
        $params[]    = password_hash($newPass, PASSWORD_BCRYPT);
    }
    $params[] = $id;
    $pdo->prepare("UPDATE cms_users SET {$setClauses} WHERE id=? AND is_superadmin=0")->execute($params);
    logAction('user_edit', "id={$id} email={$email}");
} else {
    $pdo->prepare(
        "INSERT INTO cms_users (email, password, first_name, last_name, nickname, is_superadmin)
         VALUES (?,?,?,?,?,0)"
    )->execute([$email, password_hash($newPass, PASSWORD_BCRYPT), $firstName, $lastName, $nickname]);
    logAction('user_add', "email={$email}");
}

header('Location: ' . BASE_URL . '/admin/users.php');
exit;
