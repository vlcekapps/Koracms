<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu objednávkových poptávek nemáte potřebné oprávnění.');
requireModuleEnabled('food');

$pdo = db_connect();
$orderId = inputInt('get', 'id') ?? inputInt('post', 'id');
if ($orderId === null) {
    header('Location: ' . BASE_URL . '/admin/food_orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $status = normalizeFoodOrderStatus((string)($_POST['status'] ?? 'new'));
    $pdo->prepare("UPDATE cms_food_orders SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $orderId]);
    logAction('food_order_status', "id={$orderId} status={$status}");
    header('Location: ' . BASE_URL . '/admin/food_order.php?id=' . $orderId . '&msg=saved');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT o.*, c.slug AS card_slug
     FROM cms_food_orders o
     LEFT JOIN cms_food_cards c ON c.id = o.card_id
     WHERE o.id = ?
     LIMIT 1"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch() ?: null;
if (!$order) {
    header('Location: ' . BASE_URL . '/admin/food_orders.php');
    exit;
}

$itemsStmt = $pdo->prepare(
    "SELECT *
     FROM cms_food_order_items
     WHERE order_id = ?
     ORDER BY sort_order, id"
);
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();
$message = trim((string)($_GET['msg'] ?? ''));
$statusLabels = foodOrderStatusLabels();

adminHeader('Poptávka ' . (string)$order['reference_code']);
?>

<?php if ($message === 'saved'): ?><p class="success" role="status">Stav poptávky byl uložen.</p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="food_orders.php"><span aria-hidden="true">&larr;</span> Zpět na poptávky</a>
  <?php if (trim((string)($order['card_slug'] ?? '')) !== ''): ?>
    <a href="<?= h(foodCardPublicPath(['slug' => (string)$order['card_slug']])) ?>" target="_blank" rel="noopener noreferrer">Zobrazit lístek<?= newWindowLinkSrOnlySuffix() ?></a>
  <?php endif; ?>
</p>

<section class="form-card" aria-labelledby="food-order-detail-title">
  <h2 id="food-order-detail-title">Detail poptávky</h2>
  <dl class="admin-definition-list">
    <dt>Referenční kód</dt>
    <dd><strong><?= h((string)$order['reference_code']) ?></strong></dd>
    <dt>Lístek</dt>
    <dd><?= h((string)$order['card_title']) ?></dd>
    <dt>Zákazník</dt>
    <dd><?= h((string)$order['customer_name']) ?></dd>
    <dt>E-mail</dt>
    <dd><a href="mailto:<?= h((string)$order['customer_email']) ?>"><?= h((string)$order['customer_email']) ?></a></dd>
    <dt>Telefon</dt>
    <dd><?= h((string)$order['customer_phone']) ?></dd>
    <dt>Stav</dt>
    <dd><?= h(foodOrderStatusLabel((string)$order['status'])) ?></dd>
    <dt>Vytvořeno</dt>
    <dd><?= h(formatCzechDateTime((string)$order['created_at'])) ?></dd>
    <?php if (trim((string)($order['customer_note'] ?? '')) !== ''): ?>
      <dt>Poznámka</dt>
      <dd><?= nl2br(h((string)$order['customer_note'])) ?></dd>
    <?php endif; ?>
  </dl>
</section>

<section class="form-card" aria-labelledby="food-order-items-title">
  <h2 id="food-order-items-title">Položky poptávky</h2>
  <table>
    <caption>Položky uložené jako snapshot v okamžiku odeslání poptávky</caption>
    <thead>
      <tr>
        <th scope="col">Položka</th>
        <th scope="col">Množství</th>
        <th scope="col">Jednotková cena</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= h((string)$item['item_title']) ?></td>
          <td><?= (int)$item['quantity'] ?></td>
          <td><?= h(foodPriceLabel($item['unit_price_amount'] !== null ? (string)$item['unit_price_amount'] : null, (string)$item['price_currency'], (string)$item['price_note'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($order['total_amount'] !== null): ?>
    <p><strong>Orientační součet:</strong> <?= h(foodPriceLabel((string)$order['total_amount'], (string)$order['price_currency'])) ?></p>
  <?php endif; ?>
</section>

<section class="form-card" aria-labelledby="food-order-status-title">
  <h2 id="food-order-status-title">Změnit stav</h2>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$orderId ?>">
    <fieldset>
      <legend>Stav poptávky</legend>
      <label for="food-order-status">Nový stav</label>
      <select id="food-order-status" name="status">
        <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
          <option value="<?= h($statusKey) ?>"<?= normalizeFoodOrderStatus((string)$order['status']) === $statusKey ? ' selected' : '' ?>><?= h($statusLabel) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="button-row button-row--start admin-action-row">
        <button type="submit" class="btn">Uložit stav</button>
      </div>
    </fieldset>
  </form>
</section>

<?php adminFooter(); ?>
