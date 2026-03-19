<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    db_connect()->prepare("DELETE FROM cms_subscribers WHERE id = ?")->execute([$id]);
    logAction('subscriber_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/newsletter.php');
exit;
