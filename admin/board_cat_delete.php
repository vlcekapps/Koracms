<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu kategorií úřední desky nemáte potřebné oprávnění.');
requireModuleEnabled('board');

$redirectToCategories = static function (array $params = []): void {
    $target = BASE_URL . '/admin/board_cats.php';
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

$confirmFieldName = 'confirm_board_category_delete_' . $id;
$confirmedCategoryDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedCategoryDelete) {
    $redirectToCategories(['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$pdo = db_connect();
$categoryStmt = $pdo->prepare('SELECT id FROM cms_board_categories WHERE id = ? LIMIT 1');
$categoryStmt->execute([$id]);
if (!$categoryStmt->fetch()) {
    $redirectToCategories();
}

$boardCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_board WHERE category_id = ? AND deleted_at IS NULL');
$boardCountStmt->execute([$id]);
$boardCount = (int)$boardCountStmt->fetchColumn();

$subscriberCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_board_subscriber_categories WHERE category_id = ?');
$subscriberCountStmt->execute([$id]);
$subscriberCount = (int)$subscriberCountStmt->fetchColumn();

$pdo->prepare("DELETE FROM cms_board_subscriber_categories WHERE category_id = ?")->execute([$id]);
$pdo->prepare("UPDATE cms_board SET category_id = NULL WHERE category_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM cms_board_categories WHERE id = ?")->execute([$id]);
logAction('board_cat_delete', "id={$id};board_count={$boardCount};subscriber_count={$subscriberCount}");

$redirectToCategories(['deleted' => '1']);
