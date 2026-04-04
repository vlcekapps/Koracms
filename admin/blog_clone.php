<?php
/**
 * Duplikace článku – vytvoří kopii s novým ID, stavem „draft" a aktuálním uživatelem jako autorem.
 * POST: csrf_token, id
 */
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id === null) {
    header('Location: ' . BASE_URL . '/admin/blog.php');
    exit;
}

$pdo = db_connect();

$stmt = $pdo->prepare(
    "SELECT id, title, slug, perex, content, category_id, blog_id,
            comments_enabled, meta_title, meta_description, image_file
     FROM cms_articles
     WHERE id = ? AND deleted_at IS NULL"
);
$stmt->execute([$id]);
$source = $stmt->fetch();

if (!$source) {
    header('Location: ' . BASE_URL . '/admin/blog.php');
    exit;
}

$blogId = (int)($source['blog_id'] ?? 1);
$newSlug = uniqueArticleSlug($pdo, articleSlug((string)$source['slug'] . '-kopie'), null, $blogId);
$previewToken = bin2hex(random_bytes(16));
$authorId = currentUserId();

$pdo->prepare(
    "INSERT INTO cms_articles
        (title, slug, perex, content, category_id, blog_id, comments_enabled,
         meta_title, meta_description, image_file, status, preview_token, author_id,
         created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW(), NOW())"
)->execute([
    (string)$source['title'] . ' (kopie)',
    $newSlug,
    (string)($source['perex'] ?? ''),
    (string)($source['content'] ?? ''),
    $source['category_id'],
    $blogId,
    (int)($source['comments_enabled'] ?? 1),
    (string)($source['meta_title'] ?? ''),
    (string)($source['meta_description'] ?? ''),
    (string)($source['image_file'] ?? ''),
    $previewToken,
    $authorId,
]);
$newId = (int)$pdo->lastInsertId();

// Klonování štítků
$tagStmt = $pdo->prepare("SELECT tag_id FROM cms_article_tags WHERE article_id = ?");
$tagStmt->execute([$id]);
$tagIds = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

if ($tagIds !== []) {
    $insertTag = $pdo->prepare("INSERT IGNORE INTO cms_article_tags (article_id, tag_id) VALUES (?, ?)");
    foreach ($tagIds as $tagId) {
        $insertTag->execute([$newId, (int)$tagId]);
    }
}

logAction('article_clone', "source_id={$id} new_id={$newId} title=" . (string)$source['title']);

header('Location: ' . BASE_URL . '/admin/blog_form.php?id=' . $newId);
exit;
