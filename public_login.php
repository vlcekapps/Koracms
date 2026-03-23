<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

if (isLoggedIn()) {
    if (isPublicUser()) {
        header('Location: ' . BASE_URL . '/public_profile.php');
    } else {
        header('Location: ' . BASE_URL . '/admin/index.php');
    }
    exit;
}

$siteName     = getSetting('site_name', 'Kora CMS');
$errors       = [];
$notConfirmed = false;
$redirect     = internalRedirectTarget(trim($_GET['redirect'] ?? $_POST['redirect'] ?? ''), '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('public_login', 5, 300);
    verifyCsrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Vyplňte e-mail a heslo.';
    } else {
        $pdo  = db_connect();
        $stmt = $pdo->prepare(
            "SELECT id, email, password, first_name, last_name, is_superadmin, is_confirmed
             FROM cms_users
             WHERE email = ? AND role = 'public'"
        );
        $stmt->execute([$email]);
        $userRow = $stmt->fetch();

        if ($userRow && password_verify($password, $userRow['password'])) {
            if (!(int)$userRow['is_confirmed']) {
                $notConfirmed = true;
            } else {
                $displayName = trim($userRow['first_name'] . ' ' . $userRow['last_name']);
                if ($displayName === '') {
                    $displayName = $userRow['email'];
                }

                loginUser(
                    (int)$userRow['id'],
                    $userRow['email'],
                    (bool)$userRow['is_superadmin'],
                    $displayName,
                    'public'
                );

                $target = internalRedirectTarget($redirect, BASE_URL . '/public_profile.php');
                header('Location: ' . $target);
                exit;
            }
        } else {
            $errors[] = 'Nesprávný e-mail nebo heslo.';
        }
    }
}

renderPublicPage([
    'title' => 'Přihlášení – ' . $siteName,
    'meta' => [
        'title' => 'Přihlášení – ' . $siteName,
    ],
    'view' => 'auth/login',
    'view_data' => [
        'errors' => $errors,
        'notConfirmed' => $notConfirmed,
        'redirect' => $redirect,
        'postedEmail' => trim($_POST['email'] ?? ''),
    ],
    'body_class' => 'page-auth page-login',
    'page_kind' => 'utility',
]);
