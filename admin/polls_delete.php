<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu anket nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$redirectTarget = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/polls.php');

if ($id !== null) {
    $pdo->prepare("UPDATE cms_polls SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([$id]);
    logAction('poll_delete', "id={$id} soft=true");
}

header('Location: ' . $redirectTarget);
exit;
