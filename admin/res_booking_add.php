<?php
require_once __DIR__ . '/layout.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu rezervací nemáte potřebné oprávnění.');

$pdo = db_connect();

// ── Zdroje a uživatelé pro dropdowny ──
$resources = $pdo->query(
    "SELECT id, name FROM cms_res_resources WHERE is_active = 1 ORDER BY name"
)->fetchAll();

$users = $pdo->query(
    "SELECT id, email, first_name, last_name
     FROM cms_users ORDER BY last_name, first_name, email"
)->fetchAll();

$err = '';
$fieldErrors = [];
$fieldErrorMessages = [
    'resource_id' => 'Vyberte aktivní rezervační zdroj, pro který má rezervace vzniknout.',
    'user_id' => 'Vyberte registrovaného uživatele ze seznamu, nebo přepněte typ zákazníka na Host.',
    'guest_name' => 'Doplňte jméno hosta, aby šla rezervace dohledat a potvrdit.',
    'booking_date' => 'Vyberte skutečné datum rezervace.',
    'start_time' => 'Zadejte čas začátku rezervace.',
    'end_time' => 'Zadejte čas konce pozdější než začátek.',
];

// ── Zpracování POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $mode        = ($_POST['mode'] ?? '') === 'guest' ? 'guest' : 'user';
    $resourceId  = inputInt('post', 'resource_id');
    $userId      = ($mode === 'user') ? inputInt('post', 'user_id') : null;
    $guestName   = ($mode === 'guest') ? trim($_POST['guest_name'] ?? '') : '';
    $guestEmail  = ($mode === 'guest') ? trim($_POST['guest_email'] ?? '') : '';
    $guestPhone  = ($mode === 'guest') ? trim($_POST['guest_phone'] ?? '') : '';
    $bookingDate = trim($_POST['booking_date'] ?? '');
    $startTime   = trim($_POST['start_time'] ?? '');
    $endTime     = trim($_POST['end_time'] ?? '');
    $partySize   = max(1, (int)($_POST['party_size'] ?? 1));
    $notes       = trim($_POST['notes'] ?? '');

    // Validace
    if ($resourceId === null) {
        $err = 'Rezervaci nejde vytvořit bez zdroje. U pole Zdroj je konkrétní nápověda.';
        $fieldErrors[] = 'resource_id';
    } elseif ($mode === 'user' && $userId === null) {
        $err = 'Rezervaci pro registrovaného zákazníka nejde vytvořit bez uživatele. U pole Uživatel je konkrétní nápověda.';
        $fieldErrors[] = 'user_id';
    } elseif ($mode === 'guest' && $guestName === '') {
        $err = 'Rezervaci pro hosta nejde vytvořit bez jména. U pole Jméno hosta je konkrétní nápověda.';
        $fieldErrors[] = 'guest_name';
    } elseif ($bookingDate === '' || $startTime === '' || $endTime === '') {
        $err = 'Rezervaci nejde vytvořit bez data a času. U zvýrazněných polí je konkrétní nápověda.';
        $fieldErrors = array_values(array_filter([
            $bookingDate === '' ? 'booking_date' : null,
            $startTime === '' ? 'start_time' : null,
            $endTime === '' ? 'end_time' : null,
        ]));
    } elseif ($startTime >= $endTime) {
        $err = 'Čas rezervace není použitelný. U polí Začátek a Konec je konkrétní nápověda.';
        $fieldErrors = ['start_time', 'end_time'];
    } else {
        // Kontrola dostupnosti (konflikty)
        $stmtConflict = $pdo->prepare(
            "SELECT COUNT(*) FROM cms_res_bookings
             WHERE resource_id = ?
               AND booking_date = ?
               AND status IN ('pending','confirmed')
               AND start_time < ? AND end_time > ?"
        );
        $stmtConflict->execute([$resourceId, $bookingDate, $endTime, $startTime]);
        if ((int)$stmtConflict->fetchColumn() > 0) {
            $err = 'V daném čase už existuje jiná rezervace pro tento zdroj. U data a času je konkrétní nápověda.';
            $fieldErrors = ['booking_date', 'start_time', 'end_time'];
        }
    }

    if ($err === '') {
        $calendarToken = reservationCalendarToken();
        $pdo->prepare(
            "INSERT INTO cms_res_bookings
             (resource_id, user_id, guest_name, guest_email, guest_phone,
              booking_date, start_time, end_time, party_size, notes, status, calendar_token, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, NOW(), NOW())"
        )->execute([
            $resourceId,
            $userId,
            $guestName ?: null,
            $guestEmail ?: null,
            $guestPhone ?: null,
            $bookingDate,
            $startTime,
            $endTime,
            $partySize,
            $notes ?: null,
            $calendarToken,
        ]);

        $newId = (int)$pdo->lastInsertId();
        reservationRecordBookingEvent($pdo, $newId, 'created', 'Rezervace byla vytvořena administrátorem.', currentUserId());
        $notificationBooking = reservationBookingForNotification($pdo, $newId);
        if ($notificationBooking !== null && reservationBookingContactEmail($notificationBooking) !== '') {
            $body = "Dobrý den,\n\n"
                . "vaše rezervace byla vytvořena:\n\n"
                . "Zdroj: " . (string)$notificationBooking['resource_name'] . "\n"
                . "Datum: " . (string)$notificationBooking['booking_date'] . "\n"
                . "Čas: " . substr((string)$notificationBooking['start_time'], 0, 5) . " – " . substr((string)$notificationBooking['end_time'], 0, 5) . "\n"
                . "Počet osob: " . (int)$notificationBooking['party_size'] . "\n"
                . "Stav: potvrzena\n\n"
                . "Děkujeme za rezervaci.";
            reservationSendMail($notificationBooking, 'Rezervace – ' . (string)$notificationBooking['resource_name'], $body, 'reservation_admin_created');
        }
        logAction('booking_add', "id={$newId}, resource_id={$resourceId}");

        header('Location: res_booking_detail.php?id=' . $newId . '&ok=1&redirect=' . rawurlencode(BASE_URL . '/admin/res_bookings.php'));
        exit;
    }
}

