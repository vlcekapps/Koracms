<?php

require_once __DIR__ . '/db.php';
checkMaintenanceMode();

function newsletterRequestSubscription(PDO $pdo, string $email): string
{
    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        "INSERT INTO cms_subscribers (email, token, confirmed) VALUES (?, ?, 0)
         ON DUPLICATE KEY UPDATE email = cms_subscribers.email"
    )->execute([$email, $token]);
    $statement = $pdo->prepare('SELECT email, token, confirmed FROM cms_subscribers WHERE email = ?');
    $statement->execute([$email]);
    $subscriber = $statement->fetch();
    if (!is_array($subscriber)) {
        throw new RuntimeException('Registrovanou adresu se nepodařilo načíst.');
    }
    if ((int)$subscriber['confirmed'] === 1) {
        return 'ok';
    }

    // Reuse the persisted token: retries must not invalidate links already sent.
    return sendNewsletterSubscriptionConfirmation((string)$subscriber['email'], (string)$subscriber['token'])
        ? 'ok' : 'mail_error';
}

if (!isModuleEnabled('newsletter')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$siteName = getSetting('site_name', 'Kora CMS');
$state    = 'form';
$errors = [];
$errorFields = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('subscribe', 3, 300);

    if (honeypotTriggered()) {
        $state = 'ok';
    } else {
        verifyCsrf();
        $email = trim((string)($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Zadejte platnou e-mailovou adresu.';
            $errorFields[] = 'email';
        }
        if (!captchaVerify((string)($_POST['captcha'] ?? ''))) {
            $errors[] = publicCaptchaErrorMessage();
            $errorFields[] = 'captcha';
        }

        if ($errors === []) {
            rateLimitSubject('subscribe_email', $email, 3, 3600);
            $pdo   = db_connect();

            try {
                $state = newsletterRequestSubscription($pdo, $email);
                if ($state === 'mail_error') {
                    $state = 'error';
                    $errors[] = 'Potvrzovací e-mail se nepodařilo odeslat. Zkuste přihlášení prosím znovu později.';
                }
            } catch (\PDOException $e) {
                koraLog('warning', 'newsletter subscription save failed', ['exception' => $e]);
                $state = 'error';
                $errors[] = 'Přihlášení se nepodařilo uložit. Zkuste to prosím později.';
            }
        } else {
            $state = 'error';
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
        'errors' => $errors,
        'errorFields' => $errorFields,
        'captchaExpr' => $captchaExpr,
        'postedEmail' => trim((string)($_POST['email'] ?? '')),
    ],
    'body_class' => 'page-newsletter page-subscribe',
    'page_kind' => 'utility',
]);
