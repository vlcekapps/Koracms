<?php
require_once __DIR__ . '/../db.php';
header('Cache-Control: no-cache, no-store, must-revalidate');
checkMaintenanceMode();

if (!isModuleEnabled('reservations')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function dayAvailability(
    string $dateStr,
    array $resource,
    array $hours,
    array $blockedDates,
    array $bookingsByDate,
    array $slotsByDow,
    DateTime $today,
    DateTime $maxDate
): string {
    $date = new DateTime($dateStr);
    $dayOfWeek = ((int)$date->format('N')) - 1;

    if ($date < $today) {
        return 'past';
    }
    if ($date > $maxDate) {
        return 'beyond';
    }
    if (in_array($dateStr, $blockedDates, true)) {
        return 'blocked';
    }
    if (isset($hours[$dayOfWeek]) && $hours[$dayOfWeek]['is_closed']) {
        return 'closed';
    }
    if (!isset($hours[$dayOfWeek])) {
        return 'closed';
    }

    $dayBookings = $bookingsByDate[$dateStr] ?? [];

    if ($resource['slot_mode'] === 'slots') {
        $slots = $slotsByDow[$dayOfWeek] ?? [];
        if (empty($slots)) {
            return 'closed';
        }

        foreach ($slots as $slot) {
            $booked = 0;
            foreach ($dayBookings as $booking) {
                if ($booking['start_time'] < $slot['end_time'] && $booking['end_time'] > $slot['start_time']) {
                    $booked++;
                }
            }
            if ($booked < (int)$slot['max_bookings']) {
                return 'available';
            }
        }

        return 'full';
    }

    $maxConcurrent = (int)$resource['max_concurrent'];
    $dayHours = $hours[$dayOfWeek] ?? null;
    if (!$dayHours) {
        return 'closed';
    }

    $openAt = new DateTime($dateStr . ' ' . substr($dayHours['open_time'], 0, 5));
    $closeAt = new DateTime($dateStr . ' ' . substr($dayHours['close_time'], 0, 5));

    if ($resource['slot_mode'] === 'duration') {
        $duration = (int)$resource['slot_duration_min'];
        $current = clone $openAt;
        while (true) {
            $slotEnd = (clone $current)->modify("+{$duration} minutes");
            if ($slotEnd > $closeAt) {
                break;
            }

            $overlap = 0;
            $currentStr = $current->format('H:i:s');
            $endStr = $slotEnd->format('H:i:s');
            foreach ($dayBookings as $booking) {
                if ($booking['start_time'] < $endStr && $booking['end_time'] > $currentStr) {
                    $overlap++;
                }
            }
            if ($overlap < $maxConcurrent) {
                return 'available';
            }
            $current->modify("+{$duration} minutes");
        }

        return 'full';
    }

    $current = clone $openAt;
    while ($current < $closeAt) {
        $windowEnd = (clone $current)->modify('+30 minutes');
        $currentStr = $current->format('H:i:s');
        $endStr = $windowEnd->format('H:i:s');
        $overlap = 0;
        foreach ($dayBookings as $booking) {
            if ($booking['start_time'] < $endStr && $booking['end_time'] > $currentStr) {
                $overlap++;
            }
        }
        if ($overlap < $maxConcurrent) {
            return 'available';
        }
        $current->modify('+30 minutes');
    }

    return 'full';
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    header('Location: ' . BASE_URL . '/reservations/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM cms_res_resources WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$resource = $stmt->fetch();
if (!$resource) {
    header('Location: ' . BASE_URL . '/reservations/index.php');
    exit;
}
$resourceId = (int)$resource['id'];

$monthParam = $_GET['month'] ?? '';
if (preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $year = (int)substr($monthParam, 0, 4);
    $month = (int)substr($monthParam, 5, 2);
} else {
    $year = (int)date('Y');
    $month = (int)date('n');
}
if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
    $year = (int)date('Y');
    $month = (int)date('n');
}

$firstDay = new DateTime("{$year}-{$month}-01");
$lastDay = (clone $firstDay)->modify('last day of this month');
$daysInMonth = (int)$lastDay->format('j');
$today = new DateTime('today');

$maxAdvanceDays = (int)$resource['max_advance_days'];
$maxDate = (clone $today)->modify("+{$maxAdvanceDays} days");

$hoursStmt = $pdo->prepare("SELECT * FROM cms_res_hours WHERE resource_id = ? ORDER BY day_of_week");
$hoursStmt->execute([$resourceId]);
$hours = [];
foreach ($hoursStmt->fetchAll() as $hourRow) {
    $hours[(int)$hourRow['day_of_week']] = $hourRow;
}

$blockedStmt = $pdo->prepare(
    "SELECT blocked_date FROM cms_res_blocked WHERE resource_id = ? AND blocked_date BETWEEN ? AND ?"
);
$blockedStmt->execute([$resourceId, $firstDay->format('Y-m-d'), $lastDay->format('Y-m-d')]);
$blockedDates = array_column($blockedStmt->fetchAll(), 'blocked_date');

$bookingsStmt = $pdo->prepare(
    "SELECT booking_date, start_time, end_time FROM cms_res_bookings
     WHERE resource_id = ? AND booking_date BETWEEN ? AND ? AND status IN ('pending', 'confirmed')"
);
$bookingsStmt->execute([$resourceId, $firstDay->format('Y-m-d'), $lastDay->format('Y-m-d')]);
$bookingsByDate = [];
foreach ($bookingsStmt->fetchAll() as $booking) {
    $bookingsByDate[$booking['booking_date']][] = $booking;
}

$slotsByDow = [];
if ($resource['slot_mode'] === 'slots') {
    $slotsStmt = $pdo->prepare("SELECT * FROM cms_res_slots WHERE resource_id = ? ORDER BY day_of_week, start_time");
    $slotsStmt->execute([$resourceId]);
    foreach ($slotsStmt->fetchAll() as $slotRow) {
        $slotsByDow[(int)$slotRow['day_of_week']][] = $slotRow;
    }
}

$locationsStmt = $pdo->prepare(
    "SELECT l.name, l.address FROM cms_res_locations l
     JOIN cms_res_resource_locations rl ON rl.location_id = l.id
     WHERE rl.resource_id = ? ORDER BY l.name"
);
$locationsStmt->execute([$resourceId]);
$locations = $locationsStmt->fetchAll();

$dayLabels = ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];
$dayNames = ['Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota', 'Neděle'];
$monthNames = [
    '',
    'Leden',
    'Únor',
    'Březen',
    'Duben',
    'Květen',
    'Červen',
    'Červenec',
    'Srpen',
    'Září',
    'Říjen',
    'Listopad',
    'Prosinec',
];

$openingHours = [];
for ($day = 0; $day < 7; $day++) {
    $openingHours[] = [
        'day' => $dayNames[$day],
        'closed' => !isset($hours[$day]) || (bool)$hours[$day]['is_closed'],
        'hours' => isset($hours[$day]) && !$hours[$day]['is_closed']
            ? substr($hours[$day]['open_time'], 0, 5) . ' – ' . substr($hours[$day]['close_time'], 0, 5)
            : '',
    ];
}

$modeLabels = [
    'slots' => 'Pevné časy',
    'range' => 'Volný rozsah',
    'duration' => 'Pevná délka',
];
$modeLabel = $modeLabels[$resource['slot_mode']] ?? $resource['slot_mode'];

$bookingRules = [];
if ((int)$resource['min_advance_hours'] > 0) {
    $bookingRules[] = 'Rezervaci je třeba vytvořit nejpozději ' . (int)$resource['min_advance_hours'] . ' hodin před začátkem.';
}
if ($maxAdvanceDays > 0) {
    $bookingRules[] = 'Rezervovat lze nejvýše ' . $maxAdvanceDays . ' dní dopředu.';
}
if ((int)$resource['cancellation_hours'] > 0) {
    $bookingRules[] = 'Zrušení je možné nejpozději ' . (int)$resource['cancellation_hours'] . ' hodin předem.';
} else {
    $bookingRules[] = 'Zrušení je možné kdykoli před začátkem.';
}
if ((int)$resource['capacity'] > 0) {
    $bookingRules[] = 'Kapacita je ' . (int)$resource['capacity'] . ' osob.';
}
if ((int)$resource['requires_approval']) {
    $bookingRules[] = 'Rezervace vyžaduje schválení správcem.';
}
if (!empty($resource['allow_guests'])) {
    $bookingRules[] = 'Rezervaci lze vytvořit i bez registrace jako host.';
} else {
    $bookingRules[] = 'Pro rezervaci je nutná registrace na webu a přihlášení.';
}

$prevMonth = (clone $firstDay)->modify('-1 month');
$nextMonth = (clone $firstDay)->modify('+1 month');
$startDow = ((int)$firstDay->format('N')) - 1;
$statusNotes = [
    'past' => 'Minulé',
    'blocked' => 'Blok',
    'closed' => 'Zavřeno',
    'beyond' => 'Později',
    'available' => 'Volno',
    'full' => 'Plno',
];

$calendarWeeks = [];
$currentWeek = [];
for ($cell = 0; $cell < $startDow; $cell++) {
    $currentWeek[] = ['empty' => true];
}

for ($day = 1; $day <= $daysInMonth; $day++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $status = dayAvailability($dateStr, $resource, $hours, $blockedDates, $bookingsByDate, $slotsByDow, $today, $maxDate);
    $currentWeek[] = [
        'empty' => false,
        'day' => $day,
        'status' => $status,
        'note' => $statusNotes[$status],
        'url' => BASE_URL . '/reservations/book.php?slug=' . rawurlencode($slug) . '&date=' . $dateStr,
        'aria_label' => $day . '. ' . $monthNames[$month] . ' ' . $year . ' – ' . (
            $status === 'available' ? 'volné, rezervovat' : mb_strtolower($statusNotes[$status], 'UTF-8')
        ),
    ];
    if (count($currentWeek) === 7) {
        $calendarWeeks[] = $currentWeek;
        $currentWeek = [];
    }
}

if (!empty($currentWeek)) {
    while (count($currentWeek) < 7) {
        $currentWeek[] = ['empty' => true];
    }
    $calendarWeeks[] = $currentWeek;
}

$descriptionSummary = trim(strip_tags((string)($resource['description'] ?? '')));
if ($descriptionSummary !== '' && mb_strlen($descriptionSummary, 'UTF-8') > 160) {
    $descriptionSummary = mb_substr($descriptionSummary, 0, 157, 'UTF-8') . '...';
}

renderPublicPage([
    'title' => $resource['name'] . ' – Rezervace – ' . $siteName,
    'meta' => [
        'title' => $resource['name'] . ' – Rezervace – ' . $siteName,
        'description' => $descriptionSummary,
        'url' => BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug),
    ],
    'view' => 'modules/reservations-resource',
    'view_data' => [
        'resource' => $resource,
        'modeLabel' => $modeLabel,
        'locations' => $locations,
        'openingHours' => $openingHours,
        'bookingRules' => $bookingRules,
        'monthLabel' => $monthNames[$month],
        'year' => $year,
        'dayLabels' => $dayLabels,
        'calendarWeeks' => $calendarWeeks,
        'prevMonthUrl' => BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug) . '&month=' . $prevMonth->format('Y-m'),
        'nextMonthUrl' => BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug) . '&month=' . $nextMonth->format('Y-m'),
        'prevMonthLabel' => $monthNames[(int)$prevMonth->format('n')],
        'nextMonthLabel' => $monthNames[(int)$nextMonth->format('n')],
        'successMessage' => (($_GET['msg'] ?? '') === 'ok'),
    ],
    'current_nav' => 'reservations',
    'body_class' => 'page-reservations-resource',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/res_resource_form.php?id=' . $resourceId,
]);
