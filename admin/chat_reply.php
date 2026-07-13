<?php

require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu chat zpráv nemáte potřebné oprávnění.');
verifyCsrf();

$messageId = inputInt('post', 'id');
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/chat.php');

if ($messageId === null) {
    header('Location: ' . $redirect);
    exit;
}

$subject = trim((string)($_POST['subject'] ?? ''));
$replyMessage = trim((string)($_POST['message'] ?? ''));
$confirmFieldName = 'confirm_chat_reply_send_' . $messageId;
$confirmed = isset($_POST[$confirmFieldName]) && (string)$_POST[$confirmFieldName] === '1';
$pdo = db_connect();
$chatStmt = $pdo->prepare(
    "SELECT id, name, email, status, public_visibility
     FROM cms_chat
     WHERE id = ?"
);
$chatStmt->execute([$messageId]);
$message = $chatStmt->fetch() ?: null;

if (!$message) {
    header('Location: ' . $redirect);
    exit;
}

$recipient = trim((string)($message['email'] ?? ''));
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . appendUrlQuery($redirect, ['reply' => 'missing']));
    exit;
}

$errorFields = adminReplyValidationErrorFields($subject, $replyMessage, $confirmFieldName, $confirmed);
if ($errorFields !== []) {
    adminReplyFlashStore('chat', $messageId, $subject, $replyMessage, $errorFields);
    $errorCode = in_array('reply_subject', $errorFields, true) || in_array('reply_message', $errorFields, true)
        ? 'invalid'
        : 'confirm_required';
    header('Location: ' . appendUrlQuery($redirect, ['reply' => $errorCode]));
    exit;
}

if (!sendChatReply($recipient, (string)($message['name'] ?? ''), $subject, $replyMessage)) {
    adminReplyFlashStore('chat', $messageId, $subject, $replyMessage);
    header('Location: ' . appendUrlQuery($redirect, ['reply' => 'failed']));
    exit;
}

$pdo->prepare(
    "UPDATE cms_chat
     SET replied_at = NOW(),
         replied_by_user_id = ?,
         replied_subject = ?,
         replied_to_email = ?,
         replied_body = ?,
         updated_at = NOW()
     WHERE id = ?"
)->execute([
    currentUserId(),
    $subject,
    $recipient,
    $replyMessage,
    $messageId,
]);

chatHistoryCreate(
    $pdo,
    $messageId,
    currentUserId(),
    'reply',
    "Odeslána odpověď na {$recipient}.\nPředmět: {$subject}\n\n{$replyMessage}"
);
logAction('chat_reply', 'id=' . $messageId . ';recipient=' . $recipient);

header('Location: ' . appendUrlQuery($redirect, ['reply' => 'sent']));
exit;
