<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="cancel-booking-title">
    <p class="section-kicker">Rezervace</p>
    <h1 id="cancel-booking-title" class="section-title section-title--hero"><?= h($pageTitle) ?></h1>

    <?php if ($success): ?>
      <div class="status-message status-message--success" role="status">
        <p><strong>Rezervace byla úspěšně zrušena.</strong></p>
        <p>Potvrzení jsme zaslali na váš e-mail.</p>
      </div>
      <div class="button-row button-row--start">
        <a class="button-secondary" href="<?= BASE_URL ?>/reservations/index.php">Zpět na přehled zdrojů</a>
      </div>
    <?php elseif ($error !== null): ?>
      <div class="status-message status-message--error" role="alert">
        <p><?= h($error) ?></p>
      </div>
      <div class="button-row button-row--start">
        <a class="button-secondary" href="<?= BASE_URL ?>/reservations/index.php">Zpět na přehled zdrojů</a>
      </div>
    <?php else: ?>
      <p class="section-subtitle">Opravdu chcete zrušit tuto rezervaci?</p>

      <div class="table-shell">
        <table class="data-table">
          <caption class="sr-only">Detail rezervace</caption>
          <tbody>
            <tr><th scope="row">Zdroj</th><td><?= h($booking['resource_name']) ?></td></tr>
            <tr><th scope="row">Datum</th><td><time datetime="<?= h($booking['booking_date']) ?>"><?= formatCzechDate($booking['booking_date']) ?></time></td></tr>
            <tr><th scope="row">Čas</th><td><?= h(substr($booking['start_time'], 0, 5)) ?> – <?= h(substr($booking['end_time'], 0, 5)) ?></td></tr>
            <tr><th scope="row">Počet osob</th><td><?= (int)$booking['party_size'] ?></td></tr>
            <?php if ($booking['guest_name']): ?>
              <tr><th scope="row">Jméno</th><td><?= h($booking['guest_name']) ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <form method="post" action="<?= BASE_URL ?>/reservations/cancel_booking.php" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <div class="button-row button-row--start">
          <button type="submit" class="button-danger" onclick="return confirm('Opravdu zrušit rezervaci?')">Zrušit rezervaci</button>
          <a class="button-secondary" href="<?= BASE_URL ?>/reservations/index.php">Zpět</a>
        </div>
      </form>
    <?php endif; ?>
  </section>
</div>
