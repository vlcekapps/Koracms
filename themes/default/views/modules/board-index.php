<div class="listing-shell">
  <section class="surface" aria-labelledby="board-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Veřejné dokumenty</p>
        <h1 id="board-title" class="section-title section-title--hero">Úřední deska</h1>
      </div>
    </div>

    <?php if (empty($current) && empty($archive)): ?>
      <p class="empty-state">Na úřední desce zatím nejsou zveřejněné žádné dokumenty.</p>
    <?php else: ?>
      <?php if (!empty($current)): ?>
        <div class="stack-sections">
          <?php $groupIndex = 0; foreach ($currentGrouped as $category => $files): ?>
            <section aria-labelledby="board-current-group-<?= $groupIndex ?>">
              <?php if ($showCurrentCategoryHeadings): ?>
                <h2 id="board-current-group-<?= $groupIndex ?>" class="section-title section-title--compact"><?= h($category) ?></h2>
              <?php else: ?>
                <h2 id="board-current-group-<?= $groupIndex ?>" class="sr-only">Aktuální dokumenty</h2>
              <?php endif; ?>

              <ul class="link-list">
                <?php foreach ($files as $document): ?>
                  <li class="link-list__item board-item">
                    <strong class="link-list__title">
                      <?php if ($document['filename'] !== ''): ?>
                        <a href="<?= moduleFileUrl('board', (int)$document['id']) ?>"
                           download="<?= h($document['original_name']) ?>">
                          <?= h($document['title']) ?>
                        </a>
                      <?php else: ?>
                        <?= h($document['title']) ?>
                      <?php endif; ?>
                    </strong>

                    <p class="meta-row meta-row--tight">
                      <?php if ($document['file_size'] > 0): ?>
                        <span><?= h(formatFileSize($document['file_size'])) ?></span>
                      <?php endif; ?>
                      <span>Vyvěšeno <time datetime="<?= h($document['posted_date']) ?>"><?= formatCzechDate($document['posted_date']) ?></time></span>
                      <?php if ($document['removal_date']): ?>
                        <span>Sejmuto <time datetime="<?= h($document['removal_date']) ?>"><?= formatCzechDate($document['removal_date']) ?></time></span>
                      <?php endif; ?>
                    </p>

                    <?php if (!empty($document['description'])): ?>
                      <div class="prose board-item__description"><?= renderContent($document['description']) ?></div>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php $groupIndex++; endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($archive)): ?>
        <details class="toggle-card">
          <summary>Archiv (<?= $archiveCountLabel ?>)</summary>
          <div class="toggle-card__content">
            <div class="stack-sections">
              <?php $groupIndex = 0; foreach ($archiveGrouped as $category => $files): ?>
                <section aria-labelledby="board-archive-group-<?= $groupIndex ?>">
                  <?php if ($showArchiveCategoryHeadings): ?>
                    <h2 id="board-archive-group-<?= $groupIndex ?>" class="section-title section-title--compact"><?= h($category) ?></h2>
                  <?php else: ?>
                    <h2 id="board-archive-group-<?= $groupIndex ?>" class="sr-only">Archiv dokumentů</h2>
                  <?php endif; ?>

                  <ul class="link-list">
                    <?php foreach ($files as $document): ?>
                      <li class="link-list__item board-item">
                        <strong class="link-list__title">
                          <?php if ($document['filename'] !== ''): ?>
                            <a href="<?= moduleFileUrl('board', (int)$document['id']) ?>"
                               download="<?= h($document['original_name']) ?>">
                              <?= h($document['title']) ?>
                            </a>
                          <?php else: ?>
                            <?= h($document['title']) ?>
                          <?php endif; ?>
                        </strong>

                        <p class="meta-row meta-row--tight">
                          <?php if ($document['file_size'] > 0): ?>
                            <span><?= h(formatFileSize($document['file_size'])) ?></span>
                          <?php endif; ?>
                          <span>Vyvěšeno <time datetime="<?= h($document['posted_date']) ?>"><?= formatCzechDate($document['posted_date']) ?></time></span>
                          <?php if ($document['removal_date']): ?>
                            <span>Sejmuto <time datetime="<?= h($document['removal_date']) ?>"><?= formatCzechDate($document['removal_date']) ?></time></span>
                          <?php endif; ?>
                        </p>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </section>
              <?php $groupIndex++; endforeach; ?>
            </div>
          </div>
        </details>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
