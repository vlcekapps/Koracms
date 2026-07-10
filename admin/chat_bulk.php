<?php

require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu chat zpráv nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/chat.php');
$action = trim((string)($_POST['action'] ?? ''));
$allowedActions = ['new', 'read', 'handled', 'approve', 'hide', 'delete'];
$ids = array_values(array_unique(array_filter(
    array_map('intval', (array)($_POST['ids'] ?? [])),
    static fn (int $id): bool => $id > 0
)));

if ($ids === [] || !in_array($action, $allowedActions, true)) {
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    $confirmedBulkDelete = isset($_POST['confirm_chat_bulk_delete'])
        && (string)$_POST['confirm_chat_bulk_delete'] === '1';
    if (!$confirmedBulkDelete) {
        header('Location: ' . appendUrlQuery($redirect, ['error' => 'chat_bulk_delete_confirm_required']));
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $existingStmt = $pdo->prepare("SELECT id FROM cms_chat WHERE id IN ({$placeholders}) ORDER BY id");
    $existingStmt->execute($ids);
    $existingIds = array_map('intval', $existingStmt->fetchAll(PDO::FETCH_COLUMN));

    $deletedCount = 0;
    $pdo->beginTransaction();
    try {
        foreach ($existingIds as $messageId) {
            if (deleteChatMessage($pdo, $messageId)) {
                $deletedCount++;
            }
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($deletedCount > 0) {
        logAction('chat_bulk_delete', 'count=' . $deletedCount);
    }
    header('Location: ' . appendUrlQuery($redirect, [
        'ok' => 'bulk_deleted',
        'count' => $deletedCount,
    ]));
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
