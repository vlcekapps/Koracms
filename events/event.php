<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('events')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = inputInt('get', 'id');
$slug = eventSlug(trim($_GET['slug'] ?? ''));
if ($id === null && $slug === '') {
    header('Location: ' . BASE_URL . '/events/index.php');
    exit;
}

$pdo = db_connect();

if ($slug !== '') {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM cms_events
         WHERE slug = ? AND status = 'published' AND is_published = 1
         LIMIT 1"
    );
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM cms_events
         WHERE id = ? AND status = 'published' AND is_published = 1
         LIMIT 1"
    );
    $stmt->execute([$id]);
}

$event = $stmt->fetch() ?: null;
if (!$event) {
    http_response_code(404);
    $siteName = getSetting('site_name', 'Kora CMS');
    $missingPath = $slug !== ''
        ? BASE_URL . '/events/' . rawurlencode($slug)
        : BASE_URL . '/events/event.php' . ($id !== null ? '?id=' . urlencode((string)$id) : '');

    renderPublicPage([
        'title' => 'Událost nenalezena - ' . $siteName,
        'meta' => [
            'title' => 'Událost nenalezena - ' . $siteName,
            'url' => $missingPath,
        ],
        'view' => 'not-found',
        'body_class' => 'page-event-not-found',
    ]);
    exit;
}

if ($slug === '' && !empty($event['slug'])) {
    header('Location: ' . eventPublicPath($event));
    exit;
}

if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('event', (int)$event['id']);
}

$siteName = getSetting('site_name', 'Kora CMS');
$metaDescription = normalizePlainText((string)($event['description'] ?? ''));
if ($metaDescription === '') {
    $metaDescription = 'Detail události ' . (string)$event['title'];
} else {
    $metaDescription = mb_strimwidth($metaDescription, 0, 180, '...', 'UTF-8');
}

renderPublicPage([
    'title' => $event['title'] . ' - ' . $siteName,
    'meta' => [
        'title' => $event['title'] . ' - ' . $siteName,
        'description' => $metaDescription,
        'url' => eventPublicUrl($event),
        'type' => 'article',
    ],
    'view' => 'modules/events-article',
    'view_data' => [
        'event' => $event,
    ],
    'current_nav' => 'events',
    'body_class' => 'page-events-article',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/event_form.php?id=' . (int)$event['id'],
]);
