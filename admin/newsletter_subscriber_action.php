<?php

require_once __DIR__ . '/../db.php';
requireCapability('newsletter_manage', 'Přístup odepřen. Pro správu odběratelů newsletteru nemáte potřebné oprávnění.');
requireModuleEnabled('newsletter');
verifyCsrf();

$subscriberId = inputInt('post', 'id');
$action = trim($_POST['action'] ?? '');
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/newsletter.php');

$appendRedirectParam = static function (string $target, string $name, string $value): string {
    $separator = str_contains($target, '?') ? '&' : '?';
    return $target . $separator . rawurlencode($name) . '=' . rawurlencode($value);
};

if ($subscriberId === null || !in_array($action, ['confirm', 'resend', 'delete'], true)) {
    header('Location: ' . $redirect);
    exit;
}

$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT id, email, confirmed, token
     FROM cms_subscribers
     WHERE id = ?"
);
$stmt->execute([$subscriberId]);
$subscriber = $stmt->fetch();

if (!$subscriber) {
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'confirm') {
    $pdo->prepare(
        "UPDATE cms_subscribers
         SET confirmed = 1
         WHERE id = ?"
    )->execute([$subscriberId]);
    logAction('newsletter_subscriber_confirm', 'id=' . $subscriberId . ' email=' . (string)$subscriber['email']);
    header('Location: ' . $appendRedirectParam($redirect, 'ok', 'confirmed'));
    exit;
}

if ($action === 'resend') {
    if ((int)$subscriber['confirmed'] === 0) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare(
            "UPDATE cms_subscribers
             SET token = ?
             WHERE id = ?"
        )->execute([$token, $subscriberId]);
        $mailSent = sendNewsletterSubscriptionConfirmation((string)$subscriber['email'], $token);
        logAction('newsletter_subscriber_resend', 'id=' . $subscriberId . ' email=' . (string)$subscriber['email'] . ' ok=' . ($mailSent ? '1' : '0'));
        header('Location: ' . $appendRedirectParam($redirect, $mailSent ? 'ok' : 'error', $mailSent ? 'resent' : 'resend_failed'));
        exit;
    }

    header('Location: ' . $redirect);
    exit;
}

$confirmFieldName = 'confirm_newsletter_subscriber_delete_' . $subscriberId;
$deleteConfirmed = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$deleteConfirmed) {
    $target = $appendRedirectParam($redirect, 'error', 'subscriber_delete_confirm_required');
    $target = $appendRedirectParam($target, 'delete_error_id', (string)$subscriberId);
    header('Location: ' . $target);
    exit;
}

$pdo->prepare("DELETE FROM cms_subscribers WHERE id = ?")->execute([$subscriberId]);
logAction('newsletter_subscriber_delete', 'id=' . $subscriberId . ' email=' . (string)$subscriber['email']);
header('Location: ' . $appendRedirectParam($redirect, 'ok', 'deleted'));
exit;
