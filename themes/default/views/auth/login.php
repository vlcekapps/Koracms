<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="login-title">
    <p class="section-kicker">Uživatelský účet</p>
    <h1 id="login-title" class="section-title section-title--hero">Přihlášení</h1>
    <p class="section-subtitle">Přihlaste se ke svému veřejnému účtu a pokračujte k rezervacím nebo správě profilu.</p>

    <?php if ($notConfirmed): ?>
      <div id="form-errors" class="status-message status-message--warning" role="alert" aria-atomic="true">
        <p><strong>Váš účet dosud nebyl aktivován.</strong></p>
        <?php if ($publicRegistrationEnabled): ?>
          <p>Zkontrolujte e-mail s potvrzovacím odkazem, nebo se <a href="<?= h(BASE_URL) ?>/register.php">zaregistrujte znovu</a> pro odeslání nového odkazu.</p>
        <?php else: ?>
          <p>Zkontrolujte e-mail s potvrzovacím odkazem. Pokud už zprávu nemůžete dohledat, kontaktujte správce webu.</p>
        <?php endif; ?>
      </div>
    <?php elseif (!empty($errors)): ?>
      <div id="form-errors" class="status-message status-message--error" role="alert" aria-atomic="true">
        <ul>
          <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate class="form-stack" <?php if ($notConfirmed || !empty($errors)): ?>aria-describedby="form-errors"<?php endif; ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="redirect" value="<?= h($redirect) ?>">

      <fieldset class="form-fieldset">
        <legend>Přihlašovací údaje</legend>

        <div class="field">
          <label for="email">E-mail <span aria-hidden="true">*</span></label>
          <input type="email" id="email" name="email" class="form-control" required aria-required="true"
                 maxlength="255" value="<?= h($postedEmail) ?>" autocomplete="email">
        </div>

        <div class="field">
          <label for="password">Heslo <span aria-hidden="true">*</span></label>
          <input type="password" id="password" name="password" class="form-control" required aria-required="true"
                 autocomplete="current-password">
        </div>

        <div class="button-row">
          <button type="submit" class="button-primary">Přihlásit se</button>
          <a class="button-secondary" href="<?= BASE_URL ?>/reset_password.php">Zapomenuté heslo</a>
        </div>
      </fieldset>
    </form>

    <?php if ($publicRegistrationEnabled): ?>
      <p class="inline-links">Nemáte účet? <a href="<?= BASE_URL ?>/register.php">Zaregistrujte se</a></p>
    <?php endif; ?>
  </section>
</div>
