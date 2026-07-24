<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu receptů nemáte potřebné oprávnění.');
requireModuleEnabled('recipes');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$form = [
    'category_id' => inputInt('post', 'category_id'),
    'title' => trim((string)($_POST['title'] ?? '')),
    'slug' => trim((string)($_POST['slug'] ?? '')),
    'summary' => trim((string)($_POST['summary'] ?? '')),
    'notes' => trim((string)($_POST['notes'] ?? '')),
    'servings' => trim((string)($_POST['servings'] ?? '')),
    'prep_minutes' => trim((string)($_POST['prep_minutes'] ?? '')),
    'cook_minutes' => trim((string)($_POST['cook_minutes'] ?? '')),
    'difficulty' => trim((string)($_POST['difficulty'] ?? '')),
    'dietary_flags' => (array)($_POST['dietary_flags'] ?? []),
    'allergens' => (array)($_POST['allergens'] ?? []),
    'calories_kcal' => trim((string)($_POST['calories_kcal'] ?? '')),
    'media_id' => inputInt('post', 'media_id'),
    'image_alt_text' => trim((string)($_POST['image_alt_text'] ?? '')),
    'source_name' => trim((string)($_POST['source_name'] ?? '')),
    'source_url' => trim((string)($_POST['source_url'] ?? '')),
    'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
    'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
    'status' => trim((string)($_POST['status'] ?? 'draft')),
    'publish_at' => trim((string)($_POST['publish_at'] ?? '')),
];
$errors = [];
$fieldErrors = [];
$addError = static function (string $field, string $message) use (&$errors, &$fieldErrors): void {
    $errors[] = ['field' => $field, 'message' => $message];
    $fieldErrors[] = $field;
};

$existing = null;
if ($id !== null) {
    $stmt = $pdo->prepare('SELECT * FROM cms_recipes WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!is_array($existing)) {
        header('Location: ' . BASE_URL . '/admin/recipes.php');
        exit;
    }
}

if ($form['title'] === '') {
    $addError('title', 'Doplňte název receptu.');
}
if ($form['summary'] === '') {
    $addError('summary', 'Doplňte krátké shrnutí receptu pro katalog a čtenáře.');
}

$category = null;
if ($form['category_id'] !== null) {
    $categoryStmt = $pdo->prepare('SELECT * FROM cms_recipe_categories WHERE id = ?');
    $categoryStmt->execute([$form['category_id']]);
    $category = $categoryStmt->fetch();
}
if (!is_array($category)) {
    $addError('category_id', 'Vyberte existující kategorii receptu.');
}

$slugCandidate = recipeSlug($form['slug'] !== '' ? $form['slug'] : $form['title']);
if ($slugCandidate === '') {
    $addError('slug', 'Slug nelze vytvořit. Použijte v názvu nebo slugu alespoň písmeno či číslici.');
} else {
    $uniqueSlug = uniqueRecipeSlug($pdo, $slugCandidate, $id);
    if ($form['slug'] !== '' && $uniqueSlug !== $slugCandidate) {
        $addError('slug', 'Tento slug už používá jiný recept.');
    }
    $form['slug'] = $uniqueSlug;
}

$numericFields = [
    'servings' => ['Počet porcí musí být celé kladné číslo.', 10000],
    'prep_minutes' => ['Doba přípravy musí být celé kladné číslo v minutách.', 100000],
    'cook_minutes' => ['Doba tepelné úpravy musí být celé kladné číslo v minutách.', 100000],
    'calories_kcal' => ['Energie musí být celé kladné číslo v kcal na porci.', 100000],
];
$normalizedNumbers = [];
foreach ($numericFields as $field => [$message, $maximum]) {
    $raw = (string)$form[$field];
    if ($raw === '') {
        $normalizedNumbers[$field] = null;
        continue;
    }
    $number = recipeNullablePositiveInt($raw);
    if ($number === null || $number > $maximum) {
        $addError($field, $message);
        $normalizedNumbers[$field] = null;
    } else {
        $normalizedNumbers[$field] = $number;
    }
}

if ($form['difficulty'] !== '' && !isset(recipeDifficultyDefinitions()[$form['difficulty']])) {
    $form['difficulty'] = '';
}
$dietaryFlags = normalizeRecipeSelection($form['dietary_flags'], recipeDietaryFlagDefinitions());
$allergens = normalizeRecipeSelection($form['allergens'], recipeAllergenDefinitions());

$mediaId = $form['media_id'];
if ($mediaId !== null) {
    $media = mediaGetById($mediaId);
    if (!is_array($media) || !mediaIsPublic($media) || !mediaCanPreviewImage($media)) {
        $addError('media_id', 'Vyberte existující veřejný rastrový obrázek z knihovny médií.');
    }
}

$sourceUrl = '';
if ($form['source_url'] !== '') {
    $sourceUrl = normalizeHttpExternalUrl($form['source_url'], false);
    if ($sourceUrl === '') {
        $addError('source_url', 'Odkaz na zdroj musí být úplná adresa začínající http:// nebo https://.');
    }
}

$publishAt = null;
if ($form['publish_at'] !== '') {
    $dateTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $form['publish_at']);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $hasDateErrors = is_array($dateErrors)
        && ((int)$dateErrors['warning_count'] > 0 || (int)$dateErrors['error_count'] > 0);
    if ($dateTime === false || $hasDateErrors || $dateTime->format('Y-m-d\TH:i') !== $form['publish_at']) {
        $addError('publish_at', 'Vyberte platné datum a čas zveřejnění, nebo pole nechte prázdné.');
    } else {
        $publishAt = $dateTime->format('Y-m-d H:i:s');
    }
}

$requestedStatus = in_array($form['status'], ['draft', 'pending', 'published'], true)
    ? $form['status']
    : 'draft';
