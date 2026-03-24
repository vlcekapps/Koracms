<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null && $id !== currentUserId()) {
    $pdo = db_connect();
    $pdo->prepare("DELETE FROM cms_users WHERE id = ? AND is_superadmin = 0")->execute([$id]);
    logAction('user_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/users.php');
exit;
