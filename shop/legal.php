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
shopPublicRender('Prodejce a podmínky nákupu', 'legal', ['settings' => shopSettings(), 'ready' => shopReady()], BASE_URL . '/shop/legal.php');
