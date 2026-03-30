<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('food')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$foodCardRow = $pdo->query(
    "SELECT * FROM cms_food_cards
     WHERE type = 'food' AND is_current = 1
       AND " . foodCardPublicVisibilitySql() . "
       AND " . foodCardCurrentWindowSql() . "
     ORDER BY COALESCE(valid_from, created_at) DESC, id DESC
     LIMIT 1"
)->fetch() ?: null;
$beverageCardRow = $pdo->query(
    "SELECT * FROM cms_food_cards
     WHERE type = 'beverage' AND is_current = 1
       AND " . foodCardPublicVisibilitySql() . "
       AND " . foodCardCurrentWindowSql() . "
     ORDER BY COALESCE(valid_from, created_at) DESC, id DESC
     LIMIT 1"
)->fetch() ?: null;

$foodCard = $foodCardRow ? hydrateFoodCardPresentation($foodCardRow) : null;
$beverageCard = $beverageCardRow ? hydrateFoodCardPresentation($beverageCardRow) : null;

renderPublicPage([
    'title' => 'Jídelní a nápojový lístek – ' . $siteName,
    'meta' => [
        'title' => 'Jídelní a nápojový lístek – ' . $siteName,
        'url' => BASE_URL . '/food/index.php',
    ],
    'view' => 'modules/food-index',
    'view_data' => [
        'foodCard' => $foodCard,
        'beverageCard' => $beverageCard,
    ],
    'current_nav' => 'food',
    'body_class' => 'page-food-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/food.php',
]);
