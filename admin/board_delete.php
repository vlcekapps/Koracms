<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo  = db_connect();
    $stmt = $pdo->prepare("SELECT filename FROM cms_board WHERE id = ?");
    $stmt->execute([$id]);
    $filename = $stmt->fetchColumn();
    if ($filename) {
        $delPath = __DIR__ . '/../uploads/board/' . $filename;
        if (file_exists($delPath)) { unlink($delPath); }
    }
    $pdo->prepare("DELETE FROM cms_board WHERE id = ?")->execute([$id]);
    logAction('board_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/board.php');
exit;
