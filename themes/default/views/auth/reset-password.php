<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="reset-title">
    <p class="section-kicker">Bezpečnost účtu</p>
    <h1 id="reset-title" class="section-title section-title--hero">Obnovení hesla</h1>
    <p class="section-subtitle">
      <?php if ($mode === 'request'): ?>
        Zadejte svůj e-mail a pošleme vám odkaz pro nastavení nového hesla.
      <?php else: ?>
        Nastavte nové heslo pro svůj účet a potom se znovu přihlaste.
      <?php endif; ?>
    </p>

    <?php if (!empty($errors)): ?>
      <div id="form-errors" class="status-message status-message--error" role="alert">
        <ul>
          <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($mode === 'request'): ?>
      <?php if ($success): ?>
        <div class="status-message status-message--success" role="status">
          <p><strong>Pokud účet s tímto e-mailem existuje, odeslali jsme odkaz pro obnovení hesla.</strong></p>
          <p>Zkontrolujte svou e-mailovou schránku.</p>
        </div>
      <?php else: ?>
        <form method="post" novalidate class="form-stack"<?php if (!empty($errors)): ?> aria-describedby="form-errors"<?php endif; ?>>
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

          <fieldset class="form-fieldset">
            <legend>Žádost o obnovení hesla</legend>

            <div class="field">
              <label for="email">Váš e-mail <span aria-hidden="true">*</span></label>
              <input type="email" id="email" name="email" class="form-control" required aria-required="true"
                     maxlength="255" value="<?= h($requestEmail) ?>" autocomplete="email">
            </div>

            <div class="field">
              <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
              <input type="text" id="captcha" name="captcha" class="form-control form-control--compact" required aria-required="true"
                     inputmode="numeric" autocomplete="off">
            </div>

            <div class="button-row">
              <button type="submit" class="button-primary">Odeslat odkaz</button>
            </div>
          </fieldset>
        </form>
      <?php endif; ?>
    <?php else: ?>
      <?php if ($success): ?>
        <div class="status-message status-message--success" role="status">
          <p><strong>Heslo bylo úspěšně změněno.</strong></p>
          <p>Nyní se můžete <a href="<?= BASE_URL ?>/public_login.php">přihlásit</a>.</p>
        </div>
      <?php else: ?>
        <form method="post" novalidate class="form-stack"<?php if (!empty($errors)): ?> aria-describedby="form-errors"<?php endif; ?>>
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="token" value="<?= h($token) ?>">

          <fieldset class="form-fieldset">
            <legend>Nastavení nového hesla</legend>

            <div class="field">
              <label for="new_pass">Nové heslo (min. 8 znaků) <span aria-hidden="true">*</span></label>
              <input type="password" id="new_pass" name="new_pass" class="form-control" required aria-required="true"
                     minlength="8" autocomplete="new-password">
            </div>

            <div class="field">
              <label for="new_pass2">Nové heslo znovu <span aria-hidden="true">*</span></label>
              <input type="password" id="new_pass2" name="new_pass2" class="form-control" required aria-required="true"
                     minlength="8" autocomplete="new-password">
            </div>

            <div class="button-row">
              <button type="submit" class="button-primary">Nastavit nové heslo</button>
            </div>
          </fieldset>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <p class="inline-links"><a href="<?= BASE_URL ?>/public_login.php">Zpět na přihlášení</a></p>
  </section>
</div>
