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
              <?php if (!empty($show['cover_url'])): ?>
                <a class="podcast-cover" href="<?= h((string)$show['public_path']) ?>">
                  <img src="<?= h((string)$show['cover_url']) ?>" alt="<?= h((string)$show['title']) ?>" loading="lazy">
                </a>
              <?php endif; ?>

              <div class="podcast-card__content">
                <h2 class="card__title">
                  <a href="<?= h((string)$show['public_path']) ?>"><?= h((string)$show['title']) ?></a>
                </h2>

                <p class="meta-row meta-row--tight">
                  <?php if (!empty($show['author'])): ?>
                    <span><?= h((string)$show['author']) ?></span>
                  <?php endif; ?>
                  <span><?= (int)($show['episode_count'] ?? 0) ?> epizod</span>
                  <?php if (!empty($show['latest_episode_at'])): ?>
                    <time datetime="<?= h(str_replace(' ', 'T', (string)$show['latest_episode_at'])) ?>">
                      <?= h(formatCzechDate((string)$show['latest_episode_at'])) ?>
                    </time>
                  <?php endif; ?>
                </p>

                <?php if (!empty($show['description_plain'])): ?>
                  <p class="podcast-card__description">
                    <?= h(mb_strimwidth((string)$show['description_plain'], 0, 220, '…', 'UTF-8')) ?>
                  </p>
                <?php endif; ?>

                <div class="button-row button-row--start">
                  <a class="button-secondary" href="<?= h((string)$show['public_path']) ?>">Zobrazit pořad</a>
                  <a class="button-secondary" href="<?= h(BASE_URL . '/podcast/feed.php?slug=' . rawurlencode((string)$show['slug'])) ?>">RSS feed</a>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
