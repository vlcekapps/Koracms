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

$pdo = db_connect();
$chatStmt = $pdo->prepare(
    "SELECT id, status, public_visibility, internal_note, approved_at, approved_by_user_id
     FROM cms_chat
     WHERE id = ?"
);
$chatStmt->execute([$messageId]);
$message = $chatStmt->fetch() ?: null;

if (!$message) {
    header('Location: ' . $redirect);
    exit;
}

$currentStatus = normalizeMessageStatus((string)($message['status'] ?? 'new'));
$currentVisibility = normalizeChatPublicVisibility((string)($message['public_visibility'] ?? 'pending'));
$currentInternalNote = trim((string)($message['internal_note'] ?? ''));
$currentApprovedAt = $message['approved_at'] ?? null;
$currentApprovedByUserId = isset($message['approved_by_user_id']) ? (int)$message['approved_by_user_id'] : null;

$status = normalizeMessageStatus((string)($_POST['status'] ?? $currentStatus));
$publicVisibility = normalizeChatPublicVisibility((string)($_POST['public_visibility'] ?? $currentVisibility));
$internalNote = trim((string)($_POST['internal_note'] ?? ''));
$approvedAt = null;
$approvedByUserId = null;

if ($publicVisibility === 'approved') {
    if ($currentVisibility === 'approved' && $currentApprovedAt !== null) {
        $approvedAt = (string)$currentApprovedAt;
        $approvedByUserId = $currentApprovedByUserId;
    } else {
        $approvedAt = date('Y-m-d H:i:s');
        $approvedByUserId = currentUserId();
    }
}

$pdo->prepare(
    "UPDATE cms_chat
     SET status = ?,
         public_visibility = ?,
         approved_at = ?,
         approved_by_user_id = ?,
         internal_note = ?,
         updated_at = NOW()
     WHERE id = ?"
)->execute([
    $status,
    $publicVisibility,
    $approvedAt,
    $approvedByUserId,
    $internalNote,
    $messageId,
]);

$historyParts = [];
if ($status !== $currentStatus) {
    $historyParts[] = 'Stav zprávy byl změněn na „' . messageStatusLabel($status) . '“.';
}
if ($publicVisibility !== $currentVisibility) {
    $historyParts[] = 'Veřejná viditelnost byla změněna na „' . chatPublicVisibilityLabel($publicVisibility) . '“.';
}
if ($internalNote !== $currentInternalNote) {
    $historyParts[] = $internalNote !== ''
        ? 'Interní poznámka byla upravena.'
        : 'Interní poznámka byla smazána.';
}

if ($historyParts !== []) {
    chatHistoryCreate($pdo, $messageId, currentUserId(), 'workflow', implode(' ', $historyParts));
}

logAction(
    'chat_update',
    'id=' . $messageId
    . ';status=' . $status
    . ';visibility=' . $publicVisibility
);

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
