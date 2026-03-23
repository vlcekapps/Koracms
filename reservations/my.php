<?php
require_once __DIR__ . '/../db.php';
header('Cache-Control: no-cache, no-store, must-revalidate');
checkMaintenanceMode();

if (!isModuleEnabled('reservations')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$currentUrl = BASE_URL . '/reservations/my.php';
requirePublicLogin($currentUrl);

function canCancelBooking(array $booking, int $nowTs): bool
{
    $hours = (int)$booking['cancellation_hours'];
    $bookingTs = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
    return ($bookingTs - $nowTs) >= ($hours * 3600);
}

$pdo = db_connect();
autoCompleteBookings();
$siteName = getSetting('site_name', 'Kora CMS');
$userId = currentUserId();
$todayStr = date('Y-m-d');
$nowTs = time();
$msg = $_GET['msg'] ?? '';

$statusLabels = [
    'pending' => 'Čeká na schválení',
    'confirmed' => 'Potvrzeno',
    'cancelled' => 'Zrušeno',
    'rejected' => 'Zamítnuto',
    'completed' => 'Dokončeno',
    'no_show' => 'Nedostavil se',
];

$upcomingStmt = $pdo->prepare(
    "SELECT b.*, r.name AS resource_name, r.slug AS resource_slug, r.cancellation_hours
     FROM cms_res_bookings b
     JOIN cms_res_resources r ON r.id = b.resource_id
     WHERE b.user_id = ? AND b.booking_date >= ? AND b.status IN ('pending', 'confirmed')
     ORDER BY b.booking_date, b.start_time"
);
$upcomingStmt->execute([$userId, $todayStr]);
$upcoming = $upcomingStmt->fetchAll();

$pastStmt = $pdo->prepare(
    "SELECT b.*, r.name AS resource_name, r.slug AS resource_slug
     FROM cms_res_bookings b
     JOIN cms_res_resources r ON r.id = b.resource_id
     WHERE b.user_id = ? AND b.booking_date < ? AND b.status = 'completed'
     ORDER BY b.booking_date DESC, b.start_time DESC"
);
$pastStmt->execute([$userId, $todayStr]);
$past = $pastStmt->fetchAll();

$cancelledStmt = $pdo->prepare(
    "SELECT b.*, r.name AS resource_name, r.slug AS resource_slug
     FROM cms_res_bookings b
     JOIN cms_res_resources r ON r.id = b.resource_id
     WHERE b.user_id = ? AND b.status IN ('cancelled', 'rejected', 'no_show')
     ORDER BY b.booking_date DESC, b.start_time DESC"
);
$cancelledStmt->execute([$userId]);
$cancelled = $cancelledStmt->fetchAll();

foreach ($upcoming as &$booking) {
    $booking['status_label'] = $statusLabels[$booking['status']] ?? $booking['status'];
    $booking['can_cancel'] = canCancelBooking($booking, $nowTs);
}
unset($booking);

foreach ($past as &$booking) {
    $booking['status_label'] = $statusLabels[$booking['status']] ?? $booking['status'];
}
unset($booking);

foreach ($cancelled as &$booking) {
    $booking['status_label'] = $statusLabels[$booking['status']] ?? $booking['status'];
}
unset($booking);

$flashMessage = match ($msg) {
    'ok' => 'Rezervace byla úspěšně vytvořena.',
    'cancelled' => 'Rezervace byla zrušena.',
    default => null,
};

renderPublicPage([
    'title' => 'Moje rezervace – ' . $siteName,
    'meta' => [
        'title' => 'Moje rezervace – ' . $siteName,
        'url' => BASE_URL . '/reservations/my.php',
    ],
    'view' => 'account/reservations',
    'view_data' => [
        'flashMessage' => $flashMessage,
        'sections' => [
            [
                'heading_id' => 'reservations-upcoming',
                'title' => 'Nadcházející rezervace',
                'items' => $upcoming,
                'empty' => 'Žádné nadcházející rezervace.',
                'show_actions' => true,
            ],
            [
                'heading_id' => 'reservations-past',
                'title' => 'Proběhlé rezervace',
                'items' => $past,
                'empty' => 'Žádné proběhlé rezervace.',
                'show_actions' => false,
            ],
            [
                'heading_id' => 'reservations-cancelled',
                'title' => 'Zrušené a zamítnuté',
                'items' => $cancelled,
                'empty' => 'Žádné zrušené nebo zamítnuté rezervace.',
                'show_actions' => false,
            ],
        ],
    ],
    'current_nav' => 'reservations',
    'body_class' => 'page-reservations-my',
    'page_kind' => 'account',
]);
