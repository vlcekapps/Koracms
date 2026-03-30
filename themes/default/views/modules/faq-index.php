<?php
$searchQuery = (string)($searchQuery ?? '');
$displayMode = (string)($displayMode ?? 'cards');
$displayModeLinks = is_array($displayModeLinks ?? null) ? $displayModeLinks : [];
$filterSummary = is_array($filterSummary ?? null) ? $filterSummary : [];
$resultCountLabel = (string)($resultCountLabel ?? '');
$pagerHtml = (string)($pagerHtml ?? '');
$hasActiveFilters = !empty($hasActiveFilters);
$clearUrl = (string)($clearUrl ?? (BASE_URL . '/faq/index.php'));
$detailQuery = is_array($detailQuery ?? null) ? $detailQuery : [];
$categoryRootUrl = (string)($categoryRootUrl ?? (BASE_URL . '/faq/index.php'));
$buildFaqIndexUrl = is_callable($buildFaqIndexUrl ?? null) ? $buildFaqIndexUrl : static fn(array $params = []): string => BASE_URL . '/faq/index.php';
$renderCatNav = static function (array $tree, int $parentId, int $depth, ?int $activeCatId, callable $self) use ($buildFaqIndexUrl): string {
    if (empty($tree[$parentId])) {
        return '';
    }
    $out = '<ul class="kb-tree' . ($depth > 0 ? ' kb-tree--nested' : '') . '">';
    foreach ($tree[$parentId] as $cat) {
        $cid = (int)$cat['id'];
        $isActive = $cid === $activeCatId;
        $hasChildren = !empty($tree[$cid]);
        $out .= '<li class="kb-tree__item' . ($isActive ? ' kb-tree__item--active' : '') . '">';
        $out .= '<a href="' . h($buildFaqIndexUrl([
            'kat' => $cid,
            'strana' => null,
        ])) . '"'
              . ($isActive ? ' aria-current="page"' : '') . '>'
              . h((string)$cat['name']) . '</a>';
        if ($hasChildren) {
            $out .= $self($tree, $cid, $depth + 1, $activeCatId, $self);
        }
        $out .= '</li>';
    }
    $out .= '</ul>';
    return $out;
};
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="faq-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Podpora a informace</p>
        <h1 id="faq-title" class="section-title section-title--hero">Znalostní báze</h1>
      </div>
    </div>

    <?php if ($breadcrumbs !== []): ?>
      <nav aria-label="Drobečková navigace">
        <ol class="breadcrumbs">
          <li><a href="<?= BASE_URL ?>/faq/index.php">Znalostní báze</a></li>
          <?php foreach ($breadcrumbs as $i => $crumb): ?>
            <?php $isLast = $i === count($breadcrumbs) - 1; ?>
            <li>
              <?php if ($isLast): ?>
                <span aria-current="page"><?= h((string)$crumb['name']) ?></span>
              <?php else: ?>
                <a href="<?= h($buildFaqIndexUrl(['kat' => (int)$crumb['id'], 'strana' => null])) ?>"><?= h((string)$crumb['name']) ?></a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>

    <?php if ($displayModeLinks !== []): ?>
      <nav class="tab-nav" aria-label="Zobrazení znalostní báze">
        <?php foreach ($displayModeLinks as $displayModeLink): ?>
          <a class="tab-nav__link<?= !empty($displayModeLink['active']) ? ' is-active' : '' ?>"
             href="<?= h((string)$displayModeLink['url']) ?>"
             <?= !empty($displayModeLink['active']) ? 'aria-current="page"' : '' ?>>
            <?= h((string)$displayModeLink['label']) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <form class="filter-bar filter-bar--stack" action="<?= BASE_URL ?>/faq/index.php" method="get">
      <fieldset class="filter-bar__fieldset">
        <legend class="filter-bar__legend">Filtrovat znalostní bázi</legend>

        <div class="filter-grid">
          <div class="form-group">
            <label for="faq-search-q">Hledat ve znalostní bázi</label>
            <input
              id="faq-search-q"
              class="form-control"
              type="search"
              name="q"
              value="<?= h($searchQuery) ?>"
              placeholder="Otázka, shrnutí, odpověď nebo kategorie"
            >
          </div>

          <div class="form-group">
            <label for="faq-filter-category">Kategorie</label>
            <select id="faq-filter-category" class="form-control" name="kat">
              <option value="">Všechny kategorie</option>
              <?php foreach ($categories as $category): ?>
                <?php $categoryId = (int)($category['id'] ?? 0); ?>
                <option value="<?= $categoryId ?>" <?= $filterCatId !== null && (int)$filterCatId === $categoryId ? 'selected' : '' ?>>
                  <?= h((string)($category['name'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <?php if ($displayMode === 'inline'): ?>
          <input type="hidden" name="zobrazeni" value="inline">
        <?php endif; ?>

        <div class="button-row button-row--start">
          <button class="button-primary" type="submit">Použít filtr</button>
          <?php if ($hasActiveFilters): ?>
            <a class="button-secondary" href="<?= h($clearUrl) ?>">Zrušit filtr</a>
          <?php endif; ?>
        </div>
      </fieldset>
    </form>

    <?php if (!empty($catTree[0])): ?>
      <nav aria-label="Kategorie znalostní báze" class="kb-sidebar">
        <h2 class="section-title section-title--compact">Kategorie</h2>
        <a href="<?= h($categoryRootUrl) ?>"<?= $filterCatId === null ? ' aria-current="page"' : '' ?> class="kb-tree__root-link">Vše</a>
        <?= $renderCatNav($catTree, 0, 0, $filterCatId, $renderCatNav) ?>
      </nav>
    <?php endif; ?>

    <?php if ($filterSummary !== []): ?>
      <p class="meta-row meta-row--tight">
        <strong>Aktivní filtry:</strong>
        <span><?= h(implode(', ', $filterSummary)) ?></span>
      </p>
    <?php endif; ?>

    <?php if ($resultCountLabel !== ''): ?>
      <p class="meta-row meta-row--tight">
        <span><?= h($resultCountLabel) ?></span>
      </p>
    <?php endif; ?>

    <?php if (empty($faqs)): ?>
      <p class="empty-state">
        <?= h($hasActiveFilters ? 'Pro zvolený filtr tu teď nejsou žádné otázky.' : 'Zatím nejsou zveřejněné žádné položky.') ?>
      </p>
    <?php else: ?>
      <?php if ($multipleCategories): ?>
        <nav aria-label="Kategorie v aktuálním výběru">
          <ul class="chip-list">
            <?php $categoryIndex = 0; foreach ($grouped as $categoryName => $items): ?>
              <li><a class="chip-link" href="#faq-category-<?= $categoryIndex ?>"><?= h($categoryName) ?> (<?= count($items) ?>)</a></li>
            <?php $categoryIndex++; endforeach; ?>
          </ul>
        </nav>
      <?php endif; ?>

      <div class="stack-sections">
        <?php $categoryIndex = 0; foreach ($grouped as $categoryName => $items): ?>
          <section aria-labelledby="faq-category-<?= $categoryIndex ?>">
            <?php if ($multipleCategories): ?>
              <h2 id="faq-category-<?= $categoryIndex ?>" class="section-title section-title--compact"><?= h($categoryName) ?></h2>
            <?php else: ?>
              <h2 id="faq-category-<?= $categoryIndex ?>" class="sr-only">Položky znalostní báze</h2>
            <?php endif; ?>

            <?php if ($displayMode === 'inline'): ?>
              <div class="stack-sections stack-sections--tight">
                <?php foreach ($items as $faq): ?>
                  <details class="toggle-card">
                    <summary><?= h((string)$faq['question']) ?></summary>
                    <div class="toggle-card__content">
                      <?php if ($faq['excerpt'] !== ''): ?>
                        <p class="article-shell__lead"><?= h((string)$faq['excerpt']) ?></p>
                      <?php endif; ?>
                      <div class="prose">
                        <?= renderContent((string)$faq['answer']) ?>
                      </div>
                      <div class="article-actions">
                        <a class="button-secondary" href="<?= h(faqPublicPath($faq, $detailQuery)) ?>">Otevřít samostatný detail</a>
                      </div>
                    </div>
                  </details>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="card-grid card-grid--compact">
                <?php foreach ($items as $faq): ?>
                  <article class="card card--rich">
                    <div class="card__body">
                      <?php if ($multipleCategories): ?>
                        <p class="card__eyebrow"><?= h($categoryName) ?></p>
                      <?php endif; ?>
                      <h3 class="card__title">
                        <a href="<?= h(faqPublicPath($faq, $detailQuery)) ?>"><?= h((string)$faq['question']) ?></a>
                      </h3>

                      <?php if ($faq['excerpt'] !== ''): ?>
                        <p class="card__description"><?= h((string)$faq['excerpt']) ?></p>
                      <?php endif; ?>

                      <div class="card__actions">
                        <a class="section-link" href="<?= h(faqPublicPath($faq, $detailQuery)) ?>">Zobrazit odpověď <span aria-hidden="true">→</span></a>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php $categoryIndex++; endforeach; ?>
      </div>

      <?php if ($pagerHtml !== ''): ?>
        <div class="listing-shell__pager">
          <?= $pagerHtml ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
