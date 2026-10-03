<?php

require_once __DIR__ . '/../db.php';
if (!isModuleEnabled('shop')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/../lib/shop_public.php';
requireReadOnlyHttpMethod();
checkMaintenanceMode();

$slug = shopPublicString($_GET, 'slug');
if ($slug === '' || strlen($slug) > 255) {
    shopPublicError('Produkt nebyl nalezen', 'Požadovaný produkt není veřejně dostupný.');
}
$pdo = db_connect();
$statement = $pdo->prepare('SELECT p.*, c.name AS category_name FROM cms_shop_products p LEFT JOIN cms_shop_categories c ON c.id = p.category_id WHERE p.slug = ? AND p.is_active = 1 AND c.is_active = 1 LIMIT 1');
$statement->execute([$slug]);
$product = $statement->fetch();
if (!is_array($product)) {
    shopPublicError('Produkt nebyl nalezen', 'Požadovaný produkt není veřejně dostupný.');
}
shopPublicRender((string)$product['title'], 'product', ['product' => $product, 'ready' => shopReady()], BASE_URL . '/shop/product.php?slug=' . rawurlencode((string)$product['slug']));
