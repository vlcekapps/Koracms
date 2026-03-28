<?php
// Pomocná funkce: stromové vykreslení kategorií
$renderCatNav = static function (array $tree, int $parentId, int $depth, ?int $activeCatId, callable $self) use (&$renderCatNav): string {
    if (empty($tree[$parentId])) {
        return '';
    }
    $tag = $depth === 0 ? 'ul' : 'ul';
    $out = "<{$tag} class=\"kb-tree" . ($depth > 0 ? ' kb-tree--nested' : '') . "\">";
    foreach ($tree[$parentId] as $cat) {
        $cid = (int)$cat['id'];
        $isActive = $cid === $activeCatId;
        $hasChildren = !empty($tree[$cid]);
        $out .= '<li class="kb-tree__item' . ($isActive ? ' kb-tree__item--active' : '') . '">';
        $out .= '<a href="' . BASE_URL . '/faq/index.php?kat=' . $cid . '"'
              . ($isActive ? ' aria-current="page"' : '') . '>'
              . h((string)$cat['name']) . '</a>';
        if ($hasChildren) {
            $out .= $self($tree, $cid, $depth + 1, $activeCatId, $self);
        }
        $out .= '</li>';
    }
    $out .= "</{$tag}>";
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
                <a href="<?= BASE_URL ?>/faq/index.php?kat=<?= (int)$crumb['id'] ?>"><?= h((string)$crumb['name']) ?></a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>

    <?php if (!empty($catTree[0])): ?>
      <nav aria-label="Kategorie znalostní báze" class="kb-sidebar">
        <h2 class="section-title section-title--compact">Kategorie</h2>
        <a href="<?= BASE_URL ?>/faq/index.php"<?= $filterCatId === null ? ' aria-current="page"' : '' ?> class="kb-tree__root-link">Vše</a>
        <?= $renderCatNav($catTree, 0, 0, $filterCatId, $renderCatNav) ?>
      </nav>
    <?php endif; ?>

    <?php if (empty($faqs)): ?>
      <p class="empty-state">
        <?php if ($filterCategory !== null): ?>
          V této kategorii zatím nejsou žádné položky.
        <?php else: ?>
          Zatím nejsou zveřejněné žádné položky.
        <?php endif; ?>
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

            <div class="card-grid card-grid--compact">
              <?php foreach ($items as $faq): ?>
                <article class="card card--rich">
                  <div class="card__body">
                    <?php if ($multipleCategories): ?>
                      <p class="card__eyebrow"><?= h($categoryName) ?></p>
                    <?php endif; ?>
                    <h3 class="card__title">
                      <a href="<?= h(faqPublicPath($faq)) ?>"><?= h((string)$faq['question']) ?></a>
                    </h3>

                    <?php if ($faq['excerpt'] !== ''): ?>
                      <p class="card__description"><?= h((string)$faq['excerpt']) ?></p>
                    <?php endif; ?>

                    <div class="card__actions">
                      <a class="section-link" href="<?= h(faqPublicPath($faq)) ?>">Zobrazit odpověď <span aria-hidden="true">→</span></a>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php $categoryIndex++; endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
