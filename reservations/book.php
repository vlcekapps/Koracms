<?php
require_once __DIR__ . '/../db.php';
header('Cache-Control: no-cache, no-store, must-revalidate');
checkMaintenanceMode();

if (!isModuleEnabled('reservations')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$slug    = trim($_GET['slug'] ?? '');
$dateStr = trim($_GET['date'] ?? '');

if ($slug === '' || $dateStr === '') {
    header('Location: ' . BASE_URL . '/reservations/index.php'); exit;
}

// Load resource first (needed to check allow_guests)
$stmt = $pdo->prepare("SELECT * FROM cms_res_resources WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$resource = $stmt->fetch();
if (!$resource) {
    header('Location: ' . BASE_URL . '/reservations/index.php'); exit;
}
$resId = (int)$resource['id'];

// Require login unless guests are allowed
$isGuest = false;
if (!empty($resource['allow_guests'])) {
    // Guests can proceed without login, logged-in users use their account
    $isGuest = !isset($_SESSION['cms_user_id']);
} else {
    $currentUrl = BASE_URL . '/reservations/book.php?slug=' . urlencode($slug) . '&date=' . urlencode($dateStr);
    requirePublicLogin($currentUrl);
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug)); exit;
}

$bookingDate = new DateTime($dateStr);
$today       = new DateTime('today');
$now         = new DateTime();

// Check: not in the past
if ($bookingDate < $today) {
    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug)); exit;
}

// Check: within max_advance_days
$maxDate = (clone $today)->modify('+' . (int)$resource['max_advance_days'] . ' days');
if ($bookingDate > $maxDate) {
    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug)); exit;
}

// Check: not blocked
$bStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_res_blocked WHERE resource_id = ? AND blocked_date = ?");
$bStmt->execute([$resId, $dateStr]);
if ((int)$bStmt->fetchColumn() > 0) {
    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug)); exit;
}

// Day of week (0=Mon..6=Sun)
$dow = ((int)$bookingDate->format('N')) - 1;

// Check: not closed
$hStmt = $pdo->prepare("SELECT * FROM cms_res_hours WHERE resource_id = ? AND day_of_week = ?");
$hStmt->execute([$resId, $dow]);
$dayHours = $hStmt->fetch();
if (!$dayHours || $dayHours['is_closed']) {
    header('Location: ' . BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug)); exit;
}

$openTime  = substr($dayHours['open_time'], 0, 5);
$closeTime = substr($dayHours['close_time'], 0, 5);

// Load existing bookings for this date
$existingStmt = $pdo->prepare(
    "SELECT start_time, end_time FROM cms_res_bookings
     WHERE resource_id = ? AND booking_date = ? AND status IN ('pending','confirmed')"
);
$existingStmt->execute([$resId, $dateStr]);
$existingBookings = $existingStmt->fetchAll();

// Prepare slot options based on slot_mode
$slotMode = $resource['slot_mode'];
$slots = [];

if ($slotMode === 'slots') {
    $slStmt = $pdo->prepare(
        "SELECT * FROM cms_res_slots WHERE resource_id = ? AND day_of_week = ? ORDER BY start_time"
    );
    $slStmt->execute([$resId, $dow]);
    $predefinedSlots = $slStmt->fetchAll();

    foreach ($predefinedSlots as $sl) {
        $booked = 0;
        foreach ($existingBookings as $bk) {
            if ($bk['start_time'] < $sl['end_time'] && $bk['end_time'] > $sl['start_time']) {
                $booked++;
            }
        }
        $maxB = (int)$sl['max_bookings'];
        $free = $maxB - $booked;
        if ($free > 0) {
            $slots[] = [
                'start'    => substr($sl['start_time'], 0, 5),
                'end'      => substr($sl['end_time'], 0, 5),
                'free'     => $free,
                'max'      => $maxB,
            ];
        }
    }
} elseif ($slotMode === 'range') {
    // 30-min increments within opening hours, exclude fully booked times
    $maxConcurrent = (int)$resource['max_concurrent'];
    $startDt = new DateTime($dateStr . ' ' . $openTime);
    $endDt   = new DateTime($dateStr . ' ' . $closeTime);
    $timeOptions = [];
    $cur = clone $startDt;
    while ($cur <= $endDt) {
        $curStr = $cur->format('H:i:s');
        $overlap = 0;
        foreach ($existingBookings as $bk) {
            if ($bk['start_time'] <= $curStr && $bk['end_time'] > $curStr) {
                $overlap++;
            }
        }
        if ($overlap < $maxConcurrent) {
            $timeOptions[] = $cur->format('H:i');
        }
        $cur->modify('+30 minutes');
    }
    $slots = $timeOptions;
} elseif ($slotMode === 'duration') {
    // Start times every $duration min, exclude fully booked slots
    $duration      = (int)$resource['slot_duration_min'];
    $maxConcurrent = (int)$resource['max_concurrent'];
    $startDt       = new DateTime($dateStr . ' ' . $openTime);
    $endDt         = new DateTime($dateStr . ' ' . $closeTime);
    $cur           = clone $startDt;
    while (true) {
        $slotEnd = (clone $cur)->modify("+{$duration} minutes");
        if ($slotEnd > $endDt) break;
        $curStr = $cur->format('H:i:s');
        $endStr = $slotEnd->format('H:i:s');
        $overlap = 0;
        foreach ($existingBookings as $bk) {
            if ($bk['start_time'] < $endStr && $bk['end_time'] > $curStr) {
                $overlap++;
            }
        }
        if ($overlap < $maxConcurrent) {
            $slots[] = $cur->format('H:i');
        }
        $cur->modify("+{$duration} minutes");
    }
}

