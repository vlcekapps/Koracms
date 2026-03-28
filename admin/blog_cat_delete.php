<?php
require_once __DIR__ . '/../db.php';
requireCapability('blog_taxonomies_manage', 'Přístup odepřen. Pro správu kategorií blogu nemáte potřebné oprávnění.');
verifyCsrf();

if (!hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blogs.php?msg=no_blog');
    exit;
}

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    $pdo->prepare("UPDATE cms_articles SET category_id = NULL WHERE category_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_categories WHERE id = ?")->execute([$id]);
}

header('Location: ' . BASE_URL . '/admin/blog_cats.php');
exit;
