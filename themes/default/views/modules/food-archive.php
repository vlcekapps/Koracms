<div class="listing-shell">
  <section class="surface" aria-labelledby="food-archive-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Archiv</p>
        <h1 id="food-archive-title" class="section-title section-title--hero">Archiv jídelních a nápojových lístků</h1>
      </div>
    </div>

    <div class="button-row button-row--start">
      <a class="button-secondary" href="<?= BASE_URL ?>/food/index.php"><span aria-hidden="true">←</span> Aktuální lístek</a>
    </div>

    <nav aria-label="Filtrovat podle typu">
      <ul class="chip-list">
        <li><a class="chip-link" href="<?= BASE_URL ?>/food/archive.php?typ=vse"<?= $filterType === 'vse' ? ' aria-current="page"' : '' ?>>Vše</a></li>
        <li><a class="chip-link" href="<?= BASE_URL ?>/food/archive.php?typ=food"<?= $filterType === 'food' ? ' aria-current="page"' : '' ?>>Jídelní lístky</a></li>
        <li><a class="chip-link" href="<?= BASE_URL ?>/food/archive.php?typ=beverage"<?= $filterType === 'beverage' ? ' aria-current="page"' : '' ?>>Nápojové lístky</a></li>
      </ul>
    </nav>

    <?php if (empty($cards)): ?>
      <p class="empty-state">Archiv je zatím prázdný.</p>
    <?php else: ?>
      <div class="card-grid card-grid--compact">
        <?php foreach ($cards as $card): ?>
          <article class="card<?= $card['is_current'] ? ' card--highlighted' : '' ?>">
            <div class="card__body">
              <?php if ($card['is_current']): ?>
                <p class="section-kicker">Aktuální</p>
              <?php endif; ?>
              <p class="meta-row meta-row--tight">
                <span class="pill"><?= h((string)($typeLabels[$card['type']] ?? $card['type_label'])) ?></span>
              </p>
              <h2 class="card__title"><a href="<?= h((string)$card['public_path']) ?>"><?= h((string)$card['title']) ?></a></h2>
              <p class="meta-row meta-row--tight"><?= h((string)$card['validity_label']) ?></p>
              <?php if (!empty($card['description'])): ?>
                <p><?= h((string)$card['description']) ?></p>
              <?php endif; ?>
              <div class="button-row button-row--start">
                <a class="button-secondary" href="<?= h((string)$card['public_path']) ?>">Zobrazit lístek</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
