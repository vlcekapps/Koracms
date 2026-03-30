<?php
$places = $places ?? [];
$searchQuery = (string)($searchQuery ?? '');
$kindOptions = is_array($kindOptions ?? null) ? $kindOptions : placeKindOptions();
$selectedKind = (string)($selectedKind ?? 'all');
$categoryOptions = $categoryOptions ?? [];
$selectedCategory = (string)($selectedCategory ?? '');
$localityOptions = $localityOptions ?? [];
$selectedLocality = (string)($selectedLocality ?? '');
$filterSummary = $filterSummary ?? [];
$filterSummaryLabel = implode(', ', $filterSummary);
$resultCountLabel = (string)($resultCountLabel ?? '');
$pagerHtml = (string)($pagerHtml ?? '');
$hasActiveFilters = !empty($hasActiveFilters);
$clearUrl = (string)($clearUrl ?? (BASE_URL . '/places/index.php'));
$listingQuery = is_array($listingQuery ?? null) ? $listingQuery : [];
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="places-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Tipy na výlet a služby</p>
        <h1 id="places-title" class="section-title section-title--hero">Zajímavá místa</h1>
      </div>
    </div>

    <form class="filter-bar filter-bar--stack" action="<?= BASE_URL ?>/places/index.php" method="get">
      <fieldset class="filter-bar__fieldset">
        <legend class="filter-bar__legend">Filtrovat adresář míst</legend>

        <div class="filter-grid">
          <div class="form-group">
            <label for="places-search-q">Hledat v místech</label>
            <input
              id="places-search-q"
              class="form-control"
              type="search"
              name="q"
              value="<?= h($searchQuery) ?>"
              placeholder="Název, popis, adresa nebo lokalita"
            >
          </div>

          <div class="form-group">
            <label for="places-filter-kind">Typ místa</label>
            <select id="places-filter-kind" class="form-control" name="kind">
              <option value="all">Všechny typy</option>
              <?php foreach ($kindOptions as $kindKey => $kindMeta): ?>
                <option value="<?= h((string)$kindKey) ?>" <?= $selectedKind === (string)$kindKey ? 'selected' : '' ?>>
                  <?= h((string)($kindMeta['label'] ?? $kindKey)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="places-filter-category">Kategorie</label>
            <select id="places-filter-category" class="form-control" name="category">
              <option value="">Všechny kategorie</option>
              <?php foreach ($categoryOptions as $categoryOption): ?>
                <option value="<?= h((string)$categoryOption) ?>" <?= $selectedCategory === (string)$categoryOption ? 'selected' : '' ?>>
                  <?= h((string)$categoryOption) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="places-filter-locality">Lokalita</label>
            <select id="places-filter-locality" class="form-control" name="locality">
              <option value="">Všechny lokality</option>
              <?php foreach ($localityOptions as $localityOption): ?>
                <option value="<?= h((string)$localityOption) ?>" <?= $selectedLocality === (string)$localityOption ? 'selected' : '' ?>>
                  <?= h((string)$localityOption) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

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

    <?php if ($places === []): ?>
      <p class="empty-state">
        <?= h($hasActiveFilters ? 'Pro zvolený filtr se nenašla žádná místa.' : 'Zatím tu nejsou žádná zveřejněná místa.') ?>
      </p>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($places as $place): ?>
          <article class="card card--rich place-card">
            <?php if ((string)($place['image_url'] ?? '') !== ''): ?>
              <a class="card__media" href="<?= h(placePublicPath($place, $listingQuery)) ?>">
                <img src="<?= h((string)$place['image_url']) ?>" alt="" loading="lazy">
              </a>
            <?php endif; ?>

            <div class="card__body">
              <p class="meta-row meta-row--tight">
                <span class="pill"><?= h((string)($place['place_kind_label'] ?? 'Místo')) ?></span>
                <?php if (!empty($place['category'])): ?>
                  <span><?= h((string)$place['category']) ?></span>
                <?php endif; ?>
                <?php if (!empty($place['locality'])): ?>
                  <span><?= h((string)$place['locality']) ?></span>
                <?php endif; ?>
              </p>

              <h2 class="card__title">
                <a href="<?= h(placePublicPath($place, $listingQuery)) ?>"><?= h((string)$place['name']) ?></a>
              </h2>

              <?php if ((string)($place['excerpt_plain'] ?? '') !== ''): ?>
                <p class="card__description"><?= h((string)$place['excerpt_plain']) ?></p>
              <?php endif; ?>

              <?php if (!empty($place['full_address'])): ?>
                <p class="meta-row meta-row--tight"><span><?= h((string)$place['full_address']) ?></span></p>
              <?php endif; ?>

              <div class="card__actions">
                <a class="section-link" href="<?= h(placePublicPath($place, $listingQuery)) ?>">Zobrazit místo <span aria-hidden="true">→</span></a>
                <?php if ((string)($place['url'] ?? '') !== ''): ?>
                  <a class="section-link" href="<?= h((string)$place['url']) ?>" target="_blank" rel="noopener noreferrer">Navštívit web <span aria-hidden="true">→</span></a>
                <?php endif; ?>
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
