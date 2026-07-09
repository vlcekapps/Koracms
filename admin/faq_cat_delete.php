<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu kategorií FAQ nemáte potřebné oprávnění.');
requireModuleEnabled('faq');

$redirectToCategories = static function (array $params = []): void {
    $target = BASE_URL . '/admin/faq_cats.php';
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

$pdo = db_connect();
$categoryStmt = $pdo->prepare('SELECT id FROM cms_faq_categories WHERE id = ? LIMIT 1');
$categoryStmt->execute([$id]);
if (!$categoryStmt->fetch()) {
    $redirectToCategories();
}

$confirmFieldName = 'confirm_faq_category_delete_' . $id;
$confirmedCategoryDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedCategoryDelete) {
    $redirectToCategories(['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$faqCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_faqs WHERE category_id = ? AND deleted_at IS NULL');
$faqCountStmt->execute([$id]);
$faqCount = (int)$faqCountStmt->fetchColumn();

$childCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_faq_categories WHERE parent_id = ?');
$childCountStmt->execute([$id]);
$childCount = (int)$childCountStmt->fetchColumn();

$pdo->prepare('UPDATE cms_faqs SET category_id = NULL WHERE category_id = ?')->execute([$id]);
$pdo->prepare('UPDATE cms_faq_categories SET parent_id = NULL WHERE parent_id = ?')->execute([$id]);
$pdo->prepare('DELETE FROM cms_faq_categories WHERE id = ?')->execute([$id]);
logAction('faq_cat_delete', "id={$id};faq_count={$faqCount};child_count={$childCount}");

$redirectToCategories(['deleted' => '1']);
