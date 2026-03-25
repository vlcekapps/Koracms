<?php
require_once __DIR__ . '/../db.php';
requireCapability('newsletter_manage', 'Přístup odepřen. Pro správu odběratelů newsletteru nemáte potřebné oprávnění.');
verifyCsrf();

$subscriberId = inputInt('post', 'id');
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/newsletter.php');

if ($subscriberId !== null) {
    db_connect()->prepare("DELETE FROM cms_subscribers WHERE id = ?")->execute([$subscriberId]);
    logAction('newsletter_subscriber_delete', 'id=' . $subscriberId);
}

$separator = str_contains($redirect, '?') ? '&' : '?';
header('Location: ' . $redirect . $separator . 'ok=deleted');
exit;
