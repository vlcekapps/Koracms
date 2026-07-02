<?php
$items = $items ?? [];
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="download-series-heading">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Série ke stažení</p>
        <h1 id="download-series-heading" class="section-title section-title--hero"><?= h((string)$series['title']) ?></h1>
      </div>
    </div>

    <?php if (trim((string)($series['description'] ?? '')) !== ''): ?>
      <div class="prose prose--lead">
        <?= renderContent((string)$series['description']) ?>
      </div>
    <?php endif; ?>

    <?php if ($items === []): ?>
      <p class="empty-state">V této sérii zatím nejsou žádné veřejně dostupné položky.</p>
    <?php else: ?>
      <section aria-labelledby="download-series-items-title">
        <h2 id="download-series-items-title" class="section-title section-title--compact">Verze v této sérii</h2>
        <div class="card-grid card-grid--compact">
          <?php foreach ($items as $download): ?>
            <?php $downloadTitleId = 'download-series-card-title-' . (int)$download['id']; ?>
            <article class="card card--rich" aria-labelledby="<?= h($downloadTitleId) ?>">
              <?php if ($download['image_url'] !== ''): ?>
                <a class="card__media" href="<?= h(downloadPublicPath($download)) ?>">
                  <img src="<?= h((string)$download['image_url']) ?>" alt="<?= h((string)$download['title']) ?>">
                </a>
              <?php endif; ?>
              <div class="card__body">
                <p class="card__eyebrow">
                  <?= h((string)$download['download_type_label']) ?>
                  <?php if ((int)$download['is_current_version'] === 1): ?>
                    · Aktuální verze
                  <?php endif; ?>
                </p>
                <h3 id="<?= h($downloadTitleId) ?>" class="card__title">
                  <a href="<?= h(downloadPublicPath($download)) ?>"><?= h((string)$download['title']) ?></a>
                </h3>
                <p class="meta-row meta-row--tight">
                  <?php if ($download['version_label'] !== ''): ?>
                    <span>Verze <?= h((string)$download['version_label']) ?></span>
                  <?php endif; ?>
                  <?php if ($download['release_date_label'] !== ''): ?>
                    <span>Vydáno <?= h((string)$download['release_date_label']) ?></span>
                  <?php endif; ?>
                  <?php if ($download['platform_label'] !== ''): ?>
                    <span><?= h((string)$download['platform_label']) ?></span>
                  <?php endif; ?>
                  <?php if ((int)$download['file_size'] > 0): ?>
                    <span><?= h(formatFileSize((int)$download['file_size'])) ?></span>
                  <?php endif; ?>
                </p>
                <?php if ($download['excerpt_plain'] !== ''): ?>
                  <p class="card__description"><?= h((string)$download['excerpt_plain']) ?></p>
                <?php endif; ?>
                <p><a class="section-link" href="<?= h(downloadPublicPath($download)) ?>">Zobrazit položku <span aria-hidden="true">→</span></a></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <p class="admin-fieldset-spaced"><a href="<?= h(BASE_URL . '/downloads/index.php') ?>"><span aria-hidden="true">←</span> Zpět na přehled ke stažení</a></p>
  </section>
</div>
