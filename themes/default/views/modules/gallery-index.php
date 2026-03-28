<div class="listing-shell">
  <section class="surface" aria-labelledby="gallery-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Fotografie</p>
        <h1 id="gallery-title" class="section-title section-title--hero">Galerie</h1>
      </div>
    </div>

    <?php if (empty($albums)): ?>
      <p class="empty-state">Zatím zde nejsou žádná alba.</p>
    <?php else: ?>
      <div class="gallery-grid">
        <?php foreach ($albums as $album): ?>
          <article class="card gallery-card">
            <a class="gallery-card__link" href="<?= h((string)$album['public_path']) ?>">
              <?php if ($album['cover_url'] !== ''): ?>
                <img class="gallery-card__image" src="<?= h($album['cover_url']) ?>" alt="<?= h($album['name']) ?>">
              <?php else: ?>
                <div class="gallery-card__placeholder" aria-hidden="true">Bez náhledu</div>
              <?php endif; ?>
              <div class="card__body">
                <h2 class="card__title"><?= h($album['name']) ?></h2>
                <p class="meta-row meta-row--tight">
                  <span><?= h($album['photo_count_label']) ?></span>
                  <?php if ($album['sub_count'] > 0): ?>
                    <span><?= h($album['sub_count_label']) ?></span>
                  <?php endif; ?>
                </p>
                <?php if (!empty($album['description'])): ?>
                  <p><?= h($album['description']) ?></p>
                <?php endif; ?>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
