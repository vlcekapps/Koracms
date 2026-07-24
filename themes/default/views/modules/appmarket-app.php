<?php
$releases = is_array($releases ?? null) ? $releases : [];
$screenshots = is_array($screenshots ?? null) ? $screenshots : [];
$latestRelease = is_array($latestRelease ?? null) ? $latestRelease : null;
?>
<div class="page-stack page-stack--detail">
  <article class="surface surface--hero" aria-labelledby="appmarket-app-title">
    <div class="article-shell article-shell--sidebar">
      <div class="article-shell__content">
        <p class="section-kicker">Android aplikace</p>
        <h1 id="appmarket-app-title" class="section-title section-title--hero"><?= h((string)$app['name']) ?></h1>
        <p class="section-subtitle"><?= h((string)$app['short_description']) ?></p>
        <p class="meta-row"><span><code><?= h((string)$app['package_id']) ?></code></span><?php if ((string)$app['license_label'] !== ''): ?>, <span><?= h((string)$app['license_label']) ?></span><?php endif; ?></p>
        <?php if ($latestRelease !== null): ?>
          <div class="button-row button-row--start">
            <a class="btn" href="<?= h(appmarketDownloadPath($app, (int)$latestRelease['version_code'])) ?>">Stáhnout verzi <?= h((string)$latestRelease['version_name']) ?></a>
            <a class="btn btn-secondary" href="<?= h(appmarketReleasePath($app, (int)$latestRelease['version_code'])) ?>">Podrobnosti vydání</a>
          </div>
        <?php endif; ?>
      </div>
      <?php if ((string)$app['icon_url'] !== ''): ?>
        <div class="article-shell__aside">
          <img src="<?= h((string)$app['icon_url']) ?>" alt="<?= h((string)$app['icon_alt']) ?>">
        </div>
      <?php endif; ?>
    </div>
  </article>

  <section class="surface" aria-labelledby="appmarket-description-heading">
    <div class="article-shell">
      <h2 id="appmarket-description-heading" class="section-title">O aplikaci</h2>
      <?php if (trim((string)$app['description']) !== ''): ?>
        <div class="prose"><?= renderContent((string)$app['description']) ?></div>
      <?php else: ?>
        <p><?= h((string)$app['short_description']) ?></p>
      <?php endif; ?>

      <?php if ((string)$app['website_url'] !== '' || (string)$app['support_url'] !== '' || (string)$app['privacy_url'] !== ''): ?>
        <h3>Další informace</h3>
        <ul class="link-list">
          <?php if ((string)$app['website_url'] !== ''): ?><li><a href="<?= h((string)$app['website_url']) ?>">Web aplikace</a></li><?php endif; ?>
          <?php if ((string)$app['support_url'] !== ''): ?><li><a href="<?= h((string)$app['support_url']) ?>">Podpora</a></li><?php endif; ?>
          <?php if ((string)$app['privacy_url'] !== ''): ?><li><a href="<?= h((string)$app['privacy_url']) ?>">Ochrana soukromí</a></li><?php endif; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($screenshots !== []): ?>
    <section class="surface" aria-labelledby="appmarket-screenshots-heading">
      <div class="article-shell">
        <h2 id="appmarket-screenshots-heading" class="section-title">Snímky obrazovky</h2>
        <div class="card-grid">
          <?php foreach ($screenshots as $screenshot): ?>
            <?php $captionId = 'appmarket-screenshot-caption-' . (int)$screenshot['id']; ?>
            <figure class="card" aria-labelledby="<?= h($captionId) ?>">
              <img src="<?= h((string)$screenshot['url']) ?>" alt="<?= h((string)$screenshot['alt']) ?>">
              <?php if (trim((string)$screenshot['caption']) !== ''): ?>
                <figcaption id="<?= h($captionId) ?>"><?= h((string)$screenshot['caption']) ?></figcaption>
              <?php else: ?>
                <figcaption id="<?= h($captionId) ?>" class="sr-only">Snímek obrazovky: <?= h((string)$screenshot['alt']) ?></figcaption>
              <?php endif; ?>
            </figure>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <section class="surface" aria-labelledby="appmarket-releases-heading">
    <div class="article-shell">
      <h2 id="appmarket-releases-heading" class="section-title">Vydání</h2>
      <ul class="link-list">
        <?php foreach ($releases as $release): ?>
          <li class="link-list__item">
            <a class="link-list__title" href="<?= h(appmarketReleasePath($app, (int)$release['version_code'])) ?>">Verze <?= h((string)$release['version_name']) ?></a>
            <p class="meta-row meta-row--tight">
              <span>versionCode <?= (int)$release['version_code'] ?></span>
              <?php if ((string)$release['published_at_label'] !== ''): ?><span><?= h((string)$release['published_at_label']) ?></span><?php endif; ?>
              <span><?= h(formatFileSize((int)$release['apk_size'])) ?></span>
              <span><?= h((string)$release['download_count_label']) ?></span>
            </p>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="surface" aria-labelledby="appmarket-back-heading">
    <div class="article-shell">
      <h2 id="appmarket-back-heading" class="sr-only">Další navigace</h2>
      <p><a href="<?= h(BASE_URL . '/aplikace') ?>"><span aria-hidden="true">←</span> Zpět na přehled aplikací</a></p>
    </div>
  </section>
</div>
