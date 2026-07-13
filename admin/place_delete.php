<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu míst nemáte potřebné oprávnění.');
requireModuleEnabled('places');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/places.php');
    exit;
}

verifyCsrf();

$id = inputInt('post', 'id');
$redirectTarget = internalRedirectTarget((string)($_POST['redirect'] ?? ''), BASE_URL . '/admin/places.php');

$redirectWithDeleteState = static function (array $params) use ($redirectTarget): void {
    header('Location: ' . appendUrlQuery($redirectTarget, $params));
    exit;
};

if ($id === null) {
    $redirectWithDeleteState(['delete_error' => 'invalid']);
}

$confirmFieldName = 'confirm_place_delete_' . $id;
$confirmedPlaceDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedPlaceDelete) {
    $redirectWithDeleteState(['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$pdo = db_connect();
try {
    $pdo->beginTransaction();
    $placeStmt = $pdo->prepare(
        "SELECT id
         FROM cms_places
         WHERE id = ? AND deleted_at IS NULL
         FOR UPDATE"
    );
    $placeStmt->execute([$id]);
    if (!$placeStmt->fetch()) {
        $pdo->rollBack();
        $redirectWithDeleteState(['delete_error' => 'invalid', 'delete_error_id' => $id]);
    }

    $updateStmt = $pdo->prepare("UPDATE cms_places SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $updateStmt->execute([$id]);
    if ($updateStmt->rowCount() !== 1) {
        throw new RuntimeException('Místo se nepodařilo přesunout do Koše.');
    }

    logAction('place_delete', "id={$id} soft=true");
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    koraLog('warning', 'admin place soft delete failed', [
        'operation' => 'place_soft_delete',
        'place_id' => $id,
        'exception' => $e,
    ]);
    $redirectWithDeleteState(['delete_error' => 'failed', 'delete_error_id' => $id]);
}

$redirectWithDeleteState(['deleted' => '1']);
