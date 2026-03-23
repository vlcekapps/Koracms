<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('food')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function foodCardMetaLabel(?array $card): string
{
    if (!$card) {
        return '';
    }

    $parts = [];
    $from = $card['valid_from'] ? formatCzechDate($card['valid_from']) : null;
    $to = $card['valid_to'] ? formatCzechDate($card['valid_to']) : null;
    if ($from || $to) {
        if ($from && $to) {
            $parts[] = 'Platnost: ' . $from . ' – ' . $to;
        } elseif ($from) {
            $parts[] = 'Platnost od ' . $from;
        } else {
            $parts[] = 'Platnost do ' . $to;
        }
    }
    if (!empty($card['description'])) {
        $parts[] = $card['description'];
    }

    return implode(' | ', $parts);
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$foodCard = $pdo->query(
    "SELECT * FROM cms_food_cards
     WHERE type = 'food' AND is_current = 1 AND status = 'published' AND is_published = 1
     LIMIT 1"
)->fetch() ?: null;

$beverageCard = $pdo->query(
    "SELECT * FROM cms_food_cards
     WHERE type = 'beverage' AND is_current = 1 AND status = 'published' AND is_published = 1
     LIMIT 1"
)->fetch() ?: null;

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
        'foodMeta' => foodCardMetaLabel($foodCard),
        'beverageMeta' => foodCardMetaLabel($beverageCard),
    ],
    'current_nav' => 'food',
    'body_class' => 'page-food-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/food.php',
]);
