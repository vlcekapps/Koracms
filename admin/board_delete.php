<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu úřední desky nemáte potřebné oprávnění.');
requireModuleEnabled('board');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT * FROM cms_board WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $document = $stmt->fetch() ?: null;
    if ($document) {
        recordBoardPublicationEvent($pdo, $document, 'deleted', currentUserId());
        deleteRedirectsTargetingPath($pdo, boardPublicPath($document));
    }
    $pdo->prepare("UPDATE cms_board SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([$id]);
    logAction('board_delete', "id={$id} soft=true");
}

header('Location: ' . BASE_URL . '/admin/board.php');
exit;
