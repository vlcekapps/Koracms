<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="profile-title">
    <p class="section-kicker">Můj účet</p>
    <h1 id="profile-title" class="section-title section-title--hero">Můj profil</h1>
    <p class="section-subtitle">Spravujte své kontaktní údaje, profil a přístupové heslo.</p>

    <nav aria-label="Uživatelské odkazy">
      <ul class="account-nav">
        <?php if ($showReservationsLink): ?>
          <li><a class="button-secondary" href="<?= BASE_URL ?>/reservations/my.php">Moje rezervace</a></li>
        <?php endif; ?>
        <li><a class="button-secondary" href="<?= BASE_URL ?>/public_logout.php">Odhlásit se</a></li>
      </ul>
    </nav>

    <?php if ($profileSuccess): ?>
      <div class="status-message status-message--success" role="status" aria-atomic="true">
        <p><strong>Profil byl uložen.</strong></p>
      </div>
    <?php endif; ?>

    <?php if ($passwordSuccess): ?>
      <div class="status-message status-message--success" role="status" aria-atomic="true">
        <p><strong>Heslo bylo změněno.</strong></p>
      </div>
    <?php endif; ?>

    <?php if (!empty($profileErrors)): ?>
      <div id="profile-errors" class="status-message status-message--error" role="alert" aria-atomic="true">
        <ul>
          <?php foreach ($profileErrors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate class="form-stack"<?php if (!empty($profileErrors)): ?> aria-describedby="profile-errors"<?php endif; ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="profile">

      <fieldset class="form-fieldset">
        <legend>Osobní údaje</legend>

        <div class="field">
          <label for="first_name">Jméno <span aria-hidden="true">*</span></label>
          <input type="text" id="first_name" name="first_name" class="form-control" required aria-required="true"
                 maxlength="100" value="<?= h($profileRow['first_name']) ?>" autocomplete="given-name">
        </div>

        <div class="field">
          <label for="last_name">Příjmení <span aria-hidden="true">*</span></label>
          <input type="text" id="last_name" name="last_name" class="form-control" required aria-required="true"
                 maxlength="100" value="<?= h($profileRow['last_name']) ?>" autocomplete="family-name">
        </div>

        <div class="field">
          <label for="phone">Telefon <span aria-hidden="true">*</span></label>
          <input type="tel" id="phone" name="phone" class="form-control" required aria-required="true"
                 maxlength="20" value="<?= h($profileRow['phone'] ?? '') ?>" autocomplete="tel" aria-describedby="phone-hint">
          <small id="phone-hint">Nutné pro rezervace</small>
        </div>

        <div class="field">
          <label for="email_display">E-mail</label>
          <input type="email" id="email_display" class="form-control readonly-control"
                 value="<?= h($profileRow['email']) ?>" readonly aria-readonly="true">
        </div>

        <div class="button-row button-row--start">
          <button type="submit" class="button-primary">Uložit profil</button>
        </div>
      </fieldset>
    </form>

    <?php if (!empty($passwordErrors)): ?>
      <div id="password-errors" class="status-message status-message--error" role="alert" aria-atomic="true">
        <ul>
          <?php foreach ($passwordErrors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate class="form-stack"<?php if (!empty($passwordErrors)): ?> aria-describedby="password-errors"<?php endif; ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="password">

      <fieldset class="form-fieldset">
        <legend>Změna hesla</legend>

        <div class="field">
          <label for="current_pass">Současné heslo <span aria-hidden="true">*</span></label>
          <input type="password" id="current_pass" name="current_pass" class="form-control" required aria-required="true"
                 autocomplete="current-password">
        </div>

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

        <div class="button-row button-row--start">
          <button type="submit" class="button-primary">Změnit heslo</button>
        </div>
      </fieldset>
    </form>
  </section>
</div>
