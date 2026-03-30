<?php
require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu chat zpráv nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/chat.php');
$action = trim((string)($_POST['action'] ?? ''));
$allowedActions = ['new', 'read', 'handled', 'approve', 'hide', 'delete'];
$ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), static fn(int $id): bool => $id > 0));

if ($ids === [] || !in_array($action, $allowedActions, true)) {
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    foreach ($ids as $messageId) {
        deleteChatMessage($pdo, $messageId);
    }
    logAction('chat_bulk_delete', 'count=' . count($ids));
    header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
    exit;
}

if ($action === 'approve' || $action === 'hide') {
    $targetVisibility = $action === 'approve' ? 'approved' : 'hidden';
    $historyMessage = $action === 'approve'
        ? 'Zpráva byla schválena pro veřejný chat.'
        : 'Zpráva byla skryta z veřejného chatu.';

    foreach ($ids as $messageId) {
        setChatMessagePublicVisibility($pdo, $messageId, $targetVisibility, currentUserId());
        chatHistoryCreate($pdo, $messageId, currentUserId(), 'visibility', $historyMessage);
    }
    logAction('chat_bulk_visibility', 'count=' . count($ids) . ';visibility=' . $targetVisibility);
    header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
    exit;
}

$normalizedStatus = normalizeMessageStatus($action);
foreach ($ids as $messageId) {
    setChatMessageStatus($pdo, $messageId, $normalizedStatus);
    chatHistoryCreate(
        $pdo,
        $messageId,
        currentUserId(),
        'workflow',
        'Stav zprávy byl změněn na „' . messageStatusLabel($normalizedStatus) . '“.'
    );
}
logAction('chat_bulk_status', 'count=' . count($ids) . ';status=' . $normalizedStatus);

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
