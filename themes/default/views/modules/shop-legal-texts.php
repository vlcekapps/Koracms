<?php
$legal = is_array($legal ?? null) ? $legal : [];
$prefix = (string)($prefix ?? 'shop-legal');
?>
<?php foreach (['terms' => 'Obchodní podmínky', 'privacy' => 'Ochrana soukromí', 'complaints' => 'Reklamace a práva z vad', 'withdrawal' => 'Odstoupení od smlouvy'] as $key => $label): ?>
  <section aria-labelledby="<?= h($prefix . '-' . $key) ?>">
    <h2 id="<?= h($prefix . '-' . $key) ?>" class="section-title"><?= h($label) ?></h2>
    <?php if (trim((string)($legal[$key] ?? '')) !== ''): ?>
      <div class="prose"><?= renderProjectMarkdown((string)$legal[$key]) ?></div>
    <?php else: ?>
      <p>Prodejce tento text zatím nenastavil. Prodej nelze spustit bez zveřejněných podmínek.</p>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
