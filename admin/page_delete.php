<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu statických stránek nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/pages.php');

if ($id !== null) {
    db_connect()->prepare("DELETE FROM cms_pages WHERE id = ?")->execute([$id]);
    logAction('page_delete', "id={$id}");
}

header('Location: ' . $redirect);
exit;
