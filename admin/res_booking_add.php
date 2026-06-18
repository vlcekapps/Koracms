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
        $err = 'Vyberte zdroj.';
    } elseif ($mode === 'user' && $userId === null) {
        $err = 'Vyberte registrovaného uživatele.';
    } elseif ($mode === 'guest' && $guestName === '') {
        $err = 'Vyplňte jméno hosta.';
    } elseif ($bookingDate === '' || $startTime === '' || $endTime === '') {
        $err = 'Vyplňte datum a čas rezervace.';
    } elseif ($startTime >= $endTime) {
        $err = 'Čas začátku musí být před časem konce.';
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
            $err = 'V daném čase již existuje jiná rezervace pro tento zdroj.';
        }
    }

    if ($err === '') {
        $pdo->prepare(
            "INSERT INTO cms_res_bookings
             (resource_id, user_id, guest_name, guest_email, guest_phone,
              booking_date, start_time, end_time, party_size, notes, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW(), NOW())"
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
        ]);

        $newId = (int)$pdo->lastInsertId();
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
<style nonce="<?= cspNonce() ?>">
  .res-booking-required-note { margin-top:0; font-size:.9rem; }
  .res-booking-mode-row { display:flex; gap:1.5rem; margin-top:.5rem; flex-wrap:wrap; }
  .res-booking-fieldset { border:1px solid var(--admin-border); border-radius:10px; padding:.85rem 1rem; margin-top:1rem; }
  .res-booking-time-row { display:flex; gap:1rem; flex-wrap:wrap; }
  .res-booking-input-auto { width:auto; }
  .res-booking-input-stacked { width:auto; display:block; margin-top:.2rem; }
  .res-booking-party-size { width:100px; }
  .res-booking-note { min-height:80px; }
  .res-booking-actions { margin-top:1.5rem; }
</style>

<?php if ($err !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($err) ?></p>
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
    <select id="user_id" name="user_id">
      <option value="">– Vyberte –</option>
      <?php foreach ($users as $u):
          $uName = trim($u['first_name'] . ' ' . $u['last_name']);
          $uLabel = $uName !== '' ? $uName . ' (' . $u['email'] . ')' : $u['email'];
          ?>
        <option value="<?= (int)$u['id'] ?>" <?= $userId === (int)$u['id'] ? 'selected' : '' ?>><?= h($uLabel) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div id="guest-fields"<?= $mode !== 'guest' ? ' hidden' : '' ?>>
    <label for="guest_name">Jméno hosta <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="guest_name" name="guest_name" maxlength="255" autocomplete="name" value="<?= h($guestName) ?>">

    <label for="guest_email">E-mail hosta</label>
    <input type="email" id="guest_email" name="guest_email" maxlength="255" autocomplete="email" value="<?= h($guestEmail) ?>">

    <label for="guest_phone">Telefon hosta</label>
    <input type="text" id="guest_phone" name="guest_phone" maxlength="50" autocomplete="tel" value="<?= h($guestPhone) ?>">
  </div>

  <fieldset class="res-booking-fieldset">
    <legend>Rezervace</legend>

    <label for="resource_id">Zdroj <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <select id="resource_id" name="resource_id" required aria-required="true">
      <option value="">– Vyberte –</option>
      <?php foreach ($resources as $r): ?>
        <option value="<?= (int)$r['id'] ?>" <?= $resourceId === (int)$r['id'] ? 'selected' : '' ?>><?= h($r['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="booking_date">Datum <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="date" id="booking_date" name="booking_date" required aria-required="true"
           value="<?= h($bookingDate) ?>" class="res-booking-input-auto">

    <div class="res-booking-time-row">
      <div>
        <label for="start_time">Začátek <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
        <input type="time" id="start_time" name="start_time" required aria-required="true"
               value="<?= h($startTime) ?>" class="res-booking-input-stacked">
      </div>
      <div>
        <label for="end_time">Konec <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
        <input type="time" id="end_time" name="end_time" required aria-required="true"
               value="<?= h($endTime) ?>" class="res-booking-input-stacked">
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
