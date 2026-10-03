<?php
$product = is_array($product ?? null) ? $product : [];
$ready = !empty($ready);
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="shop-product-title">
    <p class="section-kicker"><?= h((string)($product['category_name'] ?? 'Digitální produkt')) ?></p>
    <h1 id="shop-product-title" class="section-title section-title--hero"><?= h((string)$product['title']) ?></h1>
    <?= renderThemeView('modules/shop-navigation') ?>
    <p><strong><?= h(shopMoney((int)$product['price_cents'])) ?></strong> včetně případné daně. Platba v CZK bankovním převodem, bez poštovného.</p>
    <p>Formát souboru: <?= h(strtoupper(pathinfo((string)$product['file_original_name'], PATHINFO_EXTENSION))) ?>. Velikost: <?= h(formatFileSize((int)$product['file_size'])) ?>.</p>
    <section aria-labelledby="shop-product-description">
      <h2 id="shop-product-description" class="section-title">Popis produktu</h2>
      <div class="prose"><?= renderProjectMarkdown((string)$product['description']) ?></div>
    </section>
    <?php foreach (['requirements' => 'Technické požadavky a kompatibilita', 'license_text' => 'Licence a možnosti použití', 'update_policy' => 'Aktualizace a podpora'] as $key => $label): ?>
      <section aria-labelledby="shop-product-<?= h(str_replace('_', '-', $key)) ?>">
        <h2 id="shop-product-<?= h(str_replace('_', '-', $key)) ?>" class="section-title"><?= h($label) ?></h2>
        <?php if (trim((string)$product[$key]) !== ''): ?>
          <div class="prose"><?= renderProjectMarkdown((string)$product[$key]) ?></div>
        <?php else: ?>
          <p>Prodejce tuto informaci zatím neuvedl. Před objednáním se informujte u prodejce.</p>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
    <section aria-labelledby="shop-product-purchase">
      <h2 id="shop-product-purchase" class="section-title">Nákup a dodání</h2>
      <p>Soubor bude dostupný po ověření přesné platby, potřebných daňových podkladů a výslovného souhlasu s digitálním dodáním. Pro zahraniční objednávky je nutná kontrola země a daňového režimu před dodáním.</p>
      <p>Soukromý odkaz ke stažení bude platný 365 dní od zpřístupnění digitálního obsahu. Opakované odeslání e-mailu tuto dobu neprodlužuje. Vypršení odkazu samo nemění licenci již staženého souboru; jeho použití se nadále řídí uvedenou licencí. Při vrácení platby bude přístup ke stažení zrušen.</p>
      <p>Do zpřístupnění je soukromý odkaz k objednávce platný 365 dní od přijetí objednávky.</p>
      <p><a href="<?= BASE_URL ?>/shop/legal.php">Údaje prodejce, podmínky, reklamace, odstoupení a ochrana soukromí</a></p>
      <?php if ($ready): ?>
        <form method="post" action="<?= BASE_URL ?>/shop/cart.php" class="form-stack">
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
          <fieldset class="form-fieldset">
            <legend>Přidat produkt do košíku</legend>
            <p>Do košíku bude přidán jeden kus produktu <?= h((string)$product['title']) ?>.</p>
            <button type="submit" class="button-primary">Přidat do košíku</button>
          </fieldset>
        </form>
      <?php else: ?>
        <div class="status-message status-message--warning" role="status"><p>Prodej nyní není dostupný. Produkt zatím nelze objednat.</p></div>
      <?php endif; ?>
    </section>
  </section>
</div>
