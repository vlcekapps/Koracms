<?php

require_once __DIR__ . '/../db.php';
if (!isModuleEnabled('shop')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/../lib/shop_public.php';
shopPublicPrivate();
$requestMethod = requireHttpMethods(['GET', 'HEAD', 'POST']);
checkMaintenanceMode();

$pdo = db_connect();
$access = shopPublicAuthorizedOrder($pdo);
$order = $access['order'];
$authorization = $access['authorization'];
$orderId = (int)$order['id'];
$flashKey = 'shop_withdraw_flash_' . $orderId;

if ($requestMethod === 'POST') {
    shopPublicVerifyCsrf();
    $errors = [];
    $fieldErrors = [];
    if (honeypotTriggered()) {
        $errors[] = 'Odeslání nebylo přijato. Zkuste potvrzení odeslat znovu.';
    }
    rateLimit('shop_withdraw', 10, 300, static function (): void {
        header('Retry-After: 300');
        http_response_code(429);
        // The shared limiter exits after this callback returns.
        shopPublicRender('Příliš mnoho pokusů', 'status', [
            'heading' => 'Příliš mnoho pokusů',
            'message' => 'Odstoupení nebylo odesláno. Zkuste to prosím za pět minut.',
            'status' => 429,
        ], BASE_URL . '/shop/withdraw.php', true);
    });
    if (($_POST['confirm_withdrawal'] ?? '') !== '1') {
        $fieldErrors['confirm_withdrawal'] = 'Pro odeslání výslovně potvrďte odstoupení od uvedené smlouvy.';
        $errors[] = 'Chybí potvrzení odstoupení. Zkontrolujte údaje a potvrďte svou volbu.';
    }
    if ($errors === []) {
        try {
            // The core locks and rechecks eligibility, and replays are idempotent.
            shopWithdrawOrder($pdo, $orderId);
        } catch (DomainException | InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable) {
            $errors[] = 'Přijetí odstoupení nyní nelze potvrdit. Zkontrolujte stav objednávky a případně potvrzení zopakujte. Pokud potíže trvají, kontaktujte prodejce.';
            koraLog('warning', 'shop withdrawal could not be confirmed', ['order_id' => $orderId]);
        }
        if ($errors === []) {
            unset($_SESSION[$flashKey]);
            $_SESSION['shop_order_flash_' . $orderId] = [
                'message' => 'Odstoupení bylo přijato. Dodání digitálního obsahu bylo zastaveno. Potvrzení se odesílá na e-mail objednávky; případná chyba e-mailu přijaté odstoupení neruší.',
            ];
            session_write_close();
            // Withdrawal is already committed; SMTP cannot turn it into a failure.
            try {
                shopDispatchOrder($pdo, $orderId);
            } catch (Throwable) {
                koraLog('warning', 'shop withdrawal dispatch failed', ['order_id' => $orderId]);
            }
            shopPublicRedirect(shopPublicOrderLink('order', $authorization));
        }
    }
    // No confirmation is stored or restored on the next review.
    $_SESSION[$flashKey] = compact('errors', 'fieldErrors');
    shopPublicRedirect(shopPublicOrderLink('withdraw', $authorization));
}

shopPublicRender('Odstoupení od smlouvy', 'withdraw', [
    'order' => $order, 'authorization' => $authorization,
    'available' => shopWithdrawalAvailable($order),
    'flash' => shopPublicPullFlash($flashKey),
], BASE_URL . '/shop/withdraw.php', true);
