<?php
require_once __DIR__ . '/../db.php';
requireCapability('settings_manage', 'Přístup odepřen. Pro správu hlavní navigace nemáte potřebné oprávnění.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    logAction('page_reorder_legacy_redirect', 'redirected_to=nav_order_unified');
}

header('Location: ' . BASE_URL . '/admin/menu.php?page_positions=1');
exit;
