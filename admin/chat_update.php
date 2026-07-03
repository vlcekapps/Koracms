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
    "SELECT id, status, public_visibility, internal_note, approved_at, approved_by_user_id,
            topic_id, topic_label, conversation_type, is_pinned, pinned_until, pinned_at
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
$currentTopicId = $message['topic_id'] !== null ? (int)$message['topic_id'] : 0;
$currentConversationType = normalizeChatConversationType((string)($message['conversation_type'] ?? 'public'));
$currentIsPinned = (int)($message['is_pinned'] ?? 0) === 1;
$currentPinnedUntil = trim((string)($message['pinned_until'] ?? ''));
$currentApprovedAt = $message['approved_at'] ?? null;
$currentApprovedByUserId = isset($message['approved_by_user_id']) ? (int)$message['approved_by_user_id'] : null;

$status = normalizeMessageStatus((string)($_POST['status'] ?? $currentStatus));
$publicVisibility = normalizeChatPublicVisibility((string)($_POST['public_visibility'] ?? $currentVisibility));
$conversationType = normalizeChatConversationType((string)($_POST['conversation_type'] ?? $currentConversationType));
$topicId = inputInt('post', 'topic_id');
$selectedTopic = $topicId !== null ? chatTopicById($pdo, $topicId, false) : null;
$topicLabel = is_array($selectedTopic) ? (string)($selectedTopic['name'] ?? '') : '';
$isPinned = isset($_POST['is_pinned']);
$pinnedUntilRaw = trim((string)($_POST['pinned_until'] ?? ''));
$pinnedUntil = null;
if ($pinnedUntilRaw !== '') {
    $pinnedTimestamp = strtotime($pinnedUntilRaw);
    if ($pinnedTimestamp !== false) {
        $pinnedUntil = date('Y-m-d H:i:s', $pinnedTimestamp);
    }
}
if ($topicId !== null && $selectedTopic === null) {
    $topicId = null;
    $topicLabel = '';
}
if ($conversationType === 'support') {
    $publicVisibility = 'hidden';
}
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
         topic_id = ?,
         topic_label = ?,
         conversation_type = ?,
         is_pinned = ?,
         pinned_until = ?,
         pinned_at = ?,
         pinned_by_user_id = ?,
         approved_at = ?,
         approved_by_user_id = ?,
         internal_note = ?,
         updated_at = NOW()
     WHERE id = ?"
)->execute([
    $status,
    $publicVisibility,
    $topicId,
    $topicLabel,
    $conversationType,
    $isPinned ? 1 : 0,
    $isPinned ? $pinnedUntil : null,
    $isPinned && !$currentIsPinned ? date('Y-m-d H:i:s') : ($isPinned ? ($message['pinned_at'] ?? date('Y-m-d H:i:s')) : null),
    $isPinned ? currentUserId() : null,
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
if ($conversationType !== $currentConversationType) {
    $historyParts[] = 'Typ zprávy byl změněn na „' . chatConversationTypeLabel($conversationType) . '“.';
}
if (($topicId ?? 0) !== $currentTopicId) {
    $historyParts[] = $topicLabel !== '' ? 'Téma zprávy bylo změněno na „' . $topicLabel . '“.' : 'Téma zprávy bylo odebráno.';
}
if ($isPinned !== $currentIsPinned || ($isPinned && (string)($pinnedUntil ?? '') !== $currentPinnedUntil)) {
    $historyParts[] = $isPinned ? 'Připnutí zprávy bylo upraveno.' : 'Zpráva byla odepnuta.';
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
    . ';type=' . $conversationType
);

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
