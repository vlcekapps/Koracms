<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('reservations')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

requirePublicLogin(BASE_URL . '/reservations/my.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/reservations/my.php'); exit;
}

verifyCsrf();

$pdo       = db_connect();
$userId    = currentUserId();
$bookingId = (int)($_POST['booking_id'] ?? 0);

if ($bookingId < 1) {
    header('Location: ' . BASE_URL . '/reservations/my.php'); exit;
}

// Load booking + resource cancellation_hours
$stmt = $pdo->prepare(
    "SELECT b.*, r.cancellation_hours, r.name AS resource_name
     FROM cms_res_bookings b
     JOIN cms_res_resources r ON r.id = b.resource_id
     WHERE b.id = ? AND b.user_id = ? AND b.status IN ('pending','confirmed')"
);
$stmt->execute([$bookingId, $userId]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: ' . BASE_URL . '/reservations/my.php'); exit;
}

// Check cancellation window
$bookingTs = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
$nowTs     = time();
$hours     = (int)$booking['cancellation_hours'];

if (($bookingTs - $nowTs) < ($hours * 3600)) {
    // Too late to cancel
    header('Location: ' . BASE_URL . '/reservations/my.php'); exit;
}

// Cancel the booking
$upd = $pdo->prepare("UPDATE cms_res_bookings SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() WHERE id = ?");
$upd->execute([$bookingId]);

// Send cancellation email
$email = $booking['guest_email'] ?? '';
if ($email === '' && $booking['user_id']) {
    $uStmt = $pdo->prepare("SELECT email FROM cms_users WHERE id = ?");
    $uStmt->execute([$booking['user_id']]);
    $uRow = $uStmt->fetch();
    if ($uRow) $email = $uRow['email'];
}
if ($email !== '') {
    $mailBody = "Dobrý den,\n\n"
        . "vaše rezervace byla zrušena:\n\n"
        . "Prostor: " . $booking['resource_name'] . "\n"
        . "Datum: " . $booking['booking_date'] . "\n"
        . "Čas: " . substr($booking['start_time'], 0, 5) . " – " . substr($booking['end_time'], 0, 5) . "\n\n"
        . "Pokud máte dotazy, kontaktujte nás.";
    if (!sendMail($email, 'Rezervace zrušena – ' . $booking['resource_name'], $mailBody)) {
        error_log("sendMail FAILED: zrušení rezervace pro {$email}");
    }
}

header('Location: ' . BASE_URL . '/reservations/my.php?msg=cancelled');
exit;
