<?php

require_once __DIR__ . '/../db.php';
requireCapability('settings_manage', 'Přístup odepřen.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/widgets.php');
    exit;
}

verifyCsrf();

$id = inputInt('post', 'widget_id');
$redirectWithDeleteState = static function (array $params): void {
    header('Location: ' . appendUrlQuery(BASE_URL . '/admin/widgets.php', $params));
    exit;
};

if ($id === null) {
    $redirectWithDeleteState(['delete_error' => 'invalid']);
}

$pdo = db_connect();
$widgetStmt = $pdo->prepare(
    "SELECT id, zone, widget_type, title, is_active
     FROM cms_widgets
     WHERE id = ?"
);
$widgetStmt->execute([$id]);
$widget = $widgetStmt->fetch();
if (!$widget) {
    $redirectWithDeleteState(['delete_error' => 'invalid', 'delete_error_id' => $id]);
}

$confirmFieldName = 'confirm_widget_delete_' . $id;
$confirmedWidgetDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedWidgetDelete) {
    $redirectWithDeleteState(['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$pdo->prepare("DELETE FROM cms_widgets WHERE id = ?")->execute([$id]);
logAction(
    'widget_delete',
    'id=' . $id
    . ';zone=' . (string)$widget['zone']
    . ';type=' . (string)$widget['widget_type']
    . ';active=' . (int)$widget['is_active']
);

$redirectWithDeleteState(['deleted' => '1']);
