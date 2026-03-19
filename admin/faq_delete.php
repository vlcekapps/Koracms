<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    db_connect()->prepare("DELETE FROM cms_faqs WHERE id = ?")->execute([$id]);
    logAction('faq_delete', "id={$id}");
}

header('Location: faq.php');
exit;
