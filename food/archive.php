<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('food')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$filterType = $_GET['typ'] ?? 'vse';
if (!in_array($filterType, ['food', 'beverage', 'vse'], true)) {
    $filterType = 'vse';
}

$where = "WHERE status = 'published' AND is_published = 1";
$params = [];
if ($filterType !== 'vse') {
    $where .= " AND type = ?";
    $params[] = $filterType;
}

$cardsStmt = $pdo->prepare(
    "SELECT id, type, title, description, valid_from, valid_to, is_current, created_at
     FROM cms_food_cards
     {$where}
     ORDER BY is_current DESC, COALESCE(valid_from, created_at) DESC"
);
$cardsStmt->execute($params);
$cards = $cardsStmt->fetchAll();

$typeLabels = ['food' => 'Jídelní lístek', 'beverage' => 'Nápojový lístek'];
foreach ($cards as &$card) {
    $from = $card['valid_from'] ? formatCzechDate($card['valid_from']) : null;
    $to = $card['valid_to'] ? formatCzechDate($card['valid_to']) : null;
    if ($from && $to) {
        $card['validity_label'] = $from . ' – ' . $to;
    } elseif ($from) {
        $card['validity_label'] = 'Od ' . $from;
    } elseif ($to) {
        $card['validity_label'] = 'Do ' . $to;
    } else {
        $card['validity_label'] = 'Přidáno ' . formatCzechDate($card['created_at']);
    }
}
unset($card);

renderPublicPage([
    'title' => 'Archiv lístků – ' . $siteName,
    'meta' => [
        'title' => 'Archiv lístků – ' . $siteName,
        'url' => BASE_URL . '/food/archive.php',
    ],
    'view' => 'modules/food-archive',
    'view_data' => [
        'cards' => $cards,
        'filterType' => $filterType,
        'typeLabels' => $typeLabels,
    ],
    'current_nav' => 'food',
    'body_class' => 'page-food-archive',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/food.php',
]);
