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

$siteName   = getSetting('site_name', 'Kora CMS');
$errors     = [];
$success    = false;
$resent     = false;
$skipCreate = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('register', 3, 300);

    if (honeypotTriggered()) {
        $success = true;
    } else {
        verifyCsrf();

        $email      = trim($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $password2  = $_POST['password2'] ?? '';
        $firstName  = trim($_POST['first_name'] ?? '');
        $lastName   = trim($_POST['last_name'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Zadejte platnou e-mailovou adresu.';
        }
        if ($firstName === '') {
            $errors[] = 'Jméno je povinné.';
        }
        if ($lastName === '') {
            $errors[] = 'Příjmení je povinné.';
        }
        if ($phone === '') {
            $errors[] = 'Telefon je povinný.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Heslo musí mít alespoň 8 znaků.';
        }
        if ($password !== $password2) {
            $errors[] = 'Hesla se neshodují.';
        }
        if (!captchaVerify($_POST['captcha'] ?? '')) {
            $errors[] = 'Chybná odpověď na ověřovací otázku.';
        }

        if (empty($errors)) {
            $pdo = db_connect();

            $dup = $pdo->prepare("SELECT id, is_confirmed, confirmation_token FROM cms_users WHERE email = ?");
            $dup->execute([$email]);
            $existingUser = $dup->fetch();
            if ($existingUser) {
                $skipCreate = true;
                if (!(int)$existingUser['is_confirmed'] && $existingUser['confirmation_token']) {
                    $newToken = bin2hex(random_bytes(32));
                    $pdo->prepare("UPDATE cms_users SET confirmation_token = ? WHERE id = ?")
                        ->execute([$newToken, $existingUser['id']]);
                    $confirmUrl = siteUrl('/confirm_email.php?token=' . $newToken);
                    $subject    = 'Potvrďte registraci – ' . $siteName;
                    $body       = "Dobrý den,\n\n"
                                . "pro dokončení registrace na webu {$siteName} klikněte na odkaz:\n"
                                . $confirmUrl . "\n\n"
                                . "Pokud jste se neregistrovali, tento email ignorujte.\n\n"
                                . "— " . $siteName;
                    sendMail($email, $subject, $body);
                    $resent = true;
                } else {
                    $errors[] = 'Účet s tímto e-mailem již existuje.';
                }
            }
        }

        if (empty($errors) && !$skipCreate) {
            $pdo   = db_connect();
            $token = bin2hex(random_bytes(32));
            $hash  = password_hash($password, PASSWORD_BCRYPT);

            $pdo->prepare(
                "INSERT INTO cms_users (email, password, first_name, last_name, phone, role, is_superadmin, is_confirmed, confirmation_token, confirmation_expires, created_at)
                 VALUES (?, ?, ?, ?, ?, 'public', 0, 0, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())"
            )->execute([$email, $hash, $firstName, $lastName, $phone, $token]);

            $confirmUrl = siteUrl('/confirm_email.php?token=' . $token);
            $subject    = 'Potvrďte registraci – ' . $siteName;
            $body       = "Dobrý den,\n\n"
                        . "pro dokončení registrace na webu {$siteName} klikněte na odkaz:\n"
                        . $confirmUrl . "\n\n"
                        . "Pokud jste se neregistrovali, tento email ignorujte.\n\n"
                        . "— " . $siteName;
            sendMail($email, $subject, $body);

            $success = true;
        }
    }
}

$captchaExpr = captchaGenerate();

renderPublicPage([
    'title' => 'Registrace – ' . $siteName,
    'meta' => [
        'title' => 'Registrace – ' . $siteName,
    ],
    'view' => 'auth/register',
    'view_data' => [
        'errors' => $errors,
        'success' => $success,
        'resent' => $resent,
        'captchaExpr' => $captchaExpr,
        'formData' => [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
        ],
    ],
    'body_class' => 'page-auth page-register',
    'page_kind' => 'utility',
]);
