<?php

require_once __DIR__ . '/../db.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu kategorií rezervací nemáte potřebné oprávnění.');
requireModuleEnabled('reservations');

$redirectToCategories = static function (array $params = []): void {
    $target = BASE_URL . '/admin/res_categories.php';
    if ($params !== []) {
        $target .= '?' . http_build_query($params);
    }
    header('Location: ' . $target);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirectToCategories();
}

verifyCsrf();

$id = inputInt('post', 'id');
if ($id === null) {
    $redirectToCategories();
}

$confirmFieldName = 'confirm_res_category_delete_' . $id;
$confirmedCategoryDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedCategoryDelete) {
    $redirectToCategories(['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$pdo = db_connect();
$resourceCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_res_resources WHERE category_id = ?');
$resourceCountStmt->execute([$id]);
$resourceCount = (int)$resourceCountStmt->fetchColumn();

$pdo->prepare("UPDATE cms_res_resources SET category_id = NULL WHERE category_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM cms_res_categories WHERE id = ?")->execute([$id]);
logAction('res_cat_delete', "id={$id};resource_count={$resourceCount}");

$redirectToCategories(['deleted' => '1']);
