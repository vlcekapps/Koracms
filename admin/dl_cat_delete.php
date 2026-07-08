<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu kategorií ke stažení nemáte potřebné oprávnění.');
requireModuleEnabled('downloads');
verifyCsrf();

$redirectToCategories = static function (array $params = []): void {
    $target = BASE_URL . '/admin/dl_cats.php';
    if ($params !== []) {
        $target .= '?' . http_build_query($params);
    }
    header('Location: ' . $target);
    exit;
};

$id = inputInt('post', 'id');
if ($id === null) {
    $redirectToCategories(['delete_error' => 'invalid']);
}

$confirmFieldName = 'confirm_download_category_delete_' . $id;
$confirmedCategoryDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedCategoryDelete) {
    $redirectToCategories(['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$pdo = db_connect();
$categoryStmt = $pdo->prepare('SELECT id FROM cms_dl_categories WHERE id = ? LIMIT 1');
$categoryStmt->execute([$id]);
if (!$categoryStmt->fetch()) {
    $redirectToCategories(['delete_error' => 'invalid', 'delete_error_id' => $id]);
}

$downloadCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_downloads WHERE dl_category_id = ? AND deleted_at IS NULL');
$downloadCountStmt->execute([$id]);
$downloadCount = (int)$downloadCountStmt->fetchColumn();

// Zruší vazbu souborů na tuto kategorii.
$pdo->prepare('UPDATE cms_downloads SET dl_category_id = NULL WHERE dl_category_id = ?')->execute([$id]);
$pdo->prepare('DELETE FROM cms_dl_categories WHERE id = ?')->execute([$id]);
logAction('dl_cat_delete', "id={$id};download_count={$downloadCount}");

$redirectToCategories(['deleted' => '1']);
