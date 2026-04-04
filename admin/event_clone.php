<?php
/**
 * Duplikace události – vytvoří kopii s novým ID, stavem „draft" a novým preview tokenem.
 * POST: csrf_token, id
 */
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu událostí nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id === null) {
    header('Location: ' . BASE_URL . '/admin/events.php');
    exit;
}

$pdo = db_connect();

$stmt = $pdo->prepare(
    "SELECT id, title, slug, excerpt, description, event_date, event_end, event_kind,
            location, organizer_name, organizer_email, registration_url, price_note,
            accessibility_note, program_note, image_file
     FROM cms_events
     WHERE id = ? AND deleted_at IS NULL"
);
$stmt->execute([$id]);
$source = $stmt->fetch();

if (!$source) {
    header('Location: ' . BASE_URL . '/admin/events.php');
    exit;
}

$newSlug = uniqueEventSlug($pdo, eventSlug((string)$source['slug'] . '-kopie'), null);
$previewToken = bin2hex(random_bytes(16));

$pdo->prepare(
    "INSERT INTO cms_events
        (title, slug, excerpt, description, event_date, event_end, event_kind,
         location, organizer_name, organizer_email, registration_url, price_note,
         accessibility_note, program_note, image_file,
         status, is_published, preview_token, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 0, ?, NOW(), NOW())"
)->execute([
    (string)$source['title'] . ' (kopie)',
    $newSlug,
    (string)($source['excerpt'] ?? ''),
    (string)($source['description'] ?? ''),
    $source['event_date'],
    $source['event_end'],
    (string)($source['event_kind'] ?? 'general'),
    (string)($source['location'] ?? ''),
    (string)($source['organizer_name'] ?? ''),
    (string)($source['organizer_email'] ?? ''),
    (string)($source['registration_url'] ?? ''),
    (string)($source['price_note'] ?? ''),
    (string)($source['accessibility_note'] ?? ''),
    (string)($source['program_note'] ?? ''),
    (string)($source['image_file'] ?? ''),
    $previewToken,
]);
$newId = (int)$pdo->lastInsertId();

logAction('event_clone', "source_id={$id} new_id={$newId} title=" . (string)$source['title']);

header('Location: ' . BASE_URL . '/admin/event_form.php?id=' . $newId);
exit;
