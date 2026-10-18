<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu úřední desky nemáte potřebné oprávnění.');
requireModuleEnabled('board');
verifyCsrf();

$id = inputInt('post', 'id');
$redirectWithError = static function (string $error) use ($id): void {
    header('Location: ' . appendUrlQuery(BASE_URL . '/admin/board.php', [
        'delete_error' => $error,
        'delete_error_id' => $id,
    ]));
    exit;
};
if ($id === null) {
    $redirectWithError('invalid');
}
$confirmFieldName = 'confirm_board_delete_' . $id;
if (($_POST[$confirmFieldName] ?? '') !== '1') {
    $redirectWithError('confirm_required');
}
$pdo = db_connect();
$deleteErrorCode = 'failed';
try {
    $pdo->beginTransaction();
    $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare('SELECT * FROM cms_board WHERE id = ? AND deleted_at IS NULL' . $lock);
    $stmt->execute([$id]);
    $document = $stmt->fetch() ?: null;
    if ($document === null) {
        $deleteErrorCode = 'invalid';
        throw new RuntimeException('Položka vývěsky již není dostupná pro přesun do Koše.');
    }
    recordBoardPublicationEvent($pdo, $document, 'deleted', currentUserId(), true);
    deleteRedirectsTargetingPath($pdo, boardPublicPath($document), true);
    $delete = $pdo->prepare("UPDATE cms_board SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $delete->execute([$id]);
    if ($delete->rowCount() !== 1) {
        throw new RuntimeException('Položku vývěsky se nepodařilo přesunout do Koše.');
    }
    logAction('board_delete', "id={$id} soft=true");
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    koraLog('warning', 'board deletion failed', ['board_id' => $id, 'exception' => $exception]);
    $redirectWithError($deleteErrorCode);
}

header('Location: ' . BASE_URL . '/admin/board.php?deleted=1');
exit;
