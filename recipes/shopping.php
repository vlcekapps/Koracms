<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('recipes')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

sendNoStoreNoIndexHeaders();
$pdo = db_connect();
$selection = normalizeRecipeShoppingSelection($_SESSION['recipe_shopping_list'] ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'add') {
        $recipeId = inputInt('post', 'recipe_id');
        $recipe = $recipeId !== null ? recipeFindPublicById($pdo, $recipeId) : null;
        if ($recipe !== null) {
            $selection[(int)$recipe['id']] = recipeRequestedServings(
                $_POST['servings'] ?? null,
                recipeNullablePositiveInt($recipe['servings'] ?? null)
            );
            $_SESSION['recipe_shopping_list'] = normalizeRecipeShoppingSelection($selection);
            header('Location: ' . appendUrlQuery(recipeShoppingPublicPath(), ['msg' => 'added']));
            exit;
        }
    } elseif ($action === 'update') {
        $recipeId = inputInt('post', 'recipe_id');
        if ($recipeId !== null && isset($selection[$recipeId])) {
            $recipe = recipeFindPublicById($pdo, $recipeId);
            if ($recipe !== null) {
                $selection[$recipeId] = recipeRequestedServings(
                    $_POST['servings'] ?? null,
                    recipeNullablePositiveInt($recipe['servings'] ?? null)
                );
            }
        }
        $_SESSION['recipe_shopping_list'] = normalizeRecipeShoppingSelection($selection);
        header('Location: ' . appendUrlQuery(recipeShoppingPublicPath(), ['msg' => 'updated']));
        exit;
    } elseif ($action === 'remove') {
        $recipeId = inputInt('post', 'recipe_id');
        if ($recipeId !== null) {
            unset($selection[$recipeId]);
        }
        $_SESSION['recipe_shopping_list'] = $selection;
        header('Location: ' . appendUrlQuery(recipeShoppingPublicPath(), ['msg' => 'removed']));
        exit;
    } elseif ($action === 'clear' && (string)($_POST['confirm_action'] ?? '') === '1') {
        unset($_SESSION['recipe_shopping_list']);
        header('Location: ' . appendUrlQuery(recipeShoppingPublicPath(), ['msg' => 'cleared']));
        exit;
    }

    header('Location: ' . recipeShoppingPublicPath());
    exit;
}

$entries = [];
$validSelection = [];
foreach ($selection as $recipeId => $servings) {
    $recipe = recipeFindPublicById($pdo, $recipeId);
    if ($recipe === null) {
        continue;
    }
    $entries[] = [
        'recipe' => $recipe,
        'structure' => recipeLoadStructure($pdo, $recipeId),
        'servings' => $servings,
    ];
    $validSelection[$recipeId] = $servings;
}
$_SESSION['recipe_shopping_list'] = $validSelection;

$message = match (trim((string)($_GET['msg'] ?? ''))) {
    'added' => 'Recept byl přidán do nákupního seznamu.',
    'updated' => 'Počet porcí v nákupním seznamu byl upraven.',
    'removed' => 'Recept byl z nákupního seznamu odebrán.',
    'cleared' => 'Nákupní seznam byl vyprázdněn.',
    default => '',
};
$siteName = getSetting('site_name', 'Kora CMS');

renderPublicPage([
    'title' => 'Nákupní seznam - ' . $siteName,
    'meta' => [
        'title' => 'Nákupní seznam - ' . $siteName,
        'description' => 'Osobní nákupní seznam sestavený z veřejných receptů.',
        'url' => siteUrl('/recipes/nakupni-seznam'),
        'robots' => 'noindex,nofollow',
    ],
    'view' => 'modules/recipes-shopping',
    'view_data' => [
        'entries' => $entries,
        'message' => $message,
    ],
    'current_nav' => 'recipes',
    'body_class' => 'page-recipes-shopping',
    'page_kind' => 'detail',
]);
