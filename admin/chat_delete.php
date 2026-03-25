<?php
require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu chat zpráv nemáte potřebné oprávnění.');
verifyCsrf();

$messageId = inputInt('post', 'id');
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/chat.php');

if ($messageId !== null) {
    db_connect()->prepare("DELETE FROM cms_chat WHERE id = ?")->execute([$messageId]);
    logAction('chat_delete', "id={$messageId}");
}

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
