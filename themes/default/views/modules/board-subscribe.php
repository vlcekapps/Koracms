<?php
$boardLabel = (string)($boardLabel ?? boardModulePublicLabel());
$state = (string)($state ?? 'form');
$errors = is_array($errors ?? null) ? $errors : [];
$errorFields = is_array($errorFields ?? null) ? $errorFields : [];
$categories = is_array($categories ?? null) ? $categories : [];
$selectedCategoryIds = is_array($selectedCategoryIds ?? null) ? array_map('intval', $selectedCategoryIds) : [];
$postedEmail = (string)($postedEmail ?? '');
?>
<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="board-subscribe-title">
    <p class="section-kicker"><?= h($boardLabel) ?></p>
    <h1 id="board-subscribe-title" class="section-title section-title--hero">Odběr vývěsky</h1>
    <p class="section-subtitle">Nechte si poslat e-mail, když se ve vývěsce objeví nová veřejná položka. Odběr potvrdíte kliknutím na odkaz v potvrzovacím e-mailu.</p>

    <?php if ($state === 'ok'): ?>
      <div class="status-message status-message--success" role="status" aria-atomic="true" aria-labelledby="board-subscribe-success-message">
        <p id="board-subscribe-success-message"><strong>Téměř hotovo.</strong></p>
        <p>Pokud adresa čeká na potvrzení, poslali jsme na ni potvrzovací e-mail. Klikněte prosím na odkaz v e-mailu.</p>
      </div>
    <?php elseif ($state === 'mail_error'): ?>
      <div id="board-subscribe-mail-error" class="status-message status-message--error" role="alert" aria-atomic="true" aria-labelledby="board-subscribe-mail-error-message">
        <p id="board-subscribe-mail-error-message">Odběr se podařilo připravit, ale potvrzovací e-mail se nepodařilo odeslat. Zkuste to prosím později.</p>
      </div>
    <?php elseif ($state === 'error' && $errors !== []): ?>
      <div id="board-subscribe-form-errors" class="status-message status-message--error" role="alert" aria-atomic="true" aria-labelledby="board-subscribe-form-error-message">
        <p id="board-subscribe-form-error-message"><strong>Přihlášení k odběru se nepodařilo.</strong></p>
        <ul>
          <?php foreach ($errors as $error): ?><li><?= h((string)$error) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($state === 'form' || $state === 'error'): ?>
      <form method="post" novalidate class="form-stack"<?php if ($state === 'error'): ?> aria-describedby="board-subscribe-form-errors"<?php endif; ?>>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <?= honeypotField() ?>

        <fieldset class="form-fieldset">
          <legend>Přihlášení k odběru vývěsky</legend>

          <div class="field">
            <label for="board-subscribe-email">Váš e-mail <span aria-hidden="true">*</span></label>
            <?php $emailHasError = in_array('email', $errorFields, true); ?>
            <input type="email" id="board-subscribe-email" name="email" class="form-control" required aria-required="true"
                   maxlength="255" value="<?= h($postedEmail) ?>" autocomplete="email"
                   <?php if ($emailHasError): ?>aria-invalid="true" aria-describedby="board-subscribe-email-error"<?php endif; ?>>
            <?php if ($emailHasError): ?>
              <small id="board-subscribe-email-error" class="field-error">Zadejte platnou e-mailovou adresu.</small>
            <?php endif; ?>
          </div>

          <?php if ($categories !== []): ?>
            <fieldset class="form-fieldset">
              <legend>Rozsah odběru</legend>
              <p class="field-help">Pokud nevyberete žádnou kategorii, budete dostávat upozornění na všechny nové položky vývěsky.</p>
              <div class="checkbox-grid">
                <?php foreach ($categories as $category): ?>
                  <?php $categoryId = (int)($category['id'] ?? 0); ?>
                  <?php if ($categoryId <= 0) { continue; } ?>
                  <label class="checkbox-line" for="board-subscribe-category-<?= $categoryId ?>">
                    <input type="checkbox" id="board-subscribe-category-<?= $categoryId ?>" name="category_ids[]" value="<?= $categoryId ?>"
                           <?= in_array($categoryId, $selectedCategoryIds, true) ? 'checked' : '' ?>>
                    <span><?= h((string)($category['name'] ?? '')) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>
          <?php endif; ?>

          <div class="field">
            <label for="board-subscribe-captcha">Ověření: kolik je <?= h((string)$captchaExpr) ?>? <span aria-hidden="true">*</span></label>
            <?php $captchaHasError = in_array('captcha', $errorFields, true); ?>
            <input type="text" id="board-subscribe-captcha" name="captcha" class="form-control form-control--compact" required aria-required="true"
                   inputmode="numeric" autocomplete="off"
                   <?php if ($captchaHasError): ?>aria-invalid="true" aria-describedby="board-subscribe-captcha-error"<?php endif; ?>>
            <?php if ($captchaHasError): ?>
              <small id="board-subscribe-captcha-error" class="field-error"><?= h(publicCaptchaErrorMessage()) ?></small>
            <?php endif; ?>
          </div>

          <div class="button-row button-row--start">
            <button type="submit" class="button-primary">Přihlásit k odběru vývěsky</button>
            <a class="button-secondary" href="<?= BASE_URL ?>/board/index.php">Zpět na vývěsku</a>
          </div>
        </fieldset>
      </form>
    <?php endif; ?>
  </section>
</div>
