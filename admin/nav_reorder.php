<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: settings_display.php'); exit;
}

verifyCsrf();

$allKeys = array_keys(navModuleDefaults());
$module  = $_POST['module'] ?? '';
$dir     = $_POST['dir']    ?? '';

if (!in_array($module, $allKeys, true) || !in_array($dir, ['up', 'down'], true)) {
    header('Location: settings_display.php'); exit;
}

$order = navModuleOrder();

$idx = array_search($module, $order, true);
if ($idx === false) { header('Location: settings_display.php'); exit; }

if ($dir === 'up' && $idx > 0) {
    [$order[$idx - 1], $order[$idx]] = [$order[$idx], $order[$idx - 1]];
} elseif ($dir === 'down' && $idx < count($order) - 1) {
    [$order[$idx], $order[$idx + 1]] = [$order[$idx + 1], $order[$idx]];
}

saveSetting('nav_module_order', implode(',', $order));
logAction('nav_reorder');

header('Location: settings_display.php?nav_saved=1'); exit;