if ($id === null) {
    $requestedStatus = 'draft';
} elseif ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
    $requestedStatus = 'pending';
}
if ($requestedStatus === 'published') {
    if (!is_array($category) || (int)$category['is_active'] !== 1) {
        $addError('status', 'Publikovaný recept musí být zařazený v aktivní kategorii.');
    }
    if ($id === null || !recipeHasPublishableStructure($pdo, $id)) {
        $addError('status', 'Před publikováním doplňte alespoň jednu ingredienci a jeden krok postupu.');
    }
}

if ($errors !== []) {
    $_SESSION['recipe_form_flash'] = [
        'form' => $form,
        'errors' => $errors,
        'field_errors' => array_values(array_unique($fieldErrors)),
    ];
    $target = BASE_URL . '/admin/recipe_form.php' . ($id !== null ? '?id=' . $id : '');
    header('Location: ' . $target);
    exit;
}

$values = [
    (int)$form['category_id'],
    $form['title'],
    $form['slug'],
    $form['summary'],
    $form['notes'],
    $normalizedNumbers['servings'],
    $normalizedNumbers['prep_minutes'],
    $normalizedNumbers['cook_minutes'],
    $form['difficulty'] !== '' ? $form['difficulty'] : null,
    implode(',', $dietaryFlags),
    implode(',', $allergens),
    $normalizedNumbers['calories_kcal'],
    $mediaId,
    mb_substr($form['image_alt_text'], 0, 255),
    mb_substr($form['source_name'], 0, 255),
    $sourceUrl,
    mb_substr($form['meta_title'], 0, 160),
    mb_substr($form['meta_description'], 0, 320),
    $requestedStatus,
    $publishAt,
];

if (is_array($existing)) {
    $oldPath = recipePublicPath($existing);
    $oldSnapshot = recipeRevisionSnapshot($existing);
    $pdo->prepare(
        'UPDATE cms_recipes
         SET category_id = ?, title = ?, slug = ?, summary = ?, notes = ?, servings = ?,
             prep_minutes = ?, cook_minutes = ?, difficulty = ?, dietary_flags = ?, allergens = ?,
             calories_kcal = ?, media_id = ?, image_alt_text = ?, source_name = ?, source_url = ?,
             meta_title = ?, meta_description = ?, status = ?, publish_at = ?, updated_at = NOW()
         WHERE id = ?'
    )->execute(array_merge($values, [$id]));
    $newRecipe = array_merge($existing, [
        'category_id' => $form['category_id'],
        'title' => $form['title'],
        'slug' => $form['slug'],
        'summary' => $form['summary'],
        'notes' => $form['notes'],
        'servings' => $normalizedNumbers['servings'],
        'prep_minutes' => $normalizedNumbers['prep_minutes'],
        'cook_minutes' => $normalizedNumbers['cook_minutes'],
        'difficulty' => $form['difficulty'],
        'dietary_flags' => implode(',', $dietaryFlags),
        'allergens' => implode(',', $allergens),
        'calories_kcal' => $normalizedNumbers['calories_kcal'],
        'media_id' => $mediaId,
        'image_alt_text' => $form['image_alt_text'],
        'source_name' => $form['source_name'],
        'source_url' => $sourceUrl,
        'meta_title' => $form['meta_title'],
        'meta_description' => $form['meta_description'],
        'status' => $requestedStatus,
        'publish_at' => $publishAt,
    ]);
    saveRevision($pdo, 'recipe', $id, $oldSnapshot, recipeRevisionSnapshot($newRecipe));
    if (recipeIsPubliclyVisible($existing) && recipeIsPubliclyVisible($newRecipe)) {
        upsertPathRedirect($pdo, $oldPath, recipePublicPath($newRecipe));
    } elseif (!recipeIsPubliclyVisible($newRecipe)) {
        deleteRedirectsTargetingPath($pdo, $oldPath);
    }
    releaseContentLock('recipe', $id);
    logAction('recipe_edit', "id={$id} slug={$form['slug']} status={$requestedStatus}");
    header('Location: ' . appendUrlQuery(BASE_URL . '/admin/recipes.php', ['msg' => 'saved']));
    exit;
}

$pdo->prepare(
    'INSERT INTO cms_recipes
     (category_id, author_id, title, slug, summary, notes, servings, prep_minutes, cook_minutes,
      difficulty, dietary_flags, allergens, calories_kcal, media_id, image_alt_text, source_name,
      source_url, meta_title, meta_description, status, publish_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    (int)$form['category_id'],
    currentUserId(),
    $form['title'],
    $form['slug'],
    $form['summary'],
    $form['notes'],
    $normalizedNumbers['servings'],
    $normalizedNumbers['prep_minutes'],
    $normalizedNumbers['cook_minutes'],
    $form['difficulty'] !== '' ? $form['difficulty'] : null,
    implode(',', $dietaryFlags),
    implode(',', $allergens),
    $normalizedNumbers['calories_kcal'],
    $mediaId,
    mb_substr($form['image_alt_text'], 0, 255),
    mb_substr($form['source_name'], 0, 255),
    $sourceUrl,
    mb_substr($form['meta_title'], 0, 160),
    mb_substr($form['meta_description'], 0, 320),
    'draft',
    null,
]);
$newId = (int)$pdo->lastInsertId();
$pdo->prepare(
    "INSERT INTO cms_recipe_ingredient_groups (recipe_id, title, sort_order)
     VALUES (?, 'Ingredience', 10)"
)->execute([$newId]);
logAction('recipe_add', "id={$newId} slug={$form['slug']} status=draft");

header('Location: ' . BASE_URL . '/admin/recipe_content.php?id=' . $newId);
exit;
