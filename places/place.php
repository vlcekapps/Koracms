<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('places')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = inputInt('get', 'id');
$slug = placeSlug(trim((string)($_GET['slug'] ?? '')));
if ($id === null && $slug === '') {
    header('Location: ' . BASE_URL . '/places/index.php');
    exit;
}

$listingQuery = [];
foreach (['q', 'kind', 'category', 'locality', 'strana'] as $queryKey) {
    $queryValue = trim((string)($_GET[$queryKey] ?? ''));
    if ($queryValue !== '') {
        $listingQuery[$queryKey] = $queryValue;
    }
}

$pdo = db_connect();

if ($slug !== '') {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM cms_places
         WHERE slug = ? AND " . placePublicVisibilitySql() . "
         LIMIT 1"
    );
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM cms_places
         WHERE id = ? AND " . placePublicVisibilitySql() . "
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
    header('Location: ' . placePublicPath($place, $listingQuery), true, 302);
    exit;
}

if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('place', (int)$place['id']);
}

$siteName = getSetting('site_name', 'Kora CMS');
$metaTitleBase = $place['meta_title'] !== '' ? $place['meta_title'] : (string)$place['name'];
$metaDescription = $place['meta_description'] !== '' ? $place['meta_description'] : placeExcerpt($place, 180);
if ($metaDescription === '') {
    $metaDescription = 'Detail místa ' . (string)$place['name'];
}

$backUrl = BASE_URL . '/places/index.php' . ($listingQuery !== [] ? '?' . http_build_query($listingQuery) : '');

renderPublicPage([
    'title' => $metaTitleBase . ' - ' . $siteName,
    'meta' => [
        'title' => $metaTitleBase . ' - ' . $siteName,
        'description' => $metaDescription,
        'url' => placePublicUrl($place),
        'type' => 'article',
    ],
    'view' => 'modules/places-article',
    'view_data' => [
        'place' => $place,
        'backUrl' => $backUrl,
    ],
    'current_nav' => 'places',
    'body_class' => 'page-places-article',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/place_form.php?id=' . (int)$place['id'],
    'extra_head_html' => placeStructuredData($place),
]);
