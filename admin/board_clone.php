<?php
/**
 * Duplikace položky úřední desky – vytvoří kopii s novým ID, stavem „draft" a dnešním datem vyvěšení.
 * POST: csrf_token, id
 */
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu úřední desky nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id === null) {
    header('Location: ' . BASE_URL . '/admin/board.php');
    exit;
}

$pdo = db_connect();

$stmt = $pdo->prepare(
    "SELECT id, title, slug, board_type, excerpt, description, category_id,
            contact_name, contact_phone, contact_email, image_file
     FROM cms_board
     WHERE id = ? AND deleted_at IS NULL"
);
$stmt->execute([$id]);
$source = $stmt->fetch();

if (!$source) {
    header('Location: ' . BASE_URL . '/admin/board.php');
    exit;
}

$newSlug = uniqueBoardSlug($pdo, boardSlug((string)$source['slug'] . '-kopie'), null);
$previewToken = bin2hex(random_bytes(16));
$authorId = currentUserId();

$pdo->prepare(
    "INSERT INTO cms_board
        (title, slug, board_type, excerpt, description, category_id, posted_date,
         contact_name, contact_phone, contact_email, image_file,
         status, is_published, preview_token, author_id, created_at)
     VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, 'draft', 0, ?, ?, NOW())"
)->execute([
    (string)$source['title'] . ' (kopie)',
    $newSlug,
    (string)($source['board_type'] ?? 'document'),
    (string)($source['excerpt'] ?? ''),
    (string)($source['description'] ?? ''),
    $source['category_id'],
    (string)($source['contact_name'] ?? ''),
    (string)($source['contact_phone'] ?? ''),
    (string)($source['contact_email'] ?? ''),
    (string)($source['image_file'] ?? ''),
    $previewToken,
    $authorId,
]);
$newId = (int)$pdo->lastInsertId();

logAction('board_clone', "source_id={$id} new_id={$newId} title=" . (string)$source['title']);

header('Location: ' . BASE_URL . '/admin/board_form.php?id=' . $newId);
exit;
