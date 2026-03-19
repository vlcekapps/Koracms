<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$ids = array_map('intval', (array)($_POST['ids'] ?? []));

if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    db_connect()->prepare("DELETE FROM cms_chat WHERE id IN ({$placeholders})")->execute($ids);
}

header('Location: ' . BASE_URL . '/admin/chat.php');
exit;
