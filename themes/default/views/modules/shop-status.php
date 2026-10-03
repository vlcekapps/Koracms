<?php
$heading = (string)($heading ?? 'Objednávka není dostupná');
$message = (string)($message ?? 'Požadovanou stránku nelze zobrazit.');
$status = (int)($status ?? 404);
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="shop-status-title">
    <h1 id="shop-status-title" class="section-title section-title--hero"><?= h($heading) ?></h1>
    <div class="status-message status-message--<?= $status >= 400 ? 'error' : 'info' ?>" role="<?= $status >= 400 ? 'alert' : 'status' ?>">
      <p><?= h($message) ?></p>
    </div>
    <?= renderThemeView('modules/shop-navigation') ?>
  </section>
</div>
