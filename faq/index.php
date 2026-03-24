<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('faq')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$faqRows = $pdo->query(
    "SELECT f.id, f.question, f.slug, f.excerpt, f.answer, f.category_id, f.sort_order, f.updated_at,
            COALESCE(f.status,'published') AS status, c.name AS category_name, c.sort_order AS cat_sort
     FROM cms_faqs f
     LEFT JOIN cms_faq_categories c ON c.id = f.category_id
     WHERE COALESCE(f.status,'published') = 'published' AND f.is_published = 1
     ORDER BY c.sort_order, c.name, f.sort_order, f.id"
)->fetchAll();

$faqs = array_map(
    static fn(array $faq): array => hydrateFaqPresentation($faq),
    $faqRows
);

$grouped = [];
foreach ($faqs as $faq) {
    $categoryName = trim((string)($faq['category_name'] ?? ''));
    if ($categoryName === '') {
        $categoryName = 'Ostatní';
    }
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
