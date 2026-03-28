<?php
require_once __DIR__ . '/../db.php';
requireCapability('blog_taxonomies_manage', 'Přístup odepřen. Pro správu štítků blogu nemáte potřebné oprávnění.');
verifyCsrf();

if (!hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blogs.php?msg=no_blog');
    exit;
}

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();
    $pdo->prepare("DELETE FROM cms_tags WHERE id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_article_tags WHERE tag_id = ?")->execute([$id]);
    logAction('tag_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/blog_tags.php');
exit;
