<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('faq')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$q = trim((string)($_GET['q'] ?? ''));
$filterCatId = inputInt('get', 'kat');
$categorySlug = faqCategorySlug(trim((string)($_GET['category_slug'] ?? '')));
$viewMode = trim((string)($_GET['zobrazeni'] ?? 'cards'));
if (!in_array($viewMode, ['cards', 'inline'], true)) {
    $viewMode = 'cards';
}

$allCategories = $pdo->query(
    "SELECT id, name, slug, description, meta_title, meta_description, parent_id, sort_order
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

if ($categorySlug !== '') {
    $filterCatId = null;
    foreach ($allCategories as $categoryRow) {
        if (faqCategorySlug((string)($categoryRow['slug'] ?? '')) === $categorySlug) {
            $filterCatId = (int)$categoryRow['id'];
            break;
        }
    }

    if ($filterCatId === null) {
        renderPublicNotFoundPage([
            'title' => 'Kategorie nenalezena',
            'meta' => [
                'url' => BASE_URL . '/faq/kategorie/' . rawurlencode($categorySlug),
            ],
            'body_class' => 'page-faq-category-not-found',
        ]);
    }
}

if ($filterCatId !== null && !isset($catById[$filterCatId])) {
    $filterCatId = null;
}

$faqRows = [];
$faqSelect = "SELECT f.id, f.question, f.slug, f.excerpt, f.answer, f.category_id,
                     f.meta_title, f.meta_description, f.updated_at,
                     COALESCE(f.status,'published') AS status,
                     COALESCE(c.name, '') AS category_name,
                     COALESCE(c.slug, '') AS category_slug,
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
    static fn (array $faq): array => hydrateFaqPresentation($faq),
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
        static fn (array $faq): bool => in_array((int)($faq['category_id'] ?? 0), $allowedCatIds, true)
    ));
}

$buildFaqIndexUrl = static function (array $overrides = []) use ($q, $filterCatId, $viewMode, $catById): string {
    $params = [
        'q' => $q !== '' ? $q : null,
        'kat' => $filterCatId !== null ? (string)$filterCatId : null,
        'zobrazeni' => $viewMode !== 'cards' ? $viewMode : null,
        'strana' => null,
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    $targetCategoryId = isset($params['kat']) && $params['kat'] !== ''
        ? (int)$params['kat']
        : null;
    if ($targetCategoryId !== null && isset($catById[$targetCategoryId])) {
        $categoryQuery = [
            'q' => $params['q'],
            'zobrazeni' => $params['zobrazeni'],
            'strana' => $params['strana'],
        ];

        return faqCategoryPath($catById[$targetCategoryId], $categoryQuery);
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
$pageHeading = 'Znalostní báze';
$pageIntro = '';
if ($filterCategory !== null) {
    $categoryMetaTitle = trim((string)($filterCategory['meta_title'] ?? ''));
    $pageTitle = $categoryMetaTitle !== '' ? $categoryMetaTitle : (string)$filterCategory['name'] . ' – Znalostní báze';
    $pageHeading = (string)$filterCategory['name'];
    $pageIntro = trim((string)($filterCategory['description'] ?? ''));
}
if ($q !== '') {
    $pageTitle = 'Hledání „' . $q . '“ – ' . $pageTitle;
}

$metaDescription = 'Přehled častých dotazů a odpovědí.';
if ($filterCategory !== null) {
    $categoryMetaDescription = trim((string)($filterCategory['meta_description'] ?? ''));
    $metaDescription = $categoryMetaDescription !== ''
        ? $categoryMetaDescription
        : ($pageIntro !== '' ? normalizePlainText($pageIntro) : 'Otázky a odpovědi z kategorie ' . (string)$filterCategory['name'] . '.');
}
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
        'pageHeading' => $pageHeading,
        'pageIntro' => $pageIntro,
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
