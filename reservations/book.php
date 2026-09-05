<?php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/reservation_booking_validation.php';
header('Cache-Control: no-cache, no-store, must-revalidate');
checkMaintenanceMode();

if (!isModuleEnabled('reservations')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$slug = trim($_GET['slug'] ?? '');
$dateStr = trim($_GET['date'] ?? '');

if ($slug === '' || $dateStr === '') {
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
$resId = (int)$resource['id'];

$isGuest = false;
$isPostRequest = $_SERVER['REQUEST_METHOD'] === 'POST';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug));
    exit;
}

$bookingDate = DateTime::createFromFormat('!Y-m-d', $dateStr);
$bookingDateErrors = DateTime::getLastErrors();
if (
    !$bookingDate instanceof DateTime
    || (($bookingDateErrors['warning_count'] ?? 0) > 0)
    || (($bookingDateErrors['error_count'] ?? 0) > 0)
) {
    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug));
    exit;
}

if (!empty($resource['allow_guests'])) {
    $isGuest = !isset($_SESSION['cms_user_id']);
} else {
    $currentUrl = BASE_URL . '/reservations/book.php?slug=' . urlencode($slug) . '&date=' . urlencode($dateStr);
    requirePublicLogin($currentUrl);
}
$contactDefaults = currentUserContactDefaults($pdo);

$today = new DateTime('today');
$now = new DateTime();

if ($bookingDate < $today) {
    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug));
    exit;
}

$maxDate = (clone $today)->modify('+' . (int)$resource['max_advance_days'] . ' days');
if ($bookingDate > $maxDate) {
    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug));
    exit;
}

$blockedStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_res_blocked WHERE resource_id = ? AND blocked_date = ?");
$blockedStmt->execute([$resId, $dateStr]);
if ((int)$blockedStmt->fetchColumn() > 0) {
    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug));
    exit;
}

$dayOfWeek = ((int)$bookingDate->format('N')) - 1;
$hoursStmt = $pdo->prepare("SELECT * FROM cms_res_hours WHERE resource_id = ? AND day_of_week = ?");
$hoursStmt->execute([$resId, $dayOfWeek]);
$dayHours = $hoursStmt->fetch();
if (!$dayHours || $dayHours['is_closed']) {
    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug));
    exit;
}

$openTime = substr($dayHours['open_time'], 0, 5);
$closeTime = substr($dayHours['close_time'], 0, 5);

$existingStmt = $pdo->prepare(
    "SELECT start_time, end_time FROM cms_res_bookings
     WHERE resource_id = ? AND booking_date = ? AND status IN ('pending', 'confirmed')"
);
$existingStmt->execute([$resId, $dateStr]);
$existingBookings = $existingStmt->fetchAll();

$slotMode = $resource['slot_mode'];
$slots = [];
$predefinedSlots = [];

