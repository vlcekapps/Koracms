<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
autoCompleteBookings();
$id  = inputInt('get', 'id');

if ($id === null) {
    header('Location: res_bookings.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT b.*, r.name AS resource_name,
            u.email AS user_email, u.first_name AS user_first_name,
            u.last_name AS user_last_name, u.phone AS user_phone
     FROM cms_res_bookings b
     LEFT JOIN cms_res_resources r ON r.id = b.resource_id
     LEFT JOIN cms_users u ON u.id = b.user_id
     WHERE b.id = ?"
);
$stmt->execute([$id]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: res_bookings.php');
    exit;
}

// ── Štítky stavů ──
$statusLabels = [
    'pending'   => 'Čeká na schválení',
    'confirmed' => 'Potvrzená',
    'cancelled' => 'Zrušená',
    'rejected'  => 'Zamítnutá',
    'completed' => 'Dokončená',
    'no_show'   => 'Nedostavil se',
];
$statusColors = [
    'pending'   => '#8a4b00',
    'confirmed' => '#1b5e20',
    'cancelled' => '#666',
    'rejected'  => '#b71c1c',
    'completed' => '#005fcc',
    'no_show'   => '#6d0000',
];

// Jméno uživatele
$contactName  = '';
$contactEmail = '';
$contactPhone = '';

if ($booking['user_id']) {
    $contactName  = trim($booking['user_first_name'] . ' ' . $booking['user_last_name']);
    $contactEmail = $booking['user_email'] ?? '';
    $contactPhone = $booking['user_phone'] ?? '';
} else {
    $contactName  = $booking['guest_name'] ?? '';
    $contactEmail = $booking['guest_email'] ?? '';
    $contactPhone = $booking['guest_phone'] ?? '';
}

adminHeader('Detail rezervace #' . (int)$booking['id']);

$ok = isset($_GET['ok']);
?>

<?php if ($ok): ?>
  <p role="status" class="success">Rezervace byla úspěšně aktualizována.</p>
<?php endif; ?>

<p><a href="res_bookings.php">&larr; Zpět na seznam</a></p>

<table>
  <caption class="sr-only">Informace o rezervaci</caption>
  <tbody>
    <tr><th scope="row">ID</th><td><?= (int)$booking['id'] ?></td></tr>
    <tr><th scope="row">Zdroj</th><td><?= h($booking['resource_name'] ?? '–') ?></td></tr>
    <tr><th scope="row">Jméno</th><td><?= h($contactName ?: '–') ?></td></tr>
    <tr><th scope="row">E-mail</th><td><?= $contactEmail ? '<a href="mailto:' . h($contactEmail) . '">' . h($contactEmail) . '</a>' : '–' ?></td></tr>
    <tr><th scope="row">Telefon</th><td><?= h($contactPhone ?: '–') ?></td></tr>
    <tr>
      <th scope="row">Typ</th>
      <td><?= $booking['user_id'] ? 'Registrovaný uživatel' : 'Host' ?></td>
    </tr>
    <tr><th scope="row">Datum</th><td><time datetime="<?= h($booking['booking_date']) ?>"><?= h($booking['booking_date']) ?></time></td></tr>
    <tr><th scope="row">Čas</th><td><?= h($booking['start_time']) ?> – <?= h($booking['end_time']) ?></td></tr>
    <tr><th scope="row">Počet osob</th><td><?= (int)$booking['party_size'] ?></td></tr>
    <tr><th scope="row">Poznámka</th><td><?= h($booking['notes'] ?: '–') ?></td></tr>
    <tr>
      <th scope="row">Stav</th>
      <td>
        <strong style="color:<?= $statusColors[$booking['status']] ?? '#333' ?>">
          <?= h($statusLabels[$booking['status']] ?? $booking['status']) ?>
        </strong>
      </td>
    </tr>
    <tr><th scope="row">Poznámka administrátora</th><td><?= h($booking['admin_note'] ?: '–') ?></td></tr>
    <tr><th scope="row">Vytvořeno</th><td><?= h($booking['created_at']) ?></td></tr>
    <?php if ($booking['cancelled_at']): ?>
      <tr><th scope="row">Zrušeno</th><td><?= h($booking['cancelled_at']) ?></td></tr>
    <?php endif; ?>
    <?php if ($booking['updated_at']): ?>
      <tr><th scope="row">Aktualizováno</th><td><?= h($booking['updated_at']) ?></td></tr>
    <?php endif; ?>
  </tbody>
</table>

<h2>Akce</h2>

<?php if ($booking['status'] === 'pending'): ?>
  <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-start;margin-bottom:1rem">
    <form action="res_booking_save.php" method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
      <input type="hidden" name="action" value="approve">
      <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
    </form>

    <form action="res_booking_save.php" method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
      <input type="hidden" name="action" value="reject">
      <fieldset style="border:1px solid #ccc;padding:.5rem 1rem">
        <legend>Zamítnutí</legend>
        <label for="admin_note_reject">Poznámka <small>(nepovinná)</small></label>
        <textarea id="admin_note_reject" name="admin_note" rows="3" style="min-height:80px"></textarea>
        <div style="margin-top:.5rem">
          <button type="submit" class="btn btn-danger"
                  onclick="return confirm('Zamítnout rezervaci?')">Zamítnout</button>
        </div>
      </fieldset>
    </form>
  </div>
<?php endif; ?>

<?php if ($booking['status'] === 'confirmed'): ?>
  <form action="res_booking_save.php" method="post" style="margin-bottom:1rem">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
    <input type="hidden" name="action" value="cancel">
    <label for="admin_note_cancel">Poznámka <small>(nepovinná)</small></label>
    <textarea id="admin_note_cancel" name="admin_note" rows="2" style="min-height:60px;max-width:400px"></textarea>
    <div style="margin-top:.5rem">
      <button type="submit" class="btn btn-danger"
              onclick="return confirm('Zrušit rezervaci?')">Zrušit</button>
    </div>
  </form>
<?php endif; ?>

<?php
  $bookingEndDt = new DateTime($booking['booking_date'] . ' ' . $booking['end_time']);
  $canComplete  = in_array($booking['status'], ['pending', 'confirmed'], true) && $bookingEndDt <= new DateTime();
?>
<?php if ($canComplete): ?>
  <form action="res_booking_save.php" method="post" style="margin-bottom:1rem">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
    <input type="hidden" name="action" value="complete">
    <button type="submit" class="btn" style="background:#005fcc;color:#fff">Označit jako dokončenou</button>
  </form>
<?php elseif (in_array($booking['status'], ['pending', 'confirmed'], true)): ?>
  <p style="color:#666;font-style:italic">Označit jako dokončenou bude možné po <?= h($bookingEndDt->format('d.m.Y H:i')) ?>.</p>
<?php endif; ?>

<?php if (in_array($booking['status'], ['confirmed', 'completed'], true) && $booking['booking_date'] < date('Y-m-d')): ?>
  <form action="res_booking_save.php" method="post" style="margin-bottom:1rem">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
    <input type="hidden" name="action" value="no_show">
    <fieldset style="border:1px solid #ccc;padding:.5rem 1rem">
      <legend>Neomluvená absence</legend>
      <label for="admin_note_noshow">Poznámka <small>(nepovinná)</small></label>
      <textarea id="admin_note_noshow" name="admin_note" rows="2" style="min-height:60px;max-width:400px"></textarea>
      <div style="margin-top:.5rem">
        <button type="submit" class="btn btn-danger"
                onclick="return confirm('Označit rezervaci jako neomluvenou? Tato akce se zaznamená do historie.')">Nedostavil se</button>
      </div>
    </fieldset>
  </form>
<?php endif; ?>

<?php adminFooter(); ?>
