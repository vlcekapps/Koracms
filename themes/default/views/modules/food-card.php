<?php
$backUrl = (string)($backUrl ?? (BASE_URL . '/food/archive.php'));
$backLabel = (string)($backLabel ?? 'Zpět do archivu');
?>
<div class="listing-shell">
  <section class="surface surface--accent" aria-labelledby="food-card-title">
    <h2 id="food-card-breadcrumb-heading" class="sr-only">Drobečková navigace</h2>
    <nav aria-labelledby="food-card-breadcrumb-heading">
      <ol class="breadcrumb-list">
        <li><a href="<?= BASE_URL ?>/food/index.php">Jídelní lístek</a></li>
        <li><a href="<?= BASE_URL ?>/food/archive.php">Archiv</a></li>
        <li aria-current="page"><?= h($card['title']) ?></li>
      </ol>
    </nav>

    <p class="section-kicker"><?= h($typeLabel) ?></p>
    <h1 id="food-card-title" class="section-title section-title--hero"><?= h($card['title']) ?></h1>
    <p class="meta-row">
      <span class="pill"><?= h((string)($card['state_label'] ?? 'Platí nyní')) ?></span>
      <?php if ($card['is_current']): ?>
        <span>Aktuální doporučený lístek</span>
      <?php endif; ?>
      <?php if ($validityLabel !== ''): ?>
        <span><?= h($validityLabel) ?></span>
      <?php endif; ?>
      <?php if (!empty($card['description'])): ?>
        <span><?= h($card['description']) ?></span>
      <?php endif; ?>
    </p>

    <?php
    $foodFilterAction = (string)$card['public_path'];
    $foodFilterClearUrl = (string)$card['public_path'];
    $foodFilterTitleId = 'food-card-structured-filter-title';
    $foodFilters = is_array($foodFilters ?? null) ? $foodFilters : normalizeFoodStructuredFilters([]);
    $foodFilterHiddenFields = [];
    require __DIR__ . '/food-filters.php';
    ?>

    <?php if (!empty($card['has_structured_items'])): ?>
      <?php
      $foodStructuredSections = $card['sections'];
      $foodStructuredMenuId = 'food-card-structured-menu';
      $foodStructuredMenuHeading = 'Položky lístku';
      $foodStructuredEmptyMessage = '';
      require __DIR__ . '/food-structured-menu.php';
      ?>
    <?php elseif (!empty($card['structured_filter_active']) && !empty($card['has_structured_source_items'])): ?>
      <?php
      $foodStructuredSections = $card['sections'];
      $foodStructuredMenuId = 'food-card-structured-menu';
      $foodStructuredMenuHeading = 'Položky lístku';
      $foodStructuredEmptyMessage = 'Tento lístek nemá žádnou položku odpovídající filtru.';
      require __DIR__ . '/food-structured-menu.php';
      ?>
    <?php elseif (!empty($card['structured_filter_active'])): ?>
      <p class="empty-state">Tento lístek nemá strukturované položky, které by šlo filtrovat.</p>
    <?php endif; ?>
    <?php if (!empty($card['has_structured_items'])): ?>
      <?php if (trim((string)$card['content']) !== ''): ?>
        <section class="food-menu-notes" aria-labelledby="food-card-notes-title">
          <h2 id="food-card-notes-title" class="section-title section-title--compact">Poznámky k lístku</h2>
          <div class="prose menu-content"><?= renderContent($card['content']) ?></div>
        </section>
      <?php endif; ?>
    <?php elseif (empty($card['structured_filter_active'])): ?>
      <div class="prose menu-content">
        <?php if (!empty($card['content'])): ?>
          <?= renderContent($card['content']) ?>
        <?php else: ?>
          <p><em>Obsah tohoto lístku nebyl zadán.</em></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="button-row button-row--start">
      <a class="button-secondary" href="<?= h($backUrl) ?>"><span aria-hidden="true">&larr;</span> <?= h($backLabel) ?></a>
      <a class="button-secondary" href="<?= BASE_URL ?>/food/index.php">Aktuální lístek</a>
      <button type="button" class="button-secondary js-print-page">Vytisknout</button>
    </div>
  </section>
</div>
