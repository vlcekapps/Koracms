<?php
$entries = is_array($entries ?? null) ? $entries : [];
$message = trim((string)($message ?? ''));
?>
<div class="article-shell" aria-labelledby="recipe-shopping-title">
  <header class="article-shell__header">
    <p class="section-kicker">Recepty</p>
    <h1 id="recipe-shopping-title" class="article-shell__title">Nákupní seznam</h1>
    <p class="article-shell__lead">
      Suroviny zůstávají rozdělené podle receptů, aby CMS neslučoval podobné názvy ani neodhadoval převody jednotek.
    </p>
  </header>

  <?php if ($message !== ''): ?><p class="success" role="status"><?= h($message) ?></p><?php endif; ?>

  <?php if ($entries === []): ?>
    <p class="empty-state">Nákupní seznam je prázdný. Přidejte do něj recept z jeho veřejného detailu.</p>
  <?php else: ?>
    <?php foreach ($entries as $entry): ?>
      <?php
      $recipe = is_array($entry['recipe'] ?? null) ? $entry['recipe'] : [];
        $structure = is_array($entry['structure'] ?? null) ? $entry['structure'] : ['groups' => []];
        $servings = (int)($entry['servings'] ?? 1);
        $baseServings = recipeNullablePositiveInt($recipe['servings'] ?? null);
        $recipeId = (int)($recipe['id'] ?? 0);
        $titleId = 'shopping-recipe-' . $recipeId;
        ?>
      <article class="surface" aria-labelledby="<?= h($titleId) ?>">
        <h2 id="<?= h($titleId) ?>"><a href="<?= h(recipePublicPath($recipe)) ?>"><?= h((string)$recipe['title']) ?></a></h2>
        <form method="post" class="button-row button-row--start">
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="recipe_id" value="<?= $recipeId ?>">
          <div>
            <label for="shopping-servings-<?= $recipeId ?>">Porce</label>
            <input type="number" id="shopping-servings-<?= $recipeId ?>" name="servings"
                   min="1" max="100" value="<?= $servings ?>"
                   aria-describedby="shopping-servings-help-<?= $recipeId ?>">
          </div>
          <button type="submit" class="button-secondary">Přepočítat tento recept</button>
        </form>
        <p id="shopping-servings-help-<?= $recipeId ?>" class="field-help">
          Přepočítají se jen přesně číselně zadané suroviny. Textové množství zůstane beze změny.
        </p>

        <?php foreach (($structure['groups'] ?? []) as $group): ?>
          <?php $groupTitle = trim((string)($group['title'] ?? '')); ?>
          <?php if ($groupTitle !== ''): ?><h3><?= h($groupTitle) ?></h3><?php endif; ?>
          <ul class="recipe-ingredient-list recipe-shopping-list">
          <?php foreach (($group['ingredients'] ?? []) as $ingredient): ?>
            <?php
                if (!is_array($ingredient)) {
                    continue;
                }
              $ingredientId = 'shopping-item-' . $recipeId . '-' . (int)($ingredient['id'] ?? 0);
              $amount = recipeIngredientAmountLabel($ingredient, $baseServings, $servings);
              ?>
            <li>
              <input type="checkbox" id="<?= h($ingredientId) ?>">
              <label for="<?= h($ingredientId) ?>">
                <?php if ($amount !== ''): ?><strong><?= h($amount) ?></strong> <?php endif; ?>
                <?= h((string)$ingredient['name']) ?>
                <?php if (trim((string)$ingredient['note']) !== ''): ?>, <?= h((string)$ingredient['note']) ?><?php endif; ?>
                <?php if ((int)$ingredient['is_optional'] === 1): ?> (volitelné)<?php endif; ?>
              </label>
            </li>
          <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>

        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="action" value="remove">
          <input type="hidden" name="recipe_id" value="<?= $recipeId ?>">
          <button type="submit" class="button-secondary">Odebrat recept ze seznamu</button>
        </form>
      </article>
    <?php endforeach; ?>

    <div class="article-actions">
      <button type="button" class="button-secondary js-print-page">Vytisknout nákupní seznam</button>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="clear">
        <label for="confirm-shopping-clear">
          <input type="checkbox" id="confirm-shopping-clear" name="confirm_action" value="1" required aria-required="true">
          Potvrzuji vyprázdnění celého nákupního seznamu
        </label>
        <button type="submit" class="button-secondary">Vyprázdnit seznam</button>
      </form>
    </div>
  <?php endif; ?>

  <p><a class="button-secondary" href="<?= BASE_URL ?>/recipes/index.php"><span aria-hidden="true">←</span> Zpět na recepty</a></p>
</div>
