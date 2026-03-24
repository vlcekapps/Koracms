<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu úřední desky nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT filename, image_file FROM cms_board WHERE id = ?");
    $stmt->execute([$id]);
    $document = $stmt->fetch() ?: null;
    $filename = (string)($document['filename'] ?? '');
    if ($filename !== '') {
        $deletePath = __DIR__ . '/../uploads/board/' . basename($filename);
        if (file_exists($deletePath)) {
            unlink($deletePath);
        }
    }
    deleteBoardImageFile((string)($document['image_file'] ?? ''));
    $pdo->prepare("DELETE FROM cms_board WHERE id = ?")->execute([$id]);
    logAction('board_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/board.php');
exit;
