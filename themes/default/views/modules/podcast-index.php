<div class="listing-shell">
  <section class="surface" aria-labelledby="podcast-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Poslech</p>
        <h1 id="podcast-title" class="section-title section-title--hero">Podcasty</h1>
      </div>
    </div>

    <form method="get" action="<?= BASE_URL ?>/podcast/index.php" role="search" aria-labelledby="podcast-filter-title">
      <fieldset>
        <legend id="podcast-filter-title">Najít podcast</legend>
        <div class="filter-grid">
          <div>
            <label for="podcast-query">Název, autor nebo téma</label>
            <input type="search" id="podcast-query" name="q" maxlength="100" value="<?= h((string)$query) ?>">
          </div>
          <?php if (!empty($categories)): ?>
            <div>
              <label for="podcast-category">Kategorie</label>
              <select id="podcast-category" name="kategorie">
                <option value="">Všechny kategorie</option>
                <?php foreach ($categories as $category): ?>
                  <option value="<?= h((string)$category) ?>"<?= $categoryFilter === (string)$category ? ' selected' : '' ?>><?= h((string)$category) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
        </div>
        <div class="button-row button-row--start">
          <button type="submit">Použít filtr</button>
          <?php if ($query !== '' || $categoryFilter !== ''): ?>
            <a class="button-secondary" href="<?= BASE_URL ?>/podcast/index.php">Zrušit filtr</a>
          <?php endif; ?>
        </div>
      </fieldset>
    </form>

    <?php if (empty($shows)): ?>
      <p class="empty-state"><?= $query !== '' || $categoryFilter !== '' ? 'Zadanému filtru neodpovídá žádný zveřejněný podcast.' : 'Zatím nejsou zveřejněné žádné podcasty.' ?></p>
    <?php else: ?>
      <?php if (!empty($resultCount)): ?>
        <p class="meta-row meta-row--tight"><?= (int)$resultCount ?> pořadů</p>
      <?php endif; ?>
      <div class="card-grid">
        <?php foreach ($shows as $show): ?>
          <?php $podcastTitleId = 'podcast-card-title-' . (int)$show['id']; ?>
          <article class="card podcast-card" aria-labelledby="<?= h($podcastTitleId) ?>">
            <div class="card__body podcast-card__body">
              <?php if (!empty($show['cover_url'])): ?>
                <a class="podcast-cover" href="<?= h((string)$show['public_path']) ?>">
                  <img src="<?= h((string)$show['cover_url']) ?>" alt="<?= h((string)$show['title']) ?>" loading="lazy">
                </a>
              <?php endif; ?>

              <div class="podcast-card__content">
                <h2 id="<?= h($podcastTitleId) ?>" class="card__title">
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

      <?php if (!empty($pagerHtml)): ?>
        <div class="listing-shell__pager">
          <?= $pagerHtml ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
