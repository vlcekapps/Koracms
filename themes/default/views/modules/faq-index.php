<div class="listing-shell">
  <section class="surface" aria-labelledby="faq-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Podpora a informace</p>
        <h1 id="faq-title" class="section-title section-title--hero">Často kladené otázky</h1>
      </div>
    </div>

    <?php if (empty($faqs)): ?>
      <p class="empty-state">Zatím nejsou zveřejněné žádné otázky.</p>
    <?php else: ?>
      <?php if ($multipleCategories): ?>
        <nav aria-label="Kategorie otázek">
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
              <h2 id="faq-category-<?= $categoryIndex ?>" class="sr-only">Otázky a odpovědi</h2>
            <?php endif; ?>

            <div class="stack-list">
              <?php foreach ($items as $faq): ?>
                <details class="toggle-card">
                  <summary><?= h($faq['question']) ?></summary>
                  <div class="prose toggle-card__content"><?= renderContent($faq['answer']) ?></div>
                </details>
              <?php endforeach; ?>
            </div>
          </section>
        <?php $categoryIndex++; endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