if ($slotMode === 'slots') {
    $slotsStmt = $pdo->prepare(
        "SELECT * FROM cms_res_slots WHERE resource_id = ? AND day_of_week = ? ORDER BY start_time"
    );
    $slotsStmt->execute([$resId, $dayOfWeek]);
    $predefinedSlots = $slotsStmt->fetchAll();

    foreach ($predefinedSlots as $slot) {
        $booked = reservationBookingPeakOverlap($existingBookings, $slot['start_time'], $slot['end_time']);
        $maxBookings = (int)$slot['max_bookings'];
        $free = $maxBookings - $booked;
        if ($free > 0) {
            $slots[] = [
                'start' => substr($slot['start_time'], 0, 5),
                'end' => substr($slot['end_time'], 0, 5),
                'free' => $free,
                'max' => $maxBookings,
            ];
        }
    }
} elseif ($slotMode === 'range') {
    $maxConcurrent = (int)$resource['max_concurrent'];
    $startDt = new DateTime($dateStr . ' ' . $openTime);
    $endDt = new DateTime($dateStr . ' ' . $closeTime);
    $current = clone $startDt;
    while ($current <= $endDt) {
        $currentStr = $current->format('H:i:s');
        $overlap = 0;
        foreach ($existingBookings as $booking) {
            if ($booking['start_time'] <= $currentStr && $booking['end_time'] > $currentStr) {
                $overlap++;
            }
        }
        if ($overlap < $maxConcurrent) {
            $slots[] = $current->format('H:i');
        }
        $current->modify('+30 minutes');
    }
} elseif ($slotMode === 'duration') {
    $duration = (int)$resource['slot_duration_min'];
    $maxConcurrent = (int)$resource['max_concurrent'];
    $startDt = new DateTime($dateStr . ' ' . $openTime);
    $endDt = new DateTime($dateStr . ' ' . $closeTime);
    $current = clone $startDt;
    while (true) {
        $slotEnd = (clone $current)->modify("+{$duration} minutes");
        if ($slotEnd > $endDt) {
            break;
        }
        $currentStr = $current->format('H:i:s');
        $endStr = $slotEnd->format('H:i:s');
        $overlap = reservationBookingPeakOverlap($existingBookings, $currentStr, $endStr);
        if ($overlap < $maxConcurrent) {
            $slots[] = $current->format('H:i');
        }
        $current->modify("+{$duration} minutes");
    }
}

$errors = [];
$fieldErrors = [];

