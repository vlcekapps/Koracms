<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

requireLogin(BASE_URL . '/admin/login.php');
$requestMethod = requireHttpMethods(['POST']);
verifyCsrf();

$pdo = db_connect();
$action = trim((string)($_POST['action'] ?? ''));
$itemType = trim((string)($_POST['item_type'] ?? ''));
$itemKey = trim((string)($_POST['item_key'] ?? ''));
$wantsJson = (string)($_POST['json'] ?? '') === '1'
    || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

$success = false;
$message = 'Zkratku se nepodařilo upravit.';
$item = null;

if ($action === 'pin') {
    $item = adminCommandPinItem($pdo, adminCommandCurrentUserId(), $itemType, $itemKey);
    $success = $item !== null;
    $message = $success ? 'Zkratka byla připnuta.' : $message;
} elseif ($action === 'unpin') {
    $item = adminCommandResolveItem($pdo, $itemType, $itemKey);
    $success = adminCommandUnpinItem($pdo, adminCommandCurrentUserId(), $itemType, $itemKey);
    if ($item !== null) {
        $item['pinned'] = false;
    }
    $message = $success ? 'Zkratka byla odepnuta.' : 'Zkratka už nebyla připnutá.';
}

logAction('admin_shortcut_' . ($action === 'unpin' ? 'unpin' : 'pin'), "type={$itemType} key={$itemKey}");

if ($wantsJson) {
    sendAdminJsonHeaders();
    sendJsonResponse([
        'success' => $success,
        'message' => $message,
        'item' => $item,
        'csrf_token' => csrfToken(),
    ], $success ? 200 : 422);
}

$redirect = internalRedirectTarget((string)($_POST['redirect'] ?? ''), BASE_URL . '/admin/command.php');
header('Location: ' . $redirect);
exit;
