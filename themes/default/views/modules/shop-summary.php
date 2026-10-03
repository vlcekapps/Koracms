<?php
$items = is_array($items ?? null) ? $items : [];
$total = (int)($total ?? 0);
$previewTax = isset($previewTax) ? (int)$previewTax : null;
?>
<section aria-labelledby="shop-summary-title">
  <h2 id="shop-summary-title" class="section-title">Rekapitulace produktů a konečné ceny</h2>
  <div class="table-shell">
    <table class="data-table">
      <caption>Objednávané produkty a ceny v CZK včetně případné daně</caption>
      <thead><tr><th scope="col">Produkt</th><th scope="col">Počet kusů</th><th scope="col">Cena za kus</th><th scope="col">Celkem</th></tr></thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <th scope="row"><?= h((string)$item['title']) ?></th>
            <td><?= (int)$item['quantity'] ?></td>
            <td><?= $item['available'] ? h(shopMoney((int)$item['price_cents'])) : 'Nedostupná cena' ?></td>
            <td><?= $item['available'] ? h(shopMoney((int)$item['subtotal'])) : 'Nelze objednat' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <?php if ($previewTax !== null): ?>
          <tr><th scope="row" colspan="3">Základ bez daně</th><td><?= h(shopMoney((int)$total - (int)$previewTax)) ?></td></tr>
          <tr><th scope="row" colspan="3">Zahrnutá daň</th><td><?= h(shopMoney((int)$previewTax)) ?></td></tr>
        <?php endif; ?>
        <tr><th scope="row" colspan="3">Konečná cena k úhradě, bez dalších poplatků</th><td><strong><?= h(shopMoney((int)$total)) ?></strong></td></tr>
      </tfoot>
    </table>
  </div>
  <p>Platba bankovním převodem v českých korunách. Digitální dodání bez poštovného a dopravného.</p>
</section>
