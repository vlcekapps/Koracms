<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('faq')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

// Načteme všechny kategorie a sestavíme strom
$allCategories = $pdo->query(
    "SELECT id, name, parent_id, sort_order FROM cms_faq_categories ORDER BY sort_order, name"
)->fetchAll();

$catById = [];
$catTree = [];
foreach ($allCategories as $cat) {
    $catById[(int)$cat['id']] = $cat;
    $pid = $cat['parent_id'] !== null ? (int)$cat['parent_id'] : 0;
    $catTree[$pid][] = $cat;
}

// Sestavíme breadcrumbs pro danou kategorii
function faqCategoryBreadcrumbs(array $catById, int $categoryId): array
{
    $crumbs = [];
    $current = $categoryId;
    $safety = 0;
    while ($current > 0 && isset($catById[$current]) && $safety < 20) {
        $crumbs[] = $catById[$current];
        $current = (int)($catById[$current]['parent_id'] ?? 0);
        $safety++;
    }
    return array_reverse($crumbs);
}

// Filtr kategorie
$filterCatId = inputInt('get', 'kat');

$faqRows = $pdo->query(
    "SELECT f.id, f.question, f.slug, f.excerpt, f.answer, f.category_id, f.updated_at,
            COALESCE(f.status,'published') AS status, c.name AS category_name, c.sort_order AS cat_sort
     FROM cms_faqs f
     LEFT JOIN cms_faq_categories c ON c.id = f.category_id
     WHERE COALESCE(f.status,'published') = 'published' AND f.is_published = 1
     ORDER BY c.sort_order, c.name, f.created_at DESC, f.id DESC"
)->fetchAll();

$faqs = array_map(
    static fn(array $faq): array => hydrateFaqPresentation($faq),
    $faqRows
);

// Filtrujeme podle kategorie (včetně podkategorií)
$filteredFaqs = $faqs;
$filterCategory = null;
$breadcrumbs = [];

if ($filterCatId !== null && isset($catById[$filterCatId])) {
    $filterCategory = $catById[$filterCatId];
    $breadcrumbs = faqCategoryBreadcrumbs($catById, $filterCatId);

    // Rekurzivně získáme IDčka podkategorií
    $allowedCatIds = [$filterCatId];
    $queue = [$filterCatId];
    while ($queue !== []) {
        $parentId = array_shift($queue);
        foreach ($catTree[$parentId] ?? [] as $child) {
            $childId = (int)$child['id'];
            $allowedCatIds[] = $childId;
            $queue[] = $childId;
        }
    }

    $filteredFaqs = array_filter($faqs, static fn(array $faq): bool =>
        in_array((int)($faq['category_id'] ?? 0), $allowedCatIds, true)
    );
}

$grouped = [];
foreach ($filteredFaqs as $faq) {
    $categoryName = trim((string)($faq['category_name'] ?? ''));
    if ($categoryName === '') {
        $categoryName = 'Ostatní';
    }
    $grouped[$categoryName][] = $faq;
}

$pageTitle = 'Znalostní báze';
if ($filterCategory !== null) {
    $pageTitle = (string)$filterCategory['name'] . ' – Znalostní báze';
}

renderPublicPage([
    'title' => $pageTitle . ' – ' . $siteName,
    'meta' => [
        'title' => $pageTitle . ' – ' . $siteName,
        'url' => BASE_URL . '/faq/index.php',
    ],
    'view' => 'modules/faq-index',
    'view_data' => [
        'faqs' => array_values($filteredFaqs),
        'grouped' => $grouped,
        'multipleCategories' => count($grouped) > 1,
        'catTree' => $catTree,
        'catById' => $catById,
        'filterCatId' => $filterCatId,
        'filterCategory' => $filterCategory,
        'breadcrumbs' => $breadcrumbs,
    ],
    'current_nav' => 'faq',
    'body_class' => 'page-faq-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/faq.php',
]);
