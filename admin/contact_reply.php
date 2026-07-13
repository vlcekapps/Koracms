<?php

require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro odpověď na kontaktní zprávu nemáte potřebné oprávnění.');
requireModuleEnabled('contact');
verifyCsrf();

$messageId = inputInt('post', 'id');
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/contact.php');

if ($messageId === null) {
    header('Location: ' . $redirect);
    exit;
}

$subject = trim((string)($_POST['subject'] ?? ''));
$replyMessage = trim((string)($_POST['message'] ?? ''));
$confirmFieldName = 'confirm_contact_reply_send_' . $messageId;
$confirmed = isset($_POST[$confirmFieldName]) && (string)$_POST[$confirmFieldName] === '1';
$pdo = db_connect();
$stmt = $pdo->prepare(
    "SELECT id, sender_name, sender_email, reference_code, subject
     FROM cms_contact
     WHERE id = ?"
);
$stmt->execute([$messageId]);
$message = $stmt->fetch() ?: null;

if (!$message) {
    header('Location: ' . $redirect);
    exit;
}

$recipient = trim((string)($message['sender_email'] ?? ''));
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . appendUrlQuery($redirect, ['reply' => 'missing']));
    exit;
}

$errorFields = adminReplyValidationErrorFields($subject, $replyMessage, $confirmFieldName, $confirmed);
if ($errorFields !== []) {
    adminReplyFlashStore('contact', $messageId, $subject, $replyMessage, $errorFields);
    $errorCode = in_array('reply_subject', $errorFields, true) || in_array('reply_message', $errorFields, true)
        ? 'invalid'
        : 'confirm_required';
    header('Location: ' . appendUrlQuery($redirect, ['reply' => $errorCode]));
    exit;
}

if (!sendContactReply(
    $recipient,
    (string)($message['sender_name'] ?? ''),
    (string)($message['reference_code'] ?? ''),
    $subject,
    $replyMessage
)) {
    adminReplyFlashStore('contact', $messageId, $subject, $replyMessage);
    header('Location: ' . appendUrlQuery($redirect, ['reply' => 'failed']));
    exit;
}

$pdo->prepare(
    "UPDATE cms_contact
     SET replied_at = NOW(),
         replied_by_user_id = ?,
         reply_subject = ?,
         reply_body = ?,
         status = 'handled',
         is_read = 1,
         updated_at = NOW()
     WHERE id = ?"
)->execute([
    currentUserId(),
    $subject,
    $replyMessage,
    $messageId,
]);

logAction('contact_reply', 'id=' . $messageId . ';recipient=' . $recipient);

header('Location: ' . appendUrlQuery($redirect, ['reply' => 'sent']));
exit;
