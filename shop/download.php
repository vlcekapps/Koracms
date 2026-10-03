<?php

require_once __DIR__ . '/../db.php';
if (!isModuleEnabled('shop')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/../lib/shop_public.php';
shopPublicPrivate();
$isHeadRequest = requireReadOnlyHttpMethod();
checkMaintenanceMode();

$pdo = db_connect();
$access = shopPublicAuthorizedOrder($pdo);
$order = $access['order'];
if (!shopCanDownload($order)) {
    shopPublicError('Stažení není dostupné', 'Soubor dosud nebyl zpřístupněn, oprávnění bylo zrušeno nebo vypršelo.', 403);
}
$itemId = shopPublicPositiveInt($_GET['item'] ?? null);
$ownedItem = null;
foreach (shopOrderItems($pdo, (int)$order['id']) as $item) {
    if ((int)$item['id'] === $itemId && (int)$item['order_id'] === (int)$order['id']) {
        $ownedItem = $item;
        break;
    }
}
$path = $ownedItem !== null ? shopPublicVerifiedFile($ownedItem) : null;
if ($path === null) {
    shopPublicError('Soubor není dostupný', 'Zakoupený soubor nelze bezpečně ověřit nebo nepatří k této objednávce. Obraťte se na prodejce.');
}

// The shared range responder emits public caching headers. Override them at send time,
// including HEAD, 206 and 416, and do not allow conditional requests to produce a 304.
unset($_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_IF_MODIFIED_SINCE']);
header_register_callback(static function (): void {
    shopSafeHeaders();
    header('Cache-Control: private, no-store, no-cache, max-age=0, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header_remove('ETag');
    header_remove('Last-Modified');
});
session_write_close();
sendStoredFileRangeDownload($path, (string)$ownedItem['file_original_name'], $isHeadRequest, 'application/octet-stream', (string)$ownedItem['file_sha256']);