if ($isPostRequest) {
    verifyCsrf();

    if (honeypotTriggered()) {
        $redirectUrl = $isGuest
            ? BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug) . '&msg=ok'
            : BASE_URL . '/reservations/my.php?msg=ok';
        header('Location: ' . $redirectUrl);
        exit;
    }

    rateLimit('booking', 5, 300);

    $partySize = max(1, (int)($_POST['party_size'] ?? 1));
    $notes = trim($_POST['notes'] ?? '');
    $capacity = (int)$resource['capacity'];

    $guestNamePost = '';
    $guestEmailPost = '';
    $guestPhonePost = '';
    if ($isGuest) {
        $guestNamePost = trim($_POST['guest_name'] ?? '');
        $guestEmailPost = trim($_POST['guest_email'] ?? '');
        $guestPhonePost = trim($_POST['guest_phone'] ?? '');
        if ($guestNamePost === '') {
            $message = 'Vyplňte prosím jméno a příjmení.';
            $errors[] = $message;
            $fieldErrors['guest_name'] = $message;
        }
        if ($guestEmailPost === '' || !filter_var($guestEmailPost, FILTER_VALIDATE_EMAIL)) {
            $message = 'Vyplňte prosím úplnou e-mailovou adresu ve tvaru jmeno@example.cz.';
            $errors[] = $message;
            $fieldErrors['guest_email'] = $message;
        }
        if ($guestPhonePost === '') {
            $message = 'Vyplňte prosím telefonní číslo pro upřesnění rezervace.';
            $errors[] = $message;
            $fieldErrors['guest_phone'] = $message;
        }
        if (!captchaVerify($_POST['captcha'] ?? '')) {
            $captchaError = publicCaptchaErrorMessage();
            $errors[] = $captchaError;
            $fieldErrors['captcha'] = $captchaError;
        }
    }

    if ($capacity > 0 && $partySize > $capacity) {
        $message = 'Počet osob nesmí být vyšší než kapacita zdroje: ' . $capacity . '.';
        $errors[] = $message;
        $fieldErrors['party_size'] = $message;
    }

    if ($slotMode === 'slots') {
        $selectedSlot = is_string($_POST['slot'] ?? null) ? $_POST['slot'] : '';
        $matches = [];
        preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/D', $selectedSlot, $matches);
        $startTime = $matches[1] ?? '';
        $endTime = $matches[2] ?? '';
    } else {
        $startTime = is_string($_POST['start_time'] ?? null) ? $_POST['start_time'] : '';
        $endTime = $slotMode === 'duration' ? null : (is_string($_POST['end_time'] ?? null) ? $_POST['end_time'] : '');
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $validation = reservationValidateBookingInsert(
                $pdo,
                $resId,
                $dateStr,
                $startTime,
                $endTime,
                $partySize,
                true,
                $isGuest
            );
            if ($validation['error'] !== '') {
                $pdo->rollBack();
                $message = reservationBookingValidationMessage($validation['error']);
                $errors[] = $message;
                $errorField = $validation['error'] === 'party_size' ? 'party_size' : ($slotMode === 'slots' ? 'slot' : 'start_time');
                $fieldErrors[$errorField] = $message;
                if ($slotMode === 'range' && $errorField === 'start_time') {
                    $fieldErrors['end_time'] = $message;
                }
            } else {
                $resource = $validation['resource'];
                $startTime = $validation['start_time'];
                $endTime = $validation['end_time'];
                $status = (int)$resource['requires_approval'] ? 'pending' : 'confirmed';
                $token = bin2hex(random_bytes(16));
                $calendarToken = reservationCalendarToken();

                if ($isGuest) {
                    $userId = null;
                    $guestName = $guestNamePost;
                    $guestEmail = $guestEmailPost;
                    $guestPhone = $guestPhonePost;
                } else {
                    $userId = currentUserId();
                    $guestName = $contactDefaults['name'];
                    $guestEmail = $contactDefaults['email'];
                    $guestPhone = $contactDefaults['phone'];
                }

                $insertStmt = $pdo->prepare(
                    "INSERT INTO cms_res_bookings
                     (resource_id, user_id, guest_name, guest_email, guest_phone, booking_date, start_time, end_time,
                      party_size, notes, status, confirmation_token, calendar_token, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                );
                $insertStmt->execute([
                    $resId,
                    $userId,
                    $guestName,
                    $guestEmail,
                    $guestPhone,
                    $dateStr,
                    $startTime,
                    $endTime,
                    $partySize,
                    $notes,
                    $status,
                    $token,
                    $calendarToken,
                ]);
                $bookingId = (int)$pdo->lastInsertId();

                $pdo->commit();

                reservationRecordBookingEvent(
                    $pdo,
                    $bookingId,
                    'created',
                    $status === 'confirmed' ? 'Rezervace byla vytvořena a potvrzena.' : 'Rezervace byla vytvořena a čeká na schválení.',
                    null,
                    ['status' => $status]
                );

                $notificationBooking = reservationBookingForNotification($pdo, $bookingId);

                if ($guestEmail !== '' && $notificationBooking !== null) {
                    $statusLabel = $status === 'confirmed' ? 'potvrzena' : 'čeká na schválení';
                    $cancelUrl = siteUrl('/reservations/cancel_booking.php?token=' . $token);
                    $mailBody = "Dobrý den,\n\n"
                        . "vaše rezervace byla vytvořena:\n\n"
                        . "Zdroj: " . $resource['name'] . "\n"
                        . "Datum: " . $dateStr . "\n"
                        . "Čas: " . substr($startTime, 0, 5) . " – " . substr($endTime, 0, 5) . "\n"
                        . "Počet osob: " . $partySize . "\n"
                        . "Stav: " . $statusLabel . "\n\n"
                        . "Pokud chcete rezervaci zrušit, klikněte na tento odkaz:\n"
                        . $cancelUrl . "\n\n"
                        . "Děkujeme za rezervaci.";
                    reservationSendMail(
                        $notificationBooking,
                        'Rezervace – ' . $resource['name'],
                        $mailBody,
                        'reservation_created',
                        $status === 'confirmed'
                    );
                }

                if ($isGuest) {
                    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug) . '&msg=ok');
                } else {
                    header('Location: ' . BASE_URL . '/reservations/my.php?msg=ok');
                }
                exit;
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Nastala chyba při ukládání rezervace. Zkuste to prosím znovu.';
        }
    }
}

