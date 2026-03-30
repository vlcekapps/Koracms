<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('food')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$cardId = inputInt('get', 'id');
$slug = foodCardSlug(trim((string)($_GET['slug'] ?? '')));

$listingQuery = [];
foreach (['q', 'typ', 'scope', 'strana'] as $queryKey) {
    $queryValue = trim((string)($_GET[$queryKey] ?? ''));
    if ($queryValue !== '') {
        $listingQuery[$queryKey] = $queryValue;
    }
}

if ($slug !== '') {
    $stmt = $pdo->prepare(
        "SELECT * FROM cms_food_cards
         WHERE slug = ? AND " . foodCardPublicVisibilitySql() . "
         LIMIT 1"
    );
    $stmt->execute([$slug]);
} elseif ($cardId !== null) {
    $stmt = $pdo->prepare(
        "SELECT * FROM cms_food_cards
         WHERE id = ? AND " . foodCardPublicVisibilitySql() . "
         LIMIT 1"
    );
    $stmt->execute([$cardId]);
} else {
    header('Location: ' . BASE_URL . '/food/archive.php');
    exit;
}

$cardRow = $stmt->fetch() ?: null;
if (!$cardRow) {
    http_response_code(404);
    $missingPath = $slug !== ''
        ? BASE_URL . '/food/card/' . rawurlencode($slug)
        : BASE_URL . '/food/card.php' . ($cardId !== null ? '?id=' . urlencode((string)$cardId) : '');

    renderPublicPage([
        'title' => 'Lístek nebyl nalezen - ' . $siteName,
        'meta' => [
            'title' => 'Lístek nebyl nalezen - ' . $siteName,
            'url' => $missingPath,
        ],
        'view' => 'not-found',
        'view_data' => [
            'title' => 'Lístek nebyl nalezen',
            'message' => 'Požadovaný jídelní nebo nápojový lístek už není dostupný.',
        ],
        'current_nav' => 'food',
        'body_class' => 'page-food-card page-not-found',
        'page_kind' => 'utility',
    ]);
    exit;
}

$card = hydrateFoodCardPresentation($cardRow);
$canonicalPath = foodCardPublicPath($card);
$legacyPath = $cardId !== null ? BASE_URL . '/food/card.php?id=' . urlencode((string)$cardId) : '';
if ($cardId !== null && $canonicalPath !== '' && $canonicalPath !== $legacyPath) {
    header('Location: ' . $canonicalPath, true, 302);
    exit;
}

$description = trim((string)($card['description'] ?? ''));
if ($description === '') {
    $description = trim($card['type_label'] . ($card['validity_label'] !== '' ? ', ' . $card['validity_label'] : ''));
}

$backUrl = BASE_URL . '/food/archive.php';
$backLabel = 'Zpět do archivu';
if ($listingQuery !== []) {
    $backUrl .= '?' . http_build_query($listingQuery);
} elseif ((string)($card['state_key'] ?? 'current') === 'current') {
    $backUrl = BASE_URL . '/food/index.php' . ((string)$card['type'] === 'beverage' ? '#beverage' : '#food');
    $backLabel = 'Zpět na aktuální lístek';
} elseif ((string)($card['state_key'] ?? '') === 'upcoming') {
    $backUrl = BASE_URL . '/food/archive.php?scope=upcoming';
}

renderPublicPage([
    'title' => $card['title'] . ' - ' . $siteName,
    'meta' => [
        'title' => $card['title'] . ' - ' . $siteName,
        'description' => $description,
        'url' => $canonicalPath,
        'type' => 'article',
    ],
    'view' => 'modules/food-card',
    'view_data' => [
        'card' => $card,
        'typeLabel' => $card['type_label'],
        'validityLabel' => $card['validity_label'],
        'backUrl' => $backUrl,
        'backLabel' => $backLabel,
    ],
    'current_nav' => 'food',
    'body_class' => 'page-food-card',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/food_form.php?id=' . (int)$card['id'],
    'extra_head_html' => foodCardStructuredData($card),
]);
