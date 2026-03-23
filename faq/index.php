<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('faq')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$faqs = $pdo->query(
    "SELECT f.id, f.question, f.answer, f.category_id, c.name AS category_name, c.sort_order AS cat_sort
     FROM cms_faqs f
     LEFT JOIN cms_faq_categories c ON c.id = f.category_id
     WHERE f.is_published = 1
     ORDER BY c.sort_order, c.name, f.sort_order, f.id"
)->fetchAll();

$grouped = [];
foreach ($faqs as $faq) {
    $categoryName = $faq['category_name'] ?: 'Ostatní';
    $grouped[$categoryName][] = $faq;
}

renderPublicPage([
    'title' => 'FAQ – ' . $siteName,
    'meta' => [
        'title' => 'FAQ – ' . $siteName,
        'url' => BASE_URL . '/faq/index.php',
    ],
    'view' => 'modules/faq-index',
    'view_data' => [
        'faqs' => $faqs,
        'grouped' => $grouped,
        'multipleCategories' => count($grouped) > 1,
    ],
    'current_nav' => 'faq',
    'body_class' => 'page-faq-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/faq.php',
]);
