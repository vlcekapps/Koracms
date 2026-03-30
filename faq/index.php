<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('faq')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

/**
 * Sestaví breadcrumbs pro FAQ kategorii.
 *
 * @return array<int, array<string, mixed>>
 */
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

/**
 * Vrátí seznam kategorií i s potomky pro filtrování.
 *
 * @return array<int>
 */
function faqCategoryDescendantIds(array $catTree, int $categoryId): array
{
    $allowedCatIds = [$categoryId];
    $queue = [$categoryId];

    while ($queue !== []) {
        $parentId = array_shift($queue);
        foreach ($catTree[$parentId] ?? [] as $child) {
            $childId = (int)$child['id'];
            $allowedCatIds[] = $childId;
            $queue[] = $childId;
        }
    }

    return array_values(array_unique($allowedCatIds));
}

/**
 * Vygeneruje label s počtem otázek.
 */
function faqCountLabel(int $count): string
{
    $count = max(0, $count);
    if ($count === 1) {
        return '1 otázka';
    }

    $mod100 = $count % 100;
    $mod10 = $count % 10;
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
        return $count . ' otázky';
    }

    return $count . ' otázek';
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$q = trim((string)($_GET['q'] ?? ''));
$filterCatId = inputInt('get', 'kat');
$viewMode = trim((string)($_GET['zobrazeni'] ?? 'cards'));
if (!in_array($viewMode, ['cards', 'inline'], true)) {
    $viewMode = 'cards';
}

$allCategories = $pdo->query(
    "SELECT id, name, parent_id, sort_order
     FROM cms_faq_categories
     ORDER BY sort_order, name"
)->fetchAll();

$catById = [];
$catTree = [];
foreach ($allCategories as $cat) {
    $catById[(int)$cat['id']] = $cat;
    $parentId = $cat['parent_id'] !== null ? (int)$cat['parent_id'] : 0;
    $catTree[$parentId][] = $cat;
}

if ($filterCatId !== null && !isset($catById[$filterCatId])) {
    $filterCatId = null;
}

$faqRows = [];
$faqSelect = "SELECT f.id, f.question, f.slug, f.excerpt, f.answer, f.category_id,
                     f.meta_title, f.meta_description, f.updated_at,
                     COALESCE(f.status,'published') AS status,
                     COALESCE(c.name, '') AS category_name,
                     c.sort_order AS cat_sort
              FROM cms_faqs f
              LEFT JOIN cms_faq_categories c ON c.id = f.category_id";

if ($q !== '') {
    rateLimit('faq_search', 30, 60);

    if (mb_strlen($q) >= 3) {
        try {
            $faqStmt = $pdo->prepare(
                $faqSelect
                . " WHERE " . faqPublicVisibilitySql('f')
                . " AND MATCH(f.question, f.excerpt, f.answer) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY MATCH(f.question, f.excerpt, f.answer) AGAINST(? IN NATURAL LANGUAGE MODE) DESC,
                             c.sort_order, c.name, COALESCE(f.updated_at, f.created_at) DESC, f.id DESC"
            );
            $faqStmt->execute([$q, $q]);
            $faqRows = $faqStmt->fetchAll();
        } catch (\PDOException $e) {
            $faqRows = [];
        }
    }

    if ($faqRows === []) {
        $like = '%' . $q . '%';
        $faqStmt = $pdo->prepare(
            $faqSelect
            . " WHERE " . faqPublicVisibilitySql('f')
            . " AND (f.question LIKE ? OR f.excerpt LIKE ? OR f.answer LIKE ? OR c.name LIKE ?)
                ORDER BY c.sort_order, c.name, COALESCE(f.updated_at, f.created_at) DESC, f.id DESC"
        );
        $faqStmt->execute([$like, $like, $like, $like]);
        $faqRows = $faqStmt->fetchAll();
    }
} else {
    $faqRows = $pdo->query(
        $faqSelect
        . " WHERE " . faqPublicVisibilitySql('f')
        . " ORDER BY c.sort_order, c.name, COALESCE(f.updated_at, f.created_at) DESC, f.id DESC"
    )->fetchAll();
}

$faqs = array_map(
    static fn(array $faq): array => hydrateFaqPresentation($faq),
    $faqRows
);

$filterCategory = null;
$breadcrumbs = [];
$filteredFaqs = $faqs;

if ($filterCatId !== null && isset($catById[$filterCatId])) {
    $filterCategory = $catById[$filterCatId];
    $breadcrumbs = faqCategoryBreadcrumbs($catById, $filterCatId);
    $allowedCatIds = faqCategoryDescendantIds($catTree, $filterCatId);
    $filteredFaqs = array_values(array_filter(
        $faqs,
        static fn(array $faq): bool => in_array((int)($faq['category_id'] ?? 0), $allowedCatIds, true)
    ));
}

