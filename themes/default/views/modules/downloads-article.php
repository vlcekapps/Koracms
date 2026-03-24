<div class="page-stack page-stack--detail">
  <article class="surface surface--hero">
    <div class="article-shell">
      <div class="article-shell__content">
        <p class="section-kicker"><?= h((string)$download['download_type_label']) ?></p>
        <h1 class="section-title section-title--hero"><?= h((string)$download['title']) ?></h1>

        <p class="meta-row">
          <?php if ($download['category_name'] !== ''): ?>
            <span><?= h((string)$download['category_name']) ?></span>
          <?php endif; ?>
          <?php if ($download['version_label'] !== ''): ?>
            <span>Verze <?= h((string)$download['version_label']) ?></span>
          <?php endif; ?>
          <?php if ($download['platform_label'] !== ''): ?>
            <span><?= h((string)$download['platform_label']) ?></span>
          <?php endif; ?>
          <?php if ($download['license_label'] !== ''): ?>
            <span>Licence: <?= h((string)$download['license_label']) ?></span>
          <?php endif; ?>
        </p>

        <?php if ($download['excerpt_plain'] !== ''): ?>
          <p class="section-subtitle"><?= h((string)$download['excerpt_plain']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </article>

  <?php if ($download['image_url'] !== ''): ?>
    <section class="surface board-detail__hero" aria-labelledby="download-preview-title">
      <div class="article-shell">
        <h2 id="download-preview-title" class="sr-only">Náhled</h2>
        <img class="board-detail__image" src="<?= h((string)$download['image_url']) ?>" alt="">
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
      </div>

      <aside class="article-shell__aside">
        <section class="info-card" aria-labelledby="download-info-title">
          <h2 id="download-info-title" class="section-title section-title--compact">Praktické informace</h2>
          <dl class="info-list">
            <?php if ($download['version_label'] !== ''): ?>
              <div><dt>Verze</dt><dd><?= h((string)$download['version_label']) ?></dd></div>
            <?php endif; ?>
            <?php if ($download['platform_label'] !== ''): ?>
              <div><dt>Platforma</dt><dd><?= h((string)$download['platform_label']) ?></dd></div>
            <?php endif; ?>
            <?php if ($download['license_label'] !== ''): ?>
              <div><dt>Licence</dt><dd><?= h((string)$download['license_label']) ?></dd></div>
            <?php endif; ?>
            <?php if ((int)$download['file_size'] > 0): ?>
              <div><dt>Velikost</dt><dd><?= h(formatFileSize((int)$download['file_size'])) ?></dd></div>
            <?php endif; ?>
            <div><dt>Aktualizováno</dt><dd><?= formatCzechDate((string)($download['updated_at'] ?? $download['created_at'])) ?></dd></div>
          </dl>

          <div class="stack-actions" style="margin-top:1rem">
            <?php if ($download['has_file']): ?>
              <a class="btn" href="<?= moduleFileUrl('downloads', (int)$download['id']) ?>"
                 download="<?= h((string)$download['original_name']) ?>">Stáhnout soubor</a>
            <?php endif; ?>
            <?php if ($download['has_external_url']): ?>
              <a class="btn btn-secondary" href="<?= h((string)$download['external_url']) ?>" target="_blank" rel="noopener noreferrer">Otevřít externí odkaz</a>
            <?php endif; ?>
          </div>
        </section>
      </aside>
    </div>
  </section>

  <section class="surface">
    <div class="article-shell">
      <p><a href="<?= h(BASE_URL . '/downloads/index.php') ?>"><span aria-hidden="true">←</span> Zpět na ke stažení</a></p>
    </div>
  </section>
</div>
