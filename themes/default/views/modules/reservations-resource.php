<div class="listing-shell">
  <section class="surface surface--accent" aria-labelledby="reservation-resource-title">
    <div class="button-row button-row--start">
      <a class="button-secondary" href="<?= BASE_URL ?>/reservations/index.php"><span aria-hidden="true">←</span> Zpět na přehled zdrojů</a>
    </div>

    <p class="section-kicker">Rezervace</p>
    <h1 id="reservation-resource-title" class="section-title section-title--hero"><?= h($resource['name']) ?></h1>

    <?php if ($successMessage): ?>
      <div class="status-message status-message--success" role="status">
        <p>Rezervace byla úspěšně odeslána. Potvrzení jsme zaslali na váš e-mail.</p>
      </div>
    <?php endif; ?>

    <p class="meta-row">
      <span class="pill"><?= h($modeLabel) ?></span>
      <?php if ((int)$resource['capacity'] > 0): ?>
        <span>Kapacita <?= (int)$resource['capacity'] ?> osob</span>
      <?php endif; ?>
      <?php if ((int)$resource['requires_approval']): ?>
        <span>Vyžaduje schválení</span>
      <?php endif; ?>
    </p>

    <?php if (!empty($locations)): ?>
      <p class="section-subtitle">
        <strong><?= count($locations) === 1 ? 'Místo' : 'Místa' ?>:</strong>
        <?php foreach ($locations as $index => $location): ?>
          <?= h($location['name']) ?><?php if ($location['address'] !== '' && $location['address'] !== null): ?> (<?= h($location['address']) ?>)<?php endif; ?><?= $index < count($locations) - 1 ? ', ' : '' ?>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>

    <?php if ($resource['description'] !== '' && $resource['description'] !== null): ?>
      <div class="prose"><?= renderContent($resource['description']) ?></div>
    <?php endif; ?>
  </section>

  <div class="split-grid">
    <section class="surface" aria-labelledby="reservation-hours-title">
      <div class="section-heading">
        <div>
          <p class="section-kicker">Dostupnost</p>
          <h2 id="reservation-hours-title" class="section-title">Otevírací doba</h2>
        </div>
      </div>

      <div class="table-shell">
        <table class="data-table" aria-labelledby="reservation-hours-title">
          <thead>
            <tr>
              <th scope="col">Den</th>
              <th scope="col">Otevřeno</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($openingHours as $openingHour): ?>
              <tr>
                <th scope="row"><?= h($openingHour['day']) ?></th>
                <td>
                  <?php if ($openingHour['closed']): ?>
                    <span class="table-muted">Zavřeno</span>
                  <?php else: ?>
                    <?= h($openingHour['hours']) ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="surface" aria-labelledby="reservation-rules-title">
      <div class="section-heading">
        <div>
          <p class="section-kicker">Pravidla</p>
          <h2 id="reservation-rules-title" class="section-title">Jak rezervace fungují</h2>
        </div>
      </div>

      <ul class="rule-list">
        <?php foreach ($bookingRules as $rule): ?>
          <li><?= $rule ?></li>
        <?php endforeach; ?>
      </ul>

      <div class="calendar-legend">
        <p class="section-kicker">Legenda kalendáře</p>
        <ul class="legend-list">
          <li><span class="legend-swatch legend-swatch--available"></span> Volné termíny</li>
          <li><span class="legend-swatch legend-swatch--full"></span> Obsazeno</li>
          <li><span class="legend-swatch legend-swatch--blocked"></span> Blokováno správcem</li>
          <li><span class="legend-swatch legend-swatch--closed"></span> Zavřeno</li>
          <li><span class="legend-swatch legend-swatch--beyond"></span> Mimo rezervační horizont</li>
        </ul>
      </div>
    </section>
  </div>

  <section class="surface" aria-labelledby="reservation-calendar-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Výběr termínu</p>
        <h2 id="reservation-calendar-title" class="section-title">Kalendář – <?= h($monthLabel) ?> <?= $year ?></h2>
      </div>
    </div>

    <nav class="calendar-nav" aria-label="Navigace v kalendáři">
      <a class="button-secondary" href="<?= h($prevMonthUrl) ?>#reservation-calendar-title"><span aria-hidden="true">←</span> <?= h($prevMonthLabel) ?></a>
      <a class="button-secondary" href="<?= h($nextMonthUrl) ?>#reservation-calendar-title"><?= h($nextMonthLabel) ?> <span aria-hidden="true">→</span></a>
    </nav>

    <div class="table-shell">
      <table class="calendar-table" aria-label="Kalendář rezervací na <?= h($monthLabel) ?> <?= $year ?>">
        <thead>
          <tr>
            <?php foreach ($dayLabels as $dayLabel): ?>
              <th scope="col"><?= h($dayLabel) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($calendarWeeks as $week): ?>
            <tr>
              <?php foreach ($week as $cell): ?>
                <?php if ($cell['empty']): ?>
                  <td class="calendar-day calendar-day--empty"></td>
                <?php elseif ($cell['status'] === 'available'): ?>
                  <td class="calendar-day calendar-day--available">
                    <a href="<?= h($cell['url']) ?>" aria-label="<?= h($cell['aria_label']) ?>">
                      <span class="calendar-day__number"><?= $cell['day'] ?></span>
                      <span class="calendar-day__note"><?= h($cell['note']) ?></span>
                    </a>
                  </td>
                <?php else: ?>
                  <td class="calendar-day calendar-day--<?= h($cell['status']) ?>">
                    <span class="calendar-day__number"><?= $cell['day'] ?></span>
                    <span class="calendar-day__note"><?= h($cell['note']) ?></span>
                  </td>
                <?php endif; ?>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
