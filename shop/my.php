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
requirePublicLogin(BASE_URL . '/shop/my.php');
$accountId = currentUserId();
if ($accountId === null || $accountId <= 0) {
    shopPublicError('Přihlášení je vyžadováno', 'Pro přehled objednávek se přihlaste ke svému stávajícímu účtu.', 403);
}

$pdo = db_connect();
$countStatement = $pdo->prepare('SELECT COUNT(*) FROM cms_shop_orders WHERE user_id = ?');
$countStatement->execute([$accountId]);
$pages = max(1, (int)ceil((int)$countStatement->fetchColumn() / 20));
$page = min($pages, shopPublicPositiveInt($_GET['page'] ?? null) ?? 1);
$offset = ($page - 1) * 20;
$statement = $pdo->prepare('SELECT id, order_number, status, total_cents, currency, created_at FROM cms_shop_orders WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 20 OFFSET ' . $offset);
$statement->execute([$accountId]);
shopPublicRender('Moje objednávky', 'my', ['orders' => $statement->fetchAll(), 'page' => $page, 'pages' => $pages], BASE_URL . '/shop/my.php', true);
