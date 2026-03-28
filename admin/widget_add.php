<?php
require_once __DIR__ . '/../db.php';
requireCapability('settings_manage', 'Přístup odepřen.');
verifyCsrf();

$type = trim($_POST['widget_type'] ?? '');
$zone = trim($_POST['zone'] ?? 'homepage');
$available = availableWidgetTypes();
$zones = widgetZoneDefinitions();

if (!isset($available[$type]) || !isset($zones[$zone])) {
    header('Location: ' . BASE_URL . '/admin/widgets.php');
    exit;
}

$pdo = db_connect();
$sortOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM cms_widgets WHERE zone = " . $pdo->quote($zone))->fetchColumn();
$defaultTitle = $available[$type]['default_title'];

$pdo->prepare("INSERT INTO cms_widgets (zone, widget_type, title, settings, sort_order) VALUES (?, ?, ?, '{}', ?)")
    ->execute([$zone, $type, $defaultTitle, $sortOrder]);

logAction('widget_add', "type={$type} zone={$zone}");
header('Location: ' . BASE_URL . '/admin/widgets.php');
exit;
