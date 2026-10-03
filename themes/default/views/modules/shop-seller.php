<?php
$settings = is_array($settings ?? null) ? $settings : [];
$headingId = (string)($headingId ?? 'shop-seller-title');
$vatMode = (string)($settings['vat_mode'] ?? '');
$vatModeLabels = [
    'non_vat' => 'Neplátce DPH',
    'vat' => 'Plátce DPH',
    'oss' => 'Plátce DPH, režim OSS',
    'non_vat_oss' => 'Neplátce DPH v ČR, identifikovaný pro režim OSS',
];
?>
<section aria-labelledby="<?= h($headingId) ?>">
  <h2 id="<?= h($headingId) ?>" class="section-title">Prodejce</h2>
  <dl>
    <?php foreach (['seller_name' => 'Jméno nebo název', 'seller_address' => 'Sídlo a adresa', 'seller_ico' => 'IČO', 'seller_dic' => 'DIČ', 'seller_register' => 'Údaj o zápisu v rejstříku', 'seller_email' => 'Kontaktní e-mail'] as $key => $label): ?>
      <?php if (trim((string)($settings[$key] ?? '')) !== ''): ?><dt><?= h($label) ?></dt><dd><?= nl2br(h((string)$settings[$key])) ?></dd><?php endif; ?>
    <?php endforeach; ?>
    <?php if (isset($vatModeLabels[$vatMode])): ?><dt>Daňový režim prodejce</dt><dd><?= h($vatModeLabels[$vatMode]) ?></dd><?php endif; ?>
  </dl>
  <?php if ($vatMode === 'non_vat'): ?><p>Prodejce není plátcem DPH. Doklady nejsou označeny jako daňové doklady.</p><?php elseif ($vatMode === 'non_vat_oss'): ?><p>Pro tuzemské plnění není prodejce plátcem DPH. Pro přeshraniční plnění používá režim OSS; daň se řídí ověřeným pravidlem země kupujícího.</p><?php endif; ?>
</section>
