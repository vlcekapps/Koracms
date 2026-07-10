<?php

require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu chat zpráv nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$messageId = inputInt('post', 'id');
$action = trim((string)($_POST['action'] ?? ''));
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/chat.php');
$successRedirect = internalRedirectTarget(trim((string)($_POST['success_redirect'] ?? '')), $redirect);
$allowedActions = ['new', 'read', 'handled', 'approve', 'hide', 'pin', 'unpin', 'delete'];

if ($messageId === null || !in_array($action, $allowedActions, true)) {
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    $messageStmt = $pdo->prepare("SELECT id FROM cms_chat WHERE id = ?");
    $messageStmt->execute([$messageId]);
    if (!$messageStmt->fetchColumn()) {
        header('Location: ' . $successRedirect);
        exit;
    }

    $confirmFieldName = 'confirm_chat_delete_' . $messageId;
    $confirmedDelete = isset($_POST[$confirmFieldName]) && (string)$_POST[$confirmFieldName] === '1';
    if (!$confirmedDelete) {
        header('Location: ' . appendUrlQuery($redirect, [
            'error' => 'chat_delete_confirm_required',
            'delete_id' => $messageId,
        ]));
        exit;
    }

    if (deleteChatMessage($pdo, $messageId)) {
        logAction('chat_delete', "id={$messageId}");
    }
    header('Location: ' . appendUrlQuery($successRedirect, ['ok' => 'deleted']));
    exit;
}

if ($action === 'approve') {
    setChatMessagePublicVisibility($pdo, $messageId, 'approved', currentUserId());
    chatHistoryCreate($pdo, $messageId, currentUserId(), 'visibility', 'Zpráva byla schválena pro veřejný chat.');
    logAction('chat_visibility', "id={$messageId};visibility=approved");
    header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
    exit;
}

if ($action === 'hide') {
    setChatMessagePublicVisibility($pdo, $messageId, 'hidden', currentUserId());
    chatHistoryCreate($pdo, $messageId, currentUserId(), 'visibility', 'Zpráva byla skryta z veřejného chatu.');
    logAction('chat_visibility', "id={$messageId};visibility=hidden");
    header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
    exit;
}

if ($action === 'pin' || $action === 'unpin') {
    if ($action === 'pin') {
        $pdo->prepare(
            "UPDATE cms_chat
             SET is_pinned = 1, pinned_at = NOW(), pinned_by_user_id = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([currentUserId(), $messageId]);
        chatHistoryCreate($pdo, $messageId, currentUserId(), 'pin', 'Zpráva byla připnuta ve veřejném chatu.');
    } else {
        $pdo->prepare(
            "UPDATE cms_chat
             SET is_pinned = 0, pinned_until = NULL, pinned_at = NULL, pinned_by_user_id = NULL, updated_at = NOW()
             WHERE id = ?"
        )->execute([$messageId]);
        chatHistoryCreate($pdo, $messageId, currentUserId(), 'pin', 'Zpráva byla odepnuta z veřejného chatu.');
    }
    logAction('chat_pin', 'id=' . $messageId . ';action=' . $action);
    header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
    exit;
}

$normalizedStatus = normalizeMessageStatus($action);
setChatMessageStatus($pdo, $messageId, $normalizedStatus);
chatHistoryCreate(
    $pdo,
    $messageId,
    currentUserId(),
    'workflow',
    'Stav zprávy byl změněn na „' . messageStatusLabel($normalizedStatus) . '“.'
);
logAction('chat_status', "id={$messageId};status={$normalizedStatus}");

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
