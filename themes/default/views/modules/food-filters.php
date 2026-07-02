<?php
$foodFilterAction = (string)($foodFilterAction ?? (BASE_URL . '/food/archive.php'));
$foodFilterClearUrl = (string)($foodFilterClearUrl ?? $foodFilterAction);
$foodFilters = is_array($foodFilters ?? null) ? $foodFilters : normalizeFoodStructuredFilters([]);
$foodFilterHiddenFields = is_array($foodFilterHiddenFields ?? null) ? $foodFilterHiddenFields : [];
$foodFilterSummary = foodStructuredFilterSummary($foodFilters);
$foodFilterTitleId = (string)($foodFilterTitleId ?? 'food-structured-filters-title');
$foodFilterDescriptionId = $foodFilterTitleId . '-description';
$dietaryDefinitions = foodDietaryFlagDefinitions();
$allergenDefinitions = foodAllergenDefinitions();
?>
<form class="filter-bar filter-bar--stack food-structured-filters" action="<?= h($foodFilterAction) ?>" method="get" aria-labelledby="<?= h($foodFilterTitleId) ?>">
  <fieldset class="filter-bar__fieldset">
    <legend id="<?= h($foodFilterTitleId) ?>" class="filter-bar__legend">Filtrovat položky lístku</legend>
    <p id="<?= h($foodFilterDescriptionId) ?>" class="field-help field-help--flush">Filtr pracuje se strukturovanými položkami, dietními štítky a alergeny zadanými správcem.</p>

    <?php foreach ($foodFilterHiddenFields as $hiddenName => $hiddenValue): ?>
      <?php if ($hiddenValue !== null && $hiddenValue !== ''): ?>
        <input type="hidden" name="<?= h((string)$hiddenName) ?>" value="<?= h((string)$hiddenValue) ?>">
      <?php endif; ?>
    <?php endforeach; ?>

    <div class="food-filter-grid" aria-describedby="<?= h($foodFilterDescriptionId) ?>">
      <fieldset class="food-filter-group">
        <legend>Dietní štítky</legend>
        <div class="food-filter-options">
          <?php foreach ($dietaryDefinitions as $flagKey => $flagLabel): ?>
            <?php $dietaryInputId = $foodFilterTitleId . '-diet-' . (string)$flagKey; ?>
            <label for="<?= h($dietaryInputId) ?>">
              <input id="<?= h($dietaryInputId) ?>" type="checkbox" name="dieta[]" value="<?= h($flagKey) ?>"<?= in_array($flagKey, $foodFilters['dietary_flags'] ?? [], true) ? ' checked' : '' ?>>
              <?= h($flagLabel) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset class="food-filter-group">
        <legend>Zobrazit položky bez alergenů</legend>
        <div class="food-filter-options">
          <?php foreach ($allergenDefinitions as $allergenNumber => $allergenLabel): ?>
            <?php $allergenInputId = $foodFilterTitleId . '-allergen-' . (int)$allergenNumber; ?>
            <label for="<?= h($allergenInputId) ?>">
              <input id="<?= h($allergenInputId) ?>" type="checkbox" name="bez_alergenu[]" value="<?= (int)$allergenNumber ?>"<?= in_array((int)$allergenNumber, $foodFilters['excluded_allergens'] ?? [], true) ? ' checked' : '' ?>>
              <?= (int)$allergenNumber ?> - <?= h($allergenLabel) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset class="food-filter-group">
        <legend>Dostupnost</legend>
        <?php $availableInputId = $foodFilterTitleId . '-available'; ?>
        <label for="<?= h($availableInputId) ?>">
          <input id="<?= h($availableInputId) ?>" type="checkbox" name="pouze_dostupne" value="1"<?= !empty($foodFilters['available_only']) ? ' checked' : '' ?>>
          Pouze dostupné položky
        </label>
      </fieldset>
    </div>

    <div class="button-row button-row--start">
      <button class="button-primary" type="submit">Použít filtr položek</button>
      <?php if (foodStructuredFiltersAreActive($foodFilters)): ?>
        <a class="button-secondary" href="<?= h($foodFilterClearUrl) ?>">Zrušit filtr položek</a>
      <?php endif; ?>
    </div>
  </fieldset>
</form>

<?php if ($foodFilterSummary !== []): ?>
  <p class="meta-row meta-row--tight">
    <strong>Aktivní filtr položek:</strong>
    <span><?= h(implode(', ', $foodFilterSummary)) ?></span>
  </p>
<?php endif; ?>
