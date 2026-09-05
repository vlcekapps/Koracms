<?php
$apps = is_array($apps ?? null) ? $apps : [];
$query = (string)($query ?? '');
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="appmarket-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Software ke stažení</p>
        <h1 id="appmarket-title" class="section-title section-title--hero">Katalog softwaru</h1>
        <p class="section-subtitle">Vyberte aplikaci a vydání pro svou platformu. U každého vydání najdete požadavky, seznam změn a kontrolní součet souboru.</p>
      </div>
    </div>

    <form class="filter-bar" role="search" action="<?= h(BASE_URL . '/appmarket/index.php') ?>" method="get"
          aria-labelledby="appmarket-search-legend">
      <fieldset class="filter-bar__fieldset">
        <legend id="appmarket-search-legend" class="filter-bar__legend">Hledat v aplikacích</legend>
        <div class="form-group">
          <label for="appmarket-query">Název, účel nebo identifikátor aplikace</label>
          <input class="form-control" type="search" id="appmarket-query" name="q" value="<?= h($query) ?>">
        </div>
        <div class="button-row button-row--start">
          <button class="button-primary" type="submit">Hledat</button>
          <?php if ($query !== ''): ?>
            <a class="button-secondary" href="<?= h(BASE_URL . '/aplikace') ?>">Zrušit hledání</a>
          <?php endif; ?>
        </div>
      </fieldset>
    </form>

    <?php if ($apps === []): ?>
      <p class="empty-state"><?= $query !== '' ? 'Hledání neodpovídá žádné zveřejněné aplikaci.' : 'Zatím nebyla zveřejněna žádná aplikace.' ?></p>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($apps as $app): ?>
          <?php $headingId = 'appmarket-app-' . (int)$app['id']; ?>
          <article class="card card--rich" aria-labelledby="<?= h($headingId) ?>">
            <?php if ((string)$app['icon_url'] !== ''): ?>
              <a class="card__media" href="<?= h(appmarketAppPath($app)) ?>">
                <img src="<?= h((string)$app['icon_url']) ?>" alt="<?= h((string)$app['icon_alt']) ?>">
              </a>
            <?php endif; ?>
            <div class="card__body">
              <?php if ((int)$app['is_featured'] === 1): ?><p class="card__eyebrow">Doporučená aplikace</p><?php endif; ?>
              <h2 id="<?= h($headingId) ?>" class="card__title"><a href="<?= h(appmarketAppPath($app)) ?>"><?= h((string)$app['name']) ?></a></h2>
              <p class="card__description"><?= h((string)$app['short_description']) ?></p>
              <h3>Vydání podle platformy</h3>
              <ul class="link-list">
                <?php foreach ($app['preferred_releases'] as $preferredRelease): ?>
                  <li class="link-list__item">
                    <p class="meta-row meta-row--tight">
                      <span><?= h((string)$preferredRelease['platform_label']) ?></span>
                      <span>Verze <?= h((string)$preferredRelease['version_name']) ?></span>
                      <span><?= h((string)$preferredRelease['release_channel_label']) ?> kanál</span>
                      <span><?= h(strtoupper((string)$preferredRelease['file_extension'])) ?></span>
                      <span><?= h(formatFileSize((int)$preferredRelease['file_size'])) ?></span>
                      <span><?= h((string)$preferredRelease['download_count_label']) ?></span>
                    </p>
                    <div class="card__actions">
                      <a class="section-link" href="<?= h(appmarketDownloadPath($app, (int)$preferredRelease['version_code'])) ?>">Stáhnout <?= h(strtoupper((string)$preferredRelease['file_extension'])) ?> pro <?= h((string)$preferredRelease['platform_label']) ?><span class="sr-only">: <?= h((string)$app['name']) ?> <?= h((string)$preferredRelease['version_name']) ?></span></a>
                      <a class="section-link" href="<?= h(appmarketReleasePath($app, (int)$preferredRelease['version_code'])) ?>">Požadavky a podrobnosti<span class="sr-only">: <?= h((string)$app['name']) ?> <?= h((string)$preferredRelease['version_name']) ?> pro <?= h((string)$preferredRelease['platform_label']) ?></span></a>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
              <div class="card__actions">
                <a class="section-link" href="<?= h(appmarketAppPath($app)) ?>">Detail aplikace a historie verzí<span class="sr-only">: <?= h((string)$app['name']) ?></span> <span aria-hidden="true">→</span></a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
