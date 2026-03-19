<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    db_connect()->prepare("DELETE FROM cms_tags WHERE id = ?")->execute([$id]);
    db_connect()->prepare("DELETE FROM cms_article_tags WHERE tag_id = ?")->execute([$id]);
    logAction('tag_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/blog_tags.php');
exit;
