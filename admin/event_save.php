<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo         = db_connect();
$id          = inputInt('post', 'id');
$title       = trim($_POST['title']       ?? '');
$description = $_POST['description']      ?? '';
$location    = trim($_POST['location']    ?? '');
$isPublished = isset($_POST['is_published']) ? 1 : 0;

$eventDate = null;
if (!empty($_POST['event_date'])) {
    $date = trim($_POST['event_date']);
    $time = trim($_POST['event_time'] ?? '') ?: '00:00';
    $dt   = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
    if ($dt) $eventDate = $dt->format('Y-m-d H:i:s');
}

$eventEnd = null;
if (!empty($_POST['event_end_date'])) {
    $date = trim($_POST['event_end_date']);
    $time = trim($_POST['event_end_time'] ?? '') ?: '00:00';
    $dt   = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
    if ($dt) $eventEnd = $dt->format('Y-m-d H:i:s');
}

if ($title === '' || $eventDate === null) {
    $back = 'event_form.php?err=required' . ($id ? "&id={$id}" : '');
    header('Location: ' . $back);
    exit;
}

if ($id !== null) {
    $pdo->prepare(
        "UPDATE cms_events SET title=?,description=?,location=?,event_date=?,event_end=?,is_published=?,updated_at=NOW()
         WHERE id=?"
    )->execute([$title, $description, $location, $eventDate, $eventEnd, $isPublished, $id]);
    logAction('event_edit', "id={$id}");
} else {
    $status      = isSuperAdmin() ? 'published' : 'pending';
    $isPublished = isSuperAdmin() ? $isPublished : 0;
    $pdo->prepare(
        "INSERT INTO cms_events (title,description,location,event_date,event_end,is_published,status)
         VALUES (?,?,?,?,?,?,?)"
    )->execute([$title, $description, $location, $eventDate, $eventEnd, $isPublished, $status]);
    logAction('event_add', "title={$title} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/events.php');
exit;
