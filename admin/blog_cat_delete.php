<?php

require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
requireModuleEnabled('blog');

$redirectToCategories = static function (?int $blogId = null, array $params = []): void {
    $target = BASE_URL . '/admin/blog_cats.php';
    if ($blogId !== null && $blogId > 0) {
        $params = ['blog_id' => $blogId] + $params;
    }
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

if (!hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blogs.php?msg=no_blog');
    exit;
}

$id = inputInt('post', 'id');
if ($id === null) {
    $redirectToCategories();
}

$pdo = db_connect();
$categoryStmt = $pdo->prepare("SELECT id, blog_id FROM cms_categories WHERE id = ?");
$categoryStmt->execute([$id]);
$category = $categoryStmt->fetch() ?: null;

if (!$category || !canCurrentUserManageBlogTaxonomies((int)$category['blog_id'])) {
    $redirectToCategories();
}

$blogId = (int)$category['blog_id'];
$confirmFieldName = 'confirm_blog_category_delete_' . $id;
$confirmedCategoryDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedCategoryDelete) {
    $redirectToCategories($blogId, ['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$articleCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_articles WHERE category_id = ? AND deleted_at IS NULL');
$articleCountStmt->execute([$id]);
$articleCount = (int)$articleCountStmt->fetchColumn();

$childCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_categories WHERE parent_id = ? AND blog_id = ?');
$childCountStmt->execute([$id, $blogId]);
$childCount = (int)$childCountStmt->fetchColumn();

$pdo->prepare("UPDATE cms_articles SET category_id = NULL WHERE category_id = ?")->execute([$id]);
$pdo->prepare("UPDATE cms_categories SET parent_id = NULL WHERE parent_id = ? AND blog_id = ?")->execute([$id, $blogId]);
$pdo->prepare("DELETE FROM cms_categories WHERE id = ?")->execute([$id]);
logAction('blog_cat_delete', "id={$id};blog_id={$blogId};article_count={$articleCount};child_count={$childCount}");

$redirectToCategories($blogId, ['deleted' => '1']);
