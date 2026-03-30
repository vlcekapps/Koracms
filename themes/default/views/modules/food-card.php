<?php
$backUrl = (string)($backUrl ?? (BASE_URL . '/food/archive.php'));
$backLabel = (string)($backLabel ?? 'Zpět do archivu');
?>
<div class="listing-shell">
  <section class="surface surface--accent" aria-labelledby="food-card-title">
    <nav aria-label="Drobečková navigace">
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

    <div class="prose menu-content">
      <?php if (!empty($card['content'])): ?>
        <?= renderContent($card['content']) ?>
      <?php else: ?>
        <p><em>Obsah tohoto lístku nebyl zadán.</em></p>
      <?php endif; ?>
    </div>

    <div class="button-row button-row--start">
      <a class="button-secondary" href="<?= h($backUrl) ?>"><span aria-hidden="true">&larr;</span> <?= h($backLabel) ?></a>
      <a class="button-secondary" href="<?= BASE_URL ?>/food/index.php">Aktuální lístek</a>
      <button type="button" class="button-secondary" onclick="window.print()">Vytisknout</button>
    </div>
  </section>
</div>
