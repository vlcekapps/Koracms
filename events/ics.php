<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('events')) {
    http_response_code(404);
    exit;
}

$id = inputInt('get', 'id');
$slug = eventSlug(trim((string)($_GET['slug'] ?? '')));
if ($id === null && $slug === '') {
    header('Location: ' . BASE_URL . '/events/index.php');
    exit;
}

$pdo = db_connect();

if ($slug !== '') {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM cms_events
         WHERE slug = ? AND " . eventPublicVisibilitySql() . "
         LIMIT 1"
    );
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM cms_events
         WHERE id = ? AND " . eventPublicVisibilitySql() . "
         LIMIT 1"
    );
    $stmt->execute([$id]);
}

$event = $stmt->fetch() ?: null;
if (!$event) {
    http_response_code(404);
    exit('Událost nebyla nalezena.');
}

$event = hydrateEventPresentation($event);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . eventIcsFilename($event) . '"');
header('X-Content-Type-Options: nosniff');
echo eventIcsContent($event);
