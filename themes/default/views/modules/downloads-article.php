<?php
$otherVersions = $otherVersions ?? [];
$currentVersion = $currentVersion ?? null;
?>
<div class="page-stack page-stack--detail">
  <article class="surface surface--hero" aria-labelledby="download-title">
    <div class="article-shell">
      <div class="article-shell__content">
        <p class="section-kicker"><?= h((string)$download['download_type_label']) ?></p>
        <h1 id="download-title" class="section-title section-title--hero"><?= h((string)$download['title']) ?></h1>

        <p class="meta-row">
          <?php if ((int)$download['is_featured'] === 1): ?>
            <span>Doporučená položka</span>
          <?php endif; ?>
          <?php if ($download['category_name'] !== ''): ?>
            <span>
              <?php if (downloadCategorySlug((string)($download['category_slug'] ?? '')) !== ''): ?>
                <a href="<?= h(downloadCategoryPath(['id' => $download['dl_category_id'] ?? 0, 'slug' => $download['category_slug']])) ?>"><?= h((string)$download['category_name']) ?></a>
              <?php else: ?>
                <?= h((string)$download['category_name']) ?>
              <?php endif; ?>
            </span>
          <?php endif; ?>
          <?php if ($download['series_title'] !== '' && $download['series_slug'] !== ''): ?>
            <span><a href="<?= h(downloadSeriesPath(['id' => $download['download_series_id'] ?? 0, 'slug' => $download['series_slug']])) ?>"><?= h((string)$download['series_title']) ?></a></span>
          <?php endif; ?>
          <?php if ($download['version_label'] !== ''): ?>
            <span>Verze <?= h((string)$download['version_label']) ?></span>
          <?php endif; ?>
          <?php if ($download['release_date_label'] !== ''): ?>
            <span>Vydáno <?= h((string)$download['release_date_label']) ?></span>
          <?php endif; ?>
          <?php if ($download['platform_label'] !== ''): ?>
            <span><?= h((string)$download['platform_label']) ?></span>
          <?php endif; ?>
          <?php if ($download['license_label'] !== ''): ?>
            <span>Licence: <?= h((string)$download['license_label']) ?></span>
          <?php endif; ?>
          <?php if ($download['has_file']): ?>
            <span><?= h((string)$download['download_count_label']) ?></span>
          <?php endif; ?>
          <?php if ($download['has_external_url']): ?>
            <span><?= h((string)$download['external_click_count_label']) ?></span>
          <?php endif; ?>
        </p>

        <?php if ($download['excerpt_plain'] !== ''): ?>
          <p class="section-subtitle"><?= h((string)$download['excerpt_plain']) ?></p>
        <?php endif; ?>

        <?php if (is_array($currentVersion)): ?>
          <p class="status-message status-message--info" role="status">
            Tato položka není označená jako aktuální verze.
            <a href="<?= h(downloadPublicPath($currentVersion)) ?>">Přejít na aktuální verzi <?= h((string)$currentVersion['title']) ?></a>.
          </p>
        <?php endif; ?>
      </div>
    </div>
  </article>

  <?php if ($download['image_url'] !== ''): ?>
    <section class="surface board-detail__hero" aria-labelledby="download-preview-title">
      <div class="article-shell">
        <h2 id="download-preview-title" class="sr-only">Náhled</h2>
        <img class="board-detail__image" src="<?= h((string)$download['image_url']) ?>" alt="<?= h((string)$download['title']) ?>">
      </div>
    </section>
  <?php endif; ?>

  <section class="surface" aria-labelledby="download-info-title">
    <div class="article-shell article-shell--sidebar">
      <div class="article-shell__content">
        <?php if (trim((string)$download['description']) !== ''): ?>
          <div class="prose">
            <?= renderContent((string)$download['description']) ?>
          </div>
        <?php else: ?>
          <p class="empty-state">Tato položka zatím nemá doplněný podrobný popis.</p>
        <?php endif; ?>

        <?php if ($download['has_requirements']): ?>
          <section class="surface surface--subsection" aria-labelledby="download-requirements-title">
            <h2 id="download-requirements-title" class="section-title section-title--compact">Požadavky a kompatibilita</h2>
            <div class="prose"><p><?= nl2br(h((string)$download['requirements'])) ?></p></div>
          </section>
        <?php endif; ?>

        <?php if ($otherVersions !== []): ?>
          <section class="surface surface--subsection" aria-labelledby="download-versions-title">
            <h2 id="download-versions-title" class="section-title section-title--compact">Další verze ke stažení</h2>
            <ul class="link-list">
              <?php foreach ($otherVersions as $version): ?>
                <li class="link-list__item">
                  <a class="link-list__title" href="<?= h(downloadPublicPath($version)) ?>"><?= h((string)$version['title']) ?></a>
                  <p class="meta-row meta-row--tight">
                    <?php if ((int)$version['is_current_version'] === 1): ?>
                      <span>Aktuální verze</span>
                    <?php endif; ?>
                    <?php if ($version['version_label'] !== ''): ?>
                      <span>Verze <?= h((string)$version['version_label']) ?></span>
                    <?php endif; ?>
                    <?php if ($version['release_date_label'] !== ''): ?>
                      <span><?= h((string)$version['release_date_label']) ?></span>
                    <?php endif; ?>
                    <?php if ($version['platform_label'] !== ''): ?>
                      <span><?= h((string)$version['platform_label']) ?></span>
                    <?php endif; ?>
                  </p>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>
      </div>

      <aside class="article-shell__aside" aria-labelledby="download-info-title">
        <section class="info-card" aria-labelledby="download-info-title">
          <h2 id="download-info-title" class="section-title section-title--compact">Praktické informace</h2>
          <dl class="info-list">
            <?php if ($download['version_label'] !== ''): ?>
              <div><dt>Verze</dt><dd><?= h((string)$download['version_label']) ?></dd></div>
            <?php endif; ?>
            <?php if ($download['release_date_label'] !== ''): ?>
              <div><dt>Datum vydání</dt><dd><?= h((string)$download['release_date_label']) ?></dd></div>
            <?php endif; ?>
            <?php if ($download['platform_label'] !== ''): ?>
              <div><dt>Platforma</dt><dd><?= h((string)$download['platform_label']) ?></dd></div>
            <?php endif; ?>
            <?php if ($download['license_label'] !== ''): ?>
              <div><dt>Licence</dt><dd><?= h((string)$download['license_label']) ?></dd></div>
            <?php endif; ?>
            <?php if ($download['series_title'] !== '' && $download['series_slug'] !== ''): ?>
              <div><dt>Série</dt><dd><a href="<?= h(downloadSeriesPath(['id' => $download['download_series_id'] ?? 0, 'slug' => $download['series_slug']])) ?>"><?= h((string)$download['series_title']) ?></a></dd></div>
            <?php endif; ?>
            <?php if ((int)$download['file_size'] > 0): ?>
              <div><dt>Velikost</dt><dd><?= h(formatFileSize((int)$download['file_size'])) ?></dd></div>
            <?php endif; ?>
            <?php if ($download['has_checksum']): ?>
              <div><dt>SHA-256</dt><dd><code><?= h((string)$download['checksum_sha256']) ?></code></dd></div>
            <?php endif; ?>
            <?php if ($download['has_file']): ?>
              <div><dt>Stažení souboru</dt><dd><?= h((string)$download['download_count_label']) ?></dd></div>
            <?php endif; ?>
            <?php if ($download['has_external_url']): ?>
              <div><dt>Otevření externího zdroje</dt><dd><?= h((string)$download['external_click_count_label']) ?></dd></div>
            <?php endif; ?>
            <div><dt>Aktualizováno</dt><dd><?= formatCzechDate((string)($download['updated_at'] ?? $download['created_at'])) ?></dd></div>
          </dl>

          <div class="stack-actions stack-actions--spaced">
            <?php if ($download['has_file']): ?>
              <a class="btn" href="<?= moduleFileUrl('downloads', (int)$download['id']) ?>"
                 download="<?= h((string)$download['original_name']) ?>">Stáhnout soubor</a>
            <?php endif; ?>
            <?php if ($download['has_external_url']): ?>
              <a class="btn btn-secondary" href="<?= h(downloadExternalOpenPath($download)) ?>" target="_blank" rel="nofollow noopener noreferrer">Otevřít externí odkaz na <?= h((string)$download['external_host_label']) ?><?= newWindowLinkSrOnlySuffix() ?></a>
            <?php endif; ?>
            <?php if ($download['has_project_url']): ?>
              <a class="btn btn-secondary" href="<?= h((string)$download['project_url']) ?>" target="_blank" rel="noopener noreferrer">Domovská stránka projektu<?= newWindowLinkSrOnlySuffix() ?></a>
            <?php endif; ?>
          </div>
        </section>
      </aside>
    </div>
  </section>

  <section class="surface" aria-labelledby="download-actions-title">
    <div class="article-shell">
      <h2 id="download-actions-title" class="sr-only">Další akce ke stažení</h2>
      <p><a href="<?= h(BASE_URL . '/downloads/index.php') ?>"><span aria-hidden="true">←</span> Zpět na přehled ke stažení</a></p>
    </div>
  </section>
</div>
