<?php
$fieldErrors = is_array($fieldErrors ?? null) ? $fieldErrors : [];
$fieldErrorId = static fn (string $key): string => 'reservation-book-' . str_replace('_', '-', $key) . '-error';
$fieldAttributes = static function (string $key, array $extraDescriptions = []) use ($fieldErrors, $fieldErrorId): string {
    $descriptions = [];
    foreach ($extraDescriptions as $descriptionId) {
        $descriptionId = trim((string)$descriptionId);
        if ($descriptionId !== '') {
            $descriptions[] = $descriptionId;
        }
    }
    if (isset($fieldErrors[$key])) {
        $descriptions[] = $fieldErrorId($key);
    }

    $attributes = isset($fieldErrors[$key]) ? ' aria-invalid="true"' : '';
    if ($descriptions !== []) {
        $attributes .= ' aria-describedby="' . h(implode(' ', array_unique($descriptions))) . '"';
    }

    return $attributes;
};
?>
<div class="listing-shell">
  <section class="surface surface--accent" aria-labelledby="reservation-book-title">
    <div class="button-row button-row--start">
      <a class="button-secondary" href="<?= BASE_URL ?>/reservations/resource.php?slug=<?= rawurlencode($slug) ?>"><span aria-hidden="true">←</span> Zpět na <?= h($resource['name']) ?></a>
    </div>

    <p class="section-kicker">Rezervace</p>
    <h1 id="reservation-book-title" class="section-title section-title--hero"><?= h($resource['name']) ?></h1>
    <p class="meta-row">
      <span><strong>Datum:</strong> <time datetime="<?= h($dateStr) ?>"><?= formatCzechDate($dateStr) ?></time> (<?= h($weekdayLabel) ?>)</span>
      <span><strong>Otevřeno:</strong> <?= h($openTime) ?> – <?= h($closeTime) ?></span>
    </p>
    <?php if ($resource['requires_approval']): ?>
      <p class="section-subtitle">Rezervace se po odeslání nejdřív objeví jako čekající na schválení.</p>
    <?php endif; ?>
  </section>

  <?php if (!empty($errors)): ?>
    <section class="surface surface--narrow" aria-labelledby="reservation-book-errors-heading">
      <div id="form-errors" class="status-message status-message--error" role="alert" aria-atomic="true" aria-labelledby="reservation-book-errors-heading">
        <h2 id="reservation-book-errors-heading" class="sr-only">Rezervaci se nepodařilo odeslat</h2>
        <ul>
          <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
        </ul>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($slotsEmpty): ?>
    <section class="surface surface--narrow" aria-labelledby="reservation-book-empty-slots-message">
      <div class="status-message status-message--warning" role="alert" aria-atomic="true" aria-labelledby="reservation-book-empty-slots-message">
        <h2 id="reservation-book-empty-slots-message" class="section-title section-title--compact">Na tento den nejsou k dispozici žádné volné časy.</h2>
        <p>Zkuste prosím jiný termín v kalendáři rezervací.</p>
      </div>
      <div class="button-row button-row--start">
        <a class="button-secondary" href="<?= BASE_URL ?>/reservations/resource.php?slug=<?= rawurlencode($slug) ?>">Vybrat jiný den</a>
      </div>
    </section>
  <?php else: ?>
    <section class="surface surface--narrow" aria-labelledby="reservation-book-form-title">
      <h2 id="reservation-book-form-title" class="section-title">Dokončit rezervaci</h2>

      <form method="post" action="<?= BASE_URL ?>/reservations/book.php?slug=<?= rawurlencode($slug) ?>&amp;date=<?= h($dateStr) ?>"
            class="form-stack" novalidate<?php if (!empty($errors)): ?> aria-describedby="form-errors"<?php endif; ?>>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <?= honeypotField() ?>

        <fieldset class="form-fieldset">
          <legend id="reservation-time-legend">Výběr času</legend>

          <?php if ($slotMode === 'slots'): ?>
            <?php if (isset($fieldErrors['slot'])): ?><small id="<?= h($fieldErrorId('slot')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['slot']) ?></small><?php endif; ?>
            <div class="stack-list" role="radiogroup" aria-labelledby="reservation-time-legend"<?= $fieldAttributes('slot') ?>>
              <?php foreach ($slots as $index => $slot): ?>
                <label class="choice-card" for="slot-<?= $index ?>">
                  <input type="radio" id="slot-<?= $index ?>" name="slot"
                         value="<?= h($slot['start']) ?>-<?= h($slot['end']) ?>"
                         <?= $formData['slot'] === ($slot['start'] . '-' . $slot['end']) ? 'checked' : '' ?>
                         <?= $index === 0 ? 'aria-required="true"' : '' ?> required>
                  <span>
                    <?= h($slot['start']) ?> – <?= h($slot['end']) ?>
                    <small class="help-text"><?= $slot['free'] ?>/<?= $slot['max'] ?> míst volných</small>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php elseif ($slotMode === 'range'): ?>
            <div class="field">
              <label for="reservation-start-time-range">Začátek <span aria-hidden="true">*</span></label>
              <select id="reservation-start-time-range" name="start_time" class="form-control" required aria-required="true"<?= $fieldAttributes('start_time') ?>>
                <option value="">-- vyberte --</option>
                <?php foreach ($slots as $timeOption): ?>
                  <option value="<?= h($timeOption) ?>"<?= $formData['start_time'] === $timeOption ? ' selected' : '' ?>><?= h($timeOption) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($fieldErrors['start_time'])): ?><small id="<?= h($fieldErrorId('start_time')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['start_time']) ?></small><?php endif; ?>
            </div>
            <div class="field">
              <label for="end_time">Konec <span aria-hidden="true">*</span></label>
              <select id="end_time" name="end_time" class="form-control" required aria-required="true"<?= $fieldAttributes('end_time') ?>>
                <option value="">-- vyberte --</option>
                <?php foreach ($slots as $timeOption): ?>
                  <option value="<?= h($timeOption) ?>"<?= $formData['end_time'] === $timeOption ? ' selected' : '' ?>><?= h($timeOption) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($fieldErrors['end_time'])): ?><small id="<?= h($fieldErrorId('end_time')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['end_time']) ?></small><?php endif; ?>
            </div>
            <p class="help-text">Aktuální vytížení: <?= count($existingBookings) ?>/<?= (int)$resource['max_concurrent'] ?> souběžných rezervací.</p>
          <?php elseif ($slotMode === 'duration'): ?>
            <p class="help-text">Délka rezervace: <?= (int)$resource['slot_duration_min'] ?> minut.</p>
            <div class="field">
              <label for="reservation-start-time-duration">Čas začátku <span aria-hidden="true">*</span></label>
              <select id="reservation-start-time-duration" name="start_time" class="form-control" required aria-required="true"<?= $fieldAttributes('start_time') ?>>
                <option value="">-- vyberte --</option>
                <?php foreach ($slots as $timeOption): ?>
                  <option value="<?= h($timeOption) ?>"<?= $formData['start_time'] === $timeOption ? ' selected' : '' ?>><?= h($timeOption) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($fieldErrors['start_time'])): ?><small id="<?= h($fieldErrorId('start_time')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['start_time']) ?></small><?php endif; ?>
            </div>
          <?php endif; ?>
        </fieldset>

        <?php if ($isGuest): ?>
          <fieldset class="form-fieldset">
            <legend>Vaše údaje</legend>

            <div class="field">
              <label for="guest_name">Jméno a příjmení <span aria-hidden="true">*</span></label>
              <input type="text" id="guest_name" name="guest_name" class="form-control" required aria-required="true"
                     maxlength="255" value="<?= h($formData['guest_name']) ?>" autocomplete="name"<?= $fieldAttributes('guest_name') ?>>
              <?php if (isset($fieldErrors['guest_name'])): ?><small id="<?= h($fieldErrorId('guest_name')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['guest_name']) ?></small><?php endif; ?>
            </div>

            <div class="field">
              <label for="guest_email">E-mail <span aria-hidden="true">*</span></label>
              <input type="email" id="guest_email" name="guest_email" class="form-control" required aria-required="true"
                     maxlength="255" value="<?= h($formData['guest_email']) ?>" autocomplete="email"<?= $fieldAttributes('guest_email') ?>>
              <?php if (isset($fieldErrors['guest_email'])): ?><small id="<?= h($fieldErrorId('guest_email')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['guest_email']) ?></small><?php endif; ?>
            </div>

            <div class="field">
              <label for="guest_phone">Telefon <span aria-hidden="true">*</span></label>
              <input type="tel" id="guest_phone" name="guest_phone" class="form-control" required aria-required="true"
                     maxlength="30" value="<?= h($formData['guest_phone']) ?>" autocomplete="tel"<?= $fieldAttributes('guest_phone') ?>>
              <?php if (isset($fieldErrors['guest_phone'])): ?><small id="<?= h($fieldErrorId('guest_phone')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['guest_phone']) ?></small><?php endif; ?>
            </div>
          </fieldset>
        <?php endif; ?>

        <fieldset class="form-fieldset">
          <legend>Údaje rezervace</legend>

          <div class="field">
            <label for="party_size">Počet osob <span aria-hidden="true">*</span></label>
            <input type="number" id="party_size" name="party_size" class="form-control form-control--compact"
                   value="<?= (int)$formData['party_size'] ?>" min="1" max="<?= $maxPartySize ?>" required aria-required="true"<?= $fieldAttributes('party_size') ?>>
            <?php if (isset($fieldErrors['party_size'])): ?><small id="<?= h($fieldErrorId('party_size')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['party_size']) ?></small><?php endif; ?>
          </div>

          <div class="field">
            <label for="notes">Poznámka</label>
            <textarea id="notes" name="notes" class="form-control" aria-describedby="reservation-notes-help"><?= h($formData['notes']) ?></textarea>
            <small id="reservation-notes-help" class="help-text">Nepovinné pole.</small>
          </div>
        </fieldset>

        <?php if ($isGuest): ?>
          <fieldset class="form-fieldset">
            <legend>Ověření</legend>
            <div class="field">
              <label for="captcha">Kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
              <input type="text" id="captcha" name="captcha" class="form-control form-control--compact" required
                     aria-required="true" autocomplete="off" inputmode="numeric"<?= $fieldAttributes('captcha') ?>>
              <?php if (isset($fieldErrors['captcha'])): ?><small id="<?= h($fieldErrorId('captcha')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['captcha']) ?></small><?php endif; ?>
            </div>
          </fieldset>
        <?php endif; ?>

        <div class="button-row button-row--start">
          <button type="submit" class="button-primary">Odeslat rezervaci</button>
        </div>
      </form>
    </section>
  <?php endif; ?>
</div>
