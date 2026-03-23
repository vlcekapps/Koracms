<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="register-title">
    <p class="section-kicker">Nový účet</p>
    <h1 id="register-title" class="section-title section-title--hero">Registrace</h1>
    <p class="section-subtitle">Vytvořte si veřejný účet pro rezervace, správu profilu a další služby webu.</p>

    <?php if ($resent): ?>
      <div class="status-message status-message--warning" role="status" aria-atomic="true">
        <p><strong>Váš účet dosud nebyl aktivován.</strong></p>
        <p>Odeslali jsme nový potvrzovací odkaz na váš e-mail.</p>
      </div>
    <?php elseif ($success): ?>
      <div class="status-message status-message--success" role="status" aria-atomic="true">
        <p><strong>Na váš e-mail jsme odeslali potvrzovací odkaz.</strong></p>
        <p>Klikněte prosím na odkaz v e-mailu pro dokončení registrace.</p>
      </div>
    <?php else: ?>
      <?php if (!empty($errors)): ?>
        <div id="form-errors" class="status-message status-message--error" role="alert">
          <ul>
            <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" novalidate class="form-stack" <?php if (!empty($errors)): ?>aria-describedby="form-errors"<?php endif; ?>>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <?= honeypotField() ?>

        <fieldset class="form-fieldset">
          <legend>Registrační údaje</legend>

          <div class="field">
            <label for="first_name">Jméno <span aria-hidden="true">*</span></label>
            <input type="text" id="first_name" name="first_name" class="form-control" required aria-required="true"
                   maxlength="100" value="<?= h($formData['first_name']) ?>">
          </div>

          <div class="field">
            <label for="last_name">Příjmení <span aria-hidden="true">*</span></label>
            <input type="text" id="last_name" name="last_name" class="form-control" required aria-required="true"
                   maxlength="100" value="<?= h($formData['last_name']) ?>">
          </div>

          <div class="field">
            <label for="email">E-mail <span aria-hidden="true">*</span></label>
            <input type="email" id="email" name="email" class="form-control" required aria-required="true"
                   maxlength="255" value="<?= h($formData['email']) ?>" autocomplete="email">
          </div>

          <div class="field">
            <label for="phone">Telefon <span aria-hidden="true">*</span></label>
            <input type="tel" id="phone" name="phone" class="form-control" required aria-required="true"
                   maxlength="20" value="<?= h($formData['phone']) ?>" aria-describedby="phone-hint">
            <small id="phone-hint">Nutné pro rezervace</small>
          </div>

          <div class="field">
            <label for="password">Heslo (min. 8 znaků) <span aria-hidden="true">*</span></label>
            <input type="password" id="password" name="password" class="form-control" required aria-required="true"
                   minlength="8" autocomplete="new-password">
          </div>

          <div class="field">
            <label for="password2">Heslo znovu <span aria-hidden="true">*</span></label>
            <input type="password" id="password2" name="password2" class="form-control" required aria-required="true"
                   minlength="8" autocomplete="new-password">
          </div>

          <div class="field">
            <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
            <input type="text" id="captcha" name="captcha" class="form-control form-control--compact" required aria-required="true"
                   inputmode="numeric" autocomplete="off">
          </div>

          <div class="button-row">
            <button type="submit" class="button-primary">Zaregistrovat se</button>
            <a class="button-secondary" href="<?= BASE_URL ?>/public_login.php">Přejít na přihlášení</a>
          </div>
        </fieldset>
      </form>
    <?php endif; ?>

    <?php if (!$success && !$resent): ?>
      <p class="inline-links">Již máte účet? <a href="<?= BASE_URL ?>/public_login.php">Přihlaste se</a></p>
    <?php endif; ?>
  </section>
</div>
