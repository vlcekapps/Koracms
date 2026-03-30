<?php
$album = $album ?? [];
$trail = $trail ?? [];
$subAlbums = $subAlbums ?? [];
$photos = $photos ?? [];
$searchQuery = (string)($searchQuery ?? '');
$filterSummary = is_array($filterSummary ?? null) ? $filterSummary : [];
$resultCountLabel = (string)($resultCountLabel ?? '');
$pagerHtml = (string)($pagerHtml ?? '');
$hasActiveFilters = !empty($hasActiveFilters);
$clearUrl = (string)($clearUrl ?? galleryAlbumPublicPath($album));
?>
<div class="listing-shell">
  <section class="surface surface--accent" aria-labelledby="gallery-album-title">
    <nav aria-label="Drobečková navigace">
      <ol class="breadcrumb-list">
        <li><a href="<?= BASE_URL ?>/gallery/index.php">Galerie</a></li>
        <?php foreach ($trail as $index => $crumb): ?>
          <?php $isLast = $index === count($trail) - 1; ?>
          <li<?= $isLast ? ' aria-current="page"' : '' ?>>
            <?php if ($isLast): ?>
              <?= h((string)$crumb['name']) ?>
            <?php else: ?>
              <a href="<?= h((string)$crumb['public_path']) ?>"><?= h((string)$crumb['name']) ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </nav>

    <p class="section-kicker">Album</p>
    <h1 id="gallery-album-title" class="section-title section-title--hero"><?= h((string)($album['name'] ?? 'Album')) ?></h1>
    <?php if (!empty($album['description'])): ?>
      <p class="section-subtitle"><?= h((string)$album['description']) ?></p>
    <?php endif; ?>

    <form class="filter-bar filter-bar--stack" action="<?= h(galleryAlbumPublicPath($album)) ?>" method="get">
      <fieldset class="filter-bar__fieldset">
        <legend class="filter-bar__legend">Hledat v albu</legend>

        <div class="filter-grid">
          <div class="form-group">
            <label for="gallery-album-search-q">Hledat v podalbech a fotografiích</label>
            <input
              id="gallery-album-search-q"
              class="form-control"
              type="search"
              name="q"
              value="<?= h($searchQuery) ?>"
              placeholder="Název podalba, titulek nebo název souboru"
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

    <?php if ($filterSummary !== []): ?>
      <p class="meta-row meta-row--tight">
        <strong>Aktivní filtry:</strong>
        <span><?= h(implode(', ', $filterSummary)) ?></span>
      </p>
    <?php endif; ?>

    <?php if ($resultCountLabel !== ''): ?>
      <p class="meta-row meta-row--tight">
        <span><?= h($resultCountLabel) ?></span>
      </p>
    <?php endif; ?>
  </section>

  <?php if (!empty($subAlbums)): ?>
    <section class="surface" aria-labelledby="gallery-subalbums-title">
      <div class="section-heading">
        <div>
          <h2 id="gallery-subalbums-title" class="section-title">Další alba</h2>
        </div>
      </div>

      <div class="gallery-grid">
        <?php foreach ($subAlbums as $subAlbum): ?>
          <article class="card gallery-card">
            <a class="gallery-card__link" href="<?= h((string)$subAlbum['public_path']) ?>">
              <?php if ($subAlbum['cover_url'] !== ''): ?>
                <img class="gallery-card__image" src="<?= h((string)$subAlbum['cover_url']) ?>" alt="<?= h((string)$subAlbum['name']) ?>">
              <?php else: ?>
                <div class="gallery-card__placeholder" aria-hidden="true">Bez náhledu</div>
              <?php endif; ?>
              <div class="card__body">
                <h3 class="card__title"><?= h((string)$subAlbum['name']) ?></h3>
                <p class="meta-row meta-row--tight">
                  <span><?= h((string)$subAlbum['photo_count_label']) ?></span>
                  <?php if ((int)$subAlbum['sub_count'] > 0): ?>
                    <span><?= h((string)$subAlbum['sub_count_label']) ?></span>
                  <?php endif; ?>
                </p>
                <?php if (!empty($subAlbum['description'])): ?>
                  <p><?= h((string)$subAlbum['description']) ?></p>
                <?php endif; ?>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($photos)): ?>
    <section class="surface" aria-labelledby="gallery-photos-title">
      <div class="section-heading">
        <div>
          <h2 id="gallery-photos-title" class="section-title">Fotografie</h2>
        </div>
      </div>

      <div class="gallery-grid gallery-grid--photos">
        <?php foreach ($photos as $photo): ?>
          <figure class="card gallery-photo-card">
            <a class="gallery-card__link" href="<?= h((string)$photo['public_path']) ?>">
              <img class="gallery-card__image" src="<?= h((string)$photo['thumb_url']) ?>" alt="<?= h((string)$photo['label']) ?>">
            </a>
            <?php if ($photo['title'] !== ''): ?>
              <figcaption class="gallery-photo-card__caption"><?= h((string)$photo['title']) ?></figcaption>
            <?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>

      <?php if ($pagerHtml !== ''): ?>
        <div class="listing-shell__pager">
          <?= $pagerHtml ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if (empty($subAlbums) && empty($photos)): ?>
    <section class="surface">
      <p class="empty-state"><?= h($hasActiveFilters ? 'Pro zadaný filtr se v tomto albu nic nenašlo.' : 'Toto album je zatím prázdné.') ?></p>
    </section>
  <?php endif; ?>
</div>