// POST handler
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (honeypotTriggered()) {
        header('Location: ' . BASE_URL . '/reservations/my.php?msg=ok');
        exit;
    }

    rateLimit('booking', 5, 300);

    $partySize = max(1, (int)($_POST['party_size'] ?? 1));
    $notes     = trim($_POST['notes'] ?? '');
    $capacity  = (int)$resource['capacity'];

    // Guest fields + captcha
    $guestNamePost  = '';
    $guestEmailPost = '';
    $guestPhonePost = '';
    if ($isGuest) {
        $guestNamePost  = trim($_POST['guest_name'] ?? '');
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
        if ($selectedSlot === '' || !preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $selectedSlot, $m)) {
            $errors[] = 'Vyberte prosím časový slot.';
            $startTime = null;
            $endTime   = null;
        } else {
            $startTime = $m[1] . ':00';
            $endTime   = $m[2] . ':00';
        }
    } elseif ($slotMode === 'range') {
        $startTime = ($_POST['start_time'] ?? '') . ':00';
        $endTime   = ($_POST['end_time'] ?? '') . ':00';
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $endTime)) {
            $errors[] = 'Zadejte platný začátek a konec.';
        } elseif ($startTime >= $endTime) {
            $errors[] = 'Čas konce musí být po začátku.';
        }
    } elseif ($slotMode === 'duration') {
        $selStart = $_POST['start_time'] ?? '';
        if (!preg_match('/^\d{2}:\d{2}$/', $selStart)) {
            $errors[] = 'Vyberte prosím čas začátku.';
            $startTime = null;
            $endTime   = null;
        } else {
            $duration  = (int)$resource['slot_duration_min'];
            $startTime = $selStart . ':00';
            $endDt2    = (new DateTime($dateStr . ' ' . $selStart))->modify("+{$duration} minutes");
            $endTime   = $endDt2->format('H:i:s');
        }
    }

    // Check min_advance_hours
    if (empty($errors) && $startTime !== null) {
        $bookingDt = new DateTime($dateStr . ' ' . substr($startTime, 0, 5));
        $diffHours = ($bookingDt->getTimestamp() - $now->getTimestamp()) / 3600;
        if ($diffHours < (int)$resource['min_advance_hours']) {
            $errors[] = 'Rezervace musí být provedena nejméně ' . (int)$resource['min_advance_hours'] . ' hodin předem.';
        }
    }

    if (empty($errors) && $startTime !== null && $endTime !== null) {
        // Transaction + double booking prevention
        $pdo->beginTransaction();
        try {
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM cms_res_bookings
                 WHERE resource_id = ? AND booking_date = ? AND start_time < ? AND end_time > ?
                 AND status IN ('pending','confirmed') FOR UPDATE"
            );
            $countStmt->execute([$resId, $dateStr, $endTime, $startTime]);
            $overlapCount = (int)$countStmt->fetchColumn();

            $maxAllowed = ($slotMode === 'slots')
                ? (int)($predefinedSlots[0]['max_bookings'] ?? 1) // re-check specific slot
                : (int)$resource['max_concurrent'];

            // For slots mode, find the actual max for this specific slot
            if ($slotMode === 'slots') {
                $slCheck = $pdo->prepare(
                    "SELECT max_bookings FROM cms_res_slots
                     WHERE resource_id = ? AND day_of_week = ? AND start_time = ? AND end_time = ?"
                );
                $slCheck->execute([$resId, $dow, $startTime, $endTime]);
                $slRow = $slCheck->fetch();
                $maxAllowed = $slRow ? (int)$slRow['max_bookings'] : 1;
            }

            if ($overlapCount >= $maxAllowed) {
                $pdo->rollBack();
                $errors[] = 'Vybraný čas byl právě obsazen. Nabídka byla aktualizována – vyberte prosím jiný čas.';
            } else {
                $status = (int)$resource['requires_approval'] ? 'pending' : 'confirmed';
                $token  = bin2hex(random_bytes(16));

                // Get user info
                if ($isGuest) {
                    $userId     = null;
                    $guestName  = $guestNamePost;
                    $guestEmail = $guestEmailPost;
                    $guestPhone = $guestPhonePost;
                } else {
                    $userId = currentUserId();
                    $uStmt  = $pdo->prepare("SELECT email, first_name, last_name, phone FROM cms_users WHERE id = ?");
                    $uStmt->execute([$userId]);
                    $userInfo   = $uStmt->fetch();
                    $guestName  = trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? ''));
                    $guestEmail = $userInfo['email'] ?? '';
                    $guestPhone = $userInfo['phone'] ?? '';
                }

                $insStmt = $pdo->prepare(
                    "INSERT INTO cms_res_bookings
                     (resource_id, user_id, guest_name, guest_email, guest_phone, booking_date, start_time, end_time,
                      party_size, notes, status, confirmation_token, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                );

                $insStmt->execute([
                    $resId, $userId, $guestName, $guestEmail, $guestPhone,
                    $dateStr, $startTime, $endTime, $partySize, $notes, $status, $token,
                ]);

                // Double-check: recount after INSERT (catches phantom inserts)
                $recheckStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM cms_res_bookings
                     WHERE resource_id = ? AND booking_date = ? AND start_time < ? AND end_time > ?
                     AND status IN ('pending','confirmed')"
                );
                $recheckStmt->execute([$resId, $dateStr, $endTime, $startTime]);
                $finalCount = (int)$recheckStmt->fetchColumn();

                if ($finalCount > $maxAllowed) {
                    $pdo->rollBack();
                    $errors[] = 'Vybraný čas byl právě obsazen. Nabídka byla aktualizována – vyberte prosím jiný čas.';
                } else {
                    $pdo->commit();

                    // Send confirmation email
                    if ($guestEmail !== '') {
                        $statusLabel = $status === 'confirmed' ? 'potvrzena' : 'čeká na schválení';
                        $cancelUrl   = siteUrl('/reservations/cancel_booking.php?token=' . $token);
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
                        sendMail($guestEmail, 'Rezervace – ' . $resource['name'], $mailBody);
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
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Nastala chyba při ukládání rezervace. Zkuste to prosím znovu.';
        }
    }
}

