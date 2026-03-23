<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('food')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$id = inputInt('get', 'id');
if ($id === null) {
    header('Location: archive.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT * FROM cms_food_cards
     WHERE id = ? AND status = 'published' AND is_published = 1"
);
$stmt->execute([$id]);
$card = $stmt->fetch();
if (!$card) {
    header('Location: archive.php');
    exit;
}

$typeLabels = ['food' => 'Jídelní lístek', 'beverage' => 'Nápojový lístek'];
$typeLabel = $typeLabels[$card['type']] ?? '';

$from = $card['valid_from'] ? formatCzechDate($card['valid_from']) : null;
$to = $card['valid_to'] ? formatCzechDate($card['valid_to']) : null;
$validityLabel = '';
if ($from && $to) {
    $validityLabel = 'Platnost: ' . $from . ' – ' . $to;
} elseif ($from) {
    $validityLabel = 'Platnost od ' . $from;
} elseif ($to) {
    $validityLabel = 'Platnost do ' . $to;
}

renderPublicPage([
    'title' => $card['title'] . ' – ' . $siteName,
    'meta' => [
        'title' => $card['title'] . ' – ' . $siteName,
        'description' => $card['description'] ?: trim($typeLabel . ($validityLabel !== '' ? ', ' . $validityLabel : '')),
        'url' => BASE_URL . '/food/card.php?id=' . $id,
    ],
    'view' => 'modules/food-card',
    'view_data' => [
        'card' => $card,
        'typeLabel' => $typeLabel,
        'validityLabel' => $validityLabel,
    ],
    'current_nav' => 'food',
    'body_class' => 'page-food-card',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/food_form.php?id=' . $id,
]);
