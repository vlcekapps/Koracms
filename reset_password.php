<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$siteName = getSetting('site_name', 'Kora CMS');
$token    = trim($_GET['token'] ?? $_POST['token'] ?? '');
$mode     = ($token !== '') ? 'reset' : 'request';
$errors   = [];
$success  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('password_reset', 3, 300);
    verifyCsrf();

    $pdo = db_connect();

    if ($mode === 'request') {
        $email = trim($_POST['email'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Zadejte platnou e-mailovou adresu.';
        }
        if (!captchaVerify($_POST['captcha'] ?? '')) {
            $errors[] = 'Chybná odpověď na ověřovací otázku.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                "SELECT id, email FROM cms_users WHERE email = ? AND role = 'public' AND is_confirmed = 1"
            );
            $stmt->execute([$email]);
            $userRow = $stmt->fetch();

            if ($userRow) {
                $resetToken = bin2hex(random_bytes(32));
                $pdo->prepare(
                    "UPDATE cms_users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?"
                )->execute([$resetToken, $userRow['id']]);

                $resetUrl = siteUrl('/reset_password.php?token=' . $resetToken);
                $subject  = 'Obnovení hesla – ' . $siteName;
                $body     = "Dobrý den,\n\n"
                          . "obdrželi jsme žádost o obnovení hesla na webu {$siteName}.\n"
                          . "Pro nastavení nového hesla klikněte na odkaz:\n"
                          . $resetUrl . "\n\n"
                          . "Odkaz je platný 1 hodinu.\n\n"
                          . "Pokud jste o obnovení nežádali, tento email ignorujte.\n\n"
                          . "— " . $siteName;
                sendMail($userRow['email'], $subject, $body);
            }

            $success = true;
        }
    } else {
        $newPass  = $_POST['new_pass'] ?? '';
        $newPass2 = $_POST['new_pass2'] ?? '';

        if (strlen($newPass) < 8) {
            $errors[] = 'Heslo musí mít alespoň 8 znaků.';
        }
        if ($newPass !== $newPass2) {
            $errors[] = 'Hesla se neshodují.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                "SELECT id FROM cms_users WHERE reset_token = ? AND reset_expires > NOW()"
            );
            $stmt->execute([$token]);
            $userRow = $stmt->fetch();

            if ($userRow) {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $pdo->prepare(
                    "UPDATE cms_users SET password = ?, reset_token = '', reset_expires = NULL WHERE id = ?"
                )->execute([$hash, $userRow['id']]);
                $success = true;
            } else {
                $errors[] = 'Neplatný nebo vypršelý odkaz pro obnovení hesla.';
            }
        }
    }
}

$captchaExpr = captchaGenerate();

renderPublicPage([
    'title' => 'Obnovení hesla – ' . $siteName,
    'meta' => [
        'title' => 'Obnovení hesla – ' . $siteName,
    ],
    'view' => 'auth/reset-password',
    'view_data' => [
        'mode' => $mode,
        'token' => $token,
        'errors' => $errors,
        'success' => $success,
        'captchaExpr' => $captchaExpr,
        'requestEmail' => trim($_POST['email'] ?? ''),
    ],
    'body_class' => 'page-auth page-reset-password',
    'page_kind' => 'utility',
]);
