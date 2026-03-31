<?php
$items = $items ?? [];
$grouped = $grouped ?? [];
$showCategoryHeadings = !empty($showCategoryHeadings);
$searchQuery = (string)($searchQuery ?? '');
$categories = $categories ?? [];
$selectedCategoryId = $selectedCategoryId ?? null;
$selectedType = (string)($selectedType ?? 'all');
$selectedPlatform = (string)($selectedPlatform ?? '');
$selectedSource = (string)($selectedSource ?? 'all');
$featuredOnly = !empty($featuredOnly);
$platformOptions = $platformOptions ?? [];
$filterSummary = $filterSummary ?? [];
$filterSummaryLabel = implode(', ', $filterSummary);
$resultCountLabel = (string)($resultCountLabel ?? '');
$pagerHtml = (string)($pagerHtml ?? '');
$hasActiveFilters = !empty($hasActiveFilters);
$clearUrl = (string)($clearUrl ?? (BASE_URL . '/downloads/index.php'));
$hasItems = !empty($items);
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="downloads-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Dokumenty, software a materiály</p>
        <h1 id="downloads-title" class="section-title section-title--hero">Ke stažení</h1>
      </div>
    </div>

    <form class="filter-bar filter-bar--stack" action="<?= BASE_URL ?>/downloads/index.php" method="get">
      <fieldset class="filter-bar__fieldset">
        <legend class="filter-bar__legend">Filtrovat položky ke stažení</legend>

        <div class="filter-grid">
          <div class="form-group">
            <label for="downloads-search-q">Hledat v položkách ke stažení</label>
            <input
              id="downloads-search-q"
              class="form-control"
              type="search"
              name="q"
              value="<?= h($searchQuery) ?>"
              placeholder="Název, popis, platforma nebo požadavky"
            >
          </div>

          <div class="form-group">
            <label for="downloads-filter-category">Kategorie</label>
            <select id="downloads-filter-category" class="form-control" name="kat">
              <option value="">Všechny kategorie</option>
              <?php foreach ($categories as $category): ?>
                <?php $categoryId = (int)($category['id'] ?? 0); ?>
                <option value="<?= $categoryId ?>" <?= $selectedCategoryId !== null && (int)$selectedCategoryId === $categoryId ? 'selected' : '' ?>>
                  <?= h((string)($category['name'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="downloads-filter-type">Typ</label>
            <select id="downloads-filter-type" class="form-control" name="typ">
              <option value="all">Všechny typy</option>
              <?php foreach (downloadTypeDefinitions() as $typeKey => $typeMeta): ?>
                <option value="<?= h($typeKey) ?>" <?= $selectedType === $typeKey ? 'selected' : '' ?>>
                  <?= h((string)($typeMeta['label'] ?? $typeKey)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="downloads-filter-platform">Platforma</label>
            <select id="downloads-filter-platform" class="form-control" name="platform">
              <option value="">Všechny platformy</option>
              <?php foreach ($platformOptions as $platformOption): ?>
                <option value="<?= h((string)$platformOption) ?>" <?= $selectedPlatform === (string)$platformOption ? 'selected' : '' ?>>
                  <?= h((string)$platformOption) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="downloads-filter-source">Zdroj</label>
            <select id="downloads-filter-source" class="form-control" name="source">
              <option value="all"<?= $selectedSource === 'all' ? ' selected' : '' ?>>Vše</option>
              <option value="local"<?= $selectedSource === 'local' ? ' selected' : '' ?>>Jen lokální soubor</option>
              <option value="external"<?= $selectedSource === 'external' ? ' selected' : '' ?>>Jen externí odkaz</option>
              <option value="hybrid"<?= $selectedSource === 'hybrid' ? ' selected' : '' ?>>Soubor i externí odkaz</option>
            </select>
          </div>
        </div>

        <label class="filter-option-toggle">
          <input type="checkbox" name="featured" value="1" <?= $featuredOnly ? 'checked' : '' ?>>
          Jen doporučené položky
        </label>

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

    <?php if (!$hasItems): ?>
      <p class="empty-state">
        <?= h($hasActiveFilters ? 'Pro zadaný filtr se nenašla žádná položka ke stažení.' : 'Zatím tu nejsou žádné materiály ke stažení.') ?>
      </p>
    <?php else: ?>
      <div class="stack-sections">
        <?php $groupIndex = 0; foreach ($grouped as $category => $files): ?>
          <section aria-labelledby="downloads-group-<?= $groupIndex ?>">
            <?php if ($showCategoryHeadings): ?>
              <h2 id="downloads-group-<?= $groupIndex ?>" class="section-title section-title--compact"><?= h($category) ?></h2>
            <?php else: ?>
              <h2 id="downloads-group-<?= $groupIndex ?>" class="sr-only">Položky ke stažení</h2>
            <?php endif; ?>

            <div class="card-grid card-grid--compact">
              <?php foreach ($files as $download): ?>
                <article class="card card--rich">
                  <?php if ($download['image_url'] !== ''): ?>
                    <a class="card__media" href="<?= h(downloadPublicPath($download)) ?>">
                      <img src="<?= h((string)$download['image_url']) ?>" alt="">
                    </a>
                  <?php endif; ?>

                  <div class="card__body">
                    <p class="card__eyebrow"><?= h((string)$download['download_type_label']) ?></p>
                    <h3 class="card__title">
                      <a href="<?= h(downloadPublicPath($download)) ?>"><?= h((string)$download['title']) ?></a>
                    </h3>

                    <p class="meta-row meta-row--tight">
                      <?php if ((int)$download['is_featured'] === 1): ?>
                        <span>Doporučené</span>
                      <?php endif; ?>
                      <?php if ($download['version_label'] !== ''): ?>
                        <span>Verze <?= h((string)$download['version_label']) ?></span>
                      <?php endif; ?>
                      <?php if ($download['release_date_label'] !== ''): ?>
                        <span>Vydáno <?= h((string)$download['release_date_label']) ?></span>
                      <?php endif; ?>
                      <?php if ($download['platform_label'] !== ''): ?>
                        <span><?= h((string)$download['platform_label']) ?></span>
                      <?php endif; ?>
                      <?php if ((int)$download['file_size'] > 0): ?>
                        <span><?= h(formatFileSize((int)$download['file_size'])) ?></span>
                      <?php endif; ?>
                    </p>

                    <?php if ($download['excerpt_plain'] !== ''): ?>
                      <p class="card__description"><?= h((string)$download['excerpt_plain']) ?></p>
                    <?php endif; ?>

                    <div class="card__actions">
                      <a class="section-link" href="<?= h(downloadPublicPath($download)) ?>">Zobrazit položku <span aria-hidden="true">→</span></a>
                      <?php if ($download['has_file']): ?>
                        <a class="section-link" href="<?= moduleFileUrl('downloads', (int)$download['id']) ?>"
                           download="<?= h((string)$download['original_name']) ?>">Stáhnout soubor <span aria-hidden="true">→</span></a>
                      <?php elseif ($download['has_external_url']): ?>
                        <a class="section-link" href="<?= h((string)$download['external_url']) ?>" target="_blank" rel="noopener noreferrer">Otevřít odkaz <span aria-hidden="true">→</span></a>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php $groupIndex++; endforeach; ?>
      </div>

      <?php if ($pagerHtml !== ''): ?>
        <div class="listing-shell__pager">
          <?= $pagerHtml ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
