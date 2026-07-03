<?php

require_once __DIR__ . '/../db.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu rezervací nemáte potřebné oprávnění.');
requireModuleEnabled('reservations');
verifyCsrf();

$pdo       = db_connect();
$bookingId = inputInt('post', 'booking_id');
$action    = $_POST['action'] ?? '';
$adminNote = trim($_POST['admin_note'] ?? '');
$listRedirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/res_bookings.php');

if ($bookingId === null || !in_array($action, ['approve', 'reject', 'cancel', 'complete', 'no_show'], true)) {
    header('Location: ' . $listRedirect);
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
    header('Location: ' . $listRedirect);
    exit;
}

$detailRedirect = internalRedirectTarget(
    trim($_POST['redirect'] ?? ''),
    BASE_URL . '/admin/res_booking_detail.php?id=' . $bookingId
);

// ── Validace přechodu stavů ──
$allowed = [
    'approve'  => ['pending'],
    'reject'   => ['pending'],
    'cancel'   => ['confirmed'],
    'complete' => ['pending', 'confirmed'],
    'no_show'  => ['confirmed', 'completed'],
];

if (!in_array($booking['status'], $allowed[$action], true)) {
    header('Location: ' . $detailRedirect);
    exit;
}

// Dokončit lze až po uplynutí end_time rezervace
if ($action === 'complete') {
    $bookingEnd = new DateTime($booking['booking_date'] . ' ' . $booking['end_time']);
    if ($bookingEnd > new DateTime()) {
        header('Location: ' . $detailRedirect);
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
if ($action === 'approve' && trim((string)($booking['calendar_token'] ?? '')) === '') {
    $setClauses[] = 'calendar_token = ?';
    $setParams[] = reservationCalendarToken();
}

$setParams[] = $bookingId;
$pdo->prepare(
    "UPDATE cms_res_bookings SET " . implode(', ', $setClauses) . " WHERE id = ?"
)->execute($setParams);

logAction('booking_' . $action, "id={$bookingId}, new_status={$newStatus}");

// ── E-mail notifikace ──
$eventTypes = [
    'approve' => 'approved',
    'reject' => 'rejected',
    'cancel' => 'cancelled',
    'complete' => 'completed',
    'no_show' => 'no_show',
];
$eventDescriptions = [
    'approve' => 'Rezervace byla schválena.',
    'reject' => 'Rezervace byla zamítnuta.',
    'cancel' => 'Rezervace byla zrušena administrátorem.',
    'complete' => 'Rezervace byla označena jako dokončená.',
    'no_show' => 'Rezervace byla označena jako neomluvená absence.',
];
reservationRecordBookingEvent(
    $pdo,
    $bookingId,
    $eventTypes[$action],
    $eventDescriptions[$action],
    currentUserId(),
    ['from_status' => (string)$booking['status'], 'to_status' => $newStatus]
);

$notificationBooking = reservationBookingForNotification($pdo, $bookingId);
if ($notificationBooking !== null && reservationBookingContactEmail($notificationBooking) !== '') {
    $statusLabels = [
        'confirmed' => 'potvrzena',
        'rejected'  => 'zamítnuta',
        'cancelled' => 'zrušena',
        'completed' => 'dokončena',
        'no_show'   => 'označena jako neomluvená',
    ];

    $statusLabel = $statusLabels[$newStatus];
    $subject = 'Rezervace ' . $statusLabel . ' – ' . (string)$notificationBooking['resource_name'];
    $body = reservationStatusMailBody($notificationBooking, $statusLabel, $adminNote);
    reservationSendMail($notificationBooking, $subject, $body, 'reservation_status_changed', $newStatus === 'confirmed');
}

$successRedirect = appendUrlQuery($detailRedirect, ['ok' => 1]);
header('Location: ' . $successRedirect);
exit;
