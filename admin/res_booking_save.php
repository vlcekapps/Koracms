<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo       = db_connect();
$bookingId = inputInt('post', 'booking_id');
$action    = $_POST['action'] ?? '';
$adminNote = trim($_POST['admin_note'] ?? '');

if ($bookingId === null || !in_array($action, ['approve', 'reject', 'cancel', 'complete', 'no_show'], true)) {
    header('Location: res_bookings.php');
    exit;
}

// Načtení rezervace + zdroje + uživatele
$stmt = $pdo->prepare(
    "SELECT b.*, r.name AS resource_name,
            u.email AS user_email, u.first_name AS user_first_name, u.last_name AS user_last_name
     FROM cms_res_bookings b
     LEFT JOIN cms_res_resources r ON r.id = b.resource_id
     LEFT JOIN cms_users u ON u.id = b.user_id
     WHERE b.id = ?"
);
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: res_bookings.php');
    exit;
}

// ── Validace přechodu stavů ──
$allowed = [
    'approve'  => ['pending'],
    'reject'   => ['pending'],
    'cancel'   => ['confirmed'],
    'complete' => ['pending', 'confirmed'],
    'no_show'  => ['confirmed', 'completed'],
];

if (!in_array($booking['status'], $allowed[$action], true)) {
    header('Location: res_booking_detail.php?id=' . $bookingId);
    exit;
}

// Dokončit lze až po uplynutí end_time rezervace
if ($action === 'complete') {
    $bookingEnd = new DateTime($booking['booking_date'] . ' ' . $booking['end_time']);
    if ($bookingEnd > new DateTime()) {
        header('Location: res_booking_detail.php?id=' . $bookingId);
        exit;
    }
}

// ── Nový stav ──
$statusMap = [
    'approve'  => 'confirmed',
    'reject'   => 'rejected',
    'cancel'   => 'cancelled',
    'complete' => 'completed',
    'no_show'  => 'no_show',
];
$newStatus = $statusMap[$action];

// ── UPDATE ──
$setClauses = ['status = ?', 'updated_at = NOW()'];
$setParams  = [$newStatus];

if ($adminNote !== '') {
    $setClauses[] = 'admin_note = ?';
    $setParams[]  = $adminNote;
}

if ($action === 'cancel') {
    $setClauses[] = 'cancelled_at = NOW()';
}

$setParams[] = $bookingId;
$pdo->prepare(
    "UPDATE cms_res_bookings SET " . implode(', ', $setClauses) . " WHERE id = ?"
)->execute($setParams);

logAction('booking_' . $action, "id={$bookingId}, new_status={$newStatus}");

// ── E-mail notifikace ──
$email = $booking['guest_email'];
if (!$email && $booking['user_id']) {
    $email = $booking['user_email'] ?? '';
}

if ($email) {
    $statusLabels = [
        'confirmed' => 'potvrzena',
        'rejected'  => 'zamítnuta',
        'cancelled' => 'zrušena',
        'completed' => 'dokončena',
        'no_show'   => 'označena jako neomluvená',
    ];

    $statusLabel = $statusLabels[$newStatus] ?? $newStatus;
    $subject = 'Rezervace ' . $statusLabel . ' – ' . $booking['resource_name'];

    $body  = "Dobrý den,\n\n";
    $body .= "vaše rezervace byla " . $statusLabel . ".\n\n";
    $body .= "Zdroj: " . $booking['resource_name'] . "\n";
    $body .= "Datum: " . $booking['booking_date'] . "\n";
    $body .= "Čas: " . $booking['start_time'] . ' – ' . $booking['end_time'] . "\n";
    $body .= "Počet osob: " . $booking['party_size'] . "\n";

    if ($adminNote !== '') {
        $body .= "\nPoznámka administrátora:\n" . $adminNote . "\n";
    }

    // Odkaz na zrušení pro schválené rezervace (host i registrovaný)
    if ($newStatus === 'confirmed' && $booking['confirmation_token']) {
        $cancelUrl = siteUrl('/reservations/cancel_booking.php?token=' . $booking['confirmation_token']);
        $body .= "\nPokud chcete rezervaci zrušit, klikněte na tento odkaz:\n" . $cancelUrl . "\n";
    }

    $body .= "\nDěkujeme.\n";

    sendMail($email, $subject, $body);
}

header('Location: res_booking_detail.php?id=' . $bookingId . '&ok=1');
exit;
