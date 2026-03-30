<?php
$documentLink = static fn(array $document): string => boardPublicPath($document);
$boardLabel = $boardLabel ?? boardModulePublicLabel();
$emptyMessage = $emptyState ?? boardModuleListingEmptyState();
$filterSummaryLabel = implode(', ', $filterSummary ?? []);
$hasItems = !empty($items ?? []);
$scopeLinks = $scopeLinks ?? [];
$categories = $categories ?? [];
$monthOptions = $monthOptions ?? [];
$searchQuery = (string)($searchQuery ?? '');
$selectedCategoryId = $selectedCategoryId ?? null;
$selectedMonth = (string)($selectedMonth ?? '');
$clearUrl = (string)($clearUrl ?? (BASE_URL . '/board/index.php'));
$pageHeading = (string)($pageHeading ?? $boardLabel);
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="board-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker"><?= h(boardModuleSectionKicker()) ?></p>
        <h1 id="board-title" class="section-title section-title--hero"><?= h($pageHeading) ?></h1>
      </div>
    </div>

    <?php if ($scopeLinks !== []): ?>
      <nav class="tab-nav" aria-label="Rozsah výpisu vývěsky">
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

    <form class="filter-bar filter-bar--stack" action="<?= BASE_URL ?>/board/index.php" method="get">
      <fieldset class="filter-bar__fieldset">
        <legend class="filter-bar__legend">Filtrovat položky vývěsky</legend>

        <div class="filter-grid">
          <div class="form-group">
            <label for="board-search-q">Hledat ve vývěsce</label>
            <input
              id="board-search-q"
              class="form-control"
              type="search"
              name="q"
              value="<?= h($searchQuery) ?>"
              placeholder="Název, text, kontakt nebo příloha"
            >
          </div>

          <div class="form-group">
            <label for="board-filter-category">Kategorie</label>
            <select id="board-filter-category" class="form-control" name="kat">
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
            <label for="board-filter-month">Období vyvěšení</label>
            <select id="board-filter-month" class="form-control" name="month">
              <option value="">Všechna období</option>
              <?php foreach ($monthOptions as $monthOption): ?>
                <?php $monthKey = (string)($monthOption['key'] ?? ''); ?>
                <option value="<?= h($monthKey) ?>" <?= $selectedMonth === $monthKey ? 'selected' : '' ?>>
                  <?= h((string)($monthOption['label'] ?? $monthKey)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <input type="hidden" name="scope" value="<?= h((string)($scope ?? 'current')) ?>">

        <div class="button-row button-row--start">
          <button class="button-primary" type="submit">Použít filtr</button>
          <?php if (!empty($hasActiveFilters)): ?>
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

    <?php if (!empty($resultCountLabel)): ?>
      <p class="meta-row meta-row--tight">
        <span><?= h((string)$resultCountLabel) ?></span>
      </p>
    <?php endif; ?>

    <?php if (!$hasItems): ?>
      <p class="empty-state">
        <?= h(!empty($hasActiveFilters) ? 'Pro zadaný filtr se nenašla žádná položka vývěsky.' : $emptyMessage) ?>
      </p>
    <?php else: ?>
      <div class="stack-sections">
        <?php $groupIndex = 0; foreach (($itemsGrouped ?? []) as $categoryName => $files): ?>
          <section aria-labelledby="board-group-<?= $groupIndex ?>">
            <?php if (!empty($showCategoryHeadings)): ?>
              <h2 id="board-group-<?= $groupIndex ?>" class="section-title section-title--compact"><?= h((string)$categoryName) ?></h2>
            <?php else: ?>
              <h2 id="board-group-<?= $groupIndex ?>" class="sr-only"><?= h($pageHeading) ?></h2>
            <?php endif; ?>

            <ul class="link-list">
              <?php foreach ($files as $document): ?>
                <li class="link-list__item board-item">
                  <?php if ((string)($document['image_url'] ?? '') !== ''): ?>
                    <a class="board-item__media" href="<?= h($documentLink($document)) ?>" aria-hidden="true" tabindex="-1">
                      <img class="board-item__image" src="<?= h((string)$document['image_url']) ?>" alt="" loading="lazy">
                    </a>
                  <?php endif; ?>

                  <div class="board-item__content">
                    <a class="link-list__title" href="<?= h($documentLink($document)) ?>">
                      <?= h((string)($document['title'] ?? '')) ?>
                    </a>

                    <p class="meta-row meta-row--tight board-item__flags">
                      <span class="pill"><?= h((string)($document['board_type_label'] ?? 'Položka')) ?></span>
                      <?php if ((int)($document['is_pinned'] ?? 0) === 1): ?>
                        <span class="pill">Důležité</span>
                      <?php endif; ?>
                      <?php if ((string)($document['category_name'] ?? '') !== ''): ?>
                        <span class="pill"><?= h((string)$document['category_name']) ?></span>
                      <?php endif; ?>
                      <?php if ((string)($document['posted_date'] ?? '') !== ''): ?>
                        <span>Vyvěšeno <time datetime="<?= h((string)$document['posted_date']) ?>"><?= formatCzechDate((string)$document['posted_date']) ?></time></span>
                      <?php endif; ?>
                      <?php if (!empty($document['removal_date'])): ?>
                        <span>Sejmuto <time datetime="<?= h((string)$document['removal_date']) ?>"><?= formatCzechDate((string)$document['removal_date']) ?></time></span>
                      <?php endif; ?>
                    </p>

                    <?php if ((string)($document['excerpt_plain'] ?? '') !== ''): ?>
                      <p class="board-item__summary"><?= h((string)$document['excerpt_plain']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($document['has_contact'])): ?>
                      <p class="board-item__contact">
                        <strong>Kontakt:</strong>
                        <?php if ((string)($document['contact_name'] ?? '') !== ''): ?>
                          <span><?= h((string)$document['contact_name']) ?></span>
                        <?php endif; ?>
                        <?php if ((string)($document['contact_phone'] ?? '') !== ''): ?>
                          <span><a href="tel:<?= h(preg_replace('/\s+/', '', (string)$document['contact_phone'])) ?>"><?= h((string)$document['contact_phone']) ?></a></span>
                        <?php endif; ?>
                        <?php if ((string)($document['contact_email'] ?? '') !== ''): ?>
                          <span><a href="mailto:<?= h((string)$document['contact_email']) ?>"><?= h((string)$document['contact_email']) ?></a></span>
                        <?php endif; ?>
                      </p>
                    <?php endif; ?>

                    <p class="meta-row meta-row--tight">
                      <?php if ((int)($document['file_size'] ?? 0) > 0): ?>
                        <span><?= h(formatFileSize((int)$document['file_size'])) ?></span>
                      <?php endif; ?>
                    </p>

                    <div class="button-row button-row--start">
                      <a class="section-link" href="<?= h($documentLink($document)) ?>">Zobrazit detail <span aria-hidden="true">→</span></a>
                      <?php if ((string)($document['filename'] ?? '') !== ''): ?>
                        <a class="section-link" href="<?= moduleFileUrl('board', (int)$document['id']) ?>" download="<?= h((string)($document['original_name'] ?? '')) ?>">Stáhnout přílohu <span aria-hidden="true">→</span></a>
                      <?php endif; ?>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php $groupIndex++; endforeach; ?>
      </div>

      <?php if (!empty($pagerHtml)): ?>
        <div class="listing-shell__pager">
          <?= $pagerHtml ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
