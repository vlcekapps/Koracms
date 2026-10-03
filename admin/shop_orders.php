<?php
require_once __DIR__ . '/../db.php';
$requestMethod = requireHttpMethods(['GET', 'HEAD']);
requireSuperAdmin();
requireModuleEnabled('shop');
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/layout.php';
shopSafeHeaders();
if ($requestMethod === 'HEAD') {
    exit;
}

$pdo = db_connect();
$states = shopStates();
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
if (!isset($states[$status])) {
    $status = '';
}
$taxFilter = ($_GET['tax_pending'] ?? '') === '1';
$mailFilter = ($_GET['mail_failed'] ?? '') === '1';
$conditions = [];
$params = [];
if ($q !== '') {
    $conditions[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.email LIKE ?)';
    array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
}
if ($status !== '') {
    $conditions[] = 'o.status = ?';
    $params[] = $status;
}
if ($taxFilter) {
    $conditions[] = "o.country_code <> 'CZ' AND o.tax_verified_at IS NULL AND o.status = 'accepted'";
}
if ($mailFilter) {
    $conditions[] = "o.mail_last_error <> ''";
}
$where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', array_map(static fn (string $condition): string => '(' . $condition . ')', $conditions));
$paging = paginate($pdo, 'SELECT COUNT(*) FROM cms_shop_orders o' . $where, $params, 25);
$stmt = $pdo->prepare('SELECT o.id, o.order_number, o.customer_name, o.email, o.country_code, o.status,
    o.total_cents, o.currency, o.created_at, o.tax_verified_at, o.mail_last_error, o.mail_retry_at
    FROM cms_shop_orders o' . $where . ' ORDER BY o.created_at DESC, o.id DESC
    LIMIT ' . $paging['perPage'] . ' OFFSET ' . $paging['offset']);
$stmt->execute($params);
$orders = $stmt->fetchAll();
$pagerUrl = BASE_URL . '/admin/shop_orders.php?' . http_build_query([
    'q' => $q, 'status' => $status, 'tax_pending' => $taxFilter ? '1' : '0', 'mail_failed' => $mailFilter ? '1' : '0',
]) . '&';
adminHeader('Objednávky digitálního obchodu');
?>
<p class="button-row"><a href="shop.php">Produkty</a><a href="shop_categories.php">Kategorie</a><a href="shop_settings.php">Nastavení obchodu</a></p>
<form method="get" action="shop_orders.php" class="admin-filter-form">
  <fieldset class="admin-fieldset-card">
    <legend>Filtr objednávek</legend>
    <label for="order-q">Číslo objednávky, zákazník nebo e-mail</label>
    <input type="search" id="order-q" name="q" value="<?= h($q) ?>">
    <label for="order-status">Stav objednávky</label>
    <select id="order-status" name="status"><option value="">Všechny stavy</option><?php foreach ($states as $key => $label): ?><option value="<?= h($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
    <label for="tax-pending"><input type="checkbox" id="tax-pending" name="tax_pending" value="1"<?= $taxFilter ? ' checked' : '' ?>> Pouze přijaté zahraniční objednávky čekající na ověření daně</label>
    <label for="mail-failed"><input type="checkbox" id="mail-failed" name="mail_failed" value="1"<?= $mailFilter ? ' checked' : '' ?>> Pouze objednávky s chybou odeslání e-mailu</label>
    <p class="button-row"><button type="submit" class="btn">Použít filtr</button><a href="shop_orders.php">Zrušit filtr</a></p>
  </fieldset>
</form>
<p role="status">Nalezeno objednávek: <?= $paging['total'] ?>. Strana <?= $paging['page'] ?> z <?= $paging['totalPages'] ?>.</p>
<p>Daňové ověření a chyby e-mailu jsou samostatné provozní informace, nikoli další stavy objednávky. Finanční historie se nikdy nemaže.</p>
<div class="table-responsive" tabindex="0" role="region" aria-labelledby="shop-orders-caption">
  <table>
    <caption id="shop-orders-caption">Objednávky podle zvoleného filtru včetně stavu daňového ověření a e-mailu</caption>
    <thead><tr><th scope="col">Číslo</th><th scope="col">Zákazník</th><th scope="col">Přijata</th><th scope="col">Celkem</th><th scope="col">Stav</th><th scope="col">Daňové ověření</th><th scope="col">E-mail</th></tr></thead>
    <tbody>
      <?php foreach ($orders as $order): ?>
        <tr>
          <th scope="row"><a href="shop_order.php?id=<?= (int)$order['id'] ?>"><?= h((string)$order['order_number']) ?><span class="sr-only">: detail objednávky</span></a></th>
          <td><?= h((string)$order['customer_name']) ?><small class="table-meta"><?= h((string)$order['email']) ?></small></td>
          <td><?= h((string)$order['created_at']) ?></td>
          <td><?= h(shopMoney((int)$order['total_cents'])) ?></td>
          <td><?= h($states[$order['status']] ?? (string)$order['status']) ?></td>
          <td><?= h((string)$order['country_code']) ?>: <?= !empty($order['tax_verified_at']) ? 'Ověřeno' : ($order['status'] === 'accepted' ? 'Čeká na ověření, nevyzývat k platbě' : 'Neověřeno') ?></td>
          <td><?= !empty($order['mail_last_error']) ? 'Chyba odeslání' : 'Bez zaznamenané chyby' ?><?php if (!empty($order['mail_retry_at'])): ?><small class="table-meta">Další pokus: <?= h((string)$order['mail_retry_at']) ?></small><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($orders === []): ?><tr><td colspan="7">Zvolenému filtru neodpovídá žádná objednávka.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?= renderPager($paging['page'], $paging['totalPages'], $pagerUrl, 'Stránkování objednávek') ?>
<?php adminFooter(); ?>
