<?php
$documentLink = static fn(array $document): string => boardPublicPath($document);
$boardLabel = $boardLabel ?? boardModulePublicLabel();
$archiveTitle = boardModuleArchiveTitle();
$emptyState = boardModuleListingEmptyState();
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="board-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker"><?= h(boardModuleSectionKicker()) ?></p>
        <h1 id="board-title" class="section-title section-title--hero"><?= h($boardLabel) ?></h1>
      </div>
    </div>

    <?php if (empty($current) && empty($archive)): ?>
      <p class="empty-state"><?= h($emptyState) ?></p>
    <?php else: ?>
      <?php if (!empty($current)): ?>
        <div class="stack-sections">
          <?php $groupIndex = 0; foreach ($currentGrouped as $category => $files): ?>
            <section aria-labelledby="board-current-group-<?= $groupIndex ?>">
              <?php if ($showCurrentCategoryHeadings): ?>
                <h2 id="board-current-group-<?= $groupIndex ?>" class="section-title section-title--compact"><?= h($category) ?></h2>
              <?php else: ?>
                <h2 id="board-current-group-<?= $groupIndex ?>" class="sr-only">Aktuální položky</h2>
              <?php endif; ?>

              <ul class="link-list">
                <?php foreach ($files as $document): ?>
                  <li class="link-list__item board-item">
                    <?php if ($document['image_url'] !== ''): ?>
                      <a class="board-item__media" href="<?= h($documentLink($document)) ?>" aria-hidden="true" tabindex="-1">
                        <img class="board-item__image" src="<?= h($document['image_url']) ?>" alt="" loading="lazy">
                      </a>
                    <?php endif; ?>

                    <div class="board-item__content">
                      <a class="link-list__title" href="<?= h($documentLink($document)) ?>">
                        <?= h((string)$document['title']) ?>
                      </a>

                      <p class="meta-row meta-row--tight board-item__flags">
                        <span class="pill"><?= h((string)$document['board_type_label']) ?></span>
                        <?php if ($document['is_pinned'] === 1): ?>
                          <span class="pill">Důležité</span>
                        <?php endif; ?>
                        <?php if ((string)$document['category_name'] !== ''): ?>
                          <span class="pill"><?= h((string)$document['category_name']) ?></span>
                        <?php endif; ?>
                        <span>Vyvěšeno <time datetime="<?= h((string)$document['posted_date']) ?>"><?= formatCzechDate((string)$document['posted_date']) ?></time></span>
                        <?php if (!empty($document['removal_date'])): ?>
                          <span>Sejmuto <time datetime="<?= h((string)$document['removal_date']) ?>"><?= formatCzechDate((string)$document['removal_date']) ?></time></span>
                        <?php endif; ?>
                      </p>

                      <?php if ($document['excerpt_plain'] !== ''): ?>
                        <p class="board-item__summary"><?= h((string)$document['excerpt_plain']) ?></p>
                      <?php endif; ?>

                      <?php if (!empty($document['has_contact'])): ?>
                        <p class="board-item__contact">
                          <strong>Kontakt:</strong>
                          <?php if ((string)$document['contact_name'] !== ''): ?>
                            <span><?= h((string)$document['contact_name']) ?></span>
                          <?php endif; ?>
                          <?php if ((string)$document['contact_phone'] !== ''): ?>
                            <span><a href="tel:<?= h(preg_replace('/\s+/', '', (string)$document['contact_phone'])) ?>"><?= h((string)$document['contact_phone']) ?></a></span>
                          <?php endif; ?>
                          <?php if ((string)$document['contact_email'] !== ''): ?>
                            <span><a href="mailto:<?= h((string)$document['contact_email']) ?>"><?= h((string)$document['contact_email']) ?></a></span>
                          <?php endif; ?>
                        </p>
                      <?php endif; ?>

                      <p class="meta-row meta-row--tight">
                        <?php if ((int)$document['file_size'] > 0): ?>
                          <span><?= h(formatFileSize((int)$document['file_size'])) ?></span>
                        <?php endif; ?>
                      </p>

                      <div class="button-row button-row--start">
                        <a class="section-link" href="<?= h($documentLink($document)) ?>">Zobrazit detail <span aria-hidden="true">→</span></a>
                        <?php if ((string)$document['filename'] !== ''): ?>
                          <a class="section-link" href="<?= moduleFileUrl('board', (int)$document['id']) ?>" download="<?= h((string)$document['original_name']) ?>">Stáhnout přílohu <span aria-hidden="true">→</span></a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php $groupIndex++; endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($archive)): ?>
        <details class="toggle-card">
          <summary><?= h($archiveTitle) ?> (<?= h($archiveCountLabel) ?>)</summary>
          <div class="toggle-card__content">
            <div class="stack-sections">
              <?php $groupIndex = 0; foreach ($archiveGrouped as $category => $files): ?>
                <section aria-labelledby="board-archive-group-<?= $groupIndex ?>">
                  <?php if ($showArchiveCategoryHeadings): ?>
                    <h2 id="board-archive-group-<?= $groupIndex ?>" class="section-title section-title--compact"><?= h($category) ?></h2>
                  <?php else: ?>
                    <h2 id="board-archive-group-<?= $groupIndex ?>" class="sr-only"><?= h($archiveTitle) ?></h2>
                  <?php endif; ?>

                  <ul class="link-list">
                    <?php foreach ($files as $document): ?>
                      <li class="link-list__item board-item">
                        <?php if ($document['image_url'] !== ''): ?>
                          <a class="board-item__media" href="<?= h($documentLink($document)) ?>" aria-hidden="true" tabindex="-1">
                            <img class="board-item__image" src="<?= h($document['image_url']) ?>" alt="" loading="lazy">
                          </a>
                        <?php endif; ?>

                        <div class="board-item__content">
                          <a class="link-list__title" href="<?= h($documentLink($document)) ?>">
                            <?= h((string)$document['title']) ?>
                          </a>

                          <p class="meta-row meta-row--tight board-item__flags">
                            <span class="pill"><?= h((string)$document['board_type_label']) ?></span>
                            <?php if ((string)$document['category_name'] !== ''): ?>
                              <span class="pill"><?= h((string)$document['category_name']) ?></span>
                            <?php endif; ?>
                            <span>Vyvěšeno <time datetime="<?= h((string)$document['posted_date']) ?>"><?= formatCzechDate((string)$document['posted_date']) ?></time></span>
                            <?php if (!empty($document['removal_date'])): ?>
                              <span>Sejmuto <time datetime="<?= h((string)$document['removal_date']) ?>"><?= formatCzechDate((string)$document['removal_date']) ?></time></span>
                            <?php endif; ?>
                          </p>

                          <?php if ($document['excerpt_plain'] !== ''): ?>
                            <p class="board-item__summary"><?= h((string)$document['excerpt_plain']) ?></p>
                          <?php endif; ?>

                          <div class="button-row button-row--start">
                            <a class="section-link" href="<?= h($documentLink($document)) ?>">Zobrazit detail <span aria-hidden="true">→</span></a>
                            <?php if ((string)$document['filename'] !== ''): ?>
                              <a class="section-link" href="<?= moduleFileUrl('board', (int)$document['id']) ?>" download="<?= h((string)$document['original_name']) ?>">Stáhnout přílohu <span aria-hidden="true">→</span></a>
                            <?php endif; ?>
                          </div>
                        </div>
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
