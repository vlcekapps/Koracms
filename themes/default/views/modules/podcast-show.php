<div class="listing-shell">
  <section class="surface surface--accent" aria-labelledby="podcast-show-title">
    <div class="button-row button-row--start">
      <a class="button-secondary" href="<?= BASE_URL ?>/podcast/index.php"><span aria-hidden="true">&larr;</span> Všechny podcasty</a>
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
          <span><?= (int)($resultCount ?? count($episodes)) ?> epizod</span>
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
            <a class="button-secondary" href="<?= h((string)$show['website_url']) ?>" target="_blank" rel="noopener noreferrer">Web pořadu<?= newWindowLinkSrOnlySuffix() ?></a>
          <?php endif; ?>
        </div>

        <?php if (!empty($platformLinks)): ?>
          <nav aria-labelledby="podcast-platforms-title">
            <h2 id="podcast-platforms-title" class="sr-only">Poslouchejte na platformách</h2>
            <div class="button-row button-row--start">
              <?php foreach ($platformLinks as $platformLink): ?>
                <a class="button-secondary" href="<?= h((string)$platformLink['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h(podcastPlatformLabel($platformLink)) ?><?= newWindowLinkSrOnlySuffix() ?></a>
              <?php endforeach; ?>
            </div>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if (!empty($people)): ?>
    <section class="surface" aria-labelledby="podcast-show-people-title">
      <div class="section-heading">
        <div><h2 id="podcast-show-people-title" class="section-title">Tvůrci podcastu</h2></div>
      </div>
      <ul class="listing-list">
        <?php foreach ($people as $person): ?>
          <li>
            <?php if (trim((string)($person['image_url'] ?? '')) !== ''): ?>
              <img src="<?= h((string)$person['image_url']) ?>" alt="<?= h((string)$person['name']) ?>" loading="lazy" class="avatar avatar--small">
            <?php endif; ?>
            <strong><?= h((string)$person['name']) ?></strong>
            <span><?= h(podcastPersonRoleLabel((string)$person['role_key'])) ?></span>
            <?php if (trim((string)($person['profile_url'] ?? '')) !== ''): ?>
              <a href="<?= h((string)$person['profile_url']) ?>" target="_blank" rel="noopener noreferrer">Veřejný profil<?= newWindowLinkSrOnlySuffix() ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section class="surface" aria-labelledby="podcast-episodes-title">
      <div class="section-heading">
        <div>
          <h2 id="podcast-episodes-title" class="section-title">Epizody</h2>
        </div>
      </div>

      <?php if (!empty($seasons)): ?>
        <nav aria-labelledby="podcast-season-filter-title">
          <h3 id="podcast-season-filter-title" class="sr-only">Filtrovat epizody podle sezóny</h3>
          <div class="button-row button-row--start">
            <a class="button-secondary" href="<?= h((string)$show['public_path']) ?>"<?= $seasonFilter === null ? ' aria-current="page"' : '' ?>>Všechny epizody</a>
            <?php foreach ($seasons as $season): ?>
              <a class="button-secondary" href="<?= h((string)$show['public_path'] . '?sezona=' . (int)$season) ?>"<?= $seasonFilter === (int)$season ? ' aria-current="page"' : '' ?>>Sezóna <?= (int)$season ?></a>
            <?php endforeach; ?>
          </div>
        </nav>
      <?php endif; ?>

      <?php if (!empty($resultCount)): ?>
        <p class="meta-row meta-row--tight"><?= (int)$resultCount ?> epizod</p>
      <?php endif; ?>

    <?php if (empty($episodes)): ?>
      <p class="empty-state"><?= $seasonFilter !== null ? 'V této sezóně zatím nejsou žádné zveřejněné epizody.' : 'Zatím tu nejsou žádné zveřejněné epizody.' ?></p>
    <?php else: ?>
      <div class="episode-list">
        <?php foreach ($episodes as $episode): ?>
          <?php $episodeTitleId = 'podcast-episode-title-' . (int)$episode['id']; ?>
          <article class="episode-card<?= (string)($episode['image_url'] ?? '') !== '' ? ' episode-card--with-image' : '' ?>" aria-labelledby="<?= h($episodeTitleId) ?>">
            <?php if ((string)($episode['image_url'] ?? '') !== ''): ?>
              <a class="episode-card__media podcast-cover" href="<?= h((string)$episode['public_path']) ?>">
                <img src="<?= h((string)$episode['image_url']) ?>" alt="<?= h((string)$episode['title']) ?>" loading="lazy">
              </a>
            <?php endif; ?>

            <div class="episode-card__body">
              <header class="episode-card__header">
                <?php if (!empty($episode['episode_num'])): ?>
                  <p class="section-kicker">Epizoda <?= (int)$episode['episode_num'] ?></p>
                <?php endif; ?>
                <h3 id="<?= h($episodeTitleId) ?>" class="card__title">
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
                <a class="section-link" href="<?= h((string)$episode['public_path']) ?>">Zobrazit epizodu <span aria-hidden="true">&rarr;</span></a>
              </footer>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($pagerHtml)): ?>
        <div class="listing-shell__pager">
          <?= $pagerHtml ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
