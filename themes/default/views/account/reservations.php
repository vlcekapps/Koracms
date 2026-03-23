<div class="listing-shell">
  <section class="surface" aria-labelledby="my-reservations-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Můj účet</p>
        <h1 id="my-reservations-title" class="section-title section-title--hero">Moje rezervace</h1>
      </div>
    </div>

    <?php if ($flashMessage !== null): ?>
      <div class="status-message status-message--success" role="status">
        <p><?= h($flashMessage) ?></p>
      </div>
    <?php endif; ?>

    <div class="stack-sections">
      <?php foreach ($sections as $section): ?>
        <section aria-labelledby="<?= h($section['heading_id']) ?>">
          <h2 id="<?= h($section['heading_id']) ?>" class="section-title section-title--compact"><?= h($section['title']) ?></h2>

          <?php if (empty($section['items'])): ?>
            <p class="empty-state"><?= h($section['empty']) ?></p>
          <?php else: ?>
            <div class="table-shell">
              <table class="data-table" aria-labelledby="<?= h($section['heading_id']) ?>">
                <thead>
                  <tr>
                    <th scope="col">Prostor</th>
                    <th scope="col">Datum</th>
                    <th scope="col">Čas</th>
                    <th scope="col">Osob</th>
                    <th scope="col">Stav</th>
                    <?php if ($section['show_actions']): ?>
                      <th scope="col">Akce</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($section['items'] as $booking): ?>
                    <tr>
                      <td><a href="<?= BASE_URL ?>/reservations/resource.php?slug=<?= rawurlencode($booking['resource_slug']) ?>"><?= h($booking['resource_name']) ?></a></td>
                      <td><time datetime="<?= h($booking['booking_date']) ?>"><?= formatCzechDate($booking['booking_date']) ?></time></td>
                      <td><?= h(substr($booking['start_time'], 0, 5)) ?> – <?= h(substr($booking['end_time'], 0, 5)) ?></td>
                      <td><?= (int)$booking['party_size'] ?></td>
                      <td><span class="status-badge status-badge--<?= h($booking['status']) ?>"><?= h($booking['status_label']) ?></span></td>
                      <?php if ($section['show_actions']): ?>
                        <td>
                          <?php if (!empty($booking['can_cancel'])): ?>
                            <form method="post" action="<?= BASE_URL ?>/reservations/cancel.php" class="inline-form">
                              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                              <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                              <button type="submit" class="button-danger" onclick="return confirm('Opravdu chcete zrušit tuto rezervaci?')">Zrušit</button>
                            </form>
                          <?php else: ?>
                            <span class="table-muted">Nelze zrušit</span>
                          <?php endif; ?>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>

    <div class="article-actions">
      <a class="button-secondary" href="<?= BASE_URL ?>/reservations/index.php"><span aria-hidden="true">←</span> Nová rezervace</a>
    </div>
  </section>
</div>
