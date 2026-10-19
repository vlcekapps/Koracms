<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu jídelních lístků nemáte potřebné oprávnění.');
requireModuleEnabled('food');
requireHttpMethods(['POST']);
verifyCsrf();

$id = inputInt('post', 'id');
$deleteError = static function (string $error) use ($id): void {
    header('Location: ' . appendUrlQuery(BASE_URL . '/admin/food.php', ['delete_error' => $error, 'delete_error_id' => $id ?? 0]));
    exit;
};
if ($id === null) {
    $deleteError('invalid');
}
if (($_POST['confirm_food_delete_' . $id] ?? '') !== '1') {
    $deleteError('confirm_required');
}
try {
    $stmt = db_connect()->prepare("UPDATE cms_food_cards SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    if ($stmt->rowCount() !== 1) {
        $deleteError('invalid');
    }
} catch (PDOException $exception) {
    koraLog('warning', 'food card delete failed', ['id' => $id, 'exception' => $exception]);
    $deleteError('save');
}
logAction('food_delete', "id={$id} soft=true");

header('Location: ' . BASE_URL . '/admin/food.php?msg=deleted');
exit;
