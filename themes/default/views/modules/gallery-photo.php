<div class="listing-shell">
  <section class="surface surface--accent" aria-labelledby="gallery-photo-title">
    <nav aria-label="Drobečková navigace">
      <ol class="breadcrumb-list">
        <li><a href="<?= BASE_URL ?>/gallery/index.php">Galerie</a></li>
        <?php foreach ($trail as $crumb): ?>
          <li><a href="<?= h((string)$crumb['public_path']) ?>"><?= h($crumb['name']) ?></a></li>
        <?php endforeach; ?>
        <li aria-current="page"><?= h($photoTitle) ?></li>
      </ol>
    </nav>

    <p class="section-kicker">Fotografie</p>
    <h1 id="gallery-photo-title" class="section-title section-title--hero"><?= h($photoTitle) ?></h1>

    <figure class="photo-figure">
      <img class="photo-figure__image" src="<?= h((string)$photo['image_url']) ?>" alt="<?= h($photoTitle) ?>">
      <?php if ($photo['title'] !== ''): ?>
        <figcaption class="photo-figure__caption"><?= h($photo['title']) ?></figcaption>
      <?php endif; ?>
    </figure>

    <nav class="button-row button-row--start" aria-label="Navigace mezi fotografiemi">
      <?php if ($prevPhoto !== null): ?>
        <a class="button-secondary" href="<?= h((string)$prevPhoto['public_path']) ?>">← Předchozí</a>
      <?php else: ?>
        <span class="button-secondary button-secondary--disabled" aria-hidden="true">← Předchozí</span>
      <?php endif; ?>

      <?php if ($nextPhoto !== null): ?>
        <a class="button-secondary" href="<?= h((string)$nextPhoto['public_path']) ?>">Následující →</a>
      <?php else: ?>
        <span class="button-secondary button-secondary--disabled" aria-hidden="true">Následující →</span>
      <?php endif; ?>

      <a class="button-secondary" href="<?= h((string)$album['public_path']) ?>">Zpět do alba</a>
    </nav>
  </section>
</div>
