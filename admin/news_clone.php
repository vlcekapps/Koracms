<?php
/**
 * Duplikace novinky – vytvoří kopii s novým ID, stavem „draft" a aktuálním uživatelem jako autorem.
 * POST: csrf_token, id
 */
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id === null) {
    header('Location: ' . BASE_URL . '/admin/news.php');
    exit;
}

$pdo = db_connect();

$stmt = $pdo->prepare(
    "SELECT id, title, slug, content, admin_note, meta_title, meta_description
     FROM cms_news
     WHERE id = ? AND deleted_at IS NULL"
);
$stmt->execute([$id]);
$source = $stmt->fetch();

if (!$source) {
    header('Location: ' . BASE_URL . '/admin/news.php');
    exit;
}

$newSlug = uniqueNewsSlug($pdo, newsSlug((string)$source['slug'] . '-kopie'), null);
$previewToken = bin2hex(random_bytes(16));
$authorId = currentUserId();

$pdo->prepare(
    "INSERT INTO cms_news
        (title, slug, content, admin_note, meta_title, meta_description, status, preview_token, author_id,
         created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW(), NOW())"
)->execute([
    (string)$source['title'] . ' (kopie)',
    $newSlug,
    (string)($source['content'] ?? ''),
    (string)($source['admin_note'] ?? ''),
    (string)($source['meta_title'] ?? ''),
    (string)($source['meta_description'] ?? ''),
    $previewToken,
    $authorId,
]);
$newId = (int)$pdo->lastInsertId();

logAction('news_clone', "source_id={$id} new_id={$newId} title=" . (string)$source['title']);

header('Location: ' . BASE_URL . '/admin/news_form.php?id=' . $newId);
exit;
