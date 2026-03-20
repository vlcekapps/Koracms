<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo = db_connect();
$id  = inputInt('post', 'id');

if ($id !== null) {
    // Cancel non-cancelled future bookings
    $pdo->prepare(
        "UPDATE cms_res_bookings SET status = 'cancelled'
         WHERE resource_id = ? AND status != 'cancelled' AND booking_date >= CURDATE()"
    )->execute([$id]);

    // Delete related data
    $pdo->prepare("DELETE FROM cms_res_resource_locations WHERE resource_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_res_blocked WHERE resource_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_res_slots   WHERE resource_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_res_hours   WHERE resource_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_res_resources WHERE id = ?")->execute([$id]);

    logAction('res_resource_delete', "id={$id}");
}

header('Location: res_resources.php');
exit;
