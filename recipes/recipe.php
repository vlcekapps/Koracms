<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('recipes')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$slug = recipeSlug(trim((string)($_GET['slug'] ?? '')));
$recipe = $slug !== '' ? recipeFindPublicBySlug(db_connect(), $slug) : null;
if ($recipe === null) {
    renderPublicNotFoundPage([
        'title' => 'Recept nebyl nalezen',
        'meta' => ['url' => BASE_URL . '/recipes/' . rawurlencode($slug)],
        'view_data' => [
            'title' => 'Recept nebyl nalezen',
            'message' => 'Požadovaný recept není veřejně dostupný.',
        ],
        'current_nav' => 'recipes',
        'body_class' => 'page-recipe-not-found',
    ]);
}

$pdo = db_connect();
$structure = recipeLoadStructure($pdo, (int)$recipe['id']);
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
$recipe['allergen_labels'] = array_intersect_key(
    recipeAllergenDefinitions(),
    array_flip(normalizeRecipeSelection($recipe['allergens'] ?? '', recipeAllergenDefinitions()))
);
$difficultyLabel = recipeDifficultyDefinitions()[(string)($recipe['difficulty'] ?? '')] ?? '';

trackPageView('recipe', (int)$recipe['id']);

$siteName = getSetting('site_name', 'Kora CMS');
$metaTitle = trim((string)$recipe['meta_title']) !== ''
    ? (string)$recipe['meta_title']
    : (string)$recipe['title'];
$metaDescription = trim((string)$recipe['meta_description']) !== ''
    ? (string)$recipe['meta_description']
    : normalizePlainText((string)$recipe['summary']);

renderPublicPage([
    'title' => $metaTitle . ' - ' . $siteName,
    'meta' => [
        'title' => $metaTitle . ' - ' . $siteName,
        'description' => $metaDescription,
        'url' => recipePublicPath($recipe),
        'type' => 'article',
        'image' => (string)$recipe['image_url'],
    ],
    'view' => 'modules/recipes-recipe',
    'view_data' => [
        'recipe' => $recipe,
        'structure' => $structure,
        'difficultyLabel' => $difficultyLabel,
    ],
    'current_nav' => 'recipes',
    'body_class' => 'page-recipe-detail',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/recipe_form.php?id=' . (int)$recipe['id'],
    'extra_head_html' => structuredDataScript(recipeStructuredData($recipe, $structure)),
]);
