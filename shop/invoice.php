<?php

require_once __DIR__ . '/../db.php';
if (!isModuleEnabled('shop')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/../lib/shop_invoice.php';
require_once __DIR__ . '/../lib/shop_public.php';
shopPublicPrivate();
$isHeadRequest = requireReadOnlyHttpMethod();
checkMaintenanceMode();

$pdo = db_connect();
$access = shopPublicAuthorizedOrder($pdo);
$order = $access['order'];
$kind = array_key_exists('kind', $_GET) ? shopPublicString($_GET, 'kind') : 'proforma';
$format = array_key_exists('format', $_GET) ? shopPublicString($_GET, 'format') : 'html';
if (!array_key_exists($kind, shopPublicInvoiceKinds()) || !in_array($format, ['html', 'text', 'pdf', 'qr'], true)) {
    shopPublicError('Neplatný formát dokladu', 'Vyberte existující platební výzvu, fakturu nebo opravný doklad ve formátu HTML, text nebo PDF.', 400);
}
if ($format === 'qr' && ($kind !== 'proforma' || $order['status'] !== 'awaiting_payment' || empty($order['tax_verified_at']))) {
    shopPublicError('QR platba není dostupná', 'Pro tuto objednávku nyní není dostupná platební výzva s QR kódem.');
}
$invoice = shopPublicExistingInvoice($pdo, (int)$order['id'], $kind);
$snapshot = $invoice !== null ? shopPublicSnapshot($invoice['snapshot_json'] ?? '') : [];
if ($invoice === null || (int)($snapshot['order']['id'] ?? 0) !== (int)$order['id']
    || ($snapshot['kind'] ?? '') !== $kind || !is_array($snapshot['items'] ?? null)
    || !is_array($snapshot['settings'] ?? null) || !is_array($snapshot['payment'] ?? null)
) {
    shopPublicError('Doklad není dostupný', 'Požadovaný doklad dosud nebyl vystaven nebo jej nelze bezpečně načíst.');
}

$renderingSnapshot = shopPublicInvoiceRenderingSnapshot($snapshot, $order);
try {
    $content = match ($format) {
        'html' => shopInvoiceHtml($renderingSnapshot),
        'text' => shopInvoiceText($renderingSnapshot),
        'pdf' => shopInvoicePdf($renderingSnapshot),
        'qr' => shopQrPng(shopPaymentSpayd($renderingSnapshot)),
    };
} catch (Throwable) {
    shopPublicError('Doklad se nepodařilo zobrazit', 'Zkuste přístupnou HTML nebo textovou podobu dokladu, případně kontaktujte prodejce.', 503);
}
if ($format === 'html') {
    // The core's fixed HTML stylesheet shares the nonce of the response CSP.
    $content = str_replace('<style>', '<style nonce="' . h(cspNonce()) . '">', $content);
}
$contentTypes = ['html' => 'text/html; charset=UTF-8', 'text' => 'text/plain; charset=UTF-8', 'pdf' => 'application/pdf', 'qr' => 'image/png'];
header('Content-Type: ' . $contentTypes[$format]);
header('Content-Length: ' . strlen($content));
if ($format === 'pdf' || $format === 'text') {
    $extension = $format === 'pdf' ? 'pdf' : 'txt';
    header('Content-Disposition: ' . storedFileContentDisposition('attachment', safeDownloadName((string)$invoice['invoice_number'] . '.' . $extension, 'doklad.' . $extension)));
}
session_write_close();
if (!$isHeadRequest) {
    echo $content;
}
