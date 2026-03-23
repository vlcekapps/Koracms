<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="contact-title">
    <p class="section-kicker">Spojte se s námi</p>
    <h1 id="contact-title" class="section-title section-title--hero">Kontakt</h1>
    <p class="section-subtitle">Pošlete nám zprávu, dotaz nebo zpětnou vazbu. Ozveme se vám na uvedený e-mail.</p>

    <?php if ($success): ?>
      <div class="status-message status-message--success" role="status">
        <p>Zpráva byla odeslána. Děkujeme!</p>
      </div>
    <?php else: ?>
      <?php if (!empty($errors)): ?>
        <div id="form-errors" class="status-message status-message--error" role="alert">
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
            <label for="from">Váš e-mail <span aria-hidden="true">*</span></label>
            <input type="email" id="from" name="from" class="form-control" required aria-required="true"
                   maxlength="255" value="<?= h($formData['from']) ?>" autocomplete="email">
          </div>

          <div class="field">
            <label for="subject">Předmět <span aria-hidden="true">*</span></label>
            <input type="text" id="subject" name="subject" class="form-control" required aria-required="true"
                   maxlength="255" value="<?= h($formData['subject']) ?>">
          </div>

          <div class="field">
            <label for="message">Zpráva <span aria-hidden="true">*</span></label>
            <textarea id="message" name="message" class="form-control" required
                      aria-required="true"><?= h($formData['message']) ?></textarea>
          </div>

          <div class="field">
            <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
            <input type="text" id="captcha" name="captcha" class="form-control form-control--compact" required
                   aria-required="true" inputmode="numeric" autocomplete="off">
          </div>

          <div class="button-row button-row--start">
            <button type="submit" class="button-primary">Odeslat zprávu</button>
          </div>
        </fieldset>
      </form>
    <?php endif; ?>
  </section>
</div>
