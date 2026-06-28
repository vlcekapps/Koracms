<?php
$photo = $photo ?? [];
$album = $album ?? [];
$trail = $trail ?? [];
$photoTitle = (string)($photoTitle ?? ($photo['label'] ?? 'Fotografie'));
$prevPhoto = $prevPhoto ?? null;
$nextPhoto = $nextPhoto ?? null;
$relatedPhotos = $relatedPhotos ?? [];
$copyUrl = (string)($copyUrl ?? ($photo['public_url'] ?? ''));
$backPath = (string)($backPath ?? ($album['public_path'] ?? (BASE_URL . '/gallery/index.php')));
$photoPosition = $photoPosition ?? null;
$photoCount = (int)($photoCount ?? 0);
?>
<div class="listing-shell">
  <section class="surface surface--accent" aria-labelledby="gallery-photo-title">
    <h2 id="gallery-photo-breadcrumb-heading" class="sr-only">Drobečková navigace</h2>
    <nav aria-labelledby="gallery-photo-breadcrumb-heading">
      <ol class="breadcrumb-list">
        <li><a href="<?= BASE_URL ?>/gallery/index.php">Galerie</a></li>
        <?php foreach ($trail as $crumb): ?>
          <li><a href="<?= h((string)$crumb['public_path']) ?>"><?= h((string)$crumb['name']) ?></a></li>
        <?php endforeach; ?>
        <li aria-current="page"><?= h($photoTitle) ?></li>
      </ol>
    </nav>

    <p class="section-kicker">Fotografie</p>
    <h1 id="gallery-photo-title" class="section-title section-title--hero"><?= h($photoTitle) ?></h1>

    <p class="meta-row meta-row--tight">
      <span>Album <?= h((string)($album['name'] ?? 'Galerie')) ?></span>
      <?php if ($photoPosition !== null && $photoCount > 0): ?>
        <span>Fotografie <?= (int)$photoPosition ?> z <?= $photoCount ?></span>
      <?php endif; ?>
    </p>

    <figure class="photo-figure" aria-labelledby="gallery-photo-caption">
      <img class="photo-figure__image" src="<?= h((string)$photo['image_url']) ?>" alt="<?= h($photoTitle) ?>">
      <figcaption id="gallery-photo-caption" class="<?= $photo['title'] !== '' ? 'photo-figure__caption' : 'sr-only' ?>">
        <?= h($photo['title'] !== '' ? (string)$photo['title'] : $photoTitle) ?>
      </figcaption>
    </figure>

    <h2 id="gallery-photo-nav-heading" class="sr-only">Navigace mezi fotografiemi</h2>
    <nav class="button-row button-row--start" aria-labelledby="gallery-photo-nav-heading">
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

      <a class="button-secondary" href="<?= h($backPath) ?>">Zpět do alba</a>
      <?php if ($copyUrl !== ''): ?>
        <button type="button" class="button-secondary js-copy-link" data-url="<?= h($copyUrl) ?>">Kopírovat odkaz<span class="sr-only"> na fotografii</span></button>
      <?php endif; ?>
    </nav>
  </section>

  <?php if (!empty($relatedPhotos)): ?>
    <section class="surface" aria-labelledby="gallery-related-title">
      <div class="section-heading">
        <div>
          <h2 id="gallery-related-title" class="section-title">Další fotografie v albu</h2>
        </div>
      </div>

      <div class="gallery-grid gallery-grid--photos">
        <?php foreach ($relatedPhotos as $relatedIndex => $relatedPhoto): ?>
          <?php $relatedCaptionId = 'gallery-related-photo-caption-' . (int)($relatedPhoto['id'] ?? $relatedIndex); ?>
          <figure class="card gallery-photo-card" aria-labelledby="<?= h($relatedCaptionId) ?>">
            <a class="gallery-card__link" href="<?= h((string)$relatedPhoto['public_path']) ?>">
              <img class="gallery-card__image" src="<?= h((string)$relatedPhoto['thumb_url']) ?>" alt="<?= h((string)$relatedPhoto['label']) ?>">
            </a>
            <?php if ($relatedPhoto['title'] !== ''): ?>
              <figcaption id="<?= h($relatedCaptionId) ?>" class="gallery-photo-card__caption"><?= h((string)$relatedPhoto['title']) ?></figcaption>
            <?php else: ?>
              <figcaption id="<?= h($relatedCaptionId) ?>" class="sr-only"><?= h((string)$relatedPhoto['label']) ?></figcaption>
            <?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
