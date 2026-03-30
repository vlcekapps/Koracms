<?php
$albums = $albums ?? [];
$searchQuery = (string)($searchQuery ?? '');
$resultCountLabel = (string)($resultCountLabel ?? '');
$pagerHtml = (string)($pagerHtml ?? '');
$hasActiveFilters = !empty($hasActiveFilters);
$clearUrl = (string)($clearUrl ?? (BASE_URL . '/gallery/index.php'));
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="gallery-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Fotografie</p>
        <h1 id="gallery-title" class="section-title section-title--hero">Galerie</h1>
      </div>
    </div>

    <form class="filter-bar filter-bar--stack" action="<?= BASE_URL ?>/gallery/index.php" method="get">
      <fieldset class="filter-bar__fieldset">
        <legend class="filter-bar__legend">Hledat v galerii</legend>

        <div class="filter-grid">
          <div class="form-group">
            <label for="gallery-search-q">Hledat v albech</label>
            <input
              id="gallery-search-q"
              class="form-control"
              type="search"
              name="q"
              value="<?= h($searchQuery) ?>"
              placeholder="Název nebo popis alba"
            >
          </div>
        </div>

        <div class="button-row button-row--start">
          <button class="button-primary" type="submit">Použít filtr</button>
          <?php if ($hasActiveFilters): ?>
            <a class="button-secondary" href="<?= h($clearUrl) ?>">Zrušit filtr</a>
          <?php endif; ?>
        </div>
      </fieldset>
    </form>

    <?php if ($resultCountLabel !== ''): ?>
      <p class="meta-row meta-row--tight">
        <span><?= h($resultCountLabel) ?></span>
      </p>
    <?php endif; ?>

    <?php if (empty($albums)): ?>
      <p class="empty-state">
        <?= h($hasActiveFilters ? 'Pro zadaný filtr se nenašlo žádné album.' : 'Zatím zde nejsou žádná alba.') ?>
      </p>
    <?php else: ?>
      <div class="gallery-grid">
        <?php foreach ($albums as $album): ?>
          <article class="card gallery-card">
            <a class="gallery-card__link" href="<?= h((string)$album['public_path']) ?>">
              <?php if ($album['cover_url'] !== ''): ?>
                <img class="gallery-card__image" src="<?= h((string)$album['cover_url']) ?>" alt="<?= h((string)$album['name']) ?>">
              <?php else: ?>
                <div class="gallery-card__placeholder" aria-hidden="true">Bez náhledu</div>
              <?php endif; ?>
              <div class="card__body">
                <h2 class="card__title"><?= h((string)$album['name']) ?></h2>
                <p class="meta-row meta-row--tight">
                  <span><?= h((string)$album['photo_count_label']) ?></span>
                  <?php if ((int)$album['sub_count'] > 0): ?>
                    <span><?= h((string)$album['sub_count_label']) ?></span>
                  <?php endif; ?>
                </p>
                <?php if (!empty($album['description'])): ?>
                  <p><?= h((string)$album['description']) ?></p>
                <?php endif; ?>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($pagerHtml !== ''): ?>
        <div class="listing-shell__pager">
          <?= $pagerHtml ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
