<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu FAQ nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    db_connect()->prepare("DELETE FROM cms_faqs WHERE id = ?")->execute([$id]);
    logAction('faq_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/faq.php');
exit;
