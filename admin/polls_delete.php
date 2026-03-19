<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo = db_connect();
$id  = inputInt('post', 'id');

if ($id !== null) {
    $pdo->prepare("DELETE FROM cms_poll_votes   WHERE poll_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_poll_options  WHERE poll_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_polls         WHERE id = ?")->execute([$id]);
    logAction('poll_delete', "id={$id}");
}

header('Location: polls.php');
exit;
