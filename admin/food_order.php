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

$statusLabels = foodOrderStatusLabels();
$detailPath = BASE_URL . '/admin/food_order.php?id=' . $orderId;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $status = trim((string)($_POST['status'] ?? ''));
    if (!array_key_exists($status, $statusLabels)) {
        header('Location: ' . appendUrlQuery($detailPath, ['error' => 'status_invalid']));
        exit;
    }

    $confirmationField = 'confirm_food_order_status_' . $orderId;
    $statusConfirmed = isset($_POST[$confirmationField]) && (string)$_POST[$confirmationField] === '1';
    if (!$statusConfirmed) {
        header('Location: ' . appendUrlQuery($detailPath, [
            'error' => 'status_confirm_required',
            'status' => $status,
        ]));
        exit;
    }

    $previousStatus = normalizeFoodOrderStatus((string)$order['status']);
    $pdo->prepare("UPDATE cms_food_orders SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $orderId]);
    logAction('food_order_status', "id={$orderId} from={$previousStatus} to={$status}");
    header('Location: ' . appendUrlQuery($detailPath, ['msg' => 'saved']));
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
$statusError = trim((string)($_GET['error'] ?? ''));
$statusErrorMessage = match ($statusError) {
    'status_confirm_required' => 'Změnu stavu poptávky nelze uložit bez potvrzení kontroly referenčního kódu, aktuálního stavu a zvoleného nového stavu.',
    'status_invalid' => 'Vyberte nový stav z nabízeného seznamu.',
    default => '',
};
$requestedStatus = trim((string)($_GET['status'] ?? ''));
$selectedStatus = array_key_exists($requestedStatus, $statusLabels)
    ? $requestedStatus
    : normalizeFoodOrderStatus((string)$order['status']);
$statusFieldErrors = $statusError === 'status_invalid' ? ['status'] : [];
$statusConfirmationField = 'confirm_food_order_status_' . $orderId;
$statusConfirmationErrors = $statusError === 'status_confirm_required' ? [$statusConfirmationField] : [];

adminHeader('Poptávka ' . (string)$order['reference_code']);
?>

<?php if ($message === 'saved'): ?><p class="success" role="status" aria-atomic="true">Stav poptávky byl uložen.</p><?php endif; ?>
<?php if ($statusErrorMessage !== ''): ?>
  <div class="error" role="alert" aria-atomic="true" aria-labelledby="food-order-status-error">
    <p id="food-order-status-error"><?= h($statusErrorMessage) ?></p>
  </div>
<?php endif; ?>

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
  <form method="post" novalidate<?= $statusErrorMessage !== '' ? ' aria-describedby="food-order-status-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$orderId ?>">
    <fieldset>
      <legend>Stav poptávky</legend>
      <label for="food-order-status">Nový stav</label>
      <select id="food-order-status" name="status" required<?= adminFieldAttributes('status', $statusFieldErrors, [], ['food-order-status-review'], 'food-order-status-field-error') ?>>
        <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
          <option value="<?= h($statusKey) ?>"<?= $selectedStatus === $statusKey ? ' selected' : '' ?>><?= h($statusLabel) ?></option>
        <?php endforeach; ?>
      </select>
      <?php adminRenderFieldError('status', $statusFieldErrors, [], 'Vyberte jeden z nabízených stavů poptávky.', 'food-order-status-field-error'); ?>
      <p id="food-order-status-review" class="field-help">
        Poptávka <?= h((string)$order['reference_code']) ?> má aktuálně stav „<?= h(foodOrderStatusLabel((string)$order['status'])) ?>“.
        Uložení přepíše její interní stav a zapíše změnu do auditního logu; zákazníkovi se automaticky neodešle e-mail.
      </p>
      <label for="confirm-food-order-status-<?= (int)$orderId ?>" class="admin-checkbox-label">
        <input type="checkbox" id="confirm-food-order-status-<?= (int)$orderId ?>" name="<?= h($statusConfirmationField) ?>" value="1" required aria-required="true"<?= adminFieldAttributes($statusConfirmationField, $statusConfirmationErrors, [], ['food-order-status-review'], 'food-order-status-confirm-error') ?>>
        Potvrzuji, že jsem zkontroloval(a) referenční kód, aktuální stav a zvolený nový stav.
      </label>
      <?php adminRenderFieldError($statusConfirmationField, $statusConfirmationErrors, [], 'Před uložením zaškrtněte potvrzení kontroly poptávky a nového stavu.', 'food-order-status-confirm-error'); ?>
      <div class="button-row button-row--start admin-action-row">
        <button type="submit" class="btn">Uložit stav</button>
      </div>
    </fieldset>
  </form>
</section>

<?php adminFooter(); ?>
