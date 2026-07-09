<?php

require_once __DIR__ . '/../db.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu zdrojů rezervací nemáte potřebné oprávnění.');
requireModuleEnabled('reservations');

$redirectToResources = static function (array $params = []): void {
    $target = BASE_URL . '/admin/res_resources.php';
    if ($params !== []) {
        $target .= '?' . http_build_query($params);
    }
    header('Location: ' . $target);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirectToResources();
}

verifyCsrf();

$pdo = db_connect();
$id  = inputInt('post', 'id');

if ($id === null) {
    $redirectToResources();
}

$resourceStmt = $pdo->prepare('SELECT id, name FROM cms_res_resources WHERE id = ?');
$resourceStmt->execute([$id]);
$resource = $resourceStmt->fetch();
if (!$resource) {
    $redirectToResources();
}

$confirmFieldName = 'confirm_res_resource_delete_' . $id;
$confirmedResourceDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedResourceDelete) {
    $redirectToResources(['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$futureCancelCountStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM cms_res_bookings
     WHERE resource_id = ? AND status != 'cancelled' AND booking_date >= CURDATE()"
);
$futureCancelCountStmt->execute([$id]);
$futureCancelCount = (int)$futureCancelCountStmt->fetchColumn();

$locationCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_res_resource_locations WHERE resource_id = ?');
$locationCountStmt->execute([$id]);
$locationCount = (int)$locationCountStmt->fetchColumn();

$blockedCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_res_blocked WHERE resource_id = ?');
$blockedCountStmt->execute([$id]);
$blockedCount = (int)$blockedCountStmt->fetchColumn();

$slotCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_res_slots WHERE resource_id = ?');
$slotCountStmt->execute([$id]);
$slotCount = (int)$slotCountStmt->fetchColumn();

$hoursCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_res_hours WHERE resource_id = ?');
$hoursCountStmt->execute([$id]);
$hoursCount = (int)$hoursCountStmt->fetchColumn();

$pdo->beginTransaction();
try {
    $pdo->prepare(
        "UPDATE cms_res_bookings
         SET status = 'cancelled', cancelled_at = COALESCE(cancelled_at, NOW()), updated_at = NOW()
         WHERE resource_id = ? AND status != 'cancelled' AND booking_date >= CURDATE()"
    )->execute([$id]);

    $pdo->prepare("DELETE FROM cms_res_resource_locations WHERE resource_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_res_blocked WHERE resource_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_res_slots   WHERE resource_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_res_hours   WHERE resource_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_res_resources WHERE id = ?")->execute([$id]);

    logAction(
        'res_resource_delete',
        "id={$id};future_cancelled_count={$futureCancelCount};location_count={$locationCount};hours_count={$hoursCount};slot_count={$slotCount};blocked_count={$blockedCount}"
    );
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$redirectToResources(['deleted' => '1']);
