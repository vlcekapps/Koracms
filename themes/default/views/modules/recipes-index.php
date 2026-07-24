<?php
$recipes = is_array($recipes ?? null) ? $recipes : [];
$categories = is_array($categories ?? null) ? $categories : [];
$activeCategory = is_array($activeCategory ?? null) ? $activeCategory : null;
$query = (string)($query ?? '');
$difficulty = (string)($difficulty ?? '');
$dietaryFlags = is_array($dietaryFlags ?? null) ? $dietaryFlags : [];
$excludedAllergens = is_array($excludedAllergens ?? null) ? $excludedAllergens : [];
$maxTotalMinutes = isset($maxTotalMinutes) ? (int)$maxTotalMinutes : null;
$heading = (string)($heading ?? 'Recepty');
$intro = trim((string)($intro ?? ''));
$filterSummary = is_array($filterSummary ?? null) ? $filterSummary : [];
$total = (int)($total ?? count($recipes));
$pagerHtml = (string)($pagerHtml ?? '');
$clearUrl = (string)($clearUrl ?? (BASE_URL . '/recipes/index.php'));
$cookbookUrl = (string)($cookbookUrl ?? recipeCookbookPublicPath());
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="recipes-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker"><?= $activeCategory !== null ? 'Kategorie receptů' : 'Kuchařka webu' ?></p>
        <h1 id="recipes-title" class="section-title section-title--hero"><?= h($heading) ?></h1>
        <?php if ($intro !== ''): ?><p class="section-lead"><?= h(normalizePlainText($intro)) ?></p><?php endif; ?>
      </div>
    </div>

    <?php if ($activeCategory !== null): ?>
      <h2 id="recipe-category-breadcrumb-title" class="sr-only">Drobečková navigace</h2>
      <nav aria-labelledby="recipe-category-breadcrumb-title">
        <ol class="breadcrumbs">
          <li><a href="<?= BASE_URL ?>/recipes/index.php">Recepty</a></li>
          <li><span aria-current="page"><?= h((string)$activeCategory['name']) ?></span></li>
        </ol>
      </nav>
    <?php endif; ?>

    <form method="get" action="<?= BASE_URL ?>/recipes/index.php" class="filter-bar filter-bar--stack"
          role="search" aria-labelledby="recipe-filter-legend">
      <fieldset class="filter-bar__fieldset">
        <legend id="recipe-filter-legend" class="filter-bar__legend">Filtrovat recepty</legend>
        <div class="filter-grid">
          <div class="form-group">
            <label for="recipe-search">Hledat v receptech</label>
            <input type="search" id="recipe-search" name="q" class="form-control"
                   value="<?= h($query) ?>" placeholder="Název, ingredience nebo poznámka">
          </div>
          <div class="form-group">
            <label for="recipe-category-filter">Kategorie</label>
            <select id="recipe-category-filter" name="category" class="form-control">
              <option value="">Všechny kategorie</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>"<?= $activeCategory !== null && (int)$activeCategory['id'] === (int)$category['id'] ? ' selected' : '' ?>>
                  <?= h((string)$category['name']) ?> (<?= (int)$category['public_count'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="recipe-difficulty">Náročnost</label>
            <select id="recipe-difficulty" name="obtiznost" class="form-control">
              <option value="">Všechny úrovně</option>
              <?php foreach (recipeDifficultyDefinitions() as $difficultyKey => $difficultyLabel): ?>
                <option value="<?= h($difficultyKey) ?>"<?= $difficulty === $difficultyKey ? ' selected' : '' ?>><?= h($difficultyLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="recipe-max-time">Celkový čas nejvýše, minuty</label>
            <input type="number" id="recipe-max-time" name="cas_max" class="form-control"
                   min="1" max="1440" value="<?= $maxTotalMinutes ?? '' ?>"
                   aria-describedby="recipe-max-time-help">
            <small id="recipe-max-time-help" class="field-help">Při aktivním filtru se recepty bez uvedeného celkového času nezobrazí.</small>
          </div>
        </div>
        <fieldset class="filter-bar__fieldset">
          <legend>Dietní vlastnosti, musí platit všechny vybrané</legend>
          <div class="checkbox-grid">
            <?php foreach (recipeDietaryFlagDefinitions() as $flagKey => $flagLabel): ?>
              <?php $filterId = 'recipe-diet-' . $flagKey; ?>
              <label for="<?= h($filterId) ?>">
                <input type="checkbox" id="<?= h($filterId) ?>" name="dieta[]" value="<?= h($flagKey) ?>"<?= in_array($flagKey, $dietaryFlags, true) ? ' checked' : '' ?>>
                <?= h($flagLabel) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <fieldset class="filter-bar__fieldset">
          <legend>Vyloučit recepty obsahující alergeny</legend>
          <div class="checkbox-grid">
            <?php foreach (recipeAllergenDefinitions() as $allergenKey => $allergenLabel): ?>
              <?php
              $allergenValue = (string)$allergenKey;
                $filterId = 'recipe-allergen-' . $allergenValue;
                ?>
              <label for="<?= h($filterId) ?>">
                <input type="checkbox" id="<?= h($filterId) ?>" name="bez_alergenu[]"
                       value="<?= h($allergenValue) ?>"<?= in_array($allergenValue, $excludedAllergens, true) ? ' checked' : '' ?>>
                <?= h($allergenValue . '. ' . $allergenLabel) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <div class="button-row button-row--start">
          <button type="submit" class="button-primary">Použít filtr</button>
          <?php if ($filterSummary !== []): ?><a href="<?= h($clearUrl) ?>" class="button-secondary">Zrušit filtr</a><?php endif; ?>
          <a href="<?= h($cookbookUrl) ?>" class="button-secondary">Stáhnout kuchařku EPUB</a>
        </div>
      </fieldset>
    </form>

    <?php if ($filterSummary !== []): ?>
      <p class="meta-row"><strong>Aktivní filtry:</strong> <?= h(implode(', ', $filterSummary)) ?></p>
    <?php endif; ?>
    <p class="meta-row"><?= $total ?> <?= $total === 1 ? 'recept' : ($total >= 2 && $total <= 4 ? 'recepty' : 'receptů') ?></p>

    <?php if ($recipes === []): ?>
      <p class="empty-state">Pro zvolený filtr tu nejsou žádné veřejné recepty.</p>
    <?php else: ?>
      <div class="card-grid">
      <?php foreach ($recipes as $recipe): ?>
        <?php
        $titleId = 'recipe-card-title-' . (int)$recipe['id'];
          $recipeMetadata = [];
          if ((int)$recipe['servings'] > 0) {
              $recipeMetadata[] = (int)$recipe['servings'] . ' porcí';
          }
          if (recipeTotalMinutes($recipe) !== null) {
              $recipeMetadata[] = 'Celkem ' . recipeDurationLabel(recipeTotalMinutes($recipe));
          }
          if ((string)$recipe['difficulty'] !== '') {
              $difficultyLabel = recipeDifficultyDefinitions()[(string)$recipe['difficulty']] ?? '';
              if ($difficultyLabel !== '') {
                  $recipeMetadata[] = $difficultyLabel;
              }
          }
          ?>
        <article class="card card--rich" aria-labelledby="<?= h($titleId) ?>">
          <?php if ((string)$recipe['image_url'] !== ''): ?>
            <img class="card__image" src="<?= h((string)$recipe['image_url']) ?>" alt="<?= h((string)$recipe['image_alt']) ?>" loading="lazy">
          <?php endif; ?>
          <div class="card__body">
            <p class="card__eyebrow">
              <a href="<?= h(recipeCategoryPublicPath((string)$recipe['category_slug'])) ?>"><?= h((string)$recipe['category_name']) ?></a>
            </p>
            <h2 id="<?= h($titleId) ?>" class="card__title"><a href="<?= h(recipePublicPath($recipe)) ?>"><?= h((string)$recipe['title']) ?></a></h2>
            <p class="card__description"><?= h((string)$recipe['summary']) ?></p>
            <?php if ($recipeMetadata !== []): ?><p class="meta-row"><?= h(implode(', ', $recipeMetadata)) ?></p><?php endif; ?>
            <?php if ($recipe['dietary_labels'] !== []): ?>
              <ul class="chip-list" aria-label="Dietní vlastnosti">
                <?php foreach ($recipe['dietary_labels'] as $label): ?><li><?= h((string)$label) ?></li><?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <div class="card__actions"><a class="section-link" href="<?= h(recipePublicPath($recipe)) ?>">Zobrazit recept <span aria-hidden="true">→</span></a></div>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
      <?php if ($pagerHtml !== ''): ?><div class="listing-shell__pager"><?= $pagerHtml ?></div><?php endif; ?>
    <?php endif; ?>
  </section>
</div>
