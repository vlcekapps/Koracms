<?php
$items = $items ?? [];
$scopeLinks = $scopeLinks ?? [];
$searchQuery = (string)($searchQuery ?? '');
$locationOptions = $locationOptions ?? [];
$selectedLocation = (string)($selectedLocation ?? '');
$selectedType = (string)($selectedType ?? 'all');
$selectedPeriod = (string)($selectedPeriod ?? 'all');
$periodOptions = $periodOptions ?? [];
$filterSummary = $filterSummary ?? [];
$filterSummaryLabel = implode(', ', $filterSummary);
$resultCountLabel = (string)($resultCountLabel ?? '');
$pagerHtml = (string)($pagerHtml ?? '');
$hasActiveFilters = !empty($hasActiveFilters);
$clearUrl = (string)($clearUrl ?? (BASE_URL . '/events/index.php'));
$pageHeading = (string)($pageHeading ?? 'Akce a události');
$listingQuery = is_array($listingQuery ?? null) ? $listingQuery : [];
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="events-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Program</p>
        <h1 id="events-title" class="section-title section-title--hero"><?= h($pageHeading) ?></h1>
      </div>
    </div>

    <?php if ($scopeLinks !== []): ?>
      <nav class="tab-nav" aria-label="Rozsah výpisu akcí">
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

    <form class="filter-bar filter-bar--stack" action="<?= BASE_URL ?>/events/index.php" method="get">
      <fieldset class="filter-bar__fieldset">
        <legend class="filter-bar__legend">Filtrovat akce</legend>

        <div class="filter-grid">
          <div class="form-group">
            <label for="events-search-q">Hledat v akcích</label>
            <input
              id="events-search-q"
              class="form-control"
              type="search"
              name="q"
              value="<?= h($searchQuery) ?>"
              placeholder="Název, shrnutí, pořadatel nebo místo"
            >
          </div>

          <div class="form-group">
            <label for="events-filter-location">Místo</label>
            <select id="events-filter-location" class="form-control" name="misto">
              <option value="">Všechna místa</option>
              <?php foreach ($locationOptions as $locationOption): ?>
                <option value="<?= h((string)$locationOption) ?>" <?= $selectedLocation === (string)$locationOption ? 'selected' : '' ?>>
                  <?= h((string)$locationOption) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="events-filter-type">Typ akce</label>
            <select id="events-filter-type" class="form-control" name="typ">
              <option value="all">Všechny typy</option>
              <?php foreach (eventKindDefinitions() as $typeKey => $typeMeta): ?>
                <option value="<?= h($typeKey) ?>" <?= $selectedType === $typeKey ? 'selected' : '' ?>>
                  <?= h((string)($typeMeta['label'] ?? $typeKey)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="events-filter-period">Období</label>
            <select id="events-filter-period" class="form-control" name="period">
              <?php foreach ($periodOptions as $periodKey => $periodLabel): ?>
                <option value="<?= h((string)$periodKey) ?>" <?= $selectedPeriod === (string)$periodKey ? 'selected' : '' ?>>
                  <?= h((string)$periodLabel) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <input type="hidden" name="scope" value="<?= h((string)($scope ?? 'upcoming')) ?>">

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

    <?php if ($items === []): ?>
      <p class="empty-state">
        <?= h($hasActiveFilters ? 'Pro zadaný filtr se nenašla žádná akce.' : 'Zatím tu nejsou žádné zveřejněné akce.') ?>
      </p>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($items as $event): ?>
          <article class="card card--rich">
            <?php if ((string)($event['image_url'] ?? '') !== ''): ?>
              <a class="card__media" href="<?= h(eventPublicPath($event, $listingQuery)) ?>">
                <img src="<?= h((string)$event['image_url']) ?>" alt="">
              </a>
            <?php endif; ?>

            <div class="card__body">
              <p class="card__eyebrow"><?= h((string)($event['event_kind_label'] ?? 'Akce')) ?></p>
              <h2 class="card__title">
                <a href="<?= h(eventPublicPath($event, $listingQuery)) ?>"><?= h((string)($event['title'] ?? '')) ?></a>
              </h2>

              <p class="meta-row meta-row--tight">
                <span class="pill"><?= h((string)($event['event_status_label'] ?? 'Připravujeme')) ?></span>
                <time datetime="<?= h(str_replace(' ', 'T', (string)($event['event_date'] ?? ''))) ?>">
                  <?= formatCzechDate((string)($event['event_date'] ?? '')) ?>
                </time>
                <?php if (!empty($event['event_end'])): ?>
                  <span>do <?= h(formatCzechDate((string)$event['event_end'])) ?></span>
                <?php endif; ?>
                <?php if ((string)($event['location'] ?? '') !== ''): ?>
                  <span><?= h((string)$event['location']) ?></span>
                <?php endif; ?>
              </p>

              <?php if ((string)($event['excerpt_plain'] ?? '') !== ''): ?>
                <p class="card__description"><?= h((string)$event['excerpt_plain']) ?></p>
              <?php endif; ?>

              <div class="card__actions">
                <a class="section-link" href="<?= h(eventPublicPath($event, $listingQuery)) ?>">Zobrazit detail <span aria-hidden="true">→</span></a>
                <a class="section-link" href="<?= h(eventIcsPath($event)) ?>">Přidat do kalendáře <span aria-hidden="true">→</span></a>
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
