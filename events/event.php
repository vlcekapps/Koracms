<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('events')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = inputInt('get', 'id');
$slug = eventSlug(trim((string)($_GET['slug'] ?? '')));
if ($id === null && $slug === '') {
    header('Location: ' . BASE_URL . '/events/index.php');
    exit;
}

$listingQuery = [];
foreach (['q', 'misto', 'typ', 'period', 'scope', 'strana'] as $queryKey) {
    $queryValue = trim((string)($_GET[$queryKey] ?? ''));
    if ($queryValue !== '') {
        $listingQuery[$queryKey] = $queryValue;
    }
}

$pdo = db_connect();

$previewToken = trim($_GET['preview'] ?? '');
if ($previewToken !== '') {
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
             WHERE e.slug = ? AND e.preview_token = ? AND e.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$slug, $previewToken]);
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
             WHERE e.id = ? AND e.preview_token = ? AND e.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$id, $previewToken]);
    }
} else {
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
}

$event = $stmt->fetch() ?: null;
if (!$event) {
    $missingPath = $slug !== ''
        ? BASE_URL . '/events/' . rawurlencode($slug)
        : BASE_URL . '/events/event.php?id=' . urlencode((string)$id);

    renderPublicNotFoundPage([
        'title' => 'Událost nenalezena',
        'meta' => [
            'url' => $missingPath,
        ],
        'body_class' => 'page-event-not-found',
    ]);
}

$event = hydrateEventPresentation($event);
$recurrenceEvents = [];
if ($previewToken === '' && (string)($event['recurrence_group_id'] ?? '') !== '') {
    $recurrenceStmt = $pdo->prepare(
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
         WHERE e.recurrence_group_id = ?
           AND " . eventPublicVisibilitySql('e') . "
         ORDER BY e.event_date ASC, e.id ASC"
    );
    $recurrenceStmt->execute([(string)$event['recurrence_group_id']]);
    $recurrenceEvents = array_map(
        static fn (array $row): array => hydrateEventPresentation($row),
        $recurrenceStmt->fetchAll()
    );
}

if ($slug === '' && (string)$event['slug'] !== '') {
    header('Location: ' . eventPublicPath($event, $listingQuery));
    exit;
}

if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('event', (int)$event['id']);
}

$siteName = getSetting('site_name', 'Kora CMS');
$metaDescription = eventExcerpt($event, 180);
if ($metaDescription === '') {
    $metaDescription = 'Detail události ' . (string)$event['title'];
}

$backQuery = $listingQuery;
if ($backQuery === [] && (string)$event['event_status_key'] === 'past') {
    $backQuery['scope'] = 'past';
}
$backUrl = BASE_URL . '/events/index.php' . ($backQuery !== [] ? '?' . http_build_query($backQuery) : '');
$extraHeadHtml = eventStructuredData($event);

renderPublicPage([
    'title' => (string)$event['title'] . ' - ' . $siteName,
    'meta' => [
        'title' => (string)$event['title'] . ' - ' . $siteName,
        'description' => $metaDescription,
        'url' => eventPublicUrl($event),
        'type' => 'article',
    ],
    'view' => 'modules/events-article',
    'view_data' => [
        'event' => $event,
        'recurrenceEvents' => $recurrenceEvents,
        'backUrl' => $backUrl,
    ],
    'current_nav' => 'events',
    'body_class' => 'page-events-article',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/event_form.php?id=' . (int)$event['id'],
    'extra_head_html' => $extraHeadHtml,
]);