// After POST with errors, refresh available slots to show current state
if (!empty($errors)) {
    $existingStmt->execute([$resId, $dateStr]);
    $existingBookings = $existingStmt->fetchAll();
    $slots = [];
    $maxConcurrent = (int)$resource['max_concurrent'];

    if ($slotMode === 'slots') {
        foreach ($predefinedSlots as $sl) {
            $booked = 0;
            foreach ($existingBookings as $bk) {
                if ($bk['start_time'] < $sl['end_time'] && $bk['end_time'] > $sl['start_time']) {
                    $booked++;
                }
            }
            $maxB = (int)$sl['max_bookings'];
            $free = $maxB - $booked;
            if ($free > 0) {
                $slots[] = [
                    'start' => substr($sl['start_time'], 0, 5),
                    'end'   => substr($sl['end_time'], 0, 5),
                    'free'  => $free,
                    'max'   => $maxB,
                ];
            }
        }
    } elseif ($slotMode === 'range') {
        $startDt = new DateTime($dateStr . ' ' . $openTime);
        $endDt   = new DateTime($dateStr . ' ' . $closeTime);
        $cur     = clone $startDt;
        while ($cur <= $endDt) {
            $curStr = $cur->format('H:i:s');
            $overlap = 0;
            foreach ($existingBookings as $bk) {
                if ($bk['start_time'] <= $curStr && $bk['end_time'] > $curStr) {
                    $overlap++;
                }
            }
            if ($overlap < $maxConcurrent) {
                $slots[] = $cur->format('H:i');
            }
            $cur->modify('+30 minutes');
        }
    } elseif ($slotMode === 'duration') {
        $duration = (int)$resource['slot_duration_min'];
        $startDt  = new DateTime($dateStr . ' ' . $openTime);
        $endDt    = new DateTime($dateStr . ' ' . $closeTime);
        $cur      = clone $startDt;
        while (true) {
            $slotEnd = (clone $cur)->modify("+{$duration} minutes");
            if ($slotEnd > $endDt) break;
            $curStr = $cur->format('H:i:s');
            $endStr = $slotEnd->format('H:i:s');
            $overlap = 0;
            foreach ($existingBookings as $bk) {
                if ($bk['start_time'] < $endStr && $bk['end_time'] > $curStr) {
                    $overlap++;
                }
            }
            if ($overlap < $maxConcurrent) {
                $slots[] = $cur->format('H:i');
            }
            $cur->modify("+{$duration} minutes");
        }
    }
}

