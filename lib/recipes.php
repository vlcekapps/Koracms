<?php

/**
 * Sdílené helpery modulu Recepty.
 */

/**
 * @return array<string,string>
 */
function recipeDifficultyDefinitions(): array
{
    return [
        'easy' => 'Snadná',
        'medium' => 'Středně náročná',
        'hard' => 'Náročná',
    ];
}

/**
 * @return array<string,string>
 */
function recipeDietaryFlagDefinitions(): array
{
    return [
        'vegetarian' => 'Vegetariánské',
        'vegan' => 'Veganské',
        'gluten_free' => 'Bez lepku',
        'lactose_free' => 'Bez laktózy',
        'spicy' => 'Pikantní',
    ];
}

/**
 * @return array<int,string>
 */
function recipeAllergenDefinitions(): array
{
    return foodAllergenDefinitions();
}

/**
 * @param mixed $value
 * @param array<array-key,string> $allowed
 * @return list<string>
 */
function normalizeRecipeSelection(mixed $value, array $allowed): array
{
    $values = is_array($value) ? $value : explode(',', (string)$value);
    $result = [];
    $seen = [];
    foreach ($values as $item) {
        $key = trim((string)$item);
        $deduplicationKey = 'value:' . $key;
        if ($key !== '' && isset($allowed[$key]) && !isset($seen[$deduplicationKey])) {
            $seen[$deduplicationKey] = true;
            $result[] = $key;
        }
    }

    return $result;
}

/**
 * @param mixed $value
 */
function recipeNullablePositiveInt(mixed $value): ?int
{
    $normalized = trim((string)$value);
    if ($normalized === '' || !ctype_digit($normalized)) {
        return null;
    }

    $number = (int)$normalized;
    return $number > 0 ? $number : null;
}

function recipeNullableQuantity(mixed $value): ?string
{
    $normalized = str_replace(',', '.', trim((string)$value));
    if ($normalized === '' || preg_match('/^\d{1,8}(?:\.\d{1,4})?$/', $normalized) !== 1) {
        return null;
    }

    $number = (float)$normalized;
    if ($number <= 0 || $number > 99999999.9999) {
        return null;
    }

    return number_format($number, 4, '.', '');
}

function recipeQuantityInputValue(mixed $value): string
{
    $quantity = recipeNullableQuantity($value);
    if ($quantity === null) {
        return '';
    }

    return rtrim(rtrim($quantity, '0'), '.');
}

function recipeQuantityLabel(mixed $value): string
{
    if (is_int($value) || is_float($value)) {
        $number = (float)$value;
        if (!is_finite($number) || $number <= 0) {
            return '';
        }
        $precision = $number >= 1 ? 4 : 8;
        $formatted = rtrim(rtrim(number_format($number, $precision, '.', ''), '0'), '.');
        return str_replace('.', ',', $formatted);
    }

    $inputValue = recipeQuantityInputValue($value);
    return str_replace('.', ',', $inputValue);
}

/**
 * @param array<string,mixed> $ingredient
 */
function recipeIngredientAmountLabel(
    array $ingredient,
    ?int $baseServings = null,
    ?int $requestedServings = null
): string {
    $quantityMin = recipeNullableQuantity($ingredient['quantity_min'] ?? null);
    $quantityMax = recipeNullableQuantity($ingredient['quantity_max'] ?? null);
    $ratio = 1.0;
    if (
        $quantityMin !== null
        && $baseServings !== null
        && $baseServings > 0
        && $requestedServings !== null
        && $requestedServings > 0
    ) {
        $ratio = $requestedServings / $baseServings;
    }

    if ($quantityMin !== null) {
        $minimum = recipeQuantityLabel((float)$quantityMin * $ratio);
        $amount = $minimum;
        if ($quantityMax !== null && (float)$quantityMax >= (float)$quantityMin) {
            $maximum = recipeQuantityLabel((float)$quantityMax * $ratio);
            if ($maximum !== $minimum) {
                $amount .= '–' . $maximum;
            }
        }
    } else {
        $amount = trim((string)($ingredient['amount'] ?? ''));
    }

    $unit = trim((string)($ingredient['unit'] ?? ''));
    return trim($amount . ($amount !== '' && $unit !== '' ? ' ' : '') . $unit);
}

/**
 * @param array<string,mixed> $ingredient
 */
function recipeIngredientIsScalable(array $ingredient): bool
{
    return recipeNullableQuantity($ingredient['quantity_min'] ?? null) !== null;
}

function recipeRequestedServings(mixed $value, ?int $defaultServings): int
{
    $default = $defaultServings !== null && $defaultServings > 0
        ? min(100, $defaultServings)
        : 1;
    $requested = recipeNullablePositiveInt($value);
    if ($requested === null || $requested > 100) {
        return $default;
    }

    return $requested;
}

