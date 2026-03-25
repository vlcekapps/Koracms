<div class="listing-shell">
  <section class="surface surface--accent" aria-labelledby="podcast-episode-title">
    <nav aria-label="Drobečková navigace">
      <ol class="breadcrumb-list">
        <li><a href="<?= BASE_URL ?>/podcast/index.php">Podcasty</a></li>
        <li><a href="<?= h((string)$show['public_path']) ?>"><?= h((string)$show['title']) ?></a></li>
        <li aria-current="page"><?= h((string)$episode['title']) ?></li>
      </ol>
    </nav>

    <div class="podcast-hero">
      <?php if (!empty($show['cover_url'])): ?>
        <div class="podcast-cover podcast-cover--large">
          <img src="<?= h((string)$show['cover_url']) ?>" alt="<?= h((string)$show['title']) ?>" loading="lazy">
        </div>
      <?php endif; ?>

      <div class="podcast-hero__copy">
        <p class="section-kicker">Epizoda podcastu</p>
        <h1 id="podcast-episode-title" class="section-title section-title--hero"><?= h((string)$episode['title']) ?></h1>
        <p class="meta-row">
          <a href="<?= h((string)$show['public_path']) ?>"><?= h((string)$show['title']) ?></a>
          <?php if (!empty($episode['episode_num'])): ?>
            <span>Epizoda <?= (int)$episode['episode_num'] ?></span>
          <?php endif; ?>
          <?php if ((string)$episode['display_date'] !== ''): ?>
            <time datetime="<?= h(str_replace(' ', 'T', (string)$episode['display_date'])) ?>">
              <?= h(formatCzechDate((string)$episode['display_date'])) ?>
            </time>
          <?php endif; ?>
          <?php if ((string)$episode['duration'] !== ''): ?>
            <span><?= h((string)$episode['duration']) ?></span>
          <?php endif; ?>
        </p>

        <?php if ((string)$episode['audio_src'] !== ''): ?>
          <audio controls class="audio-player" aria-label="Přehrát epizodu <?= h((string)$episode['title']) ?>">
            <source src="<?= h((string)$episode['audio_src']) ?>">
            Váš prohlížeč nepodporuje přehrávání audia.
            <a href="<?= h((string)$episode['audio_src']) ?>">Stáhnout nebo otevřít audio</a>
          </audio>
        <?php endif; ?>

        <div class="button-row button-row--start">
          <a class="button-secondary" href="<?= h((string)$show['public_path']) ?>"><span aria-hidden="true">←</span> Zpět na pořad</a>
          <a class="button-secondary" href="<?= h($feedUrl) ?>">RSS feed</a>
          <?php if (!empty($show['website_url'])): ?>
            <a class="button-secondary" href="<?= h((string)$show['website_url']) ?>" target="_blank" rel="noopener noreferrer">Web pořadu</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="surface" aria-labelledby="podcast-episode-detail">
    <div class="section-heading">
      <div>
        <h2 id="podcast-episode-detail" class="section-title">Popis epizody</h2>
      </div>
    </div>

    <?php if ((string)$episode['description'] !== ''): ?>
      <div class="prose episode-detail__content"><?= renderContent((string)$episode['description']) ?></div>
    <?php else: ?>
      <p class="empty-state">Tato epizoda zatím nemá doplněný podrobný popis.</p>
    <?php endif; ?>
  </section>
</div>
