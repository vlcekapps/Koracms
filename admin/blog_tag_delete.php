<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

if (!hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blogs.php?msg=no_blog');
    exit;
}

$id = inputInt('post', 'id');
$redirect = BASE_URL . '/admin/blog_tags.php';

if ($id !== null) {
    $pdo = db_connect();
    $tagStmt = $pdo->prepare("SELECT id, blog_id FROM cms_tags WHERE id = ?");
    $tagStmt->execute([$id]);
    $tag = $tagStmt->fetch() ?: null;

    if ($tag && canCurrentUserManageBlogTaxonomies((int)$tag['blog_id'])) {
        $pdo->prepare("DELETE FROM cms_tags WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM cms_article_tags WHERE tag_id = ?")->execute([$id]);
        logAction('tag_delete', 'id=' . $id);
        $redirect = BASE_URL . '/admin/blog_tags.php?blog_id=' . (int)$tag['blog_id'];
    }
}

header('Location: ' . $redirect);
exit;
