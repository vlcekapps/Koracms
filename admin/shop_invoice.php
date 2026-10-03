<?php

require_once __DIR__ . '/../db.php';
$isHeadRequest = requireReadOnlyHttpMethod();
requireSuperAdmin();
requireModuleEnabled('shop');
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/../lib/shop_invoice.php';
require_once __DIR__ . '/layout.php';
shopSafeHeaders();

$fail = static function (int $status, string $title, string $message) use ($isHeadRequest): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    if (!$isHeadRequest) {
        adminHeader(h($title));
        echo '<p class="error">' . h($message) . '</p><p><a href="shop_orders.php">Zpět na objednávky</a></p>';
        adminFooter();
    }
    exit;
};
$format = array_key_exists('format', $_GET) ? (is_string($_GET['format']) ? $_GET['format'] : '') : 'html';
if (!in_array($format, ['html', 'text', 'pdf'], true)) {
    $fail(400, 'Neplatný formát dokladu', 'Vyberte HTML, text nebo PDF.');
}
$invoiceId = inputInt('get', 'id');
$pdo = db_connect();
$stmt = $pdo->prepare('SELECT i.*, o.status AS order_status, o.paid_at AS order_paid_at, o.country_code, o.tax_verified_at
    FROM cms_shop_invoices i JOIN cms_shop_orders o ON o.id = i.order_id WHERE i.id = ?');
$stmt->execute([$invoiceId ?? 0]);
$invoice = $stmt->fetch() ?: null;
if ($invoice === null || !in_array($invoice['kind'], ['proforma', 'final', 'credit'], true)
    || ($invoice['kind'] === 'proforma' && ($invoice['order_status'] === 'accepted'
        || ($invoice['country_code'] !== 'CZ' && empty($invoice['tax_verified_at']))))) {
    $fail(404, 'Doklad není dostupný', 'Doklad nebyl vystaven nebo jej v tomto stavu nelze zobrazit.');
}
try {
    $snapshot = json_decode((string)$invoice['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($snapshot) || (int)($snapshot['order']['id'] ?? 0) !== (int)$invoice['order_id']
        || ($snapshot['kind'] ?? '') !== $invoice['kind'] || ($snapshot['invoice_number'] ?? '') !== $invoice['invoice_number']
        || !is_array($snapshot['items'] ?? null) || !is_array($snapshot['settings'] ?? null)
        || !is_array($snapshot['legal'] ?? null) || !is_array($snapshot['payment'] ?? null)) {
        $fail(404, 'Doklad není dostupný', 'Neměnný snapshot dokladu nelze bezpečně načíst.');
    }
    $renderSnapshot = $snapshot;
    if ($invoice['kind'] === 'proforma') {
        // Keep financial history immutable; suppress obsolete payment instructions in the rendered copy.
        $renderSnapshot['order']['status'] = $invoice['order_status'];
        $renderSnapshot['order']['paid_at'] = $invoice['order_paid_at'];
    }
    $content = match ($format) {
        'html' => shopInvoiceHtml($renderSnapshot),
        'text' => shopInvoiceText($renderSnapshot),
        'pdf' => shopInvoicePdf($renderSnapshot),
    };
} catch (Throwable $exception) {
    $fail(503, 'Doklad nelze zobrazit', 'Zkuste přístupnou HTML nebo textovou podobu; případně zkontrolujte generátor dokladů.');
}
if ($format === 'html') {
    $content = str_replace('<style>', '<style nonce="' . h(cspNonce()) . '">', $content);
}
header('Content-Type: ' . ['html' => 'text/html; charset=UTF-8', 'text' => 'text/plain; charset=UTF-8', 'pdf' => 'application/pdf'][$format]);
header('Content-Length: ' . strlen($content));
if ($format !== 'html') {
    $extension = $format === 'pdf' ? 'pdf' : 'txt';
    header('Content-Disposition: ' . storedFileContentDisposition('attachment', safeDownloadName((string)$invoice['invoice_number'] . '.' . $extension, 'doklad.' . $extension)));
}
session_write_close();
if (!$isHeadRequest) {
    echo $content;
}
