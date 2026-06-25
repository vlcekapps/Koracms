<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="subscribe-title">
    <p class="section-kicker">Newsletter</p>
    <h1 id="subscribe-title" class="section-title section-title--hero">Přihlášení k odběru novinek</h1>
    <p class="section-subtitle">Získejte novinky z webu přímo do e-mailu. Odběr potvrdíte kliknutím na odkaz v potvrzovacím e-mailu.</p>

    <?php if ($state === 'ok'): ?>
      <div class="status-message status-message--success" role="status" aria-atomic="true" aria-labelledby="newsletter-subscribe-success-message">
        <p id="newsletter-subscribe-success-message"><strong>Téměř hotovo!</strong></p>
        <p>Na vaši adresu jsme odeslali potvrzovací e-mail. Klikněte prosím na odkaz v e-mailu.</p>
      </div>
    <?php elseif ($state === 'exists'): ?>
      <div class="status-message status-message--success" role="status" aria-atomic="true" aria-labelledby="newsletter-subscribe-exists-message">
        <p id="newsletter-subscribe-exists-message">Tato adresa je již přihlášena k odběru.</p>
      </div>
    <?php elseif ($state === 'mail_error'): ?>
      <div id="newsletter-mail-error" class="status-message status-message--error" role="alert" aria-atomic="true" aria-labelledby="newsletter-mail-error-message">
        <p id="newsletter-mail-error-message">Adresa byla zaregistrována, ale potvrzovací e-mail se nepodařilo odeslat. Zkuste to prosím později.</p>
      </div>
    <?php elseif ($state === 'error'): ?>
      <div id="newsletter-form-errors" class="status-message status-message--error" role="alert" aria-atomic="true" aria-labelledby="newsletter-form-error-message">
        <p id="newsletter-form-error-message">Zadejte platnou e-mailovou adresu.</p>
      </div>
    <?php endif; ?>

    <?php if ($state === 'form' || $state === 'error'): ?>
      <form method="post" novalidate class="form-stack"<?php if ($state === 'error'): ?> aria-describedby="newsletter-form-errors"<?php endif; ?>>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <?= honeypotField() ?>

        <fieldset class="form-fieldset">
          <legend>Přihlášení k odběru</legend>

          <div class="field">
            <label for="email">Váš e-mail <span aria-hidden="true">*</span></label>
            <input type="email" id="email" name="email" class="form-control" required aria-required="true"
                   maxlength="255" value="<?= h($postedEmail) ?>" autocomplete="email">
          </div>

          <div class="field">
            <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
            <input type="text" id="captcha" name="captcha" class="form-control form-control--compact" required aria-required="true"
                   inputmode="numeric" autocomplete="off">
          </div>

          <div class="button-row button-row--start">
            <button type="submit" class="button-primary">Přihlásit k odběru</button>
          </div>
        </fieldset>
      </form>
    <?php endif; ?>
  </section>
</div>
