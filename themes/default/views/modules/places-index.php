<div class="listing-shell">
  <section class="surface" aria-labelledby="places-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Tipy na výlet</p>
        <h1 id="places-title" class="section-title section-title--hero">Zajímavá místa</h1>
      </div>
    </div>

    <?php if (empty($places)): ?>
      <p class="empty-state">Zatím nejsou zveřejněná žádná místa.</p>
    <?php else: ?>
      <div class="stack-sections">
        <?php $groupIndex = 0; foreach ($grouped as $category => $items): ?>
          <section aria-labelledby="places-group-<?= $groupIndex ?>">
            <h2 id="places-group-<?= $groupIndex ?>" class="section-title section-title--compact"><?= h($category) ?></h2>

            <div class="card-grid card-grid--compact">
              <?php foreach ($items as $place): ?>
                <article class="card place-card" id="place-<?= (int)$place['id'] ?>">
                  <div class="card__body">
                    <h3 class="card__title">
                      <?php if ($place['url'] !== ''): ?>
                        <a href="<?= h($place['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($place['name']) ?></a>
                      <?php else: ?>
                        <?= h($place['name']) ?>
                      <?php endif; ?>
                    </h3>

                    <?php if (!empty($place['description'])): ?>
                      <div class="prose place-card__description"><?= renderContent($place['description']) ?></div>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php $groupIndex++; endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