function recipeMaximumMinutes(mixed $value): ?int
{
    $minutes = recipeNullablePositiveInt($value);
    return $minutes !== null && $minutes <= 1440 ? $minutes : null;
}

function recipeShoppingPublicPath(): string
{
    return BASE_URL . '/recipes/nakupni-seznam';
}

/**
 * @return array<int,int>
 */
function normalizeRecipeShoppingSelection(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $selection = [];
    foreach ($value as $recipeId => $servings) {
        $normalizedRecipeId = filter_var($recipeId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $normalizedServings = recipeNullablePositiveInt($servings);
        if ($normalizedRecipeId === false || $normalizedServings === null || $normalizedServings > 100) {
            continue;
        }
        $selection[(int)$normalizedRecipeId] = $normalizedServings;
        if (count($selection) >= 50) {
            break;
        }
    }

    return $selection;
}

function recipeSlug(string $candidate): string
{
    return slugify($candidate);
}

function uniqueRecipeSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $base = recipeSlug($candidate);
    if ($base === '') {
        $base = 'recept';
    }

    $slug = $base;
    $suffix = 2;
    while (true) {
        $sql = 'SELECT id FROM cms_recipes WHERE slug = ?';
        $params = [$slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}

function recipeCategorySlug(string $candidate): string
{
    return slugify($candidate);
}

function uniqueRecipeCategorySlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $base = recipeCategorySlug($candidate);
    if ($base === '') {
        $base = 'kategorie';
    }

    $slug = $base;
    $suffix = 2;
    while (true) {
        $sql = 'SELECT id FROM cms_recipe_categories WHERE slug = ?';
        $params = [$slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}

/**
 * @param array<string,mixed>|string $recipe
 */
function recipePublicRequestPath(array|string $recipe): string
{
    $slug = is_array($recipe) ? (string)($recipe['slug'] ?? '') : $recipe;
    return '/recipes/' . rawurlencode($slug);
}

/**
 * @param array<string,mixed>|string $recipe
 */
function recipePublicPath(array|string $recipe): string
{
    return BASE_URL . recipePublicRequestPath($recipe);
}

/**
 * @param array<string,mixed>|string $recipe
 */
function recipePublicUrl(array|string $recipe): string
{
    return siteUrl(recipePublicRequestPath($recipe));
}

/**
 * @param array<string,mixed>|string $category
 */
function recipeCategoryPublicPath(array|string $category): string
{
    $slug = is_array($category) ? (string)($category['slug'] ?? '') : $category;
    return BASE_URL . '/recipes/kategorie/' . rawurlencode($slug);
}

function recipeCookbookPublicPath(string $categorySlug = ''): string
{
    if ($categorySlug !== '') {
        return BASE_URL . '/recipes/kucharka/' . rawurlencode($categorySlug) . '.epub';
    }

    return BASE_URL . '/recipes/kucharka.epub';
}

function recipePublicVisibilitySql(string $alias = 'r'): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    return $prefix . "status = 'published'"
        . ' AND ' . $prefix . 'deleted_at IS NULL'
        . ' AND (' . $prefix . 'publish_at IS NULL OR ' . $prefix . 'publish_at <= NOW())';
}

/**
 * @param array<string,mixed> $recipe
 */
function recipeIsPubliclyVisible(array $recipe, ?DateTimeImmutable $now = null): bool
{
    if ((string)($recipe['status'] ?? '') !== 'published' || !empty($recipe['deleted_at'])) {
        return false;
    }
    $publishAt = trim((string)($recipe['publish_at'] ?? ''));
    if ($publishAt === '') {
        return true;
    }

    $timestamp = strtotime($publishAt);
    if ($timestamp === false) {
        return false;
    }

    $now ??= new DateTimeImmutable('now');
    return $timestamp <= $now->getTimestamp();
}

function recipeDurationLabel(?int $minutes): string
{
    if ($minutes === null || $minutes <= 0) {
        return '';
    }
    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;
    if ($hours === 0) {
        return $minutes . ' min';
    }
    if ($remainingMinutes === 0) {
        return $hours . ' h';
    }

    return $hours . ' h ' . $remainingMinutes . ' min';
}

/**
 * @param array<string,mixed> $recipe
 */
function recipeTotalMinutes(array $recipe): ?int
{
    $prep = isset($recipe['prep_minutes']) ? (int)$recipe['prep_minutes'] : 0;
    $cook = isset($recipe['cook_minutes']) ? (int)$recipe['cook_minutes'] : 0;
    $total = max(0, $prep) + max(0, $cook);
    return $total > 0 ? $total : null;
}

/**
 * @param array<string,mixed> $recipe
 * @param array<string,mixed>|null $media
 */
function recipeImageAlt(array $recipe, ?array $media = null): string
{
    $custom = trim((string)($recipe['image_alt_text'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }
    $mediaAlt = trim((string)($media['alt_text'] ?? ''));
    if ($mediaAlt !== '') {
        return $mediaAlt;
    }

    return trim((string)($recipe['title'] ?? 'Recept'));
}

/**
 * @return array<string,mixed>|null
 */
function recipeFindPublicBySlug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, c.name AS category_name, c.slug AS category_slug,
                c.is_active AS category_is_active,
                m.filename AS media_filename, m.folder AS media_folder,
                m.original_name AS media_original_name, m.alt_text AS media_alt_text,
                m.mime_type AS media_mime_type, m.visibility AS media_visibility
         FROM cms_recipes r
         INNER JOIN cms_recipe_categories c ON c.id = r.category_id AND c.is_active = 1
         LEFT JOIN cms_media m ON m.id = r.media_id
         WHERE r.slug = ? AND ' . recipePublicVisibilitySql('r') . '
         LIMIT 1'
    );
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/**
 * @return array<string,mixed>|null
 */
function recipeFindPublicById(PDO $pdo, int $recipeId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, c.name AS category_name, c.slug AS category_slug,
                c.is_active AS category_is_active
         FROM cms_recipes r
         INNER JOIN cms_recipe_categories c ON c.id = r.category_id AND c.is_active = 1
         WHERE r.id = ? AND ' . recipePublicVisibilitySql('r') . '
         LIMIT 1'
    );
    $stmt->execute([$recipeId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/**
 * @return array{
 *   groups:list<array<string,mixed>>,
 *   steps:list<array<string,mixed>>,
 *   ingredient_count:int,
 *   step_count:int
 * }
 */
function recipeLoadStructure(PDO $pdo, int $recipeId): array
{
    $groupStmt = $pdo->prepare(
        'SELECT * FROM cms_recipe_ingredient_groups
         WHERE recipe_id = ? ORDER BY sort_order, id'
    );
    $groupStmt->execute([$recipeId]);
    $groups = $groupStmt->fetchAll();

    $ingredientStmt = $pdo->prepare(
        'SELECT * FROM cms_recipe_ingredients
         WHERE recipe_id = ? ORDER BY sort_order, id'
    );
    $ingredientStmt->execute([$recipeId]);
    $ingredients = $ingredientStmt->fetchAll();
    $ingredientsByGroup = [];
    foreach ($ingredients as $ingredient) {
        $groupId = (int)($ingredient['group_id'] ?? 0);
        $ingredientsByGroup[$groupId][] = $ingredient;
    }
    foreach ($groups as &$group) {
        $group['ingredients'] = $ingredientsByGroup[(int)$group['id']] ?? [];
    }
    unset($group);

    $stepStmt = $pdo->prepare(
        'SELECT s.*, m.filename AS media_filename, m.folder AS media_folder,
                m.original_name AS media_original_name, m.alt_text AS media_alt_text,
                m.mime_type AS media_mime_type, m.visibility AS media_visibility
         FROM cms_recipe_steps s
         LEFT JOIN cms_media m ON m.id = s.media_id
         WHERE s.recipe_id = ?
         ORDER BY s.sort_order, s.id'
    );
    $stepStmt->execute([$recipeId]);
    $steps = $stepStmt->fetchAll();

    return [
        'groups' => $groups,
        'steps' => $steps,
        'ingredient_count' => count($ingredients),
        'step_count' => count($steps),
    ];
}

function recipeHasPublishableStructure(PDO $pdo, int $recipeId): bool
{
    $ingredientStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_recipe_ingredients WHERE recipe_id = ?');
    $ingredientStmt->execute([$recipeId]);
    $stepStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_recipe_steps WHERE recipe_id = ?');
    $stepStmt->execute([$recipeId]);

    return (int)$ingredientStmt->fetchColumn() > 0 && (int)$stepStmt->fetchColumn() > 0;
}

/**
 * @return array{
 *   version:int,
 *   recipe_title:string,
 *   groups:list<array{
 *     title:string,
 *     sort_order:int,
 *     ingredients:list<array{
 *       amount:string,
 *       quantity_min:?string,
 *       quantity_max:?string,
 *       unit:string,
 *       name:string,
 *       note:string,
 *       is_optional:int,
 *       sort_order:int
 *     }>
 *   }>,
 *   steps:list<array{
 *     title:string,
 *     instruction:string,
 *     media_id:?int,
 *     image_alt_text:string,
 *     sort_order:int
 *   }>
 * }
 */
function recipeStructureSnapshot(PDO $pdo, int $recipeId): array
{
    $titleStmt = $pdo->prepare('SELECT title FROM cms_recipes WHERE id = ? LIMIT 1');
    $titleStmt->execute([$recipeId]);
    $recipeTitle = (string)($titleStmt->fetchColumn() ?: '');
    $structure = recipeLoadStructure($pdo, $recipeId);
    $groups = [];
    foreach ($structure['groups'] as $group) {
        $ingredients = [];
        foreach (($group['ingredients'] ?? []) as $ingredient) {
            if (!is_array($ingredient)) {
                continue;
            }
            $ingredients[] = [
                'amount' => (string)($ingredient['amount'] ?? ''),
                'quantity_min' => recipeNullableQuantity($ingredient['quantity_min'] ?? null),
                'quantity_max' => recipeNullableQuantity($ingredient['quantity_max'] ?? null),
                'unit' => (string)($ingredient['unit'] ?? ''),
                'name' => (string)($ingredient['name'] ?? ''),
                'note' => (string)($ingredient['note'] ?? ''),
                'is_optional' => (int)($ingredient['is_optional'] ?? 0) === 1 ? 1 : 0,
                'sort_order' => (int)($ingredient['sort_order'] ?? 0),
            ];
        }
        $groups[] = [
            'title' => (string)($group['title'] ?? ''),
            'sort_order' => (int)($group['sort_order'] ?? 0),
            'ingredients' => $ingredients,
        ];
    }

    $steps = [];
    foreach ($structure['steps'] as $step) {
        $mediaId = isset($step['media_id']) && (int)$step['media_id'] > 0
            ? (int)$step['media_id']
            : null;
        $steps[] = [
            'title' => (string)($step['title'] ?? ''),
            'instruction' => (string)($step['instruction'] ?? ''),
            'media_id' => $mediaId,
            'image_alt_text' => (string)($step['image_alt_text'] ?? ''),
            'sort_order' => (int)($step['sort_order'] ?? 0),
        ];
    }

    return [
        'version' => 1,
        'recipe_title' => $recipeTitle,
        'groups' => $groups,
        'steps' => $steps,
    ];
}

function recipeSaveStructureSnapshot(PDO $pdo, int $recipeId, string $actionLabel, ?int $userId): int
{
    $snapshot = recipeStructureSnapshot($pdo, $recipeId);
    $ingredientCount = 0;
    foreach ($snapshot['groups'] as $group) {
        $ingredientCount += count($group['ingredients']);
    }
    $encoded = json_encode(
        $snapshot,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $stmt = $pdo->prepare(
        'INSERT INTO cms_recipe_structure_snapshots
         (recipe_id, action_label, snapshot_json, ingredient_count, step_count, user_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $recipeId,
        mb_substr(trim($actionLabel), 0, 255),
        $encoded,
        $ingredientCount,
        count($snapshot['steps']),
        $userId,
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * @return array<string,mixed>|null
 */
function recipeDecodeStructureSnapshot(string $snapshotJson): ?array
{
    try {
        $snapshot = json_decode($snapshotJson, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (
        !is_array($snapshot)
        || (int)($snapshot['version'] ?? 0) !== 1
        || !is_array($snapshot['groups'] ?? null)
        || !is_array($snapshot['steps'] ?? null)
    ) {
        return null;
    }

    return $snapshot;
}

/**
 * @param array<string,mixed> $snapshot
 */
function recipeStructureSnapshotIsPublishable(array $snapshot): bool
{
    $groups = is_array($snapshot['groups'] ?? null) ? $snapshot['groups'] : [];
    $steps = is_array($snapshot['steps'] ?? null) ? $snapshot['steps'] : [];
    $hasIngredient = false;
    foreach ($groups as $group) {
        if (!is_array($group) || !is_array($group['ingredients'] ?? null)) {
            continue;
        }
        foreach ($group['ingredients'] as $ingredient) {
            $name = is_array($ingredient) ? ($ingredient['name'] ?? '') : '';
            if (is_scalar($name) && trim((string)$name) !== '') {
                $hasIngredient = true;
                break 2;
            }
        }
    }
    if (!$hasIngredient) {
        return false;
    }
    foreach ($steps as $step) {
        $instruction = is_array($step) ? ($step['instruction'] ?? '') : '';
        if (is_scalar($instruction) && trim((string)$instruction) !== '') {
            return true;
        }
    }
    return false;
}

/**
 * @param array<string,mixed> $snapshot
 */
function recipeRestoreStructure(PDO $pdo, int $recipeId, array $snapshot): bool
{
    if (
        (int)($snapshot['version'] ?? 0) !== 1
        || !is_array($snapshot['groups'] ?? null)
        || !is_array($snapshot['steps'] ?? null)
    ) {
        return false;
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $recipeStatement = $pdo->prepare('SELECT status FROM cms_recipes WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $recipeStatement->execute([$recipeId]);
        $recipeStatus = $recipeStatement->fetchColumn();
        if ($recipeStatus === false) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            return false;
        }
        // Validate the entries restoration actually keeps, not array counts.
        // The row lock keeps concurrent publication/deletion outside this change.
        if ($recipeStatus === 'published' && !recipeStructureSnapshotIsPublishable($snapshot)) {
            throw new DomainException('Zveřejněný recept musí mít alespoň jednu ingredienci a jeden krok postupu. Pro obnovení neúplné verze nejprve přepněte recept na koncept.');
        }
        $pdo->prepare('DELETE FROM cms_recipe_steps WHERE recipe_id = ?')->execute([$recipeId]);
        $pdo->prepare('DELETE FROM cms_recipe_ingredients WHERE recipe_id = ?')->execute([$recipeId]);
        $pdo->prepare('DELETE FROM cms_recipe_ingredient_groups WHERE recipe_id = ?')->execute([$recipeId]);

        $insertGroup = $pdo->prepare(
            'INSERT INTO cms_recipe_ingredient_groups (recipe_id, title, sort_order) VALUES (?, ?, ?)'
        );
        $insertIngredient = $pdo->prepare(
            'INSERT INTO cms_recipe_ingredients
             (recipe_id, group_id, amount, quantity_min, quantity_max, unit, name, note, is_optional, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($snapshot['groups'] as $group) {
            if (!is_array($group)) {
                continue;
            }
            $insertGroup->execute([
                $recipeId,
                mb_substr(trim((string)($group['title'] ?? '')), 0, 255),
                max(0, (int)($group['sort_order'] ?? 0)),
            ]);
            $groupId = (int)$pdo->lastInsertId();
            $ingredients = is_array($group['ingredients'] ?? null) ? $group['ingredients'] : [];
            foreach ($ingredients as $ingredient) {
                if (!is_array($ingredient) || !is_scalar($ingredient['name'] ?? null)) {
                    continue;
                }
                $name = mb_substr(trim((string)$ingredient['name']), 0, 255);
                if ($name === '') {
                    continue;
                }
                $quantityMin = recipeNullableQuantity($ingredient['quantity_min'] ?? null);
                $quantityMax = recipeNullableQuantity($ingredient['quantity_max'] ?? null);
                if ($quantityMin === null || ($quantityMax !== null && (float)$quantityMax < (float)$quantityMin)) {
                    $quantityMax = null;
                }
                $insertIngredient->execute([
                    $recipeId,
                    $groupId,
                    mb_substr(trim((string)($ingredient['amount'] ?? '')), 0, 40),
                    $quantityMin,
                    $quantityMax,
                    mb_substr(trim((string)($ingredient['unit'] ?? '')), 0, 40),
                    $name,
                    mb_substr(trim((string)($ingredient['note'] ?? '')), 0, 255),
                    (int)($ingredient['is_optional'] ?? 0) === 1 ? 1 : 0,
                    max(0, (int)($ingredient['sort_order'] ?? 0)),
                ]);
            }
        }

        $insertStep = $pdo->prepare(
            'INSERT INTO cms_recipe_steps
             (recipe_id, title, instruction, media_id, image_alt_text, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($snapshot['steps'] as $step) {
            if (!is_array($step) || !is_scalar($step['instruction'] ?? null)) {
                continue;
            }
            $instruction = trim((string)$step['instruction']);
            if ($instruction === '') {
                continue;
            }
            $mediaId = isset($step['media_id']) && (int)$step['media_id'] > 0
                ? (int)$step['media_id']
                : null;
            $insertStep->execute([
                $recipeId,
                mb_substr(trim((string)($step['title'] ?? '')), 0, 255),
                $instruction,
                $mediaId,
                mb_substr(trim((string)($step['image_alt_text'] ?? '')), 0, 255),
                max(0, (int)($step['sort_order'] ?? 0)),
            ]);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return true;
}

function recipeDuplicate(PDO $pdo, int $sourceId, ?int $authorId): ?int
{
    $stmt = $pdo->prepare('SELECT * FROM cms_recipes WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$sourceId]);
    $source = $stmt->fetch();
    if (!is_array($source)) {
        return null;
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $title = mb_substr((string)$source['title'] . ' (kopie)', 0, 255);
        $slug = uniqueRecipeSlug($pdo, recipeSlug((string)$source['slug'] . '-kopie'));
        $pdo->prepare(
            'INSERT INTO cms_recipes
             (category_id, author_id, title, slug, summary, notes, servings, prep_minutes, cook_minutes,
              difficulty, dietary_flags, allergens, calories_kcal, media_id, image_alt_text, source_name,
              source_url, meta_title, meta_description, status, publish_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'draft\', NULL)'
        )->execute([
            (int)$source['category_id'],
            $authorId !== null && $authorId > 0 ? $authorId : null,
            $title,
            $slug,
            (string)($source['summary'] ?? ''),
            (string)($source['notes'] ?? ''),
            $source['servings'],
            $source['prep_minutes'],
            $source['cook_minutes'],
            $source['difficulty'],
            (string)($source['dietary_flags'] ?? ''),
            (string)($source['allergens'] ?? ''),
            $source['calories_kcal'],
            $source['media_id'],
            (string)($source['image_alt_text'] ?? ''),
            (string)($source['source_name'] ?? ''),
            (string)($source['source_url'] ?? ''),
            (string)($source['meta_title'] ?? ''),
            (string)($source['meta_description'] ?? ''),
        ]);
        $newId = (int)$pdo->lastInsertId();
        $snapshot = recipeStructureSnapshot($pdo, $sourceId);
        if (!recipeRestoreStructure($pdo, $newId, $snapshot)) {
            throw new RuntimeException('Strukturu receptu se nepodařilo zkopírovat.');
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return $newId;
}

/**
 * @param array<string,mixed> $recipe
 * @return array<string,string>
 */
function recipeRevisionSnapshot(array $recipe): array
{
    $fields = [
        'category_id',
        'title',
        'slug',
        'summary',
        'notes',
        'servings',
        'prep_minutes',
        'cook_minutes',
        'difficulty',
        'dietary_flags',
        'allergens',
        'calories_kcal',
        'media_id',
        'image_alt_text',
        'source_name',
        'source_url',
        'meta_title',
        'meta_description',
        'status',
        'publish_at',
    ];
    $snapshot = [];
    foreach ($fields as $field) {
        $snapshot[$field] = (string)($recipe[$field] ?? '');
    }

    return $snapshot;
}

/**
 * @param array<string,mixed> $recipe
 * @param array{groups:list<array<string,mixed>>,steps:list<array<string,mixed>>,ingredient_count:int,step_count:int} $structure
 * @return array<string,mixed>
 */
function recipeStructuredData(array $recipe, array $structure): array
{
    $ingredients = [];
    foreach ($structure['groups'] as $group) {
        foreach (($group['ingredients'] ?? []) as $ingredient) {
            if (!is_array($ingredient)) {
                continue;
            }
            $parts = array_filter([
                recipeIngredientAmountLabel($ingredient),
                trim((string)($ingredient['name'] ?? '')),
                trim((string)($ingredient['note'] ?? '')),
            ], static fn (string $part): bool => $part !== '');
            if ($parts !== []) {
                $ingredients[] = implode(' ', $parts);
            }
        }
    }
    $instructions = [];
    foreach ($structure['steps'] as $step) {
        $text = trim((string)($step['instruction'] ?? ''));
        if ($text === '') {
            continue;
        }
        $instructions[] = [
            '@type' => 'HowToStep',
            'name' => trim((string)($step['title'] ?? '')),
            'text' => $text,
        ];
    }

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Recipe',
        'name' => (string)($recipe['title'] ?? ''),
        'description' => (string)($recipe['summary'] ?? ''),
        'url' => recipePublicUrl($recipe),
        'recipeIngredient' => $ingredients,
        'recipeInstructions' => $instructions,
    ];
    $prep = isset($recipe['prep_minutes']) ? (int)$recipe['prep_minutes'] : 0;
    $cook = isset($recipe['cook_minutes']) ? (int)$recipe['cook_minutes'] : 0;
    if ($prep > 0) {
        $data['prepTime'] = 'PT' . $prep . 'M';
    }
    if ($cook > 0) {
        $data['cookTime'] = 'PT' . $cook . 'M';
    }
    $total = recipeTotalMinutes($recipe);
    if ($total !== null) {
        $data['totalTime'] = 'PT' . $total . 'M';
    }
    if ((int)($recipe['servings'] ?? 0) > 0) {
        $data['recipeYield'] = (int)$recipe['servings'] . ' porcí';
    }
    $calories = (int)($recipe['calories_kcal'] ?? 0);
    if ($calories > 0) {
        $data['nutrition'] = [
            '@type' => 'NutritionInformation',
            'calories' => $calories . ' kcal',
        ];
    }
    if (trim((string)($recipe['category_name'] ?? '')) !== '') {
        $data['recipeCategory'] = (string)$recipe['category_name'];
    }

    $media = [
        'filename' => $recipe['media_filename'] ?? '',
        'folder' => $recipe['media_folder'] ?? 'media',
        'original_name' => $recipe['media_original_name'] ?? '',
        'alt_text' => $recipe['media_alt_text'] ?? '',
        'mime_type' => $recipe['media_mime_type'] ?? '',
        'visibility' => $recipe['media_visibility'] ?? '',
    ];
    if (mediaIsPublic($media) && mediaCanPreviewImage($media)) {
        $mediaPath = mediaFileUrl($media);
        $requestPath = BASE_URL !== '' && str_starts_with($mediaPath, BASE_URL)
            ? substr($mediaPath, strlen(BASE_URL))
            : $mediaPath;
        $data['image'] = siteUrl($requestPath);
    }

    return $data;
}

function recipeEpubEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function recipeEpubParagraphs(string $value): string
{
    $paragraphs = preg_split('/\R{2,}/u', trim($value)) ?: [];
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($paragraph)) ?? '');
        if ($text !== '') {
            $html .= '<p>' . recipeEpubEscape($text) . '</p>';
        }
    }
    return $html;
}

/**
 * @param list<array<string,mixed>> $recipes
 */
function buildRecipeCookbookEpub(array $recipes, string $title): ?string
{
    if (!class_exists(ZipArchive::class)) {
        return null;
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'kora-recipes-');
    if ($tmpPath === false) {
        return null;
    }
    $epubPath = $tmpPath . '.epub';
    @unlink($tmpPath);

    $groups = [];
    foreach ($recipes as $recipe) {
        $category = trim((string)($recipe['category_name'] ?? ''));
        if ($category === '') {
            $category = 'Ostatní';
        }
        $groups[$category][] = $recipe;
    }

    $navItems = '';
    $body = '<h1>' . recipeEpubEscape($title) . '</h1>';
    $body .= '<p>Tato kuchařka byla vytvořena z veřejných receptů webu '
        . recipeEpubEscape((string)getSetting('site_name', 'Kora CMS')) . '.</p>';
    foreach ($groups as $categoryName => $categoryRecipes) {
        $categoryId = 'category-' . recipeSlug($categoryName);
        $categoryNavItems = '';
        $body .= '<section aria-labelledby="' . recipeEpubEscape($categoryId) . '">';
        $body .= '<h2 id="' . recipeEpubEscape($categoryId) . '">' . recipeEpubEscape($categoryName) . '</h2>';
        foreach ($categoryRecipes as $recipe) {
            $recipeId = 'recipe-' . recipeSlug((string)($recipe['slug'] ?? $recipe['title'] ?? 'recept'));
            $categoryNavItems .= '<li><a href="cookbook.xhtml#' . recipeEpubEscape($recipeId) . '">'
                . recipeEpubEscape((string)$recipe['title']) . '</a></li>';
            $body .= '<article aria-labelledby="' . recipeEpubEscape($recipeId) . '">';
            $body .= '<h3 id="' . recipeEpubEscape($recipeId) . '">' . recipeEpubEscape((string)$recipe['title']) . '</h3>';
            $summary = trim((string)($recipe['summary'] ?? ''));
            if ($summary !== '') {
                $body .= '<p>' . recipeEpubEscape($summary) . '</p>';
            }
            $meta = [];
            if ((int)($recipe['servings'] ?? 0) > 0) {
                $meta[] = (int)$recipe['servings'] . ' porcí';
            }
            if ((int)($recipe['prep_minutes'] ?? 0) > 0) {
                $meta[] = 'Příprava ' . recipeDurationLabel((int)$recipe['prep_minutes']);
            }
            if ((int)($recipe['cook_minutes'] ?? 0) > 0) {
                $meta[] = 'Vaření ' . recipeDurationLabel((int)$recipe['cook_minutes']);
            }
            if ($meta !== []) {
                $body .= '<p>' . recipeEpubEscape(implode(', ', $meta)) . '</p>';
            }

            $structure = is_array($recipe['structure'] ?? null) ? $recipe['structure'] : [
                'groups' => [],
                'steps' => [],
            ];
            $body .= '<section><h4>Ingredience</h4>';
            foreach (($structure['groups'] ?? []) as $group) {
                if (!is_array($group)) {
                    continue;
                }
                $groupTitle = trim((string)($group['title'] ?? ''));
                if ($groupTitle !== '') {
                    $body .= '<h5>' . recipeEpubEscape($groupTitle) . '</h5>';
                }
                $body .= '<ul>';
                foreach (($group['ingredients'] ?? []) as $ingredient) {
                    if (!is_array($ingredient)) {
                        continue;
                    }
                    $parts = array_filter([
                        recipeIngredientAmountLabel($ingredient),
                        trim((string)($ingredient['name'] ?? '')),
                        trim((string)($ingredient['note'] ?? '')),
                    ], static fn (string $part): bool => $part !== '');
                    $body .= '<li>' . recipeEpubEscape(implode(' ', $parts));
                    if (!empty($ingredient['is_optional'])) {
                        $body .= ' (volitelné)';
                    }
                    $body .= '</li>';
                }
                $body .= '</ul>';
            }
            $body .= '</section>';

            $body .= '<section><h4>Postup</h4><ol>';
            foreach (($structure['steps'] ?? []) as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $instruction = trim((string)($step['instruction'] ?? ''));
                if ($instruction === '') {
                    continue;
                }
                $body .= '<li>';
                $stepTitle = trim((string)($step['title'] ?? ''));
                if ($stepTitle !== '') {
                    $body .= '<strong>' . recipeEpubEscape($stepTitle) . '.</strong> ';
                }
                $body .= recipeEpubEscape($instruction) . '</li>';
            }
            $body .= '</ol></section>';

            $notes = trim((string)($recipe['notes'] ?? ''));
            if ($notes !== '') {
                $body .= '<section><h4>Poznámky</h4>' . recipeEpubParagraphs($notes) . '</section>';
            }
            $body .= '</article>';
        }
        $navItems .= '<li><a href="cookbook.xhtml#' . recipeEpubEscape($categoryId) . '">'
            . recipeEpubEscape($categoryName) . '</a><ol>' . $categoryNavItems . '</ol></li>';
        $body .= '</section>';
    }

    $language = 'cs';
    $identifier = 'urn:uuid:' . sprintf(
        '%s-%s-%s-%s-%s',
        bin2hex(random_bytes(4)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(6))
    );
    $modified = gmdate('Y-m-d\TH:i:s\Z');
    $xhtmlHead = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml"'
        . ' xmlns:epub="http://www.idpf.org/2007/ops" lang="' . $language . '" xml:lang="' . $language . '">'
        . '<head><meta charset="UTF-8"/><title>' . recipeEpubEscape($title)
        . '</title><link rel="stylesheet" type="text/css" href="styles.css"/></head>';
    $nav = $xhtmlHead . '<body><nav epub:type="toc" aria-labelledby="toc-title"><h1 id="toc-title">Obsah</h1><ol>'
        . $navItems . '</ol></nav></body></html>';
    $content = $xhtmlHead . '<body>' . $body . '</body></html>';
    $package = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="book-id"'
        . ' xml:lang="' . $language . '" prefix="schema: http://schema.org/">'
        . '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:identifier id="book-id">' . recipeEpubEscape($identifier) . '</dc:identifier>'
        . '<dc:title>' . recipeEpubEscape($title) . '</dc:title><dc:language>' . $language . '</dc:language>'
        . '<dc:creator>' . recipeEpubEscape((string)getSetting('site_name', 'Kora CMS')) . '</dc:creator>'
        . '<meta property="dcterms:modified">' . $modified . '</meta>'
        . '<meta property="schema:accessMode">textual</meta>'
        . '<meta property="schema:accessibilityFeature">structuralNavigation</meta>'
        . '<meta property="schema:accessibilityFeature">tableOfContents</meta>'
        . '<meta property="schema:accessibilityHazard">none</meta>'
        . '<meta property="schema:accessibilitySummary">Kuchařka používá sémantické nadpisy, seznamy a obsah.</meta>'
        . '</metadata><manifest>'
        . '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>'
        . '<item id="content" href="cookbook.xhtml" media-type="application/xhtml+xml"/>'
        . '<item id="css" href="styles.css" media-type="text/css"/>'
        . '</manifest><spine><itemref idref="content"/></spine></package>';
    $container = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">'
        . '<rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>'
        . '</rootfiles></container>';
    $css = 'body{font-family:serif;line-height:1.55;margin:5%;}'
        . 'h1,h2,h3,h4,h5{line-height:1.2;break-after:avoid;}'
        . 'article{border-top:1px solid #777;margin-top:2em;padding-top:1em;}'
        . 'li{margin:.3em 0;}';

    $zip = new ZipArchive();
    if ($zip->open($epubPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return null;
    }
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
    $zip->addFromString('META-INF/container.xml', $container);
    $zip->addFromString('OEBPS/content.opf', $package);
    $zip->addFromString('OEBPS/nav.xhtml', $nav);
    $zip->addFromString('OEBPS/cookbook.xhtml', $content);
    $zip->addFromString('OEBPS/styles.css', $css);
    $zip->close();

    return is_file($epubPath) ? $epubPath : null;
}

/**
 * @return list<array{name:string,slug:string,sort_order:int}>
 */
function recipeDefaultCategories(): array
{
    $names = [
        'Předkrmy',
        'Polévky',
        'Hlavní jídla',
        'Dezerty',
        'Saláty',
        'Přílohy',
        'Pečivo',
        'Nápoje',
        'Snídaně a svačiny',
        'Ostatní',
    ];
    $result = [];
    foreach ($names as $index => $name) {
        $result[] = [
            'name' => $name,
            'slug' => recipeCategorySlug($name),
            'sort_order' => ($index + 1) * 10,
        ];
    }

    return $result;
}

function seedRecipeCategories(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO cms_recipe_categories (name, slug, sort_order, is_active)
         VALUES (?, ?, ?, 1)'
    );
    foreach (recipeDefaultCategories() as $category) {
        $stmt->execute([$category['name'], $category['slug'], $category['sort_order']]);
    }
}
