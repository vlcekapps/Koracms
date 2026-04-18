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
$publicRegistrationEnabled = publicRegistrationEnabled();
$redirect     = internalRedirectTarget(trim($_GET['redirect'] ?? $_POST['redirect'] ?? ''), '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('public_login', 5, 300);
    verifyCsrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    rateLimitSubject('public_login_email', $email, 5, 900);

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

        // Valid bcrypt hash keeps password_verify() work comparable even for unknown accounts.
        $storedHash = $userRow ? $userRow['password'] : '$2y$12$/6yvhn9vu9UsOxpa0hp/4eptlAKyrXvFpcDWCHNm9UDf1Xi/QvPTu';
        $passwordOk = password_verify($password, $storedHash);

        if ($userRow && $passwordOk) {
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
            sleep(1);
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
        'publicRegistrationEnabled' => $publicRegistrationEnabled,
        'redirect' => $redirect,
        'postedEmail' => trim($_POST['email'] ?? ''),
    ],
    'body_class' => 'page-auth page-login',
    'page_kind' => 'utility',
]);
