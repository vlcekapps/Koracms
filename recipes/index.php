<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('recipes')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$query = trim((string)($_GET['q'] ?? ''));
$categorySlug = recipeCategorySlug(trim((string)($_GET['category_slug'] ?? '')));
$categoryId = inputInt('get', 'category');
$difficulty = trim((string)($_GET['obtiznost'] ?? ''));
$dietaryFlags = normalizeRecipeSelection($_GET['dieta'] ?? [], recipeDietaryFlagDefinitions());
if ($difficulty !== '' && !isset(recipeDifficultyDefinitions()[$difficulty])) {
    $difficulty = '';
}
if ($query !== '') {
    rateLimit('recipes_search', 30, 60);
}

$categories = $pdo->query(
    'SELECT c.*,
            (SELECT COUNT(*) FROM cms_recipes r
             WHERE r.category_id = c.id AND ' . recipePublicVisibilitySql('r') . ') AS public_count
     FROM cms_recipe_categories c
     WHERE c.is_active = 1
     ORDER BY c.sort_order, c.name'
)->fetchAll();
$activeCategory = null;
if ($categorySlug !== '') {
    foreach ($categories as $category) {
        if ((string)$category['slug'] === $categorySlug) {
            $activeCategory = $category;
            $categoryId = (int)$category['id'];
            break;
        }
    }
    if ($activeCategory === null) {
        renderPublicNotFoundPage([
            'title' => 'Kategorie receptů nenalezena',
            'meta' => ['url' => BASE_URL . '/recipes/kategorie/' . rawurlencode($categorySlug)],
            'current_nav' => 'recipes',
            'body_class' => 'page-recipes-category-not-found',
        ]);
    }
} elseif ($categoryId !== null) {
    foreach ($categories as $category) {
        if ((int)$category['id'] === $categoryId) {
            $activeCategory = $category;
            break;
        }
    }
    if ($activeCategory === null) {
        $categoryId = null;
    }
}

$where = [recipePublicVisibilitySql('r')];
$params = [];
if ($categoryId !== null) {
    $where[] = 'r.category_id = ?';
    $params[] = $categoryId;
}
if ($difficulty !== '') {
    $where[] = 'r.difficulty = ?';
    $params[] = $difficulty;
}
foreach ($dietaryFlags as $dietaryFlag) {
    $where[] = 'FIND_IN_SET(?, r.dietary_flags) > 0';
    $params[] = $dietaryFlag;
}
if ($query !== '') {
    $where[] = '(r.title LIKE ? OR r.summary LIKE ? OR r.notes LIKE ? OR c.name LIKE ?
        OR EXISTS (
            SELECT 1 FROM cms_recipe_ingredients ri
            WHERE ri.recipe_id = r.id
              AND (ri.name LIKE ? OR ri.note LIKE ? OR ri.unit LIKE ?)
        )
        OR EXISTS (
            SELECT 1 FROM cms_recipe_ingredient_groups rig
            WHERE rig.recipe_id = r.id AND rig.title LIKE ?
        ))';
    for ($i = 0; $i < 8; $i++) {
        $params[] = '%' . $query . '%';
    }
}
$whereSql = implode(' AND ', $where);
$pagination = paginate(
    $pdo,
    'SELECT COUNT(*) FROM cms_recipes r
     INNER JOIN cms_recipe_categories c ON c.id = r.category_id AND c.is_active = 1
     WHERE ' . $whereSql,
    $params,
    12
);
['total' => $total, 'totalPages' => $pages, 'page' => $page, 'offset' => $offset, 'perPage' => $perPage] = $pagination;

