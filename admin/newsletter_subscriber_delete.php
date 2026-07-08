<?php

require_once __DIR__ . '/../db.php';
requireCapability('newsletter_manage', 'Přístup odepřen. Pro správu odběratelů newsletteru nemáte potřebné oprávnění.');
requireModuleEnabled('newsletter');
verifyCsrf();

$subscriberId = inputInt('post', 'id');
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/newsletter.php');

if ($subscriberId === null) {
    $separator = str_contains($redirect, '?') ? '&' : '?';
    header('Location: ' . $redirect . $separator . 'error=invalid');
    exit;
}

$confirmFieldName = 'confirm_newsletter_subscriber_delete_' . $subscriberId;
$deleteConfirmed = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$deleteConfirmed) {
    $separator = str_contains($redirect, '?') ? '&' : '?';
    header('Location: ' . $redirect . $separator . 'error=subscriber_delete_confirm_required&delete_error_id=' . $subscriberId);
    exit;
}

$pdo = db_connect();
$stmt = $pdo->prepare('SELECT email FROM cms_subscribers WHERE id = ? LIMIT 1');
$stmt->execute([$subscriberId]);
$subscriber = $stmt->fetch();

if ($subscriber) {
    $pdo->prepare("DELETE FROM cms_subscribers WHERE id = ?")->execute([$subscriberId]);
    logAction('newsletter_subscriber_delete', 'id=' . $subscriberId . ' email=' . (string)$subscriber['email']);
}

$separator = str_contains($redirect, '?') ? '&' : '?';
header('Location: ' . $redirect . $separator . 'ok=deleted');
exit;
