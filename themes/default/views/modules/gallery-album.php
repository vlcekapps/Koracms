<div class="listing-shell">
  <section class="surface surface--accent" aria-labelledby="gallery-album-title">
    <nav aria-label="Drobečková navigace">
      <ol class="breadcrumb-list">
        <li><a href="<?= BASE_URL ?>/gallery/index.php">Galerie</a></li>
        <?php foreach ($trail as $index => $crumb): ?>
          <?php $isLast = $index === count($trail) - 1; ?>
          <li<?= $isLast ? ' aria-current="page"' : '' ?>>
            <?php if ($isLast): ?>
              <?= h($crumb['name']) ?>
            <?php else: ?>
              <a href="<?= h((string)$crumb['public_path']) ?>"><?= h($crumb['name']) ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </nav>

    <p class="section-kicker">Album</p>
    <h1 id="gallery-album-title" class="section-title section-title--hero"><?= h($album['name']) ?></h1>
    <?php if (!empty($album['description'])): ?>
      <p class="section-subtitle"><?= h($album['description']) ?></p>
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
                <img class="gallery-card__image" src="<?= h($subAlbum['cover_url']) ?>" alt="<?= h($subAlbum['name']) ?>">
              <?php else: ?>
                <div class="gallery-card__placeholder" aria-hidden="true">Bez náhledu</div>
              <?php endif; ?>
              <div class="card__body">
                <h3 class="card__title"><?= h($subAlbum['name']) ?></h3>
                <p class="meta-row meta-row--tight">
                  <span><?= h($subAlbum['photo_count_label']) ?></span>
                  <?php if ($subAlbum['sub_count'] > 0): ?>
                    <span><?= h($subAlbum['sub_count_label']) ?></span>
                  <?php endif; ?>
                </p>
                <?php if (!empty($subAlbum['description'])): ?>
                  <p><?= h($subAlbum['description']) ?></p>
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
              <img class="gallery-card__image" src="<?= h((string)$photo['thumb_url']) ?>"
                   alt="<?= h($photo['label']) ?>">
            </a>
            <?php if ($photo['title'] !== ''): ?>
              <figcaption class="gallery-photo-card__caption"><?= h($photo['title']) ?></figcaption>
            <?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (empty($subAlbums) && empty($photos)): ?>
    <section class="surface">
      <p class="empty-state">Toto album je zatím prázdné.</p>
    </section>
  <?php endif; ?>
</div>
