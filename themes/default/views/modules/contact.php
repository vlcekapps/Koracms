<?php
$fieldErrors = is_array($fieldErrors ?? null) ? $fieldErrors : [];
$topics = is_array($topics ?? null) ? $topics : [];
$topicRequired = (bool)($topicRequired ?? false);
$referenceCode = trim((string)($referenceCode ?? ''));
$formData = is_array($formData ?? null) ? $formData : [];
$formValue = static function (string $key) use ($formData): string {
    return (string)($formData[$key] ?? '');
};
$fieldErrorId = static fn (string $key): string => 'contact-' . str_replace('_', '-', $key) . '-error';
$fieldAttributes = static function (string $key, array $extraDescriptions = []) use ($fieldErrors, $fieldErrorId): string {
    $descriptions = [];
    foreach ($extraDescriptions as $descriptionId) {
        if (trim((string)$descriptionId) !== '') {
            $descriptions[] = trim((string)$descriptionId);
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
<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="contact-title">
    <p class="section-kicker">Spojte se s námi</p>
    <h1 id="contact-title" class="section-title section-title--hero">Kontakt</h1>
    <p class="section-subtitle">Pošlete nám zprávu, dotaz nebo zpětnou vazbu. Ozveme se vám na uvedený e-mail.</p>

    <?php if ($success): ?>
      <div class="status-message status-message--success" role="status" aria-atomic="true" aria-labelledby="contact-success-message">
        <p id="contact-success-message">Zpráva byla odeslána. Děkujeme!</p>
        <?php if ($referenceCode !== ''): ?>
          <p>Referenční kód zprávy: <strong><?= h($referenceCode) ?></strong></p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <?php if (!empty($errors)): ?>
        <div id="form-errors" class="status-message status-message--error" role="alert" aria-atomic="true" aria-labelledby="contact-errors-heading">
          <p id="contact-errors-heading" class="sr-only">Kontaktní zprávu se nepodařilo odeslat</p>
          <ul>
            <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" novalidate class="form-stack"<?php if (!empty($errors)): ?> aria-describedby="form-errors"<?php endif; ?>>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <?= honeypotField() ?>

        <fieldset class="form-fieldset">
          <legend>Kontaktní formulář</legend>

          <div class="field">
            <label for="sender_name">Vaše jméno</label>
            <input type="text" id="sender_name" name="sender_name" class="form-control"
                   maxlength="255" value="<?= h($formValue('sender_name')) ?>" autocomplete="name"<?= $fieldAttributes('sender_name') ?>>
            <?php if (isset($fieldErrors['sender_name'])): ?><small id="<?= h($fieldErrorId('sender_name')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['sender_name']) ?></small><?php endif; ?>
          </div>

          <div class="field">
            <label for="from">Váš e-mail <span aria-hidden="true">*</span></label>
            <input type="email" id="from" name="from" class="form-control" required aria-required="true"
                   maxlength="255" value="<?= h($formValue('from')) ?>" autocomplete="email"<?= $fieldAttributes('from') ?>>
            <?php if (isset($fieldErrors['from'])): ?><small id="<?= h($fieldErrorId('from')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['from']) ?></small><?php endif; ?>
          </div>

          <?php if ($topics !== []): ?>
            <div class="field">
              <label for="topic_id">Téma dotazu <span aria-hidden="true">*</span></label>
              <select id="topic_id" name="topic_id" class="form-control" required aria-required="true"<?= $fieldAttributes('topic_id') ?>>
                <option value="">Vyberte téma</option>
                <?php foreach ($topics as $topic): ?>
                  <?php $topicId = (string)(int)($topic['id'] ?? 0); ?>
                  <option value="<?= h($topicId) ?>"<?= $formValue('topic_id') === $topicId ? ' selected' : '' ?>>
                    <?= h((string)($topic['name'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($fieldErrors['topic_id'])): ?><small id="<?= h($fieldErrorId('topic_id')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['topic_id']) ?></small><?php endif; ?>
              <?php foreach ($topics as $topic): ?>
                <?php if (trim((string)($topic['description'] ?? '')) !== ''): ?>
                  <small class="field-help"><?= h((string)$topic['name']) ?>: <?= h((string)$topic['description']) ?></small>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="field">
            <label for="subject">Předmět <span aria-hidden="true">*</span></label>
            <input type="text" id="subject" name="subject" class="form-control" required aria-required="true"
                   maxlength="255" value="<?= h($formValue('subject')) ?>"<?= $fieldAttributes('subject') ?>>
            <?php if (isset($fieldErrors['subject'])): ?><small id="<?= h($fieldErrorId('subject')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['subject']) ?></small><?php endif; ?>
          </div>

          <div class="field">
            <label for="message">Zpráva <span aria-hidden="true">*</span></label>
            <textarea id="message" name="message" class="form-control" required
                      aria-required="true"<?= $fieldAttributes('message') ?>><?= h($formValue('message')) ?></textarea>
            <?php if (isset($fieldErrors['message'])): ?><small id="<?= h($fieldErrorId('message')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['message']) ?></small><?php endif; ?>
          </div>

          <div class="field">
            <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
            <input type="text" id="captcha" name="captcha" class="form-control form-control--compact" required
                   aria-required="true" inputmode="numeric" autocomplete="off"<?= $fieldAttributes('captcha') ?>>
            <?php if (isset($fieldErrors['captcha'])): ?><small id="<?= h($fieldErrorId('captcha')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['captcha']) ?></small><?php endif; ?>
          </div>

          <div class="button-row button-row--start">
            <button type="submit" class="button-primary">Odeslat zprávu</button>
          </div>
        </fieldset>
      </form>
    <?php endif; ?>
  </section>
</div>
