<?php
$apps = is_array($apps ?? null) ? $apps : [];
$query = (string)($query ?? '');
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="appmarket-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Android aplikace</p>
        <h1 id="appmarket-title" class="section-title section-title--hero">Aplikace</h1>
      </div>
    </div>

    <form class="filter-bar" role="search" action="<?= h(BASE_URL . '/appmarket/index.php') ?>" method="get"
          aria-labelledby="appmarket-search-legend">
      <fieldset class="filter-bar__fieldset">
        <legend id="appmarket-search-legend" class="filter-bar__legend">Hledat v aplikacích</legend>
        <div class="form-group">
          <label for="appmarket-query">Název, účel nebo applicationId</label>
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
              <p class="meta-row meta-row--tight">
                <span>Verze <?= h((string)$app['version_name']) ?></span>
                <span><?= h(formatFileSize((int)$app['apk_size'])) ?></span>
                <span><?= h((string)$app['download_count_label']) ?></span>
              </p>
              <div class="card__actions">
                <a class="section-link" href="<?= h(appmarketAppPath($app)) ?>">Detail aplikace <span aria-hidden="true">→</span></a>
                <a class="section-link" href="<?= h(appmarketDownloadPath($app, (int)$app['version_code'])) ?>">Stáhnout APK <span aria-hidden="true">→</span></a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
