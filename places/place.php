<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('places')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = inputInt('get', 'id');
$slug = placeSlug(trim($_GET['slug'] ?? ''));
if ($id === null && $slug === '') {
    header('Location: ' . BASE_URL . '/places/index.php');
    exit;
}

$pdo = db_connect();

if ($slug !== '') {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM cms_places
         WHERE slug = ? AND status = 'published' AND is_published = 1
         LIMIT 1"
    );
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM cms_places
         WHERE id = ? AND status = 'published' AND is_published = 1
         LIMIT 1"
    );
    $stmt->execute([$id]);
}

$place = $stmt->fetch() ?: null;
if (!$place) {
    http_response_code(404);
    $siteName = getSetting('site_name', 'Kora CMS');
    $missingPath = $slug !== ''
        ? BASE_URL . '/places/' . rawurlencode($slug)
        : BASE_URL . '/places/place.php' . ($id !== null ? '?id=' . urlencode((string)$id) : '');

    renderPublicPage([
        'title' => 'Místo nenalezeno - ' . $siteName,
        'meta' => [
            'title' => 'Místo nenalezeno - ' . $siteName,
            'url' => $missingPath,
        ],
        'view' => 'not-found',
        'body_class' => 'page-place-not-found',
    ]);
    exit;
}

$place = hydratePlacePresentation($place);

if ($slug === '' && !empty($place['slug'])) {
    header('Location: ' . placePublicPath($place));
    exit;
}

if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('place', (int)$place['id']);
}

$siteName = getSetting('site_name', 'Kora CMS');
$metaDescription = placeExcerpt($place, 180);
if ($metaDescription === '') {
    $metaDescription = 'Detail místa ' . (string)$place['name'];
}

renderPublicPage([
    'title' => (string)$place['name'] . ' - ' . $siteName,
    'meta' => [
        'title' => (string)$place['name'] . ' - ' . $siteName,
        'description' => $metaDescription,
        'url' => placePublicUrl($place),
        'type' => 'article',
    ],
    'view' => 'modules/places-article',
    'view_data' => [
        'place' => $place,
    ],
    'current_nav' => 'places',
    'body_class' => 'page-places-article',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/place_form.php?id=' . (int)$place['id'],
]);
