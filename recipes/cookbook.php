<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('recipes')) {
    renderPublicNotFoundPage([
        'title' => 'Kuchařka není dostupná',
        'body_class' => 'page-recipe-cookbook-not-found',
    ]);
}

$pdo = db_connect();
$categorySlug = recipeCategorySlug(trim((string)($_GET['category_slug'] ?? '')));
$category = null;
$params = [];
$categorySql = '';
if ($categorySlug !== '') {
    $stmt = $pdo->prepare(
        'SELECT * FROM cms_recipe_categories WHERE slug = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$categorySlug]);
    $category = $stmt->fetch();
    if (!is_array($category)) {
        renderPublicNotFoundPage([
            'title' => 'Kategorie kuchařky nebyla nalezena',
            'meta' => ['url' => recipeCookbookPublicPath($categorySlug)],
            'current_nav' => 'recipes',
            'body_class' => 'page-recipe-cookbook-not-found',
        ]);
    }
    $categorySql = ' AND r.category_id = ?';
    $params[] = (int)$category['id'];
}

$stmt = $pdo->prepare(
    'SELECT r.*, c.name AS category_name, c.sort_order AS category_sort_order
     FROM cms_recipes r
     INNER JOIN cms_recipe_categories c ON c.id = r.category_id AND c.is_active = 1
     WHERE ' . recipePublicVisibilitySql('r') . $categorySql . '
     ORDER BY c.sort_order, c.name, r.title, r.id'
);
$stmt->execute($params);
$recipes = $stmt->fetchAll();
if ($recipes === []) {
    renderPublicNotFoundPage([
        'title' => 'Kuchařka zatím nemá recepty',
        'meta' => ['url' => recipeCookbookPublicPath($categorySlug)],
        'view_data' => [
            'title' => 'Kuchařka zatím nemá recepty',
            'message' => 'Pro zvolenou kuchařku nejsou dostupné žádné publikované recepty.',
        ],
        'current_nav' => 'recipes',
        'body_class' => 'page-recipe-cookbook-empty',
    ]);
}

foreach ($recipes as &$recipe) {
    $recipe['structure'] = recipeLoadStructure($pdo, (int)$recipe['id']);
}
unset($recipe);

$title = is_array($category)
    ? 'Kuchařka: ' . (string)$category['name']
    : 'Kuchařka receptů';
$epubPath = buildRecipeCookbookEpub($recipes, $title);
if ($epubPath === null) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo 'Kuchařku se nyní nepodařilo vytvořit. Zkuste stažení později.';
    exit;
}
register_shutdown_function(static function () use ($epubPath): void {
    if (is_file($epubPath)) {
        @unlink($epubPath);
    }
});
$filename = $categorySlug !== ''
    ? 'kucharka-' . $categorySlug . '.epub'
    : 'kucharka-receptu.epub';
sendStoredFileResponse($epubPath, $filename, 'attachment', 'application/epub+zip');
