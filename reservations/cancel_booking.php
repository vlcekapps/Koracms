<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('reservations')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    header('Location: ' . BASE_URL . '/reservations/index.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT b.*, r.cancellation_hours, r.name AS resource_name
     FROM cms_res_bookings b
     JOIN cms_res_resources r ON r.id = b.resource_id
     WHERE b.confirmation_token = ?"
);
$stmt->execute([$token]);
$booking = $stmt->fetch();

if (!$booking) {
    $error = 'Rezervace nebyla nalezena.';
} elseif (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
    $error = 'Tato rezervace již byla zrušena nebo dokončena.';
} else {
    $error = null;
}

$canCancel = false;
if ($error === null) {
    $bookingTs = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
    $nowTs = time();
    $hours = (int)$booking['cancellation_hours'];

    if ($hours === 0 || ($bookingTs - $nowTs) >= ($hours * 3600)) {
        $canCancel = true;
    } else {
        $error = 'Lhůta pro bezplatné zrušení již vypršela (nejpozději ' . $hours . ' hodin předem).';
    }
}

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canCancel && $error === null) {
    verifyCsrf();

    $updateStmt = $pdo->prepare(
        "UPDATE cms_res_bookings
         SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW()
         WHERE id = ? AND confirmation_token = ?"
    );
    $updateStmt->execute([$booking['id'], $token]);

    $email = $booking['guest_email'] ?: '';
    if ($email === '' && $booking['user_id']) {
        $userStmt = $pdo->prepare("SELECT email FROM cms_users WHERE id = ?");
        $userStmt->execute([$booking['user_id']]);
        $userRow = $userStmt->fetch();
        if ($userRow) {
            $email = $userRow['email'];
        }
    }
    if ($email !== '') {
        $mailBody = "Dobrý den,\n\n"
            . "vaše rezervace byla úspěšně zrušena:\n\n"
            . "Zdroj: " . $booking['resource_name'] . "\n"
            . "Datum: " . $booking['booking_date'] . "\n"
            . "Čas: " . substr($booking['start_time'], 0, 5) . " – " . substr($booking['end_time'], 0, 5) . "\n\n"
            . "Pokud máte dotazy, kontaktujte nás.";
        sendMail($email, 'Rezervace zrušena – ' . $booking['resource_name'], $mailBody);
    }

    $success = true;
}

renderPublicPage([
    'title' => 'Zrušení rezervace – ' . $siteName,
    'meta' => [
        'title' => 'Zrušení rezervace – ' . $siteName,
        'url' => BASE_URL . '/reservations/cancel_booking.php?token=' . urlencode($token),
    ],
    'view' => 'modules/reservations-cancel-booking',
    'view_data' => [
        'pageTitle' => 'Zrušení rezervace',
        'success' => $success,
        'error' => $error,
        'booking' => $booking ?: null,
        'token' => $token,
    ],
    'current_nav' => 'reservations',
    'body_class' => 'page-reservations-cancel-booking',
    'page_kind' => 'utility',
]);
