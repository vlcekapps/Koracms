<div class="listing-shell">
  <section class="surface" aria-labelledby="places-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Tipy na výlet a služby</p>
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

            <div class="card-grid">
              <?php foreach ($items as $place): ?>
                <article class="card place-card">
                  <?php if ($place['image_url'] !== ''): ?>
                    <a class="card__media" href="<?= h(placePublicPath($place)) ?>">
                      <img src="<?= h((string)$place['image_url']) ?>" alt="" loading="lazy">
                    </a>
                  <?php endif; ?>
                  <div class="card__body">
                    <p class="meta-row meta-row--tight">
                      <span class="pill"><?= h((string)$place['place_kind_label']) ?></span>
                      <?php if (!empty($place['locality'])): ?>
                        <span><?= h((string)$place['locality']) ?></span>
                      <?php endif; ?>
                    </p>

                    <h3 class="card__title">
                      <a href="<?= h(placePublicPath($place)) ?>"><?= h((string)$place['name']) ?></a>
                    </h3>

                    <?php if ($place['excerpt_plain'] !== ''): ?>
                      <p class="place-card__description"><?= h((string)$place['excerpt_plain']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($place['full_address'])): ?>
                      <p class="meta-row meta-row--tight"><span><?= h((string)$place['full_address']) ?></span></p>
                    <?php endif; ?>

                    <div class="button-row button-row--start">
                      <a class="section-link" href="<?= h(placePublicPath($place)) ?>">Zobrazit detail <span aria-hidden="true">→</span></a>
                      <?php if ($place['url'] !== ''): ?>
                        <a class="section-link" href="<?= h((string)$place['url']) ?>" target="_blank" rel="noopener noreferrer">Navštívit web <span aria-hidden="true">→</span></a>
                      <?php endif; ?>
                    </div>
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
