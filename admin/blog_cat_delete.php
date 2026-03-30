<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

if (!hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blogs.php?msg=no_blog');
    exit;
}

$id = inputInt('post', 'id');
$redirect = BASE_URL . '/admin/blog_cats.php';

if ($id !== null) {
    $pdo = db_connect();
    $categoryStmt = $pdo->prepare("SELECT id, blog_id FROM cms_categories WHERE id = ?");
    $categoryStmt->execute([$id]);
    $category = $categoryStmt->fetch() ?: null;

    if ($category && canCurrentUserManageBlogTaxonomies((int)$category['blog_id'])) {
        $pdo->prepare("UPDATE cms_articles SET category_id = NULL WHERE category_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM cms_categories WHERE id = ?")->execute([$id]);
        $redirect = BASE_URL . '/admin/blog_cats.php?blog_id=' . (int)$category['blog_id'];
    }
}

header('Location: ' . $redirect);
exit;
