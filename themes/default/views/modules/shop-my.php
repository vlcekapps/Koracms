<?php
$orders = is_array($orders ?? null) ? $orders : [];
$page = (int)($page ?? 1);
$pages = (int)($pages ?? 1);
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="shop-my-title">
    <h1 id="shop-my-title" class="section-title section-title--hero">Moje objednávky</h1>
    <?= renderThemeView('modules/shop-navigation') ?>
    <p>Zobrazují se pouze objednávky přiřazené k vašemu přihlášenému účtu při nákupu. Nákupy hosta se sem nepřiřazují podle e-mailu; použijte jejich soukromý odkaz.</p>
    <p><a href="<?= BASE_URL ?>/public_profile.php">Profil stávajícího účtu</a></p>
    <?php if ($orders === []): ?><p class="empty-state">K tomuto účtu zatím nejsou přiřazeny žádné objednávky.</p><?php else: ?>
      <div class="table-shell">
        <table class="data-table">
          <caption>Objednávky vašeho účtu, jejich stav a konečná cena</caption>
          <thead><tr><th scope="col">Objednávka</th><th scope="col">Přijata</th><th scope="col">Stav</th><th scope="col">Celkem</th></tr></thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
              <tr>
                <th scope="row"><a href="<?= h(BASE_URL . '/shop/order.php?id=' . (int)$order['id']) ?>">Objednávka <?= h((string)$order['order_number']) ?></a></th>
                <td><?= h((string)$order['created_at']) ?></td>
                <td><?= h(shopStates()[(string)$order['status']] ?? 'Nedostupný stav') ?></td>
                <td><?= h(shopMoney((int)$order['total_cents'])) ?> (<?= h((string)$order['currency']) ?>)</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <?php if ($pages > 1): ?>
      <nav aria-labelledby="shop-my-pages-title">
        <h2 id="shop-my-pages-title" class="sr-only">Stránky objednávek</h2>
        <p>Stránka <?= (int)$page ?> z <?= (int)$pages ?>.</p>
        <div class="button-row button-row--start">
          <?php if ($page > 1): ?><a class="button-secondary" href="<?= BASE_URL ?>/shop/my.php?page=<?= (int)$page - 1 ?>" rel="prev">Předchozí stránka</a><?php endif; ?>
          <?php if ($page < $pages): ?><a class="button-secondary" href="<?= BASE_URL ?>/shop/my.php?page=<?= (int)$page + 1 ?>" rel="next">Další stránka</a><?php endif; ?>
        </div>
      </nav>
    <?php endif; ?>
  </section>
</div>
