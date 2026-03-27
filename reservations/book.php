<?php
require_once __DIR__ . '/../db.php';
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
if (!empty($resource['allow_guests'])) {
    $isGuest = !isset($_SESSION['cms_user_id']);
} else {
    $currentUrl = BASE_URL . '/reservations/book.php?slug=' . urlencode($slug) . '&date=' . urlencode($dateStr);
    requirePublicLogin($currentUrl);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug));
    exit;
}

$bookingDate = new DateTime($dateStr);
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
        $booked = 0;
        foreach ($existingBookings as $booking) {
            if ($booking['start_time'] < $slot['end_time'] && $booking['end_time'] > $slot['start_time']) {
                $booked++;
            }
        }
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
        $overlap = 0;
        foreach ($existingBookings as $booking) {
            if ($booking['start_time'] < $endStr && $booking['end_time'] > $currentStr) {
                $overlap++;
            }
        }
        if ($overlap < $maxConcurrent) {
            $slots[] = $current->format('H:i');
        }
        $current->modify("+{$duration} minutes");
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $errors[] = 'Vyplňte prosím jméno a příjmení.';
        }
        if ($guestEmailPost === '' || !filter_var($guestEmailPost, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Vyplňte prosím platný e-mail.';
        }
        if ($guestPhonePost === '') {
            $errors[] = 'Vyplňte prosím telefonní číslo.';
        }
        if (!captchaVerify($_POST['captcha'] ?? '')) {
            $errors[] = 'Chybný výsledek ověřovacího příkladu.';
        }
    }

    if ($capacity > 0 && $partySize > $capacity) {
        $errors[] = 'Maximální počet osob je ' . $capacity . '.';
    }

    if ($slotMode === 'slots') {
        $selectedSlot = $_POST['slot'] ?? '';
        if ($selectedSlot === '' || !preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $selectedSlot, $matches)) {
            $errors[] = 'Vyberte prosím časový slot.';
            $startTime = null;
            $endTime = null;
        } else {
            $startTime = $matches[1] . ':00';
            $endTime = $matches[2] . ':00';
        }
    } elseif ($slotMode === 'range') {
        $startTime = ($_POST['start_time'] ?? '') . ':00';
        $endTime = ($_POST['end_time'] ?? '') . ':00';
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $endTime)) {
            $errors[] = 'Zadejte platný začátek a konec.';
        } elseif ($startTime >= $endTime) {
            $errors[] = 'Čas konce musí být po začátku.';
        }
    } else {
        $selectedStart = $_POST['start_time'] ?? '';
        if (!preg_match('/^\d{2}:\d{2}$/', $selectedStart)) {
            $errors[] = 'Vyberte prosím čas začátku.';
            $startTime = null;
            $endTime = null;
        } else {
            $duration = (int)$resource['slot_duration_min'];
            $startTime = $selectedStart . ':00';
            $endTime = (new DateTime($dateStr . ' ' . $selectedStart))->modify("+{$duration} minutes")->format('H:i:s');
        }
    }

    if (empty($errors) && $startTime !== null) {
        $bookingDateTime = new DateTime($dateStr . ' ' . substr($startTime, 0, 5));
        $diffHours = ($bookingDateTime->getTimestamp() - $now->getTimestamp()) / 3600;
        if ($diffHours < (int)$resource['min_advance_hours']) {
            $errors[] = 'Rezervace musí být provedena nejméně ' . (int)$resource['min_advance_hours'] . ' hodin předem.';
        }
    }

    if (empty($errors) && $startTime !== null && $endTime !== null) {
        $pdo->beginTransaction();
        try {
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM cms_res_bookings
                 WHERE resource_id = ? AND booking_date = ? AND start_time < ? AND end_time > ?
                 AND status IN ('pending', 'confirmed') FOR UPDATE"
            );
            $countStmt->execute([$resId, $dateStr, $endTime, $startTime]);
            $overlapCount = (int)$countStmt->fetchColumn();

            $maxAllowed = ($slotMode === 'slots')
                ? (int)($predefinedSlots[0]['max_bookings'] ?? 1)
                : (int)$resource['max_concurrent'];

            if ($slotMode === 'slots') {
                $slotCheckStmt = $pdo->prepare(
                    "SELECT max_bookings FROM cms_res_slots
                     WHERE resource_id = ? AND day_of_week = ? AND start_time = ? AND end_time = ?"
                );
                $slotCheckStmt->execute([$resId, $dayOfWeek, $startTime, $endTime]);
                $slotRow = $slotCheckStmt->fetch();
                $maxAllowed = $slotRow ? (int)$slotRow['max_bookings'] : 1;
            }

            if ($overlapCount >= $maxAllowed) {
                $pdo->rollBack();
                $errors[] = 'Vybraný čas byl právě obsazen. Nabídka byla aktualizována, vyberte prosím jiný čas.';
            } else {
                $status = (int)$resource['requires_approval'] ? 'pending' : 'confirmed';
                $token = bin2hex(random_bytes(16));

                if ($isGuest) {
                    $userId = null;
                    $guestName = $guestNamePost;
                    $guestEmail = $guestEmailPost;
                    $guestPhone = $guestPhonePost;
                } else {
                    $userId = currentUserId();
                    $userStmt = $pdo->prepare("SELECT email, first_name, last_name, phone FROM cms_users WHERE id = ?");
                    $userStmt->execute([$userId]);
                    $userInfo = $userStmt->fetch();
                    $guestName = trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? ''));
                    $guestEmail = $userInfo['email'] ?? '';
                    $guestPhone = $userInfo['phone'] ?? '';
                }

                $insertStmt = $pdo->prepare(
                    "INSERT INTO cms_res_bookings
                     (resource_id, user_id, guest_name, guest_email, guest_phone, booking_date, start_time, end_time,
                      party_size, notes, status, confirmation_token, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
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
                ]);

                $recheckStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM cms_res_bookings
                     WHERE resource_id = ? AND booking_date = ? AND start_time < ? AND end_time > ?
                     AND status IN ('pending', 'confirmed')"
                );
                $recheckStmt->execute([$resId, $dateStr, $endTime, $startTime]);
                $finalCount = (int)$recheckStmt->fetchColumn();

                if ($finalCount > $maxAllowed) {
                    $pdo->rollBack();
                    $errors[] = 'Vybraný čas byl právě obsazen. Nabídka byla aktualizována, vyberte prosím jiný čas.';
                } else {
                    $pdo->commit();

                    if ($guestEmail !== '') {
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
                        if (!sendMail($guestEmail, 'Rezervace – ' . $resource['name'], $mailBody)) {
                            error_log("sendMail FAILED: potvrzení rezervace pro {$guestEmail}");
                        }
                    }

                    if ($isGuest) {
                        header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug) . '&msg=ok');
                    } else {
                        header('Location: ' . BASE_URL . '/reservations/my.php?msg=ok');
                    }
                    exit;
                }
            }
        } catch (\Exception $e) {
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
            $booked = 0;
            foreach ($existingBookings as $booking) {
                if ($booking['start_time'] < $slot['end_time'] && $booking['end_time'] > $slot['start_time']) {
                    $booked++;
                }
            }
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
            $overlap = 0;
            foreach ($existingBookings as $booking) {
                if ($booking['start_time'] < $endStr && $booking['end_time'] > $currentStr) {
                    $overlap++;
                }
            }
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
        'isGuest' => $isGuest,
        'existingBookings' => $existingBookings,
        'maxPartySize' => (int)$resource['capacity'] ?: 100,
        'captchaExpr' => $captchaExpr,
        'formData' => [
            'slot' => $_POST['slot'] ?? '',
            'start_time' => $_POST['start_time'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'guest_name' => $_POST['guest_name'] ?? '',
            'guest_email' => $_POST['guest_email'] ?? '',
            'guest_phone' => $_POST['guest_phone'] ?? '',
            'party_size' => (int)($_POST['party_size'] ?? 1),
            'notes' => $_POST['notes'] ?? '',
        ],
    ],
    'current_nav' => 'reservations',
    'body_class' => 'page-reservations-book',
    'page_kind' => 'form',
]);
