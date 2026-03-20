<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: res_locations.php'); exit;
}
verifyCsrf();

$id = inputInt('post', 'id');
if ($id === null) { header('Location: res_locations.php'); exit; }

$pdo = db_connect();

// Odebrat vazby na zdroje
$pdo->prepare("DELETE FROM cms_res_resource_locations WHERE location_id = ?")->execute([$id]);

// Smazat místo
$pdo->prepare("DELETE FROM cms_res_locations WHERE id = ?")->execute([$id]);

logAction('res_location_delete', "id={$id}");
header('Location: res_locations.php');
exit;
