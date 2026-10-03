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
$cart = shopPublicCart();

if ($requestMethod === 'POST') {
    shopPublicVerifyCsrf();
    $action = shopPublicString($_POST, 'action');
    $errors = [];
    $fieldErrors = [];
    $quantityValues = [];
    $message = '';
    if ($action === 'add') {
        $productId = shopPublicPositiveInt($_POST['product_id'] ?? null);
        if (!shopReady()) {
            $errors[] = 'Prodej není nyní dostupný. Košík nebyl změněn.';
        } elseif ($productId === null || shopPublicProduct($pdo, $productId) === null) {
            $errors[] = 'Produkt již není dostupný. Košík nebyl změněn.';
        } elseif (($cart[$productId] ?? 0) >= 100 || (!isset($cart[$productId]) && count($cart) >= 20)) {
            $errors[] = 'Byl dosažen limit košíku: 20 produktů a nejvýše 100 kusů od každého.';
        } else {
            $cart[$productId] = ($cart[$productId] ?? 0) + 1;
            $message = 'Produkt byl přidán do košíku.';
        }
    } elseif ($action === 'update') {
        if (array_key_exists('remove', $_POST)) {
            $removeId = shopPublicPositiveInt($_POST['remove']);
            if ($removeId === null || !isset($cart[$removeId])) {
                $errors[] = 'Položka nebyla v košíku nalezena.';
            } else {
                unset($cart[$removeId]);
                $message = 'Položka byla odebrána z košíku.';
            }
        } else {
            $quantities = is_array($_POST['quantity'] ?? null) ? $_POST['quantity'] : [];
            $updated = $cart;
            foreach ($cart as $id => $quantity) {
                $value = $quantities[$id] ?? null;
                $quantityValues[$id] = is_string($value) ? $value : '';
                $valid = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]) : false;
                if ($valid === false) {
                    $fieldErrors['quantity_' . $id] = 'Zadejte celé číslo od 0 do 100. Nula odebere položku.';
                } elseif ($valid === 0) {
                    unset($updated[$id]);
                } else {
                    $updated[$id] = (int)$valid;
                }
            }
            if ($fieldErrors === []) {
                $cart = $updated;
                $message = 'Košík byl aktualizován. Ceny jsou načteny z aktuální nabídky.';
            } else {
                $errors[] = 'Košík nebyl změněn. Opravte označené počty kusů.';
            }
        }
    } elseif ($action === 'clear') {
        $cart = [];
        $message = 'Košík byl vyprázdněn.';
    } else {
        $errors[] = 'Neznámá akce. Košík nebyl změněn.';
    }
    if ($errors === []) {
        $_SESSION['shop_cart'] = $cart;
        unset($_SESSION['shop_checkout_flash']);
    }
    $_SESSION['shop_cart_flash'] = compact('message', 'errors', 'fieldErrors', 'quantityValues');
    shopPublicRedirect(BASE_URL . '/shop/cart.php');
}

shopPublicRender('Košík', 'cart', array_merge(shopPublicCartSummary($pdo, $cart), [
    'ready' => shopReady(), 'flash' => shopPublicPullFlash('shop_cart_flash'),
]), BASE_URL . '/shop/cart.php', true);
