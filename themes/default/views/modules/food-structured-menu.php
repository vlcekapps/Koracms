<?php
$foodStructuredSections = is_array($foodStructuredSections ?? null) ? $foodStructuredSections : [];
$foodStructuredMenuId = (string)($foodStructuredMenuId ?? 'food-structured-menu');
$foodStructuredMenuHeading = (string)($foodStructuredMenuHeading ?? 'Položky lístku');
?>
<?php if (foodCardHasStructuredItems($foodStructuredSections)): ?>
  <section class="food-structured-menu" aria-labelledby="<?= h($foodStructuredMenuId) ?>">
    <h2 id="<?= h($foodStructuredMenuId) ?>" class="section-title section-title--compact"><?= h($foodStructuredMenuHeading) ?></h2>
    <?php foreach ($foodStructuredSections as $sectionIndex => $section): ?>
      <?php if (empty($section['items'])) {
          continue;
      } ?>
      <?php $foodSectionTitleId = $foodStructuredMenuId . '-section-' . (int)$sectionIndex; ?>
      <section class="food-menu-section" aria-labelledby="<?= h($foodSectionTitleId) ?>">
        <h3 id="<?= h($foodSectionTitleId) ?>" class="food-menu-section__title"><?= h((string)$section['title']) ?></h3>
        <?php if (trim((string)($section['description'] ?? '')) !== ''): ?>
          <p class="food-menu-section__description"><?= h((string)$section['description']) ?></p>
        <?php endif; ?>
        <ul class="food-menu-list">
          <?php foreach ($section['items'] as $item): ?>
            <li class="food-menu-item<?= (int)$item['is_available'] === 1 ? '' : ' food-menu-item--unavailable' ?>">
              <div class="food-menu-item__main">
                <h4 class="food-menu-item__title"><?= h((string)$item['title']) ?></h4>
                <?php if (trim((string)($item['description'] ?? '')) !== ''): ?>
                  <p class="food-menu-item__description"><?= h((string)$item['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($item['dietary_flag_labels']) || !empty($item['allergen_labels']) || (int)$item['is_available'] !== 1): ?>
                  <p class="food-menu-item__meta">
                    <?php if ((int)$item['is_available'] !== 1): ?>
                      <strong class="status-badge">Nedostupné</strong>
                    <?php endif; ?>
                    <?php if (!empty($item['dietary_flag_labels'])): ?>
                      <span><?= h(implode(', ', $item['dietary_flag_labels'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($item['allergen_labels'])): ?>
                      <span>Alergeny: <?= h(implode(', ', $item['allergen_labels'])) ?></span>
                    <?php endif; ?>
                  </p>
                <?php endif; ?>
              </div>
              <?php if ((string)($item['price_label'] ?? '') !== ''): ?>
                <p class="food-menu-item__price"><?= h((string)$item['price_label']) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