if (!empty($errors)) {
    $existingStmt->execute([$resId, $dateStr]);
    $existingBookings = $existingStmt->fetchAll();
    $slots = [];
    $maxConcurrent = (int)$resource['max_concurrent'];

    if ($slotMode === 'slots') {
        foreach ($predefinedSlots as $slot) {
            $booked = reservationBookingPeakOverlap($existingBookings, $slot['start_time'], $slot['end_time']);
            $maxBookings = (int)$slot['max_bookings'];
            $free = $maxBookings - $booked;
            if ($free > 0) {
                $slots[] = [
                    'start' => substr($slot['start_time'], 0, 5),
                    'end' => substr($slot['end_time'], 0, 5),
                    'free' => $free,
                    'max' => $maxBookings,
                ];
            }
        }
    } elseif ($slotMode === 'range') {
        $startDt = new DateTime($dateStr . ' ' . $openTime);
        $endDt = new DateTime($dateStr . ' ' . $closeTime);
        $current = clone $startDt;
        while ($current <= $endDt) {
            $currentStr = $current->format('H:i:s');
            $overlap = 0;
            foreach ($existingBookings as $booking) {
                if ($booking['start_time'] <= $currentStr && $booking['end_time'] > $currentStr) {
                    $overlap++;
                }
            }
            if ($overlap < $maxConcurrent) {
                $slots[] = $current->format('H:i');
            }
            $current->modify('+30 minutes');
        }
    } elseif ($slotMode === 'duration') {
        $duration = (int)$resource['slot_duration_min'];
        $startDt = new DateTime($dateStr . ' ' . $openTime);
        $endDt = new DateTime($dateStr . ' ' . $closeTime);
        $current = clone $startDt;
        while (true) {
            $slotEnd = (clone $current)->modify("+{$duration} minutes");
            if ($slotEnd > $endDt) {
                break;
            }
            $currentStr = $current->format('H:i:s');
            $endStr = $slotEnd->format('H:i:s');
            $overlap = reservationBookingPeakOverlap($existingBookings, $currentStr, $endStr);
            if ($overlap < $maxConcurrent) {
                $slots[] = $current->format('H:i');
            }
            $current->modify("+{$duration} minutes");
        }
    }
}

$weekdayLabels = ['Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota', 'Neděle'];
$captchaExpr = $isGuest ? captchaGenerate() : '';

renderPublicPage([
    'title' => 'Rezervace – ' . $resource['name'] . ' – ' . $siteName,
    'meta' => [
        'title' => 'Rezervace – ' . $resource['name'] . ' – ' . $siteName,
        'description' => 'Rezervace termínu pro ' . $resource['name'] . ' na datum ' . $dateStr . '.',
        'url' => BASE_URL . '/reservations/book.php?slug=' . rawurlencode($slug) . '&date=' . urlencode($dateStr),
    ],
    'view' => 'modules/reservations-book',
    'view_data' => [
        'resource' => $resource,
        'slug' => $slug,
        'dateStr' => $dateStr,
        'weekdayLabel' => $weekdayLabels[$dayOfWeek],
        'openTime' => $openTime,
        'closeTime' => $closeTime,
        'slotMode' => $slotMode,
        'slots' => $slots,
        'slotsEmpty' => empty($slots),
        'errors' => $errors,
        'fieldErrors' => $fieldErrors,
        'isGuest' => $isGuest,
        'existingBookings' => $existingBookings,
        'maxPartySize' => (int)$resource['capacity'] ?: 100,
        'captchaExpr' => $captchaExpr,
        'formData' => [
            'slot' => $_POST['slot'] ?? '',
            'start_time' => $_POST['start_time'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'guest_name' => $isPostRequest ? trim((string)($_POST['guest_name'] ?? '')) : $contactDefaults['name'],
            'guest_email' => $isPostRequest ? trim((string)($_POST['guest_email'] ?? '')) : $contactDefaults['email'],
            'guest_phone' => $isPostRequest ? trim((string)($_POST['guest_phone'] ?? '')) : $contactDefaults['phone'],
            'party_size' => (int)($_POST['party_size'] ?? 1),
            'notes' => $_POST['notes'] ?? '',
        ],
    ],
    'current_nav' => 'reservations',
    'body_class' => 'page-reservations-book',
    'page_kind' => 'form',
]);
