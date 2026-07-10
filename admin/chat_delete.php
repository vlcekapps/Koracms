<?php

require_once __DIR__ . '/../db.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu chat zpráv nemáte potřebné oprávnění.');
verifyCsrf();

$messageId = inputInt('post', 'id');
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/chat.php');
$successRedirect = internalRedirectTarget(trim((string)($_POST['success_redirect'] ?? '')), $redirect);

if ($messageId !== null) {
    $pdo = db_connect();
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

header('Location: ' . $successRedirect);
exit;
