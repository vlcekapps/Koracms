<?php

require_once __DIR__ . '/../db.php';
if (!isModuleEnabled('shop')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/../lib/shop_public.php';
shopPublicPrivate();
requireReadOnlyHttpMethod();
checkMaintenanceMode();

$pdo = db_connect();
$access = shopPublicAuthorizedOrder($pdo);
$order = $access['order'];
$authorization = $access['authorization'];
$invoices = [];
$visibleKinds = match ($order['status']) {
    'awaiting_payment', 'cancelled' => ['proforma'],
    'paid', 'fulfilled' => ['final'],
    'refunded' => ['credit'],
    default => [],
};
foreach (shopPublicInvoiceKinds() as $kind => $label) {
    if (!in_array($kind, $visibleKinds, true)) {
        continue;
    }
    if (shopPublicExistingInvoice($pdo, (int)$order['id'], $kind) !== null) {
        $invoices[$kind] = $label;
    }
}
shopPublicRender('Objednávka', 'order', [
    'order' => $order, 'authorization' => $authorization,
    'items' => shopOrderItems($pdo, (int)$order['id']),
    'payment' => shopPublicSnapshot($order['payment_snapshot'] ?? ''),
    'seller' => shopPublicSnapshot($order['seller_snapshot'] ?? ''),
    'legal' => shopPublicSnapshot($order['legal_snapshot'] ?? ''),
    'invoices' => $invoices, 'canDownload' => shopCanDownload($order),
    'canPay' => $order['status'] === 'awaiting_payment' && !empty($order['tax_verified_at']),
    'withdrawalAvailable' => shopWithdrawalAvailable($order),
    'withdrawalReceipt' => $order['status'] === 'cancelled' ? shopWithdrawalText($pdo, $order) : null,
    'flash' => shopPublicPullFlash('shop_order_flash_' . (int)$order['id']),
], BASE_URL . '/shop/order.php', true);
