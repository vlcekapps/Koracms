<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu událostí nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT image_file FROM cms_events WHERE id = ?");
    $stmt->execute([$id]);
    $event = $stmt->fetch() ?: null;
    if ($event) {
        deleteEventImageFile((string)($event['image_file'] ?? ''));
    }
    $pdo->prepare("UPDATE cms_events SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([$id]);
    logAction('event_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/events.php');
exit;
