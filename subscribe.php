<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

if (!isModuleEnabled('newsletter')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$siteName = getSetting('site_name', 'Kora CMS');
$state    = 'form';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('subscribe', 3, 300);

    if (honeypotTriggered()) {
        $state = 'ok';
    } else {
        verifyCsrf();
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $state = 'error';
        } else {
            $pdo   = db_connect();
            $token = bin2hex(random_bytes(32));

            try {
                $pdo->prepare(
                    "INSERT INTO cms_subscribers (email, token, confirmed) VALUES (?, ?, 0)"
                )->execute([$email, $token]);

                $confirmUrl = siteUrl('/subscribe_confirm.php?token=' . $token);
                $subject    = 'Potvrďte přihlášení k odběru – ' . $siteName;
                $body       = "Dobrý den,\n\n"
                            . "pro potvrzení odběru novinek webu {$siteName} klikněte na odkaz:\n"
                            . $confirmUrl . "\n\n"
                            . "Pokud jste se k odběru nepřihlásili, tento email ignorujte.\n\n"
                            . "— " . $siteName;
                sendMail($email, $subject, $body);
                $state = 'ok';
            } catch (\PDOException $e) {
                $state = 'exists';
            }
        }
    }
}

$captchaExpr = captchaGenerate();

renderPublicPage([
    'title' => 'Odběr novinek – ' . $siteName,
    'meta' => [
        'title' => 'Odběr novinek – ' . $siteName,
    ],
    'view' => 'newsletter/subscribe',
    'view_data' => [
        'state' => $state,
        'captchaExpr' => $captchaExpr,
        'postedEmail' => trim($_POST['email'] ?? ''),
    ],
    'body_class' => 'page-newsletter page-subscribe',
    'page_kind' => 'utility',
]);