$buildFaqIndexUrl = static function (array $overrides = []) use ($q, $filterCatId, $viewMode): string {
    $params = [
        'q' => $q !== '' ? $q : null,
        'kat' => $filterCatId !== null ? (string)$filterCatId : null,
        'zobrazeni' => $viewMode !== 'cards' ? $viewMode : null,
        'strana' => null,
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    return BASE_URL . appendUrlQuery('/faq/index.php', $params);
};

$pagination = paginateArray(count($filteredFaqs), 12, (int)($_GET['strana'] ?? 1));
['total' => $totalItems, 'totalPages' => $pages, 'page' => $page, 'offset' => $offset, 'perPage' => $perPage] = $pagination;
$pageFaqs = array_slice($filteredFaqs, $offset, $perPage);

$grouped = [];
foreach ($pageFaqs as $faq) {
    $categoryName = trim((string)($faq['category_name'] ?? ''));
    if ($categoryName === '') {
        $categoryName = 'Ostatní';
    }
    $grouped[$categoryName][] = $faq;
}

$hasActiveFilters = $q !== '' || $filterCategory !== null || $viewMode !== 'cards';
$filterSummary = [];
if ($q !== '') {
    $filterSummary[] = 'Hledání: ' . $q;
}
if ($filterCategory !== null) {
    $filterSummary[] = 'Kategorie: ' . (string)$filterCategory['name'];
}
if ($viewMode === 'inline') {
    $filterSummary[] = 'Zobrazení: rozbalené odpovědi';
}

$resultCountLabel = faqCountLabel($totalItems);
if ($totalItems > 0 && $pages > 1) {
    $resultCountLabel .= ' · strana ' . $page . ' z ' . $pages;
}

$pageTitle = 'Znalostní báze';
if ($filterCategory !== null) {
    $pageTitle = (string)$filterCategory['name'] . ' – Znalostní báze';
}
if ($q !== '') {
    $pageTitle = 'Hledání „' . $q . '“ – ' . $pageTitle;
}

$metaDescription = $filterCategory !== null
    ? 'Otázky a odpovědi z kategorie ' . (string)$filterCategory['name'] . '.'
    : 'Přehled častých dotazů a odpovědí.';
if ($q !== '') {
    $metaDescription = 'Výsledky hledání „' . $q . '“ ve znalostní bázi.';
}

$displayModeLinks = [
    [
        'label' => 'Přehled karet',
        'url' => $buildFaqIndexUrl(['zobrazeni' => null, 'strana' => null]),
        'active' => $viewMode === 'cards',
    ],
    [
        'label' => 'Rozbalené odpovědi',
        'url' => $buildFaqIndexUrl(['zobrazeni' => 'inline', 'strana' => null]),
        'active' => $viewMode === 'inline',
    ],
];

$detailQuery = [
    'q' => $q !== '' ? $q : null,
    'kat' => $filterCategory !== null ? (string)$filterCatId : null,
    'zobrazeni' => $viewMode !== 'cards' ? $viewMode : null,
    'strana' => $page > 1 ? (string)$page : null,
];
$pagerBase = $buildFaqIndexUrl(['strana' => null]);
$pagerBase .= str_contains($pagerBase, '?') ? '&' : '?';
$canonicalUrl = siteUrl(str_replace(BASE_URL, '', $buildFaqIndexUrl(['strana' => $page > 1 ? (string)$page : null])));
$extraHeadHtml = faqStructuredData($pageFaqs, $canonicalUrl);

renderPublicPage([
    'title' => $pageTitle . ' – ' . $siteName,
    'meta' => [
        'title' => $pageTitle . ' – ' . $siteName,
        'description' => $metaDescription,
        'url' => $canonicalUrl,
        'type' => 'website',
    ],
    'view' => 'modules/faq-index',
    'view_data' => [
        'faqs' => $pageFaqs,
        'grouped' => $grouped,
        'multipleCategories' => count($grouped) > 1,
        'catTree' => $catTree,
        'catById' => $catById,
        'filterCatId' => $filterCatId,
        'filterCategory' => $filterCategory,
        'breadcrumbs' => $breadcrumbs,
        'searchQuery' => $q,
        'displayMode' => $viewMode,
        'displayModeLinks' => $displayModeLinks,
        'resultCountLabel' => $resultCountLabel,
        'filterSummary' => $filterSummary,
        'hasActiveFilters' => $hasActiveFilters,
        'clearUrl' => BASE_URL . '/faq/index.php',
        'pagerHtml' => renderPager($page, $pages, $pagerBase, 'Stránkování znalostní báze', 'Předchozí stránka', 'Další stránka'),
        'categories' => $allCategories,
        'categoryRootUrl' => $buildFaqIndexUrl(['kat' => null, 'strana' => null]),
        'detailQuery' => $detailQuery,
        'buildFaqIndexUrl' => $buildFaqIndexUrl,
    ],
    'current_nav' => 'faq',
    'body_class' => 'page-faq-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/faq.php',
    'extra_head_html' => $extraHeadHtml,
]);
