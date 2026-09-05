<?php
$releases = is_array($releases ?? null) ? $releases : [];
$screenshots = is_array($screenshots ?? null) ? $screenshots : [];
$preferredReleases = is_array($preferredReleases ?? null) ? $preferredReleases : [];
?>
<div class="page-stack page-stack--detail">
  <article class="surface surface--hero" aria-labelledby="appmarket-app-title">
    <div class="article-shell article-shell--sidebar">
      <div class="article-shell__content">
        <p class="section-kicker">Software ke stažení</p>
        <h1 id="appmarket-app-title" class="section-title section-title--hero"><?= h((string)$app['name']) ?></h1>
        <p class="section-subtitle"><?= h((string)$app['short_description']) ?></p>
        <?php if ((string)$app['license_label'] !== ''): ?><p class="meta-row"><span>Licence: <?= h((string)$app['license_label']) ?></span></p><?php endif; ?>
        <?php if ($preferredReleases !== []): ?>
          <div class="button-row button-row--start">
            <a class="btn" href="#appmarket-downloads-heading">Vybrat verzi ke stažení</a>
            <a class="btn btn-secondary" href="#appmarket-releases-heading">Historie verzí</a>
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

  <?php if ($preferredReleases !== []): ?>
    <section class="surface" aria-labelledby="appmarket-downloads-heading">
      <div class="article-shell">
        <h2 id="appmarket-downloads-heading" class="section-title">Stažení podle platformy</h2>
        <p>Pro každou platformu nabízíme nejnovější stabilní vydání, případně beta vydání, pokud stabilní není dostupné. Před stažením zkontrolujte systémové požadavky.</p>
        <div class="card-grid">
          <?php foreach ($preferredReleases as $preferredRelease): ?>
            <?php $platformHeadingId = 'appmarket-platform-' . (int)$preferredRelease['id']; ?>
            <article class="card" aria-labelledby="<?= h($platformHeadingId) ?>">
              <div class="card__body">
                <h3 id="<?= h($platformHeadingId) ?>" class="card__title"><?= h((string)$preferredRelease['platform_label']) ?></h3>
                <p class="meta-row meta-row--tight">
                  <span>Verze <?= h((string)$preferredRelease['version_name']) ?></span>
                  <span><?= h((string)$preferredRelease['release_channel_label']) ?> kanál</span>
                  <span><?= h(strtoupper((string)$preferredRelease['file_extension'])) ?></span>
                  <span><?= h(formatFileSize((int)$preferredRelease['file_size'])) ?></span>
                  <span><?= h((string)$preferredRelease['download_count_label']) ?></span>
                </p>
                <h4>Systémové požadavky</h4>
                <p class="break-long-token"><?= trim((string)$preferredRelease['system_requirements']) !== '' ? nl2br(h((string)$preferredRelease['system_requirements'])) : 'Požadavky nejsou uvedeny. Před instalací ověřte kompatibilitu u autora aplikace.' ?></p>
                <div class="button-row button-row--start">
                  <a class="btn" href="<?= h(appmarketDownloadPath($app, (int)$preferredRelease['version_code'])) ?>">Stáhnout <?= h(strtoupper((string)$preferredRelease['file_extension'])) ?> pro <?= h((string)$preferredRelease['platform_label']) ?><span class="sr-only">, verze <?= h((string)$preferredRelease['version_name']) ?></span></a>
                  <a class="btn btn-secondary" href="<?= h(appmarketReleasePath($app, (int)$preferredRelease['version_code'])) ?>">Podrobnosti vydání<span class="sr-only"> <?= h((string)$preferredRelease['version_name']) ?> pro <?= h((string)$preferredRelease['platform_label']) ?></span></a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

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
      <h2 id="appmarket-releases-heading" class="section-title">Historie verzí</h2>
      <p>Všechna dostupná vydání seřazená od nejnovějšího. Platforma a distribuční kanál jsou uvedeny u každé verze.</p>
      <ul class="link-list">
        <?php foreach ($releases as $release): ?>
          <li class="link-list__item">
            <a class="link-list__title" href="<?= h(appmarketReleasePath($app, (int)$release['version_code'])) ?>">Verze <?= h((string)$release['version_name']) ?> pro <?= h((string)$release['platform_label']) ?></a>
            <p class="meta-row meta-row--tight">
              <span><?= h((string)$release['release_channel_label']) ?> kanál</span>
              <?php if (($release['metadata_source'] ?? 'apk') !== 'manual'): ?><span>versionCode <?= (int)$release['version_code'] ?></span><?php endif; ?>
              <?php if ((string)$release['published_at_label'] !== ''): ?><span><?= h((string)$release['published_at_label']) ?></span><?php endif; ?>
              <span><?= h(strtoupper((string)$release['file_extension'])) ?></span>
              <span><?= h(formatFileSize((int)$release['file_size'])) ?></span>
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
