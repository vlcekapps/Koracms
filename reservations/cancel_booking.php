<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('reservations')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pdo   = db_connect();
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    header('Location: ' . BASE_URL . '/reservations/index.php'); exit;
}

// Načíst rezervaci podle tokenu
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

// Kontrola lhůty pro zrušení
$canCancel = false;
if (!$error) {
    $bookingTs = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
    $nowTs     = time();
    $hours     = (int)$booking['cancellation_hours'];

    if ($hours === 0 || ($bookingTs - $nowTs) >= ($hours * 3600)) {
        $canCancel = true;
    } else {
        $error = 'Lhůta pro bezplatné zrušení již vypršela (nejpozději ' . $hours . ' hodin předem).';
    }
}

// POST — zrušení
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canCancel && !$error) {
    verifyCsrf();

    $upd = $pdo->prepare("UPDATE cms_res_bookings SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() WHERE id = ? AND confirmation_token = ?");
    $upd->execute([$booking['id'], $token]);

    // Odeslat potvrzení o zrušení
    $email = $booking['guest_email'] ?: '';
    if ($email === '' && $booking['user_id']) {
        $uStmt = $pdo->prepare("SELECT email FROM cms_users WHERE id = ?");
        $uStmt->execute([$booking['user_id']]);
        $uRow = $uStmt->fetch();
        if ($uRow) $email = $uRow['email'];
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

$pageTitle = 'Zrušení rezervace';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle) ?> – <?= h(getSetting('site_name', 'Kora CMS')) ?></title>
  <?= faviconTag() ?>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 600px; margin: 2rem auto; padding: 0 1rem; line-height: 1.6; }
    .error-box { background: #fdecea; border: 2px solid #c00; color: #900; padding: .8rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
    .success-box { background: #e6f4ea; border: 2px solid #2e7d32; color: #1b5e20; padding: .8rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 1.5rem; }
    th, td { text-align: left; padding: .4rem .6rem; border-bottom: 1px solid #ddd; }
    th { width: 40%; color: #555; }
    .btn { display: inline-block; padding: .6rem 1.5rem; background: #c00; color: #fff; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
    .btn:hover { background: #a00; }
    .btn:focus { outline: 3px solid #005fcc; outline-offset: 2px; }
    a:focus { outline: 2px solid #005fcc; outline-offset: 2px; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
    .skip-link { position: absolute; left: -9999px; }
    .skip-link:focus { left: 1rem; top: 1rem; z-index: 9999; background: #fff; padding: .5rem 1rem; border: 2px solid #000; }
  </style>
</head>
<body>
  <a href="#obsah" class="skip-link">Přeskočit na obsah</a>
  <main id="obsah">
  <h1><?= h($pageTitle) ?></h1>

  <?php if ($success): ?>
    <div class="success-box" role="status" aria-atomic="true">
      <p><strong>Rezervace byla úspěšně zrušena.</strong></p>
      <p>Potvrzení jsme zaslali na váš e-mail.</p>
    </div>
    <p><a href="<?= h(BASE_URL) ?>/reservations/index.php">Zpět na přehled zdrojů</a></p>

  <?php elseif ($error): ?>
    <div class="error-box" role="alert" aria-atomic="true">
      <p><?= h($error) ?></p>
    </div>
    <p><a href="<?= h(BASE_URL) ?>/reservations/index.php">Zpět na přehled zdrojů</a></p>

  <?php else: ?>
    <p>Opravdu chcete zrušit tuto rezervaci?</p>

    <table>
      <caption class="sr-only">Detail rezervace</caption>
      <tbody>
        <tr><th scope="row">Zdroj</th><td><?= h($booking['resource_name']) ?></td></tr>
        <tr><th scope="row">Datum</th><td><time datetime="<?= h($booking['booking_date']) ?>"><?= h($booking['booking_date']) ?></time></td></tr>
        <tr><th scope="row">Čas</th><td><?= h(substr($booking['start_time'], 0, 5)) ?> – <?= h(substr($booking['end_time'], 0, 5)) ?></td></tr>
        <tr><th scope="row">Počet osob</th><td><?= (int)$booking['party_size'] ?></td></tr>
        <?php if ($booking['guest_name']): ?>
          <tr><th scope="row">Jméno</th><td><?= h($booking['guest_name']) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <form method="post" action="cancel_booking.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <button type="submit" class="btn" onclick="return confirm('Opravdu zrušit rezervaci?')">Zrušit rezervaci</button>
    </form>

    <p style="margin-top:1rem"><a href="<?= h(BASE_URL) ?>/reservations/index.php">Zpět na přehled zdrojů</a></p>
  <?php endif; ?>
  </main>
</body>
</html>
