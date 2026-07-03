<?php

require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu odpovědí chatu nemáte potřebné oprávnění.');
requireModuleEnabled('chat');
verifyCsrf();

$pdo = db_connect();
$replyId = inputInt('post', 'id');
$action = trim((string)($_POST['action'] ?? ''));
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/chat.php');

if ($replyId === null || !in_array($action, ['approve', 'hide', 'delete'], true)) {
    header('Location: ' . $redirect);
    exit;
}

$stmt = $pdo->prepare("SELECT id, chat_id FROM cms_chat_replies WHERE id = ?");
$stmt->execute([$replyId]);
$reply = $stmt->fetch() ?: null;

if (!$reply) {
    header('Location: ' . $redirect);
    exit;
}

$chatId = (int)$reply['chat_id'];

if ($action === 'delete') {
    $pdo->prepare("DELETE FROM cms_chat_replies WHERE id = ?")->execute([$replyId]);
    chatHistoryCreate($pdo, $chatId, currentUserId(), 'reply', 'Veřejná odpověď byla smazána.');
    logAction('chat_reply_delete', 'id=' . $replyId . ';chat_id=' . $chatId);
    header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
    exit;
}

$targetStatus = $action === 'approve' ? 'approved' : 'hidden';
$pdo->prepare(
    "UPDATE cms_chat_replies
     SET status = ?, approved_at = ?, approved_by_user_id = ?, updated_at = NOW()
     WHERE id = ?"
)->execute([
    $targetStatus,
    $targetStatus === 'approved' ? date('Y-m-d H:i:s') : null,
    $targetStatus === 'approved' ? currentUserId() : null,
    $replyId,
]);

chatHistoryCreate(
    $pdo,
    $chatId,
    currentUserId(),
    'reply',
    $targetStatus === 'approved'
        ? 'Veřejná odpověď byla schválena.'
        : 'Veřejná odpověď byla skryta.'
);
logAction('chat_reply_status', 'id=' . $replyId . ';chat_id=' . $chatId . ';status=' . $targetStatus);

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