$czDaysFull = ['Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota', 'Neděle'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Rezervace – ' . h($resource['name']) . ' – ' . $siteName]) ?>
  <title>Rezervace – <?= h($resource['name']) ?> – <?= h($siteName) ?></title>
  <style>
    .skip-link { position: absolute; left: -9999px; }
    .skip-link:focus { left: 1rem; top: 1rem; z-index: 9999;
      background: #fff; padding: .5rem 1rem; border: 2px solid #000; }
    .book-form fieldset { border: 1px solid #ddd; border-radius: 6px; padding: 1rem 1.2rem; margin-bottom: 1rem; }
    .book-form legend { font-weight: bold; font-size: 1.1rem; padding: 0 .5rem; }
    .book-form label { display: block; margin-bottom: .3rem; font-weight: bold; }
    .book-form input[type="number"],
    .book-form textarea,
    .book-form select { width: 100%; max-width: 400px; padding: .4rem; margin-bottom: .75rem; border: 1px solid #ccc; border-radius: 3px; font-size: 1rem; }
    .book-form .radio-group { margin-bottom: .75rem; }
    .book-form .radio-group label { font-weight: normal; cursor: pointer; }
    .slot-info { font-size: .85rem; color: #666; }
    .error-box { background: #fdecea; border: 1px solid #c62828; color: #c62828; padding: .6rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
  </style>
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('reservations') ?>
</header>

<main id="obsah">
  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0"></div>
  <p><a href="<?= h(BASE_URL) ?>/reservations/resource.php?slug=<?= rawurlencode($slug) ?>"><span aria-hidden="true">&larr;</span> Zpět na <?= h($resource['name']) ?></a></p>

  <h2>Rezervace: <?= h($resource['name']) ?></h2>
  <p><strong>Datum:</strong> <?= h($dateStr) ?> (<?= h($czDaysFull[$dow]) ?>)
     &middot; <strong>Otevřeno:</strong> <?= h($openTime) ?> – <?= h($closeTime) ?></p>

  <?php if (!empty($errors)): ?>
    <ul id="form-errors" role="alert" style="color:#c00">
      <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (empty($slots)): ?>
    <p role="alert"><strong>Na tento den nejsou k dispozici žádné volné časy.</strong></p>
    <p><a href="<?= h(BASE_URL) ?>/reservations/resource.php?slug=<?= rawurlencode($slug) ?>">Vybrat jiný den</a></p>
  <?php else: ?>

  <form method="post" action="<?= h(BASE_URL) ?>/reservations/book.php?slug=<?= rawurlencode($slug) ?>&amp;date=<?= h($dateStr) ?>"
        class="book-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <?= honeypotField() ?>

    <fieldset>
      <legend>Výběr času</legend>

      <?php if ($slotMode === 'slots'): ?>
        <div class="radio-group" role="radiogroup" aria-label="Dostupné časové sloty">
          <?php foreach ($slots as $i => $sl): ?>
            <div>
              <input type="radio" id="slot_<?= $i ?>" name="slot"
                     value="<?= h($sl['start']) ?>-<?= h($sl['end']) ?>"
                     <?= $i === 0 ? 'aria-required="true"' : '' ?>
                     required>
              <label for="slot_<?= $i ?>">
                <?= h($sl['start']) ?> – <?= h($sl['end']) ?>
                <span class="slot-info">(<?= $sl['free'] ?>/<?= $sl['max'] ?> míst volných)</span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>

      <?php elseif ($slotMode === 'range'): ?>
        <div>
          <label for="start_time">Začátek</label>
          <select id="start_time" name="start_time" required aria-required="true">
            <option value="">-- vyberte --</option>
            <?php foreach ($slots as $t): ?>
              <option value="<?= h($t) ?>" <?= ($_POST['start_time'] ?? '') === $t ? 'selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="end_time">Konec</label>
          <select id="end_time" name="end_time" required aria-required="true">
            <option value="">-- vyberte --</option>
            <?php foreach ($slots as $t): ?>
              <option value="<?= h($t) ?>" <?= ($_POST['end_time'] ?? '') === $t ? 'selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <p class="slot-info">Aktuální vytížení: <?= count($existingBookings) ?>/<?= (int)$resource['max_concurrent'] ?> souběžných rezervací</p>

      <?php elseif ($slotMode === 'duration'): ?>
        <p>Délka rezervace: <?= (int)$resource['slot_duration_min'] ?> minut</p>
        <div>
          <label for="start_time">Čas začátku</label>
          <select id="start_time" name="start_time" required aria-required="true">
            <option value="">-- vyberte --</option>
            <?php foreach ($slots as $t): ?>
              <option value="<?= h($t) ?>" <?= ($_POST['start_time'] ?? '') === $t ? 'selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
    </fieldset>

    <?php if ($isGuest): ?>
    <fieldset>
      <legend>Vaše údaje</legend>

      <label for="guest_name">Jméno a příjmení <span aria-hidden="true">*</span></label>
      <input type="text" id="guest_name" name="guest_name" required aria-required="true"
             maxlength="255" value="<?= h($_POST['guest_name'] ?? '') ?>"
             style="width:100%;max-width:400px;padding:.4rem;margin-bottom:.75rem;border:1px solid #ccc;border-radius:3px;font-size:1rem">

      <label for="guest_email">E-mail <span aria-hidden="true">*</span></label>
      <input type="email" id="guest_email" name="guest_email" required aria-required="true"
             maxlength="255" value="<?= h($_POST['guest_email'] ?? '') ?>"
             style="width:100%;max-width:400px;padding:.4rem;margin-bottom:.75rem;border:1px solid #ccc;border-radius:3px;font-size:1rem">

      <label for="guest_phone">Telefon <span aria-hidden="true">*</span></label>
      <input type="tel" id="guest_phone" name="guest_phone" required aria-required="true"
             maxlength="30" value="<?= h($_POST['guest_phone'] ?? '') ?>"
             style="width:100%;max-width:400px;padding:.4rem;margin-bottom:.75rem;border:1px solid #ccc;border-radius:3px;font-size:1rem">
    </fieldset>
    <?php endif; ?>

    <fieldset>
      <legend>Údaje rezervace</legend>

      <label for="party_size">Počet osob</label>
      <input type="number" id="party_size" name="party_size"
             value="<?= (int)($_POST['party_size'] ?? 1) ?>"
             min="1" max="<?= (int)$resource['capacity'] ?: 100 ?>"
             required aria-required="true">

      <label for="notes">Poznámka <span style="font-weight:normal">(nepovinné)</span></label>
      <textarea id="notes" name="notes" rows="3"><?= h($_POST['notes'] ?? '') ?></textarea>
    </fieldset>

    <?php if ($isGuest): ?>
      <?php $captchaQ = captchaGenerate(); ?>
      <div style="margin-bottom:.75rem">
        <label for="captcha">Ověření: kolik je <?= h($captchaQ) ?>? <span aria-hidden="true">*</span></label>
        <input type="text" id="captcha" name="captcha" required aria-required="true"
               autocomplete="off" inputmode="numeric" style="width:8rem;padding:.4rem;border:1px solid #ccc;border-radius:3px;font-size:1rem">
      </div>
    <?php endif; ?>

    <button type="submit" class="btn">Odeslat rezervaci</button>
  </form>

  <?php endif; ?>
</main>

<?= siteFooter() ?>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}});</script>
</body>
</html>
