<?php
require_once __DIR__ . '/../db.php';
requireCapability('settings_manage', 'Přístup odepřen. Pro správu hlavní navigace nemáte potřebné oprávnění.');

header('Location: ' . BASE_URL . '/admin/menu.php?page_positions=1');
exit;
