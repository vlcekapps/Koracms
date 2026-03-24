<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu událostí nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$title = trim($_POST['title'] ?? '');
$submittedSlug = trim($_POST['slug'] ?? '');
$description = $_POST['description'] ?? '';
$location = trim($_POST['location'] ?? '');
$isPublished = isset($_POST['is_published']) ? 1 : 0;

$eventDate = null;
if (!empty($_POST['event_date'])) {
    $date = trim($_POST['event_date']);
    $time = trim($_POST['event_time'] ?? '') ?: '00:00';
    $dateTime = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
    if ($dateTime) {
        $eventDate = $dateTime->format('Y-m-d H:i:s');
    }
}

$eventEnd = null;
if (!empty($_POST['event_end_date'])) {
    $date = trim($_POST['event_end_date']);
    $time = trim($_POST['event_end_time'] ?? '') ?: '00:00';
    $dateTime = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
    if ($dateTime) {
        $eventEnd = $dateTime->format('Y-m-d H:i:s');
    }
}

if ($title === '' || $eventDate === null) {
    header('Location: event_form.php?err=required' . ($id ? '&id=' . $id : ''));
    exit;
}

$slug = eventSlug($submittedSlug !== '' ? $submittedSlug : $title);
if ($slug === '') {
    header('Location: event_form.php?err=slug' . ($id ? '&id=' . $id : ''));
    exit;
}

$uniqueSlug = uniqueEventSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    header('Location: event_form.php?err=slug' . ($id ? '&id=' . $id : ''));
    exit;
}
$slug = $uniqueSlug;

if ($id !== null) {
    $pdo->prepare(
        "UPDATE cms_events
         SET title = ?, slug = ?, description = ?, location = ?, event_date = ?, event_end = ?, is_published = ?, updated_at = NOW()
         WHERE id = ?"
    )->execute([$title, $slug, $description, $location, $eventDate, $eventEnd, $isPublished, $id]);
    logAction('event_edit', "id={$id} title={$title} slug={$slug}");
} else {
    $status = currentUserHasCapability('content_approve_shared') ? 'published' : 'pending';
    $visible = currentUserHasCapability('content_approve_shared') ? $isPublished : 0;
    $pdo->prepare(
        "INSERT INTO cms_events (title, slug, description, location, event_date, event_end, is_published, status)
         VALUES (?,?,?,?,?,?,?,?)"
    )->execute([$title, $slug, $description, $location, $eventDate, $eventEnd, $visible, $status]);
    $id = (int)$pdo->lastInsertId();
    logAction('event_add', "id={$id} title={$title} slug={$slug} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/events.php');
exit;
