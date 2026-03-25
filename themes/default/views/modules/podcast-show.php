<div class="listing-shell">
  <section class="surface surface--accent" aria-labelledby="podcast-show-title">
    <div class="button-row button-row--start">
      <a class="button-secondary" href="<?= BASE_URL ?>/podcast/index.php"><span aria-hidden="true">←</span> Všechny podcasty</a>
    </div>

    <div class="podcast-hero">
      <?php if (!empty($show['cover_url'])): ?>
        <div class="podcast-cover podcast-cover--large">
          <img src="<?= h((string)$show['cover_url']) ?>" alt="<?= h((string)$show['title']) ?>" loading="lazy">
        </div>
      <?php endif; ?>

      <div class="podcast-hero__copy">
        <p class="section-kicker">Podcast</p>
        <h1 id="podcast-show-title" class="section-title section-title--hero"><?= h((string)$show['title']) ?></h1>
        <p class="meta-row">
          <?php if (!empty($show['author'])): ?>
            <span><?= h((string)$show['author']) ?></span>
          <?php endif; ?>
          <span><?= count($episodes) ?> epizod</span>
          <?php if (!empty($show['category'])): ?>
            <span class="pill"><?= h((string)$show['category']) ?></span>
          <?php endif; ?>
        </p>

        <?php if (!empty($show['description'])): ?>
          <div class="prose"><?= renderContent((string)$show['description']) ?></div>
        <?php endif; ?>

        <div class="button-row button-row--start">
          <a class="button-secondary" href="<?= h($feedUrl) ?>">RSS feed</a>
          <?php if (!empty($show['website_url'])): ?>
            <a class="button-secondary" href="<?= h((string)$show['website_url']) ?>" target="_blank" rel="noopener noreferrer">Web pořadu</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="surface" aria-labelledby="podcast-episodes-title">
    <div class="section-heading">
      <div>
        <h2 id="podcast-episodes-title" class="section-title">Epizody</h2>
      </div>
    </div>

    <?php if (empty($episodes)): ?>
      <p class="empty-state">Zatím tu nejsou žádné zveřejněné epizody.</p>
    <?php else: ?>
      <div class="episode-list">
        <?php foreach ($episodes as $episode): ?>
          <article class="episode-card">
            <header class="episode-card__header">
              <?php if (!empty($episode['episode_num'])): ?>
                <p class="section-kicker">Epizoda <?= (int)$episode['episode_num'] ?></p>
              <?php endif; ?>
              <h3 class="card__title">
                <a href="<?= h((string)$episode['public_path']) ?>"><?= h((string)$episode['title']) ?></a>
              </h3>
              <p class="meta-row meta-row--tight">
                <?php if ((string)$episode['display_date'] !== ''): ?>
                  <time datetime="<?= h(str_replace(' ', 'T', (string)$episode['display_date'])) ?>">
                    <?= h(formatCzechDate((string)$episode['display_date'])) ?>
                  </time>
                <?php endif; ?>
                <?php if ((string)$episode['duration'] !== ''): ?>
                  <span><?= h((string)$episode['duration']) ?></span>
                <?php endif; ?>
              </p>
            </header>

            <?php if ((string)$episode['excerpt'] !== ''): ?>
              <p class="card__description"><?= h((string)$episode['excerpt']) ?></p>
            <?php endif; ?>

            <footer class="episode-card__footer">
              <a class="section-link" href="<?= h((string)$episode['public_path']) ?>">Zobrazit epizodu <span aria-hidden="true">→</span></a>
            </footer>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
