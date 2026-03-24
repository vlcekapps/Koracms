<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu událostí nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    db_connect()->prepare("DELETE FROM cms_events WHERE id = ?")->execute([$id]);
    logAction('event_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/events.php');
exit;
