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
    <nav aria-label="Drobečková navigace">
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

    <figure class="photo-figure">
      <img class="photo-figure__image" src="<?= h((string)$photo['image_url']) ?>" alt="<?= h($photoTitle) ?>">
      <?php if ($photo['title'] !== ''): ?>
        <figcaption class="photo-figure__caption"><?= h((string)$photo['title']) ?></figcaption>
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

      <a class="button-secondary" href="<?= h($backPath) ?>">Zpět do alba</a>
      <?php if ($copyUrl !== ''): ?>
        <button type="button" class="button-secondary" data-copy-gallery-link="<?= h($copyUrl) ?>">Kopírovat odkaz</button>
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
        <?php foreach ($relatedPhotos as $relatedPhoto): ?>
          <figure class="card gallery-photo-card">
            <a class="gallery-card__link" href="<?= h((string)$relatedPhoto['public_path']) ?>">
              <img class="gallery-card__image" src="<?= h((string)$relatedPhoto['thumb_url']) ?>" alt="<?= h((string)$relatedPhoto['label']) ?>">
            </a>
            <?php if ($relatedPhoto['title'] !== ''): ?>
              <figcaption class="gallery-photo-card__caption"><?= h((string)$relatedPhoto['title']) ?></figcaption>
            <?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php if ($copyUrl !== ''): ?>
  <script nonce="<?= cspNonce() ?>">
  (function () {
    var button = document.querySelector('[data-copy-gallery-link]');
    if (!button || !navigator.clipboard) {
      return;
    }

    button.addEventListener('click', function () {
      navigator.clipboard.writeText(button.getAttribute('data-copy-gallery-link') || '').then(function () {
        var original = button.textContent;
        button.textContent = 'Odkaz zkopírován';
        window.setTimeout(function () {
          button.textContent = original;
        }, 1800);
      });
    });
  })();
  </script>
<?php endif; ?>
