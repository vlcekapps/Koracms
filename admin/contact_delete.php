<?php
require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu kontaktních zpráv nemáte potřebné oprávnění.');
verifyCsrf();

$messageId = inputInt('post', 'id');
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/contact.php');

if ($messageId !== null) {
    db_connect()->prepare("DELETE FROM cms_contact WHERE id = ?")->execute([$messageId]);
    logAction('contact_delete', "id={$messageId}");
}

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
