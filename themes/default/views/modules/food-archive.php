<?php
$cards = $cards ?? [];
$filterType = (string)($filterType ?? 'vse');
$searchQuery = (string)($searchQuery ?? '');
$scope = (string)($scope ?? 'all');
$scopeLinks = $scopeLinks ?? [];
$filterSummary = $filterSummary ?? [];
$filterSummaryLabel = implode(', ', $filterSummary);
$resultCountLabel = (string)($resultCountLabel ?? '');
$pagerHtml = (string)($pagerHtml ?? '');
$hasActiveFilters = !empty($hasActiveFilters);
$clearUrl = (string)($clearUrl ?? (BASE_URL . '/food/archive.php'));
$pageHeading = (string)($pageHeading ?? 'Archiv lístků');
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="food-archive-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Přehled lístků</p>
        <h1 id="food-archive-title" class="section-title section-title--hero"><?= h($pageHeading) ?></h1>
      </div>
    </div>

    <div class="button-row button-row--start">
      <a class="button-secondary" href="<?= BASE_URL ?>/food/index.php"><span aria-hidden="true">&larr;</span> Aktuální lístek</a>
    </div>

    <?php if ($scopeLinks !== []): ?>
      <nav class="tab-nav" aria-label="Rozsah výpisu lístků">
        <?php foreach ($scopeLinks as $scopeLink): ?>
          <a class="tab-nav__link<?= !empty($scopeLink['active']) ? ' is-active' : '' ?>"
             href="<?= h((string)$scopeLink['url']) ?>"
             <?= !empty($scopeLink['active']) ? 'aria-current="page"' : '' ?>>
            <?= h((string)$scopeLink['label']) ?>
            <span class="tab-nav__count"><?= h((string)($scopeLink['count'] ?? 0)) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <form class="filter-bar filter-bar--stack" action="<?= BASE_URL ?>/food/archive.php" method="get">
      <fieldset class="filter-bar__fieldset">
        <legend class="filter-bar__legend">Filtrovat lístky</legend>

        <div class="filter-grid">
          <div class="form-group">
            <label for="food-archive-q">Hledat v lístcích</label>
            <input
              id="food-archive-q"
              class="form-control"
              type="search"
              name="q"
              value="<?= h($searchQuery) ?>"
              placeholder="Název, poznámka nebo obsah lístku"
            >
          </div>

          <div class="form-group">
            <label for="food-archive-type">Typ lístku</label>
            <select id="food-archive-type" class="form-control" name="typ">
              <option value="vse"<?= $filterType === 'vse' ? ' selected' : '' ?>>Vše</option>
              <option value="food"<?= $filterType === 'food' ? ' selected' : '' ?>>Jídelní lístky</option>
              <option value="beverage"<?= $filterType === 'beverage' ? ' selected' : '' ?>>Nápojové lístky</option>
            </select>
          </div>
        </div>

        <input type="hidden" name="scope" value="<?= h($scope) ?>">

        <div class="button-row button-row--start">
          <button class="button-primary" type="submit">Použít filtr</button>
          <?php if ($hasActiveFilters): ?>
            <a class="button-secondary" href="<?= h($clearUrl) ?>">Zrušit filtr</a>
          <?php endif; ?>
        </div>
      </fieldset>
    </form>

    <?php if ($filterSummaryLabel !== ''): ?>
      <p class="meta-row meta-row--tight">
        <strong>Aktivní filtry:</strong>
        <span><?= h($filterSummaryLabel) ?></span>
      </p>
    <?php endif; ?>

    <?php if ($resultCountLabel !== ''): ?>
      <p class="meta-row meta-row--tight">
        <span><?= h($resultCountLabel) ?></span>
      </p>
    <?php endif; ?>

    <?php if (empty($cards)): ?>
      <p class="empty-state">
        <?= h($hasActiveFilters ? 'Pro zadaný filtr se nenašel žádný lístek.' : 'Zatím tu nejsou žádné zveřejněné lístky.') ?>
      </p>
    <?php else: ?>
      <div class="card-grid card-grid--compact">
        <?php foreach ($cards as $card): ?>
          <article class="card<?= (string)($card['state_key'] ?? '') === 'current' ? ' card--highlighted' : '' ?>">
            <div class="card__body">
              <p class="meta-row meta-row--tight">
                <span class="pill"><?= h((string)($card['type_label'] ?? 'Lístek')) ?></span>
                <span><?= h((string)($card['state_label'] ?? 'Platí nyní')) ?></span>
              </p>

              <h2 class="card__title">
                <a href="<?= h((string)($card['listing_path'] ?? $card['public_path'])) ?>"><?= h((string)$card['title']) ?></a>
              </h2>

              <p class="meta-row meta-row--tight"><?= h((string)$card['validity_label']) ?></p>

              <?php if (!empty($card['description'])): ?>
                <p><?= h((string)$card['description']) ?></p>
              <?php endif; ?>

              <div class="button-row button-row--start">
                <a class="button-secondary" href="<?= h((string)($card['listing_path'] ?? $card['public_path'])) ?>">Zobrazit lístek</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($pagerHtml !== ''): ?>
        <div class="listing-shell__pager">
          <?= $pagerHtml ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
