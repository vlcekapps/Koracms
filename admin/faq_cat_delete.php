<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu kategorií FAQ nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    $pdo->prepare("UPDATE cms_faqs SET category_id = NULL WHERE category_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_faq_categories WHERE id = ?")->execute([$id]);
    logAction('faq_cat_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/faq_cats.php');
exit;