$stmt = $pdo->prepare(
    'SELECT r.*, c.name AS category_name, c.slug AS category_slug,
            m.filename AS media_filename, m.folder AS media_folder,
            m.original_name AS media_original_name, m.alt_text AS media_alt_text,
            m.mime_type AS media_mime_type, m.visibility AS media_visibility
     FROM cms_recipes r
     INNER JOIN cms_recipe_categories c ON c.id = r.category_id AND c.is_active = 1
     LEFT JOIN cms_media m ON m.id = r.media_id
     WHERE ' . $whereSql . '
     ORDER BY COALESCE(r.publish_at, r.created_at) DESC, r.id DESC
     LIMIT ? OFFSET ?'
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$recipes = $stmt->fetchAll();
foreach ($recipes as &$recipe) {
    $media = [
        'filename' => $recipe['media_filename'] ?? '',
        'folder' => $recipe['media_folder'] ?? 'media',
        'original_name' => $recipe['media_original_name'] ?? '',
        'alt_text' => $recipe['media_alt_text'] ?? '',
        'mime_type' => $recipe['media_mime_type'] ?? '',
        'visibility' => $recipe['media_visibility'] ?? '',
    ];
    $recipe['image_url'] = mediaIsPublic($media) && mediaCanPreviewImage($media)
        ? mediaFileUrl($media)
        : '';
    $recipe['image_alt'] = recipeImageAlt($recipe, $media);
    $recipe['dietary_labels'] = array_intersect_key(
        recipeDietaryFlagDefinitions(),
        array_flip(normalizeRecipeSelection($recipe['dietary_flags'] ?? '', recipeDietaryFlagDefinitions()))
    );
}
unset($recipe);

$buildUrl = static function (array $overrides = []) use (
    $query,
    $activeCategory,
    $categoryId,
    $difficulty,
    $dietaryFlags
): string {
    $params = [
        'q' => $query !== '' ? $query : null,
        'category' => $activeCategory === null ? $categoryId : null,
        'obtiznost' => $difficulty !== '' ? $difficulty : null,
        'dieta' => $dietaryFlags !== [] ? $dietaryFlags : null,
        'strana' => null,
    ];
    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }
    $params = array_filter($params, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    $base = $activeCategory !== null
        ? recipeCategoryPublicPath($activeCategory)
        : BASE_URL . '/recipes/index.php';
    return appendUrlQuery($base, $params);
};

$filterSummary = [];
if ($query !== '') {
    $filterSummary[] = 'hledání „' . $query . '“';
}
if ($activeCategory !== null) {
    $filterSummary[] = 'kategorie ' . (string)$activeCategory['name'];
}
if ($difficulty !== '') {
    $filterSummary[] = 'náročnost ' . mb_strtolower(recipeDifficultyDefinitions()[$difficulty]);
}
foreach ($dietaryFlags as $flag) {
    $filterSummary[] = mb_strtolower(recipeDietaryFlagDefinitions()[$flag]);
}

$heading = $activeCategory !== null ? (string)$activeCategory['name'] : 'Recepty';
$intro = $activeCategory !== null
    ? trim((string)($activeCategory['description'] ?? ''))
    : 'Přehled receptů s přesnými ingrediencemi, postupem a ověřenými údaji.';
$title = $activeCategory !== null && trim((string)$activeCategory['meta_title']) !== ''
    ? (string)$activeCategory['meta_title']
    : $heading;
$description = $activeCategory !== null && trim((string)$activeCategory['meta_description']) !== ''
    ? (string)$activeCategory['meta_description']
    : ($intro !== '' ? normalizePlainText($intro) : 'Veřejný katalog receptů.');
$pagerBase = $buildUrl(['strana' => null]);
$pagerBase .= str_contains($pagerBase, '?') ? '&' : '?';
$canonical = $buildUrl(['strana' => $page > 1 ? $page : null]);

renderPublicPage([
    'title' => $title . ' - ' . $siteName,
    'meta' => [
        'title' => $title . ' - ' . $siteName,
        'description' => $description,
        'url' => siteUrl(str_replace(BASE_URL, '', $canonical)),
        'type' => 'website',
    ],
    'view' => 'modules/recipes-index',
    'view_data' => [
        'recipes' => $recipes,
        'categories' => $categories,
        'activeCategory' => $activeCategory,
        'query' => $query,
        'difficulty' => $difficulty,
        'dietaryFlags' => $dietaryFlags,
        'heading' => $heading,
        'intro' => $intro,
        'filterSummary' => $filterSummary,
        'total' => $total,
        'pagerHtml' => renderPager($page, $pages, $pagerBase, 'Stránkování receptů'),
        'clearUrl' => BASE_URL . '/recipes/index.php',
        'cookbookUrl' => recipeCookbookPublicPath(
            $activeCategory !== null ? (string)$activeCategory['slug'] : ''
        ),
    ],
    'current_nav' => 'recipes',
    'body_class' => 'page-recipes-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/recipes.php',
]);
