<div class="listing-shell">
  <section class="surface surface--accent" aria-labelledby="podcast-show-title">
    <div class="button-row button-row--start">
      <a class="button-secondary" href="<?= BASE_URL ?>/podcast/index.php"><span aria-hidden="true">←</span> Všechny podcasty</a>
    </div>

    <div class="podcast-hero">
      <?php if (!empty($show['cover_image'])): ?>
        <div class="podcast-cover podcast-cover--large">
          <img src="<?= BASE_URL ?>/uploads/podcasts/covers/<?= rawurlencode($show['cover_image']) ?>"
               alt="<?= h($show['title']) ?>" loading="lazy">
        </div>
      <?php endif; ?>

      <div class="podcast-hero__copy">
        <p class="section-kicker">Podcast</p>
        <h1 id="podcast-show-title" class="section-title section-title--hero"><?= h($show['title']) ?></h1>
        <p class="meta-row">
          <?php if (!empty($show['author'])): ?>
            <span><?= h($show['author']) ?></span>
          <?php endif; ?>
          <span><?= count($episodes) ?> epizod</span>
        </p>

        <?php if (!empty($show['description'])): ?>
          <p class="section-subtitle"><?= h($show['description']) ?></p>
        <?php endif; ?>

        <div class="button-row button-row--start">
          <a class="button-secondary" href="<?= h($feedUrl) ?>">RSS feed</a>
          <?php if (!empty($show['website_url'])): ?>
            <a class="button-secondary" href="<?= h($show['website_url']) ?>" target="_blank" rel="noopener noreferrer">Web pořadu</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="surface" aria-labelledby="podcast-episodes-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Epizody</p>
        <h2 id="podcast-episodes-title" class="section-title">Seznam epizod</h2>
      </div>
    </div>

    <?php if (empty($episodes)): ?>
      <p class="empty-state">Zatím nejsou zveřejněné žádné epizody.</p>
    <?php else: ?>
      <div class="episode-list">
        <?php foreach ($episodes as $episode): ?>
          <article id="ep-<?= (int)$episode['id'] ?>" class="episode-card">
            <header class="episode-card__header">
              <?php if ($episode['episode_num']): ?>
                <p class="section-kicker">Epizoda <?= (int)$episode['episode_num'] ?></p>
              <?php endif; ?>
              <h3 class="card__title"><?= h($episode['title']) ?></h3>
              <?php $displayDate = !empty($episode['publish_at']) ? $episode['publish_at'] : $episode['created_at']; ?>
              <p class="meta-row meta-row--tight">
                <time datetime="<?= h(str_replace(' ', 'T', $displayDate)) ?>"><?= formatCzechDate($displayDate) ?></time>
                <?php if ($episode['duration'] !== ''): ?>
                  <span><?= h($episode['duration']) ?></span>
                <?php endif; ?>
              </p>
            </header>

            <?php
            $audioSrc = '';
            if ($episode['audio_file'] !== '') {
                $audioSrc = BASE_URL . '/uploads/podcasts/' . rawurlencode($episode['audio_file']);
            } elseif ($episode['audio_url'] !== '') {
                $audioSrc = $episode['audio_url'];
            }
            ?>
            <?php if ($audioSrc !== ''): ?>
              <audio controls class="audio-player" aria-label="Přehrát epizodu <?= h($episode['title']) ?>">
                <source src="<?= h($audioSrc) ?>">
                Váš prohlížeč nepodporuje přehrávání audia.
                <a href="<?= h($audioSrc) ?>">Stáhnout epizodu</a>
              </audio>
            <?php endif; ?>

            <?php if (!empty($episode['description'])): ?>
              <div class="prose"><?= renderContent($episode['description']) ?></div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
