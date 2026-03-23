<div class="listing-shell">
  <section class="surface" aria-labelledby="downloads-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Soubory a dokumenty</p>
        <h1 id="downloads-title" class="section-title section-title--hero">Ke stažení</h1>
      </div>
    </div>

    <?php if (empty($items)): ?>
      <p class="empty-state">Zatím nejsou k dispozici žádné soubory ke stažení.</p>
    <?php else: ?>
      <div class="stack-sections">
        <?php $groupIndex = 0; foreach ($grouped as $category => $files): ?>
          <section aria-labelledby="downloads-group-<?= $groupIndex ?>">
            <?php if ($showCategoryHeadings): ?>
              <h2 id="downloads-group-<?= $groupIndex ?>" class="section-title section-title--compact"><?= h($category) ?></h2>
            <?php else: ?>
              <h2 id="downloads-group-<?= $groupIndex ?>" class="sr-only">Soubory ke stažení</h2>
            <?php endif; ?>

            <ul class="link-list">
              <?php foreach ($files as $file): ?>
                <li class="link-list__item download-item">
                  <?php if ($file['filename'] !== ''): ?>
                    <a class="link-list__title" href="<?= BASE_URL ?>/uploads/downloads/<?= rawurlencode($file['filename']) ?>"
                       download="<?= h($file['original_name']) ?>">
                      <?= h($file['title']) ?>
                    </a>
                  <?php else: ?>
                    <strong class="link-list__title"><?= h($file['title']) ?></strong>
                  <?php endif; ?>

                  <p class="meta-row meta-row--tight">
                    <?php if ($file['file_size'] > 0): ?>
                      <span><?= h(formatFileSize($file['file_size'])) ?></span>
                    <?php endif; ?>
                    <time datetime="<?= h(str_replace(' ', 'T', $file['created_at'])) ?>"><?= formatCzechDate($file['created_at']) ?></time>
                  </p>

                  <?php if (!empty($file['description'])): ?>
                    <div class="prose download-item__description"><?= renderContent($file['description']) ?></div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php $groupIndex++; endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
