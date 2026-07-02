<?php
$foodStructuredSections = is_array($foodStructuredSections ?? null) ? $foodStructuredSections : [];
$foodStructuredMenuId = (string)($foodStructuredMenuId ?? 'food-structured-menu');
$foodStructuredMenuHeading = (string)($foodStructuredMenuHeading ?? 'Položky lístku');
$foodStructuredEmptyMessage = trim((string)($foodStructuredEmptyMessage ?? ''));
$foodStructuredHasItems = foodCardHasStructuredItems($foodStructuredSections);
$foodStructuredAllergenLegend = foodStructuredAllergenLegend($foodStructuredSections);
?>
<?php if ($foodStructuredHasItems || $foodStructuredEmptyMessage !== ''): ?>
  <section class="food-structured-menu" aria-labelledby="<?= h($foodStructuredMenuId) ?>">
    <h2 id="<?= h($foodStructuredMenuId) ?>" class="section-title section-title--compact"><?= h($foodStructuredMenuHeading) ?></h2>
    <?php if (!$foodStructuredHasItems): ?>
      <p class="empty-state"><?= h($foodStructuredEmptyMessage) ?></p>
    <?php else: ?>
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
              <li class="food-menu-item<?= (string)($item['image_thumb_url'] ?? '') !== '' ? ' food-menu-item--with-image' : '' ?><?= (int)$item['is_available'] === 1 ? '' : ' food-menu-item--unavailable' ?>">
                <?php if ((string)($item['image_thumb_url'] ?? '') !== ''): ?>
                  <img class="food-menu-item__image" src="<?= h((string)$item['image_thumb_url']) ?>" alt="<?= h((string)$item['image_alt']) ?>" loading="lazy">
                <?php endif; ?>
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
      <?php if ($foodStructuredAllergenLegend !== []): ?>
        <?php $foodAllergenLegendId = $foodStructuredMenuId . '-allergen-legend'; ?>
        <section class="food-allergen-legend" aria-labelledby="<?= h($foodAllergenLegendId) ?>">
          <h3 id="<?= h($foodAllergenLegendId) ?>" class="food-allergen-legend__title">Použité alergeny</h3>
          <dl class="food-allergen-legend__list">
            <?php foreach ($foodStructuredAllergenLegend as $legendItem): ?>
              <div>
                <dt><?= (int)$legendItem['number'] ?></dt>
                <dd><?= h((string)$legendItem['label']) ?></dd>
              </div>
            <?php endforeach; ?>
          </dl>
        </section>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php endif; ?>
