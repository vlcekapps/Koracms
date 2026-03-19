<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo  = db_connect();
    $stmt = $pdo->prepare("SELECT filename FROM cms_downloads WHERE id = ?");
    $stmt->execute([$id]);
    $filename = $stmt->fetchColumn();
    if ($filename) {
        $delPath = __DIR__ . '/../uploads/downloads/' . $filename;
        if (file_exists($delPath)) { unlink($delPath); }
    }
    $pdo->prepare("DELETE FROM cms_downloads WHERE id = ?")->execute([$id]);
    logAction('download_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/downloads.php');
exit;
