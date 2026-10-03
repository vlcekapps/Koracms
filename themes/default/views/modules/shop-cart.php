<?php
$flash = is_array($flash ?? null) ? $flash : [];
$items = is_array($items ?? null) ? $items : [];
$ready = !empty($ready);
$available = !empty($available);
$total = (int)($total ?? 0);
$limitExceeded = !empty($limitExceeded);
$maximumTotal = (int)($maximumTotal ?? shopMaximumOrderCents());
$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$fieldErrors = is_array($flash['fieldErrors'] ?? null) ? $flash['fieldErrors'] : [];
$quantityValues = is_array($flash['quantityValues'] ?? null) ? $flash['quantityValues'] : [];
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="shop-cart-title">
    <h1 id="shop-cart-title" class="section-title section-title--hero">Košík</h1>
    <?= renderThemeView('modules/shop-navigation') ?>
    <?php if (!empty($flash['message'])): ?><div class="status-message status-message--success" role="status"><p><?= h((string)$flash['message']) ?></p></div><?php endif; ?>
    <?php if ($errors !== []): ?>
      <div id="shop-cart-errors" class="status-message status-message--error" role="alert">
        <h2 class="section-title section-title--compact">Košík nebyl změněn</h2>
        <ul><?php foreach ($errors as $error): ?><li><?= h((string)$error) ?></li><?php endforeach; ?></ul>
        <?php if ($fieldErrors !== []): ?><p>Opravte označená pole a aktualizujte košík znovu.</p><?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($items === []): ?>
      <p class="empty-state">Košík je prázdný. Vyberte si produkt v nabídce.</p>
    <?php else: ?>
      <form action="<?= BASE_URL ?>/shop/cart.php" method="post" class="form-stack" novalidate<?= $errors !== [] ? ' aria-describedby="shop-cart-errors"' : '' ?>>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="update">
        <fieldset class="form-fieldset">
          <legend>Položky a množství</legend>
          <p id="shop-cart-quantity-help">Zadejte 0 až 100 kusů. Nula odebere položku; změny potvrďte tlačítkem Aktualizovat košík.</p>
          <div class="table-shell">
            <table class="data-table">
              <caption>Obsah košíku a aktuální ceny v CZK včetně případné daně</caption>
              <thead><tr><th scope="col">Produkt</th><th scope="col">Cena za kus</th><th scope="col">Počet kusů</th><th scope="col">Celkem</th><th scope="col">Akce</th></tr></thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <?php $id = (int)$item['id'];
                    $quantityKey = 'quantity_' . $id; ?>
                  <tr>
                    <th scope="row">
                      <?php if ($item['available']): ?><a href="<?= h(BASE_URL . '/shop/product.php?slug=' . rawurlencode((string)$item['slug'])) ?>"><?= h((string)$item['title']) ?></a><?php else: ?><?= h((string)$item['title']) ?> (nelze objednat)<?php endif; ?>
                    </th>
                    <td><?= $item['available'] ? h(shopMoney((int)$item['price_cents'])) : 'Nedostupná cena' ?></td>
                    <td>
                      <label for="shop-quantity-<?= $id ?>">Počet kusů: <?= h((string)$item['title']) ?></label>
                      <input class="form-control" id="shop-quantity-<?= $id ?>" name="quantity[<?= $id ?>]" type="number" min="0" max="100" step="1" required value="<?= h((string)($quantityValues[$id] ?? $item['quantity'])) ?>"<?= shopPublicFieldAttributes($fieldErrors, $quantityKey, ['shop-cart-quantity-help']) ?>>
                      <?= shopPublicFieldError($fieldErrors, $quantityKey) ?>
                    </td>
                    <td><?= $item['available'] ? h(shopMoney((int)$item['subtotal'])) : 'Nedostupné' ?></td>
                    <td><button type="submit" name="remove" value="<?= $id ?>" class="button-secondary" formnovalidate>Odebrat <?= h((string)$item['title']) ?></button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><th scope="row" colspan="3">Celkem za dostupné položky</th><td colspan="2"><strong><?= h(shopMoney((int)$total)) ?></strong></td></tr></tfoot>
            </table>
          </div>
          <button type="submit" class="button-primary">Aktualizovat košík</button>
        </fieldset>
      </form>
      <form action="<?= BASE_URL ?>/shop/cart.php" method="post" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="clear">
        <fieldset class="form-fieldset"><legend>Vyprázdnit košík</legend><button type="submit" class="button-secondary">Odebrat všechny položky</button></fieldset>
      </form>
      <?php if ($limitExceeded): ?>
        <div class="status-message status-message--warning" role="status"><p>Celková cena překračuje limit jedné objednávky <?= h(shopMoney($maximumTotal)) ?>. Snižte počet kusů nebo odeberte položky; objednávku zatím nelze odeslat.</p></div>
      <?php elseif ($ready && $available): ?>
        <p><a class="button-primary" href="<?= BASE_URL ?>/shop/checkout.php">Pokračovat k objednávce</a></p>
      <?php else: ?>
        <div class="status-message status-message--warning" role="status"><p><?= $ready ? 'Před objednáním odeberte nedostupné položky.' : 'Prodej nyní není dostupný. Košík můžete upravit, ale objednávku zatím nelze odeslat.' ?></p></div>
      <?php endif; ?>
    <?php endif; ?>
    <p>Ceny jsou vždy ověřeny na serveru při přijetí objednávky. Žádné dopravné se neúčtuje.</p>
  </section>
</div>
