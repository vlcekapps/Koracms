<?php

require_once __DIR__ . '/db.php';
checkMaintenanceMode();
sendNoStoreNoIndexHeaders();

function newsletterWidgetRedirect(string $target): void
{
    sendNoStoreNoIndexHeaders();
    header('Location: ' . $target);
    exit;
}

$defaultRedirect = BASE_URL . '/subscribe.php';
$returnUrl = safePublicReturnTarget(trim((string)($_POST['return_url'] ?? '')), $defaultRedirect);

if (!isModuleEnabled('newsletter')) {
    newsletterWidgetRedirect($returnUrl);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    newsletterWidgetRedirect($defaultRedirect);
}

rateLimit('subscribe_widget', 3, 300);

if (honeypotTriggered()) {
    $_SESSION['newsletter_widget_flash'] = [
        'type' => 'success',
        'message' => 'Na vaši adresu jsme odeslali potvrzovací e-mail. Klikněte prosím na odkaz v e-mailu.',
        'email' => '',
    ];
    newsletterWidgetRedirect($returnUrl);
}

verifyCsrf();

$email = trim((string)($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['newsletter_widget_flash'] = [
        'type' => 'error',
        'message' => 'Zadejte platnou e-mailovou adresu.',
        'email' => $email,
    ];
    newsletterWidgetRedirect($returnUrl);
}

$pdo = db_connect();
$token = bin2hex(random_bytes(32));

try {
    $pdo->prepare(
        "INSERT INTO cms_subscribers (email, token, confirmed) VALUES (?, ?, 0)"
    )->execute([$email, $token]);

    if (!sendNewsletterSubscriptionConfirmation($email, $token)) {
        $_SESSION['newsletter_widget_flash'] = [
            'type' => 'error',
            'message' => 'Adresa byla zaregistrována, ale potvrzovací e-mail se nepodařilo odeslat. Zkuste to prosím později.',
            'email' => $email,
        ];
    } else {
        $_SESSION['newsletter_widget_flash'] = [
            'type' => 'success',
            'message' => 'Na vaši adresu jsme odeslali potvrzovací e-mail. Klikněte prosím na odkaz v e-mailu.',
            'email' => '',
        ];
    }
} catch (\PDOException $e) {
    $_SESSION['newsletter_widget_flash'] = [
        'type' => 'success',
        'message' => 'Tato adresa je již přihlášena k odběru, nebo čeká na potvrzení.',
        'email' => '',
    ];
}

newsletterWidgetRedirect($returnUrl);
