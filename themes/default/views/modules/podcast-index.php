<div class="listing-shell">
  <section class="surface" aria-labelledby="podcast-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Poslech</p>
        <h1 id="podcast-title" class="section-title section-title--hero">Podcasty</h1>
      </div>
    </div>

    <?php if (empty($shows)): ?>
      <p class="empty-state">Zatím nejsou zveřejněné žádné podcasty.</p>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($shows as $show): ?>
          <article class="card podcast-card">
            <div class="card__body podcast-card__body">
              <?php if (!empty($show['cover_image'])): ?>
                <div class="podcast-cover">
                  <img src="<?= BASE_URL ?>/uploads/podcasts/covers/<?= rawurlencode($show['cover_image']) ?>"
                       alt="<?= h($show['title']) ?>" loading="lazy">
                </div>
              <?php endif; ?>

              <div class="podcast-card__content">
                <h2 class="card__title">
                  <a href="<?= BASE_URL ?>/podcast/show.php?slug=<?= rawurlencode($show['slug']) ?>"><?= h($show['title']) ?></a>
                </h2>

                <p class="meta-row meta-row--tight">
                  <?php if (!empty($show['author'])): ?>
                    <span><?= h($show['author']) ?></span>
                  <?php endif; ?>
                  <span><?= (int)$show['episode_count'] ?> epizod</span>
                </p>

                <?php if (!empty($show['description'])): ?>
                  <p class="podcast-card__description"><?= h($show['description']) ?></p>
                <?php endif; ?>

                <div class="button-row button-row--start">
                  <a class="button-secondary" href="<?= BASE_URL ?>/podcast/show.php?slug=<?= rawurlencode($show['slug']) ?>">Zobrazit pořad</a>
                  <a class="button-secondary" href="<?= BASE_URL ?>/podcast/feed.php?slug=<?= rawurlencode($show['slug']) ?>">RSS feed</a>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
