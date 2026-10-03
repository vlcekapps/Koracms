<?php
$ready = !empty($ready);
$categories = is_array($categories ?? null) ? $categories : [];
$products = is_array($products ?? null) ? $products : [];
$activeCategory = is_array($activeCategory ?? null) ? $activeCategory : null;
$categorySlug = (string)($categorySlug ?? '');
$count = (int)($count ?? 0);
$page = (int)($page ?? 1);
$pages = (int)($pages ?? 1);
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="shop-catalog-title">
    <p class="section-kicker">Soubory ke stažení</p>
    <h1 id="shop-catalog-title" class="section-title section-title--hero">Digitální obchod</h1>
    <p class="prose prose--lead">Digitální produkty jednoho prodejce. Platba bankovním převodem v českých korunách; nákup je možný i bez registrace.</p>
    <?= renderThemeView('modules/shop-navigation') ?>
    <?php if (!$ready): ?>
      <div class="status-message status-message--warning" role="status"><p>Prodej nyní není dostupný. Nabídku si můžete prohlédnout, objednávku ale zatím nelze odeslat.</p></div>
    <?php endif; ?>
    <form class="filter-bar filter-bar--stack" action="<?= BASE_URL ?>/shop/index.php" method="get">
      <fieldset class="filter-bar__fieldset">
        <legend class="filter-bar__legend">Výběr kategorie</legend>
        <div class="form-group">
          <label for="shop-category">Kategorie produktů</label>
          <select id="shop-category" name="category" class="form-control">
            <option value="">Všechny kategorie</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= h((string)$category['slug']) ?>"<?= $categorySlug === $category['slug'] ? ' selected' : '' ?>><?= h((string)$category['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="button-primary" type="submit">Zobrazit kategorii</button>
      </fieldset>
    </form>
    <?php if ($activeCategory !== null): ?>
      <section aria-labelledby="shop-selected-category">
        <h2 id="shop-selected-category" class="section-title"><?= h((string)$activeCategory['name']) ?></h2>
        <div class="prose"><?= renderProjectMarkdown((string)$activeCategory['description']) ?></div>
      </section>
    <?php endif; ?>
    <section aria-labelledby="shop-products-title">
      <h2 id="shop-products-title" class="section-title">Produkty</h2>
      <p>Počet nalezených produktů: <?= (int)$count ?>.</p>
      <?php if ($products === []): ?>
        <p class="empty-state">V této nabídce zatím nejsou žádné dostupné produkty.</p>
      <?php else: ?>
        <div class="card-grid card-grid--compact">
          <?php foreach ($products as $product): ?>
            <article class="card card--rich" aria-labelledby="shop-product-<?= (int)$product['id'] ?>-title">
              <div class="card__body">
                <p class="card__eyebrow"><?= h((string)($product['category_name'] ?? 'Digitální produkt')) ?></p>
                <h3 id="shop-product-<?= (int)$product['id'] ?>-title" class="card__title"><a href="<?= h(BASE_URL . '/shop/product.php?slug=' . rawurlencode((string)$product['slug'])) ?>"><?= h((string)$product['title']) ?></a></h3>
                <p><strong><?= h(shopMoney((int)$product['price_cents'])) ?></strong> včetně případné daně</p>
                <p class="card__description"><?= h(mb_substr(normalizePlainText((string)$product['description']), 0, 240)) ?></p>
                <p><a class="section-link" href="<?= h(BASE_URL . '/shop/product.php?slug=' . rawurlencode((string)$product['slug'])) ?>">Podrobnosti produktu <?= h((string)$product['title']) ?></a></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php if ($pages > 1): ?>
      <nav aria-labelledby="shop-catalog-pages">
        <h2 id="shop-catalog-pages" class="sr-only">Stránky nabídky</h2>
        <p>Stránka <?= (int)$page ?> z <?= (int)$pages ?>.</p>
        <div class="button-row button-row--start">
          <?php if ($page > 1): ?><a class="button-secondary" href="<?= h(BASE_URL . '/shop/index.php?' . http_build_query(['category' => $categorySlug, 'page' => $page - 1])) ?>" rel="prev">Předchozí stránka</a><?php endif; ?>
          <?php if ($page < $pages): ?><a class="button-secondary" href="<?= h(BASE_URL . '/shop/index.php?' . http_build_query(['category' => $categorySlug, 'page' => $page + 1])) ?>" rel="next">Další stránka</a><?php endif; ?>
        </div>
      </nav>
    <?php endif; ?>
  </section>
</div>
