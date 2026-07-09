<?php

require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
requireModuleEnabled('blog');

$redirectToTags = static function (?int $blogId = null, array $params = []): void {
    $target = BASE_URL . '/admin/blog_tags.php';
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
    $redirectToTags();
}

verifyCsrf();

if (!hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blogs.php?msg=no_blog');
    exit;
}

$id = inputInt('post', 'id');
if ($id === null) {
    $redirectToTags();
}

$pdo = db_connect();
$tagStmt = $pdo->prepare("SELECT id, blog_id FROM cms_tags WHERE id = ?");
$tagStmt->execute([$id]);
$tag = $tagStmt->fetch() ?: null;

if (!$tag || !canCurrentUserManageBlogTaxonomies((int)$tag['blog_id'])) {
    $redirectToTags();
}

$blogId = (int)$tag['blog_id'];
$confirmFieldName = 'confirm_blog_tag_delete_' . $id;
$confirmedTagDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedTagDelete) {
    $redirectToTags($blogId, ['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$articleCountStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT a.id)
     FROM cms_article_tags at
     INNER JOIN cms_articles a ON a.id = at.article_id AND a.deleted_at IS NULL
     WHERE at.tag_id = ?"
);
$articleCountStmt->execute([$id]);
$articleCount = (int)$articleCountStmt->fetchColumn();

$pdo->prepare("DELETE FROM cms_article_tags WHERE tag_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM cms_tags WHERE id = ?")->execute([$id]);
logAction('tag_delete', "id={$id};blog_id={$blogId};article_count={$articleCount}");

$redirectToTags($blogId, ['deleted' => '1']);
