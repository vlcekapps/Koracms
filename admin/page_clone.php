<?php
/**
 * Duplikace stránky – vytvoří kopii s novým ID, stavem „draft" a skrytou viditelností.
 * POST: csrf_token, id
 */
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu statických stránek nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id === null) {
    header('Location: ' . BASE_URL . '/admin/pages.php');
    exit;
}

$pdo = db_connect();

$stmt = $pdo->prepare(
    "SELECT id, title, slug, content, blog_id, admin_note
     FROM cms_pages
     WHERE id = ? AND deleted_at IS NULL"
);
$stmt->execute([$id]);
$source = $stmt->fetch();

if (!$source) {
    header('Location: ' . BASE_URL . '/admin/pages.php');
    exit;
}

$newSlug = uniquePageSlug($pdo, pageSlug((string)$source['slug'] . '-kopie'), null);
$previewToken = bin2hex(random_bytes(16));

$pdo->prepare(
    "INSERT INTO cms_pages
        (title, slug, content, blog_id, admin_note, status, is_published, preview_token,
         created_at)
     VALUES (?, ?, ?, ?, ?, 'draft', 0, ?, NOW())"
)->execute([
    (string)$source['title'] . ' (kopie)',
    $newSlug,
    (string)($source['content'] ?? ''),
    $source['blog_id'],
    (string)($source['admin_note'] ?? ''),
    $previewToken,
]);
$newId = (int)$pdo->lastInsertId();

logAction('page_clone', "source_id={$id} new_id={$newId} title=" . (string)$source['title']);

header('Location: ' . BASE_URL . '/admin/page_form.php?id=' . $newId);
exit;
