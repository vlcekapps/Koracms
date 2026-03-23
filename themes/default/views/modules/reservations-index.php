<div class="listing-shell">
  <section class="surface" aria-labelledby="reservations-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Plánování návštěvy</p>
        <h1 id="reservations-title" class="section-title section-title--hero">Rezervace</h1>
      </div>
    </div>

    <?php if (empty($sections)): ?>
      <p class="empty-state">Momentálně nejsou k dispozici žádné rezervovatelné prostory.</p>
    <?php else: ?>
      <div class="stack-sections">
        <?php foreach ($sections as $section): ?>
          <section aria-labelledby="<?= h($section['heading_id']) ?>">
            <?php if ($section['show_heading']): ?>
              <h2 id="<?= h($section['heading_id']) ?>" class="section-title section-title--compact"><?= h($section['label']) ?></h2>
            <?php else: ?>
              <h2 id="<?= h($section['heading_id']) ?>" class="sr-only">Rezervovatelné prostory</h2>
            <?php endif; ?>

            <div class="card-grid card-grid--compact">
              <?php foreach ($section['items'] as $resource): ?>
                <article class="card reservation-card">
                  <div class="card__body">
                    <h3 class="card__title">
                      <a href="<?= BASE_URL ?>/reservations/resource.php?slug=<?= rawurlencode($resource['slug']) ?>"><?= h($resource['name']) ?></a>
                    </h3>

                    <p class="meta-row meta-row--tight">
                      <span class="pill"><?= h($resource['mode_label']) ?></span>
                      <?php if (!empty($resource['location_names'])): ?>
                        <span>Místo: <?= h(implode(', ', $resource['location_names'])) ?></span>
                      <?php endif; ?>
                    </p>

                    <?php if ($resource['excerpt'] !== ''): ?>
                      <p><?= h($resource['excerpt']) ?></p>
                    <?php endif; ?>

                    <div class="button-row button-row--start">
                      <a class="button-secondary" href="<?= BASE_URL ?>/reservations/resource.php?slug=<?= rawurlencode($resource['slug']) ?>">Zobrazit termíny</a>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
