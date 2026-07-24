<?php

require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu receptů nemáte potřebné oprávnění.');
requireModuleEnabled('recipes');

$pdo = db_connect();
$recipeId = inputInt('get', 'id') ?? inputInt('post', 'recipe_id');
if ($recipeId === null) {
    header('Location: ' . BASE_URL . '/admin/recipes.php');
    exit;
}
$recipeStmt = $pdo->prepare('SELECT * FROM cms_recipes WHERE id = ? AND deleted_at IS NULL');
$recipeStmt->execute([$recipeId]);
$recipe = $recipeStmt->fetch();
if (!is_array($recipe)) {
    header('Location: ' . BASE_URL . '/admin/recipes.php');
    exit;
}

$contentLockWarning = acquireContentLock('recipe', $recipeId);
$error = '';
$fieldErrors = [];
$fieldErrorMessages = [];
$groupState = ['id' => 0, 'title' => ''];
$ingredientState = [
    'id' => 0,
    'group_id' => '',
    'amount' => '',
    'unit' => '',
    'name' => '',
    'note' => '',
    'is_optional' => 0,
];
$stepState = [
    'id' => 0,
    'title' => '',
    'instruction' => '',
    'media_id' => '',
    'image_alt_text' => '',
];

$redirectToContent = static function (string $message = '') use ($recipeId): void {
    $params = ['id' => $recipeId];
    if ($message !== '') {
        $params['msg'] = $message;
    }
    header('Location: ' . BASE_URL . '/admin/recipe_content.php?' . http_build_query($params));
    exit;
};
$groupBelongs = static function (?int $groupId) use ($pdo, $recipeId): bool {
    if ($groupId === null) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT id FROM cms_recipe_ingredient_groups WHERE id = ? AND recipe_id = ?');
    $stmt->execute([$groupId, $recipeId]);
    return (bool)$stmt->fetchColumn();
};
$ingredientForRecipe = static function (?int $ingredientId) use ($pdo, $recipeId): ?array {
    if ($ingredientId === null) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM cms_recipe_ingredients WHERE id = ? AND recipe_id = ?');
    $stmt->execute([$ingredientId, $recipeId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
};
$stepForRecipe = static function (?int $stepId) use ($pdo, $recipeId): ?array {
    if ($stepId === null) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM cms_recipe_steps WHERE id = ? AND recipe_id = ?');
    $stmt->execute([$stepId, $recipeId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
};
$nextOrder = static function (string $table, string $where, array $params) use ($pdo): int {
    if (!in_array($table, [
        'cms_recipe_ingredient_groups',
        'cms_recipe_ingredients',
        'cms_recipe_steps',
    ], true)) {
        return 10;
    }
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM {$table} WHERE {$where}");
    $stmt->execute($params);
    return max(10, (int)$stmt->fetchColumn());
};
$moveRow = static function (
    string $table,
    int $rowId,
    string $where,
    array $params,
    string $direction
) use ($pdo): bool {
    if (!in_array($table, [
        'cms_recipe_ingredient_groups',
        'cms_recipe_ingredients',
        'cms_recipe_steps',
    ], true) || !in_array($direction, ['up', 'down'], true)) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT id, sort_order FROM {$table} WHERE {$where} ORDER BY sort_order, id");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $index = null;
    foreach ($rows as $rowIndex => $row) {
        if ((int)$row['id'] === $rowId) {
            $index = $rowIndex;
            break;
        }
    }
    if ($index === null) {
        return false;
    }
    $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
    if (!isset($rows[$targetIndex])) {
        return false;
    }
    $current = $rows[$index];
    $target = $rows[$targetIndex];
    $update = $pdo->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ?");
    $update->execute([(int)$target['sort_order'], (int)$current['id']]);
    $update->execute([(int)$current['sort_order'], (int)$target['id']]);
    return true;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'save_group') {
        $groupId = inputInt('post', 'group_id');
        $groupState = [
            'id' => $groupId ?? 0,
            'title' => mb_substr(trim((string)($_POST['group_title'] ?? '')), 0, 255),
        ];
        if ($groupId !== null && !$groupBelongs($groupId)) {
            $error = 'Upravovanou skupinu se v tomto receptu nepodařilo najít.';
        } elseif ($groupId !== null) {
            $pdo->prepare(
                'UPDATE cms_recipe_ingredient_groups SET title = ?, updated_at = NOW()
                 WHERE id = ? AND recipe_id = ?'
            )->execute([$groupState['title'], $groupId, $recipeId]);
            logAction('recipe_group_edit', "recipe={$recipeId} group={$groupId}");
            $redirectToContent('saved');
        } else {
            $order = $nextOrder('cms_recipe_ingredient_groups', 'recipe_id = ?', [$recipeId]);
            $pdo->prepare(
                'INSERT INTO cms_recipe_ingredient_groups (recipe_id, title, sort_order) VALUES (?, ?, ?)'
            )->execute([$recipeId, $groupState['title'], $order]);
            logAction('recipe_group_add', "recipe={$recipeId}");
            $redirectToContent('saved');
        }
    }

    if ($action === 'save_ingredient') {
        $ingredientId = inputInt('post', 'ingredient_id');
        $groupId = inputInt('post', 'group_id');
        $ingredientState = [
            'id' => $ingredientId ?? 0,
            'group_id' => $groupId ?? '',
            'amount' => mb_substr(trim((string)($_POST['amount'] ?? '')), 0, 40),
            'unit' => mb_substr(trim((string)($_POST['unit'] ?? '')), 0, 40),
            'name' => mb_substr(trim((string)($_POST['ingredient_name'] ?? '')), 0, 255),
            'note' => mb_substr(trim((string)($_POST['ingredient_note'] ?? '')), 0, 255),
            'is_optional' => isset($_POST['is_optional']) ? 1 : 0,
        ];
        $existingIngredient = $ingredientForRecipe($ingredientId);
        if ($ingredientState['name'] === '') {
            $error = 'Ingredienci nejde uložit bez názvu.';
            $fieldErrors[] = 'ingredient_name';
            $fieldErrorMessages['ingredient_name'] = 'Doplňte název suroviny, například hladká mouka.';
        } elseif (!$groupBelongs($groupId)) {
            $error = 'Vyberte skupinu ingrediencí, která patří k tomuto receptu.';
            $fieldErrors[] = 'ingredient_group';
            $fieldErrorMessages['ingredient_group'] = 'Vyberte existující skupinu tohoto receptu.';
        } elseif ($ingredientId !== null && $existingIngredient === null) {
            $error = 'Upravovanou ingredienci se v tomto receptu nepodařilo najít.';
        } elseif ($existingIngredient !== null) {
            $pdo->prepare(
                'UPDATE cms_recipe_ingredients
                 SET group_id = ?, amount = ?, unit = ?, name = ?, note = ?, is_optional = ?, updated_at = NOW()
                 WHERE id = ? AND recipe_id = ?'
            )->execute([
                $groupId,
                $ingredientState['amount'],
                $ingredientState['unit'],
                $ingredientState['name'],
                $ingredientState['note'],
                $ingredientState['is_optional'],
                $ingredientId,
                $recipeId,
            ]);
            logAction('recipe_ingredient_edit', "recipe={$recipeId} ingredient={$ingredientId}");
            $redirectToContent('saved');
        } else {
            $order = $nextOrder(
                'cms_recipe_ingredients',
                'recipe_id = ? AND group_id = ?',
                [$recipeId, $groupId]
            );
            $pdo->prepare(
                'INSERT INTO cms_recipe_ingredients
                 (recipe_id, group_id, amount, unit, name, note, is_optional, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $recipeId,
                $groupId,
                $ingredientState['amount'],
                $ingredientState['unit'],
                $ingredientState['name'],
                $ingredientState['note'],
                $ingredientState['is_optional'],
                $order,
            ]);
            logAction('recipe_ingredient_add', "recipe={$recipeId}");
            $redirectToContent('saved');
        }
    }

    if ($action === 'save_step') {
        $stepId = inputInt('post', 'step_id');
        $mediaId = inputInt('post', 'media_id');
        $stepState = [
            'id' => $stepId ?? 0,
            'title' => mb_substr(trim((string)($_POST['step_title'] ?? '')), 0, 255),
            'instruction' => trim((string)($_POST['instruction'] ?? '')),
            'media_id' => $mediaId ?? '',
            'image_alt_text' => mb_substr(trim((string)($_POST['image_alt_text'] ?? '')), 0, 255),
        ];
        $existingStep = $stepForRecipe($stepId);
        if ($stepState['instruction'] === '') {
            $error = 'Krok postupu nejde uložit bez instrukce.';
            $fieldErrors[] = 'instruction';
            $fieldErrorMessages['instruction'] = 'Popište jeden konkrétní krok postupu.';
        }
        if ($mediaId !== null) {
            $media = mediaGetById($mediaId);
            if (!is_array($media) || !mediaIsPublic($media) || !mediaCanPreviewImage($media)) {
                $error = 'Vybraný obrázek kroku není veřejný rastrový obrázek.';
                $fieldErrors[] = 'step_media_id';
                $fieldErrorMessages['step_media_id'] = 'Vyberte veřejný rastrový obrázek z knihovny médií, nebo obrázek odeberte.';
            }
        }
        if ($stepId !== null && $existingStep === null) {
            $error = 'Upravovaný krok se v tomto receptu nepodařilo najít.';
        }
        if ($error === '' && $existingStep !== null) {
            $pdo->prepare(
                'UPDATE cms_recipe_steps
                 SET title = ?, instruction = ?, media_id = ?, image_alt_text = ?, updated_at = NOW()
                 WHERE id = ? AND recipe_id = ?'
            )->execute([
                $stepState['title'],
                $stepState['instruction'],
                $mediaId,
                $stepState['image_alt_text'],
                $stepId,
                $recipeId,
            ]);
            logAction('recipe_step_edit', "recipe={$recipeId} step={$stepId}");
            $redirectToContent('saved');
        } elseif ($error === '') {
            $order = $nextOrder('cms_recipe_steps', 'recipe_id = ?', [$recipeId]);
            $pdo->prepare(
                'INSERT INTO cms_recipe_steps
                 (recipe_id, title, instruction, media_id, image_alt_text, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $recipeId,
                $stepState['title'],
                $stepState['instruction'],
                $mediaId,
                $stepState['image_alt_text'],
                $order,
            ]);
            logAction('recipe_step_add', "recipe={$recipeId}");
            $redirectToContent('saved');
        }
    }

    if (in_array($action, ['delete_group', 'delete_ingredient', 'delete_step'], true)) {
        $confirmed = (string)($_POST['confirm_action'] ?? '') === '1';
        if (!$confirmed) {
            $error = 'Smazání vyžaduje potvrzení kontroly dopadu.';
        } elseif ($action === 'delete_group') {
            $groupId = inputInt('post', 'group_id');
            if ($groupBelongs($groupId)) {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare(
                        'DELETE FROM cms_recipe_ingredients WHERE recipe_id = ? AND group_id = ?'
                    )->execute([$recipeId, $groupId]);
                    $pdo->prepare(
                        'DELETE FROM cms_recipe_ingredient_groups WHERE recipe_id = ? AND id = ?'
                    )->execute([$recipeId, $groupId]);
                    $pdo->commit();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
                logAction('recipe_group_delete', "recipe={$recipeId} group={$groupId}");
            }
            $redirectToContent('deleted');
        } elseif ($action === 'delete_ingredient') {
            $ingredientId = inputInt('post', 'ingredient_id');
            if ($ingredientForRecipe($ingredientId) !== null) {
                $pdo->prepare(
                    'DELETE FROM cms_recipe_ingredients WHERE id = ? AND recipe_id = ?'
                )->execute([$ingredientId, $recipeId]);
                logAction('recipe_ingredient_delete', "recipe={$recipeId} ingredient={$ingredientId}");
            }
            $redirectToContent('deleted');
        } else {
            $stepId = inputInt('post', 'step_id');
            if ($stepForRecipe($stepId) !== null) {
                $pdo->prepare(
                    'DELETE FROM cms_recipe_steps WHERE id = ? AND recipe_id = ?'
                )->execute([$stepId, $recipeId]);
                logAction('recipe_step_delete', "recipe={$recipeId} step={$stepId}");
            }
            $redirectToContent('deleted');
        }
    }

    if (in_array($action, ['move_group', 'move_ingredient', 'move_step'], true)) {
        $direction = trim((string)($_POST['direction'] ?? ''));
        if ($action === 'move_group') {
            $groupId = inputInt('post', 'group_id');
            if ($groupBelongs($groupId)) {
                $moveRow(
                    'cms_recipe_ingredient_groups',
                    (int)$groupId,
                    'recipe_id = ?',
                    [$recipeId],
                    $direction
                );
            }
        } elseif ($action === 'move_ingredient') {
            $ingredientId = inputInt('post', 'ingredient_id');
            $ingredient = $ingredientForRecipe($ingredientId);
            if ($ingredient !== null) {
                $moveRow(
                    'cms_recipe_ingredients',
                    (int)$ingredient['id'],
                    'recipe_id = ? AND group_id = ?',
                    [$recipeId, (int)$ingredient['group_id']],
                    $direction
                );
            }
        } else {
            $stepId = inputInt('post', 'step_id');
            if ($stepForRecipe($stepId) !== null) {
                $moveRow(
                    'cms_recipe_steps',
                    (int)$stepId,
                    'recipe_id = ?',
                    [$recipeId],
                    $direction
                );
            }
        }
        $redirectToContent('moved');
    }
}

$editGroupId = inputInt('get', 'edit_group');
if ($editGroupId !== null && $groupBelongs($editGroupId)) {
    $stmt = $pdo->prepare('SELECT id, title FROM cms_recipe_ingredient_groups WHERE id = ? AND recipe_id = ?');
    $stmt->execute([$editGroupId, $recipeId]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        $groupState = $row;
    }
}
$editIngredientId = inputInt('get', 'edit_ingredient');
if ($editIngredientId !== null) {
    $row = $ingredientForRecipe($editIngredientId);
    if ($row !== null) {
        $ingredientState = $row;
    }
}
$editStepId = inputInt('get', 'edit_step');
if ($editStepId !== null) {
    $row = $stepForRecipe($editStepId);
    if ($row !== null) {
        $stepState = $row;
    }
}

$structure = recipeLoadStructure($pdo, $recipeId);
$mediaRows = $pdo->query(
    "SELECT id, original_name, alt_text
     FROM cms_media
     WHERE visibility = 'public' AND mime_type LIKE 'image/%' AND mime_type != 'image/svg+xml'
     ORDER BY created_at DESC, id DESC
     LIMIT 500"
)->fetchAll();
$message = match (trim((string)($_GET['msg'] ?? ''))) {
    'saved' => 'Obsah receptu byl uložen.',
    'deleted' => 'Vybraná část receptu byla smazána.',
    'moved' => 'Pořadí bylo změněno.',
    default => '',
};

adminHeader('Ingredience a postup: ' . (string)$recipe['title']);
?>
<p class="button-row button-row--start">
  <a href="recipe_form.php?id=<?= $recipeId ?>"><span aria-hidden="true">←</span> Základní údaje receptu</a>
  <a href="recipes.php">Přehled receptů</a>
</p>

<?php if ($contentLockWarning !== null): ?>
  <p class="warning" role="status">
    Tento recept právě upravuje <?= h((string)$contentLockWarning['locked_by']) ?>.
    Před uložením se domluvte, aby se změny navzájem nepřepsaly.
  </p>
<?php endif; ?>
<?php if ($message !== ''): ?><p class="success" role="status"><?= h($message) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert" id="recipe-content-error"><?= h($error) ?></p><?php endif; ?>

<section aria-labelledby="recipe-groups-title">
  <h2 id="recipe-groups-title">Skupiny ingrediencí</h2>
  <p class="admin-description">Skupina může být například Těsto, Náplň nebo Na dokončení. Prázdný název vytvoří obecnou skupinu Ingredience.</p>

  <form method="post" novalidate<?= $error !== '' ? ' aria-describedby="recipe-content-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="recipe_id" value="<?= $recipeId ?>">
    <input type="hidden" name="action" value="save_group">
    <?php if ((int)$groupState['id'] > 0): ?><input type="hidden" name="group_id" value="<?= (int)$groupState['id'] ?>"><?php endif; ?>
    <fieldset>
      <legend><?= (int)$groupState['id'] > 0 ? 'Upravit skupinu' : 'Přidat skupinu' ?></legend>
      <label for="group_title">Název skupiny, volitelné</label>
      <input type="text" id="group_title" name="group_title" maxlength="255" value="<?= h((string)$groupState['title']) ?>">
      <div class="button-row">
        <button type="submit" class="btn"><?= (int)$groupState['id'] > 0 ? 'Uložit skupinu' : 'Přidat skupinu' ?></button>
        <?php if ((int)$groupState['id'] > 0): ?><a href="recipe_content.php?id=<?= $recipeId ?>">Zrušit úpravu</a><?php endif; ?>
      </div>
    </fieldset>
  </form>

  <?php if ($structure['groups'] !== []): ?>
    <div class="table-responsive">
      <table>
        <caption>Pořadí skupin ingrediencí</caption>
        <thead><tr><th scope="col">Skupina</th><th scope="col">Ingredience</th><th scope="col">Akce</th></tr></thead>
        <tbody>
        <?php foreach ($structure['groups'] as $group): ?>
          <?php $groupId = (int)$group['id']; ?>
          <tr>
            <td><?= h(trim((string)$group['title']) !== '' ? (string)$group['title'] : 'Ingredience') ?></td>
            <td><?= count((array)($group['ingredients'] ?? [])) ?></td>
            <td>
              <a href="recipe_content.php?id=<?= $recipeId ?>&amp;edit_group=<?= $groupId ?>#group_title">Upravit</a>
              <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="recipe_id" value="<?= $recipeId ?>">
                <input type="hidden" name="action" value="move_group"><input type="hidden" name="group_id" value="<?= $groupId ?>">
                <button type="submit" name="direction" value="up" class="btn">Nahoru</button>
                <button type="submit" name="direction" value="down" class="btn">Dolů</button>
              </form>
              <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="recipe_id" value="<?= $recipeId ?>">
                <input type="hidden" name="action" value="delete_group"><input type="hidden" name="group_id" value="<?= $groupId ?>">
                <label class="admin-checkbox-label" for="confirm-group-delete-<?= $groupId ?>">
                  <input type="checkbox" id="confirm-group-delete-<?= $groupId ?>" name="confirm_action" value="1" required aria-required="true">
                  Potvrzuji smazání skupiny včetně jejích ingrediencí
                </label>
                <button type="submit" class="btn btn-danger">Smazat skupinu</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section aria-labelledby="recipe-ingredients-title">
  <h2 id="recipe-ingredients-title">Ingredience</h2>
  <?php if ($structure['groups'] === []): ?>
    <p class="empty-state">Nejprve přidejte alespoň jednu skupinu ingrediencí.</p>
  <?php else: ?>
    <form method="post" novalidate<?= $error !== '' ? ' aria-describedby="recipe-content-error"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="recipe_id" value="<?= $recipeId ?>">
      <input type="hidden" name="action" value="save_ingredient">
      <?php if ((int)$ingredientState['id'] > 0): ?><input type="hidden" name="ingredient_id" value="<?= (int)$ingredientState['id'] ?>"><?php endif; ?>
      <fieldset>
        <legend><?= (int)$ingredientState['id'] > 0 ? 'Upravit ingredienci' : 'Přidat ingredienci' ?></legend>
        <div class="form-grid">
          <div>
            <label for="ingredient_group">Skupina <span aria-hidden="true">*</span></label>
            <select id="ingredient_group" name="group_id" required aria-required="true"
                    <?= adminFieldAttributes('ingredient_group', $fieldErrors, [], []) ?>>
              <?php foreach ($structure['groups'] as $group): ?>
                <option value="<?= (int)$group['id'] ?>"<?= (int)$ingredientState['group_id'] === (int)$group['id'] ? ' selected' : '' ?>>
                  <?= h(trim((string)$group['title']) !== '' ? (string)$group['title'] : 'Ingredience') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php adminRenderFieldError('ingredient_group', $fieldErrors, [], $fieldErrorMessages['ingredient_group'] ?? ''); ?>
          </div>
          <div><label for="amount">Množství</label><input type="text" id="amount" name="amount" maxlength="40" value="<?= h((string)$ingredientState['amount']) ?>" placeholder="např. 250"></div>
          <div><label for="unit">Jednotka</label><input type="text" id="unit" name="unit" maxlength="40" value="<?= h((string)$ingredientState['unit']) ?>" placeholder="např. g"></div>
          <div>
            <label for="ingredient_name">Název <span aria-hidden="true">*</span></label>
            <input type="text" id="ingredient_name" name="ingredient_name" maxlength="255" required aria-required="true"
                   value="<?= h((string)$ingredientState['name']) ?>" <?= adminFieldAttributes('ingredient_name', $fieldErrors) ?>>
            <?php adminRenderFieldError('ingredient_name', $fieldErrors, [], $fieldErrorMessages['ingredient_name'] ?? ''); ?>
          </div>
          <div><label for="ingredient_note">Poznámka</label><input type="text" id="ingredient_note" name="ingredient_note" maxlength="255" value="<?= h((string)$ingredientState['note']) ?>" placeholder="např. pokojové teploty"></div>
        </div>
        <label class="admin-checkbox-label"><input type="checkbox" name="is_optional" value="1"<?= (int)$ingredientState['is_optional'] === 1 ? ' checked' : '' ?>> Volitelná ingredience</label>
        <div class="button-row">
          <button type="submit" class="btn"><?= (int)$ingredientState['id'] > 0 ? 'Uložit ingredienci' : 'Přidat ingredienci' ?></button>
          <?php if ((int)$ingredientState['id'] > 0): ?><a href="recipe_content.php?id=<?= $recipeId ?>#recipe-ingredients-title">Zrušit úpravu</a><?php endif; ?>
        </div>
      </fieldset>
    </form>

    <?php foreach ($structure['groups'] as $group): ?>
      <section aria-labelledby="recipe-group-<?= (int)$group['id'] ?>">
        <h3 id="recipe-group-<?= (int)$group['id'] ?>"><?= h(trim((string)$group['title']) !== '' ? (string)$group['title'] : 'Ingredience') ?></h3>
        <?php if (empty($group['ingredients'])): ?>
          <p class="empty-state">V této skupině zatím nejsou ingredience.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table>
              <caption>Ingredience ve skupině <?= h(trim((string)$group['title']) !== '' ? (string)$group['title'] : 'Ingredience') ?></caption>
              <thead><tr><th scope="col">Množství</th><th scope="col">Ingredience</th><th scope="col">Akce</th></tr></thead>
              <tbody>
              <?php foreach ($group['ingredients'] as $ingredient): ?>
                <?php $ingredientId = (int)$ingredient['id']; ?>
                <tr>
                  <td><?= h(trim((string)$ingredient['amount'] . ' ' . (string)$ingredient['unit'])) ?: 'Neuvedeno' ?></td>
                  <td>
                    <strong><?= h((string)$ingredient['name']) ?></strong>
                    <?php if (trim((string)$ingredient['note']) !== ''): ?>, <?= h((string)$ingredient['note']) ?><?php endif; ?>
                    <?php if ((int)$ingredient['is_optional'] === 1): ?> <small>(volitelné)</small><?php endif; ?>
                  </td>
                  <td>
                    <a href="recipe_content.php?id=<?= $recipeId ?>&amp;edit_ingredient=<?= $ingredientId ?>#ingredient_group">Upravit</a>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="recipe_id" value="<?= $recipeId ?>">
                      <input type="hidden" name="action" value="move_ingredient"><input type="hidden" name="ingredient_id" value="<?= $ingredientId ?>">
                      <button type="submit" name="direction" value="up" class="btn">Nahoru</button>
                      <button type="submit" name="direction" value="down" class="btn">Dolů</button>
                    </form>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="recipe_id" value="<?= $recipeId ?>">
                      <input type="hidden" name="action" value="delete_ingredient"><input type="hidden" name="ingredient_id" value="<?= $ingredientId ?>">
                      <label class="admin-checkbox-label" for="confirm-ingredient-delete-<?= $ingredientId ?>"><input type="checkbox" id="confirm-ingredient-delete-<?= $ingredientId ?>" name="confirm_action" value="1" required aria-required="true"> Potvrzuji smazání ingredience <?= h((string)$ingredient['name']) ?></label>
                      <button type="submit" class="btn btn-danger">Smazat</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section aria-labelledby="recipe-steps-title">
  <h2 id="recipe-steps-title">Postup</h2>
  <form method="post" novalidate<?= $error !== '' ? ' aria-describedby="recipe-content-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="recipe_id" value="<?= $recipeId ?>">
    <input type="hidden" name="action" value="save_step">
    <?php if ((int)$stepState['id'] > 0): ?><input type="hidden" name="step_id" value="<?= (int)$stepState['id'] ?>"><?php endif; ?>
    <fieldset>
      <legend><?= (int)$stepState['id'] > 0 ? 'Upravit krok' : 'Přidat krok' ?></legend>
      <label for="step_title">Krátký název kroku, volitelné</label>
      <input type="text" id="step_title" name="step_title" maxlength="255" value="<?= h((string)$stepState['title']) ?>">
      <label for="instruction">Instrukce <span aria-hidden="true">*</span></label>
      <textarea id="instruction" name="instruction" rows="5" required aria-required="true"
                <?= adminFieldAttributes('instruction', $fieldErrors) ?>><?= h((string)$stepState['instruction']) ?></textarea>
      <?php adminRenderFieldError('instruction', $fieldErrors, [], $fieldErrorMessages['instruction'] ?? ''); ?>
      <label for="step_media_id">Obrázek kroku</label>
      <select id="step_media_id" name="media_id" <?= adminFieldAttributes('step_media_id', $fieldErrors, [], ['step-media-help']) ?>>
        <option value="">Bez obrázku</option>
        <?php foreach ($mediaRows as $media): ?>
          <option value="<?= (int)$media['id'] ?>"<?= (int)$stepState['media_id'] === (int)$media['id'] ? ' selected' : '' ?>><?= h((string)($media['original_name'] ?: ('Médium #' . $media['id']))) ?></option>
        <?php endforeach; ?>
      </select>
      <small id="step-media-help" class="field-help">Volitelný veřejný rastrový obrázek z knihovny médií.</small>
      <?php adminRenderFieldError('step_media_id', $fieldErrors, [], $fieldErrorMessages['step_media_id'] ?? ''); ?>
      <label for="step_image_alt_text">Alternativní text obrázku kroku</label>
      <input type="text" id="step_image_alt_text" name="image_alt_text" maxlength="255" value="<?= h((string)$stepState['image_alt_text']) ?>">
      <div class="button-row">
        <button type="submit" class="btn"><?= (int)$stepState['id'] > 0 ? 'Uložit krok' : 'Přidat krok' ?></button>
        <?php if ((int)$stepState['id'] > 0): ?><a href="recipe_content.php?id=<?= $recipeId ?>#recipe-steps-title">Zrušit úpravu</a><?php endif; ?>
      </div>
    </fieldset>
  </form>

  <?php if ($structure['steps'] === []): ?>
    <p class="empty-state">Postup zatím nemá žádný krok.</p>
  <?php else: ?>
    <ol class="admin-sort-list">
    <?php foreach ($structure['steps'] as $step): ?>
      <?php $stepId = (int)$step['id']; ?>
      <li class="admin-sort-item">
        <div class="admin-sort-item__body">
          <?php if (trim((string)$step['title']) !== ''): ?><strong><?= h((string)$step['title']) ?></strong><br><?php endif; ?>
          <?= nl2br(h((string)$step['instruction'])) ?>
          <?php if ($step['media_id'] !== null): ?><br><small class="table-meta">Obrázek kroku připojen</small><?php endif; ?>
        </div>
        <div class="button-row">
          <a href="recipe_content.php?id=<?= $recipeId ?>&amp;edit_step=<?= $stepId ?>#step_title">Upravit</a>
          <form method="post" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="recipe_id" value="<?= $recipeId ?>">
            <input type="hidden" name="action" value="move_step"><input type="hidden" name="step_id" value="<?= $stepId ?>">
            <button type="submit" name="direction" value="up" class="btn">Nahoru</button>
            <button type="submit" name="direction" value="down" class="btn">Dolů</button>
          </form>
          <form method="post" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="recipe_id" value="<?= $recipeId ?>">
            <input type="hidden" name="action" value="delete_step"><input type="hidden" name="step_id" value="<?= $stepId ?>">
            <label class="admin-checkbox-label" for="confirm-step-delete-<?= $stepId ?>"><input type="checkbox" id="confirm-step-delete-<?= $stepId ?>" name="confirm_action" value="1" required aria-required="true"> Potvrzuji smazání tohoto kroku</label>
            <button type="submit" class="btn btn-danger">Smazat</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>

<p><a class="btn" href="recipe_form.php?id=<?= $recipeId ?>">Dokončit v základních údajích a zveřejnit</a></p>

<?php adminRenderContentLockRefreshScript('recipe', $recipeId); ?>
<?php adminFooter(); ?>
