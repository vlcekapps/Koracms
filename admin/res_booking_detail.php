<?php
require_once __DIR__ . '/layout.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu rezervací nemáte potřebné oprávnění.');

$pdo = db_connect();
autoCompleteBookings();
$id = inputInt('get', 'id');

if ($id === null) {
    header('Location: res_bookings.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT b.*, r.name AS resource_name, r.slug AS resource_slug,
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

$statusLabels = reservationBookingStatusLabels();
$statusColors = reservationBookingStatusColors();
$redirect = internalRedirectTarget(trim($_GET['redirect'] ?? ''), BASE_URL . '/admin/res_bookings.php');
$resourcePublicPath = !empty($booking['resource_slug']) ? reservationResourcePublicPath($booking) : '';

$contactName = '';
$contactEmail = '';
$contactPhone = '';

if (!empty($booking['user_id'])) {
    $contactName = trim((string)($booking['user_first_name'] ?? '') . ' ' . (string)($booking['user_last_name'] ?? ''));
    $contactEmail = (string)($booking['user_email'] ?? '');
    $contactPhone = (string)($booking['user_phone'] ?? '');
} else {
    $contactName = trim((string)($booking['guest_name'] ?? ''));
    $contactEmail = (string)($booking['guest_email'] ?? '');
    $contactPhone = (string)($booking['guest_phone'] ?? '');
}

$bookingEndDt = new DateTime((string)$booking['booking_date'] . ' ' . (string)$booking['end_time']);
$canComplete = in_array($booking['status'], ['pending', 'confirmed'], true) && $bookingEndDt <= new DateTime();

adminHeader('Detail rezervace #' . (int)$booking['id']);
?>

<?php if (isset($_GET['ok'])): ?>
  <p role="status" class="success">Rezervace byla úspěšně aktualizována.</p>
<?php endif; ?>

<p><a href="<?= h($redirect) ?>">&larr; Zpět</a></p>

<table>
  <caption class="sr-only">Informace o rezervaci</caption>
  <tbody>
    <tr><th scope="row">ID</th><td><?= (int)$booking['id'] ?></td></tr>
    <tr>
      <th scope="row">Zdroj</th>
      <td>
        <?= h((string)($booking['resource_name'] ?? '–')) ?>
        <?php if ($resourcePublicPath !== ''): ?>
          <br><small><a href="<?= h($resourcePublicPath) ?>" target="_blank" rel="noopener noreferrer">Veřejná stránka zdroje</a></small>
        <?php endif; ?>
      </td>
    </tr>
    <tr><th scope="row">Jméno</th><td><?= h($contactName !== '' ? $contactName : '–') ?></td></tr>
    <tr><th scope="row">E-mail</th><td><?= $contactEmail !== '' ? '<a href="mailto:' . h($contactEmail) . '">' . h($contactEmail) . '</a>' : '–' ?></td></tr>
    <tr><th scope="row">Telefon</th><td><?= h($contactPhone !== '' ? $contactPhone : '–') ?></td></tr>
    <tr><th scope="row">Typ</th><td><?= !empty($booking['user_id']) ? 'Registrovaný uživatel' : 'Host' ?></td></tr>
    <tr><th scope="row">Datum</th><td><time datetime="<?= h((string)$booking['booking_date']) ?>"><?= h((string)$booking['booking_date']) ?></time></td></tr>
    <tr><th scope="row">Čas</th><td><?= h((string)$booking['start_time']) ?> – <?= h((string)$booking['end_time']) ?></td></tr>
    <tr><th scope="row">Počet osob</th><td><?= (int)$booking['party_size'] ?></td></tr>
    <tr><th scope="row">Poznámka</th><td><?= h((string)($booking['notes'] ?: '–')) ?></td></tr>
    <tr>
      <th scope="row">Stav</th>
      <td>
        <strong style="color:<?= h((string)($statusColors[$booking['status']] ?? '#333333')) ?>">
          <?= h((string)($statusLabels[$booking['status']] ?? $booking['status'])) ?>
        </strong>
      </td>
    </tr>
    <tr><th scope="row">Poznámka administrátora</th><td><?= h((string)($booking['admin_note'] ?: '–')) ?></td></tr>
    <tr><th scope="row">Vytvořeno</th><td><?= h((string)$booking['created_at']) ?></td></tr>
    <?php if (!empty($booking['cancelled_at'])): ?>
      <tr><th scope="row">Zrušeno</th><td><?= h((string)$booking['cancelled_at']) ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($booking['updated_at'])): ?>
      <tr><th scope="row">Aktualizováno</th><td><?= h((string)$booking['updated_at']) ?></td></tr>
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
      <input type="hidden" name="redirect" value="<?= h(BASE_URL . '/admin/res_booking_detail.php?id=' . (int)$booking['id'] . '&redirect=' . rawurlencode($redirect)) ?>">
      <button type="submit" class="btn btn-success">Schválit</button>
    </form>

    <form action="res_booking_save.php" method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="redirect" value="<?= h(BASE_URL . '/admin/res_booking_detail.php?id=' . (int)$booking['id'] . '&redirect=' . rawurlencode($redirect)) ?>">
      <fieldset style="border:1px solid #ccc;padding:.5rem 1rem">
        <legend>Zamítnutí</legend>
        <label for="admin_note_reject">Poznámka</label>
        <textarea id="admin_note_reject" name="admin_note" rows="3" style="min-height:80px" aria-describedby="admin-note-reject-help"></textarea>
        <small id="admin-note-reject-help" class="field-help">Nepovinné pole.</small>
        <div style="margin-top:.5rem">
          <button type="submit" class="btn btn-danger" onclick="return confirm('Zamítnout rezervaci?')">Zamítnout</button>
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
    <input type="hidden" name="redirect" value="<?= h(BASE_URL . '/admin/res_booking_detail.php?id=' . (int)$booking['id'] . '&redirect=' . rawurlencode($redirect)) ?>">
    <label for="admin_note_cancel">Poznámka</label>
    <textarea id="admin_note_cancel" name="admin_note" rows="2" style="min-height:60px;max-width:400px" aria-describedby="admin-note-cancel-help"></textarea>
    <small id="admin-note-cancel-help" class="field-help">Nepovinné pole.</small>
    <div style="margin-top:.5rem">
      <button type="submit" class="btn btn-danger" onclick="return confirm('Zrušit rezervaci?')">Zrušit</button>
    </div>
  </form>
<?php endif; ?>

<?php if ($canComplete): ?>
  <form action="res_booking_save.php" method="post" style="margin-bottom:1rem">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
    <input type="hidden" name="action" value="complete">
    <input type="hidden" name="redirect" value="<?= h(BASE_URL . '/admin/res_booking_detail.php?id=' . (int)$booking['id'] . '&redirect=' . rawurlencode($redirect)) ?>">
    <button type="submit" class="btn" style="background:#005fcc;color:#fff">Označit jako dokončenou</button>
  </form>
<?php elseif (in_array($booking['status'], ['pending', 'confirmed'], true)): ?>
  <p style="color:#666;font-style:italic">Označit jako dokončenou bude možné po <?= h($bookingEndDt->format('d.m.Y H:i')) ?>.</p>
<?php endif; ?>

<?php if (in_array($booking['status'], ['confirmed', 'completed'], true) && (string)$booking['booking_date'] < date('Y-m-d')): ?>
  <form action="res_booking_save.php" method="post" style="margin-bottom:1rem">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
    <input type="hidden" name="action" value="no_show">
    <input type="hidden" name="redirect" value="<?= h(BASE_URL . '/admin/res_booking_detail.php?id=' . (int)$booking['id'] . '&redirect=' . rawurlencode($redirect)) ?>">
    <fieldset style="border:1px solid #ccc;padding:.5rem 1rem">
      <legend>Neomluvená absence</legend>
      <label for="admin_note_noshow">Poznámka</label>
      <textarea id="admin_note_noshow" name="admin_note" rows="2" style="min-height:60px;max-width:400px" aria-describedby="admin-note-noshow-help"></textarea>
      <small id="admin-note-noshow-help" class="field-help">Nepovinné pole.</small>
      <div style="margin-top:.5rem">
        <button type="submit" class="btn btn-danger"
                onclick="return confirm('Označit rezervaci jako neomluvenou? Tato akce se zaznamená do historie.')">Nedostavil se</button>
      </div>
    </fieldset>
  </form>
<?php endif; ?>

<?php adminFooter(); ?>
