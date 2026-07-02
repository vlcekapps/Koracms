<?php

require_once __DIR__ . '/../db.php';

$isHeadRequest = requireReadOnlyHttpMethod();

checkMaintenanceMode();

if (!isModuleEnabled('events')) {
    sendReadOnlyNotFoundResponse('Události nejsou dostupné.', $isHeadRequest);
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
        "SELECT e.*,
                t.title AS event_type_title, t.slug AS event_type_slug, t.legacy_key AS event_type_legacy_key,
                t.description AS event_type_description, t.meta_title AS event_type_meta_title,
                t.meta_description AS event_type_meta_description, t.is_active AS event_type_is_active,
                p.name AS place_name, p.slug AS place_slug, p.address AS place_address, p.locality AS place_locality,
                p.latitude AS place_latitude, p.longitude AS place_longitude, p.status AS place_status,
                p.is_published AS place_is_published
         FROM cms_events e
         LEFT JOIN cms_event_types t ON t.id = e.event_type_id
         LEFT JOIN cms_places p ON p.id = e.place_id
         WHERE e.slug = ? AND " . eventPublicVisibilitySql('e') . "
         LIMIT 1"
    );
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT e.*,
                t.title AS event_type_title, t.slug AS event_type_slug, t.legacy_key AS event_type_legacy_key,
                t.description AS event_type_description, t.meta_title AS event_type_meta_title,
                t.meta_description AS event_type_meta_description, t.is_active AS event_type_is_active,
                p.name AS place_name, p.slug AS place_slug, p.address AS place_address, p.locality AS place_locality,
                p.latitude AS place_latitude, p.longitude AS place_longitude, p.status AS place_status,
                p.is_published AS place_is_published
         FROM cms_events e
         LEFT JOIN cms_event_types t ON t.id = e.event_type_id
         LEFT JOIN cms_places p ON p.id = e.place_id
         WHERE e.id = ? AND " . eventPublicVisibilitySql('e') . "
         LIMIT 1"
    );
    $stmt->execute([$id]);
}

$event = $stmt->fetch() ?: null;
if (!$event) {
    sendReadOnlyNotFoundResponse('Událost nebyla nalezena.', $isHeadRequest);
}

$event = hydrateEventPresentation($event);

sendReadOnlyContentHeaders(
    'text/calendar; charset=utf-8',
    $isHeadRequest,
    'attachment; filename="' . eventIcsFilename($event) . '"'
);
echo eventIcsContent($event);
