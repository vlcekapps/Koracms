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

$pdo = db_connect();
$categories = $pdo->query('SELECT id, name, slug, description FROM cms_shop_categories WHERE is_active = 1 ORDER BY sort_order, name, id')->fetchAll();
$categorySlug = shopPublicString($_GET, 'category');
$activeCategory = null;
foreach ($categories as $category) {
    if ($category['slug'] === $categorySlug) {
        $activeCategory = $category;
        break;
    }
}
if ($categorySlug !== '' && $activeCategory === null) {
    shopPublicError('Kategorie nebyla nalezena', 'Zvolená kategorie není veřejně dostupná.');
}
$where = 'p.is_active = 1 AND c.is_active = 1';
$parameters = [];
if ($activeCategory !== null) {
    $where .= ' AND p.category_id = ?';
    $parameters[] = (int)$activeCategory['id'];
}
$countQuery = $pdo->prepare('SELECT COUNT(*) FROM cms_shop_products p LEFT JOIN cms_shop_categories c ON c.id = p.category_id WHERE ' . $where);
$countQuery->execute($parameters);
$count = (int)$countQuery->fetchColumn();
$pages = max(1, (int)ceil($count / 24));
$page = min($pages, shopPublicPositiveInt($_GET['page'] ?? null) ?? 1);
$offset = ($page - 1) * 24;
$statement = $pdo->prepare('SELECT p.*, c.name AS category_name FROM cms_shop_products p LEFT JOIN cms_shop_categories c ON c.id = p.category_id WHERE ' . $where . ' ORDER BY p.title, p.id LIMIT 24 OFFSET ' . $offset);
$statement->execute($parameters);
$canonical = BASE_URL . '/shop/index.php' . ($activeCategory !== null ? '?category=' . rawurlencode($categorySlug) : '');
shopPublicRender('Digitální obchod', 'index', [
    'products' => $statement->fetchAll(), 'categories' => $categories,
    'activeCategory' => $activeCategory, 'categorySlug' => $categorySlug,
    'ready' => shopReady(), 'count' => $count, 'page' => $page, 'pages' => $pages,
], $canonical);
