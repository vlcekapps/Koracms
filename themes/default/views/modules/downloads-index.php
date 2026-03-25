<div class="listing-shell">
  <section class="surface" aria-labelledby="downloads-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Dokumenty, software a materiály</p>
        <h1 id="downloads-title" class="section-title section-title--hero">Ke stažení</h1>
      </div>
    </div>

    <?php if (empty($items)): ?>
      <p class="empty-state">Zatím tu nejsou žádné materiály ke stažení.</p>
    <?php else: ?>
      <div class="stack-sections">
        <?php $groupIndex = 0; foreach ($grouped as $category => $files): ?>
          <section aria-labelledby="downloads-group-<?= $groupIndex ?>">
            <?php if ($showCategoryHeadings): ?>
              <h2 id="downloads-group-<?= $groupIndex ?>" class="section-title section-title--compact"><?= h($category) ?></h2>
            <?php else: ?>
              <h2 id="downloads-group-<?= $groupIndex ?>" class="sr-only">Položky ke stažení</h2>
            <?php endif; ?>

            <div class="card-grid card-grid--compact">
              <?php foreach ($files as $download): ?>
                <article class="card card--rich">
                  <?php if ($download['image_url'] !== ''): ?>
                    <a class="card__media" href="<?= h(downloadPublicPath($download)) ?>">
                      <img src="<?= h((string)$download['image_url']) ?>" alt="">
                    </a>
                  <?php endif; ?>

                  <div class="card__body">
                    <p class="card__eyebrow"><?= h((string)$download['download_type_label']) ?></p>
                    <h3 class="card__title">
                      <a href="<?= h(downloadPublicPath($download)) ?>"><?= h((string)$download['title']) ?></a>
                    </h3>

                    <p class="meta-row meta-row--tight">
                      <?php if ($download['version_label'] !== ''): ?>
                        <span>Verze <?= h((string)$download['version_label']) ?></span>
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

                    <div class="card__actions">
                      <a class="section-link" href="<?= h(downloadPublicPath($download)) ?>">Detail položky <span aria-hidden="true">→</span></a>
                      <?php if ($download['has_file']): ?>
                        <a class="section-link" href="<?= moduleFileUrl('downloads', (int)$download['id']) ?>"
                           download="<?= h((string)$download['original_name']) ?>">Stáhnout soubor <span aria-hidden="true">→</span></a>
                      <?php elseif ($download['has_external_url']): ?>
                        <a class="section-link" href="<?= h((string)$download['external_url']) ?>" target="_blank" rel="noopener noreferrer">Otevřít odkaz <span aria-hidden="true">→</span></a>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php $groupIndex++; endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
