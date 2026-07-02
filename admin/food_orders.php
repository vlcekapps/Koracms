<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu objednávkových poptávek nemáte potřebné oprávnění.');
requireModuleEnabled('food');

$pdo = db_connect();
$q = trim((string)($_GET['q'] ?? ''));
$requestedStatus = trim((string)($_GET['status'] ?? 'all'));
$statusLabels = foodOrderStatusLabels();
$statusFilter = array_key_exists($requestedStatus, $statusLabels) ? $requestedStatus : 'all';

$whereParts = ['1=1'];
$params = [];
if ($q !== '') {
    $whereParts[] = '(o.reference_code LIKE ? OR o.card_title LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ?)';
    for ($i = 0; $i < 5; $i++) {
        $params[] = '%' . $q . '%';
    }
}
if ($statusFilter !== 'all') {
    $whereParts[] = 'o.status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare(
    "SELECT o.id, o.card_id, o.card_title, o.reference_code, o.customer_name, o.customer_email,
            o.customer_phone, o.status, o.total_amount, o.price_currency, o.created_at, c.slug AS card_slug
     FROM cms_food_orders o
     LEFT JOIN cms_food_cards c ON c.id = o.card_id
     WHERE " . implode(' AND ', $whereParts) . "
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 200"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

adminHeader('Objednávkové poptávky z lístků');
?>

<p class="button-row button-row--start">
  <a href="food.php"><span aria-hidden="true">&larr;</span> Zpět na lístky</a>
</p>

<form method="get" class="button-row button-row--baseline admin-stack-sm">
  <div>
    <label for="q" class="visually-hidden">Hledat v poptávkách</label>
    <input type="search" id="q" name="q" placeholder="Hledat podle kódu, lístku nebo zákazníka"
           value="<?= h($q) ?>" class="admin-search-input">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
        <option value="<?= h($statusKey) ?>"<?= $statusFilter === $statusKey ? ' selected' : '' ?>><?= h($statusLabel) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="food_orders.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if ($orders === []): ?>
  <p>Pro zvolený filtr tu nejsou žádné objednávkové poptávky.</p>
<?php else: ?>
  <table>
    <caption>Přehled objednávkových poptávek z jídelních a nápojových lístků</caption>
    <thead>
      <tr>
        <th scope="col">Referenční kód</th>
        <th scope="col">Lístek</th>
        <th scope="col">Zákazník</th>
        <th scope="col">Stav</th>
        <th scope="col">Součet</th>
        <th scope="col">Vytvořeno</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $order): ?>
        <tr>
          <td><strong><?= h((string)$order['reference_code']) ?></strong></td>
          <td>
            <?= h((string)$order['card_title']) ?>
            <?php if (trim((string)($order['card_slug'] ?? '')) !== ''): ?>
              <br><a class="table-meta" href="<?= h(foodCardPublicPath(['slug' => (string)$order['card_slug']])) ?>" target="_blank" rel="noopener noreferrer">Zobrazit lístek<?= newWindowLinkSrOnlySuffix() ?></a>
            <?php endif; ?>
          </td>
          <td>
            <?= h((string)$order['customer_name']) ?>
            <br><small class="table-meta"><?= h((string)$order['customer_email']) ?></small>
            <br><small class="table-meta"><?= h((string)$order['customer_phone']) ?></small>
          </td>
          <td><?= h(foodOrderStatusLabel((string)$order['status'])) ?></td>
          <td><?= h(foodPriceLabel($order['total_amount'] !== null ? (string)$order['total_amount'] : null, (string)$order['price_currency'])) ?></td>
          <td><?= h(formatCzechDateTime((string)$order['created_at'])) ?></td>
          <td class="actions">
            <a class="btn" href="food_order.php?id=<?= (int)$order['id'] ?>">Detail</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
