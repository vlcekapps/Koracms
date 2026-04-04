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
            "SELECT *
             FROM cms_events
             WHERE slug = ? AND preview_token = ? AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$slug, $previewToken]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM cms_events
             WHERE id = ? AND preview_token = ? AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$id, $previewToken]);
    }
} else {
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

$event = hydrateEventPresentation($event);

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
        'backUrl' => $backUrl,
    ],
    'current_nav' => 'events',
    'body_class' => 'page-events-article',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/event_form.php?id=' . (int)$event['id'],
    'extra_head_html' => $extraHeadHtml,
]);