adminHeader('Nová rezervace');

// Výchozí hodnoty pro formulář
$mode        = $_POST['mode'] ?? 'user';
$resourceId  = inputInt('post', 'resource_id');
$userId      = inputInt('post', 'user_id');
$guestName   = $_POST['guest_name'] ?? '';
$guestEmail  = $_POST['guest_email'] ?? '';
$guestPhone  = $_POST['guest_phone'] ?? '';
$bookingDate = $_POST['booking_date'] ?? '';
$startTime   = $_POST['start_time'] ?? '';
$endTime     = $_POST['end_time'] ?? '';
$partySize   = $_POST['party_size'] ?? '1';
$notes       = $_POST['notes'] ?? '';
?>
<?php if ($err !== ''): ?>
  <p role="alert" class="error" id="form-error" aria-atomic="true"><?= h($err) ?></p>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
  <p role="status" class="success">Rezervace byla vytvořena.</p>
<?php endif; ?>

<p class="res-booking-required-note">
  Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<form method="post" action="res_booking_add.php" novalidate
      <?= $err !== '' ? 'aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Typ zákazníka</legend>
    <div class="res-booking-mode-row">
      <label class="admin-checkbox-label">
        <input type="radio" name="mode" value="user" <?= $mode !== 'guest' ? 'checked' : '' ?>
               data-booking-mode-toggle> Registrovaný uživatel
      </label>
      <label class="admin-checkbox-label">
        <input type="radio" name="mode" value="guest" <?= $mode === 'guest' ? 'checked' : '' ?>
               data-booking-mode-toggle> Host
      </label>
    </div>
  </fieldset>

  <div id="user-fields"<?= $mode === 'guest' ? ' hidden' : '' ?>>
    <label for="user_id">Uživatel <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <select id="user_id" name="user_id"<?= adminFieldAttributes('user_id', $fieldErrors) ?>>
      <option value="">– Vyberte –</option>
      <?php foreach ($users as $u):
          $uName = trim($u['first_name'] . ' ' . $u['last_name']);
          $uLabel = $uName !== '' ? $uName . ' (' . $u['email'] . ')' : $u['email'];
          ?>
        <option value="<?= (int)$u['id'] ?>" <?= $userId === (int)$u['id'] ? 'selected' : '' ?>><?= h($uLabel) ?></option>
      <?php endforeach; ?>
    </select>
    <?php adminRenderFieldError('user_id', $fieldErrors, [], $fieldErrorMessages['user_id']); ?>
  </div>

  <div id="guest-fields"<?= $mode !== 'guest' ? ' hidden' : '' ?>>
    <label for="guest_name">Jméno hosta <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="guest_name" name="guest_name" maxlength="255" autocomplete="name" value="<?= h($guestName) ?>"
           <?= adminFieldAttributes('guest_name', $fieldErrors) ?>>
    <?php adminRenderFieldError('guest_name', $fieldErrors, [], $fieldErrorMessages['guest_name']); ?>

    <label for="guest_email">E-mail hosta</label>
    <input type="email" id="guest_email" name="guest_email" maxlength="255" autocomplete="email" value="<?= h($guestEmail) ?>">

    <label for="guest_phone">Telefon hosta</label>
    <input type="text" id="guest_phone" name="guest_phone" maxlength="50" autocomplete="tel" value="<?= h($guestPhone) ?>">
  </div>

  <fieldset class="res-booking-fieldset res-booking-fieldset--spaced">
    <legend>Rezervace</legend>

    <label for="resource_id">Zdroj <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <select id="resource_id" name="resource_id" required aria-required="true"<?= adminFieldAttributes('resource_id', $fieldErrors) ?>>
      <option value="">– Vyberte –</option>
      <?php foreach ($resources as $r): ?>
        <option value="<?= (int)$r['id'] ?>" <?= $resourceId === (int)$r['id'] ? 'selected' : '' ?>><?= h($r['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php adminRenderFieldError('resource_id', $fieldErrors, [], $fieldErrorMessages['resource_id']); ?>

    <label for="booking_date">Datum <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="date" id="booking_date" name="booking_date" required aria-required="true"
           value="<?= h($bookingDate) ?>" class="res-booking-input-auto"
           <?= adminFieldAttributes('booking_date', $fieldErrors) ?>>
    <?php adminRenderFieldError('booking_date', $fieldErrors, [], $fieldErrorMessages['booking_date']); ?>

    <div class="res-booking-time-row">
      <div>
        <label for="start_time">Začátek <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
        <input type="time" id="start_time" name="start_time" required aria-required="true"
               value="<?= h($startTime) ?>" class="res-booking-input-stacked"
               <?= adminFieldAttributes('start_time', $fieldErrors) ?>>
        <?php adminRenderFieldError('start_time', $fieldErrors, [], $fieldErrorMessages['start_time']); ?>
      </div>
      <div>
        <label for="end_time">Konec <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
        <input type="time" id="end_time" name="end_time" required aria-required="true"
               value="<?= h($endTime) ?>" class="res-booking-input-stacked"
               <?= adminFieldAttributes('end_time', $fieldErrors) ?>>
        <?php adminRenderFieldError('end_time', $fieldErrors, [], $fieldErrorMessages['end_time']); ?>
      </div>
    </div>

    <label for="party_size">Počet osob</label>
    <input type="number" id="party_size" name="party_size" min="1" value="<?= h($partySize) ?>" class="res-booking-party-size">

    <label for="notes">Poznámka</label>
    <textarea id="notes" name="notes" rows="3" class="res-booking-note" aria-describedby="booking-notes-help"><?= h($notes) ?></textarea>
    <small id="booking-notes-help" class="field-help">Nepovinné pole.</small>
  </fieldset>

  <div class="button-row res-booking-actions">
    <button type="submit" class="btn">Vytvořit rezervaci</button>
    <a href="res_bookings.php" class="btn">Zrušit</a>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
  function toggleMode() {
    var guestToggle = document.querySelector('input[name="mode"][value="guest"]');
    var userFields = document.getElementById('user-fields');
    var guestFields = document.getElementById('guest-fields');
    if (!guestToggle || !userFields || !guestFields) {
      return;
    }
    var isGuest = guestToggle.checked;
    userFields.hidden = isGuest;
    guestFields.hidden = !isGuest;
  }

  document.querySelectorAll('[data-booking-mode-toggle]').forEach(function (radio) {
    radio.addEventListener('change', toggleMode);
  });

  toggleMode();
})();
</script>

<?php adminFooter(); ?>
