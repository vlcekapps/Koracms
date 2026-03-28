<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="form-title">
    <h1 id="form-title" class="section-title section-title--hero"><?= h((string)$form['title']) ?></h1>

    <?php if (trim((string)($form['description'] ?? '')) !== ''): ?>
      <p class="section-subtitle"><?= h((string)$form['description']) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="status-message status-message--success" role="status" aria-atomic="true">
        <p><strong><?= h(trim((string)($form['success_message'] ?? '')) !== '' ? (string)$form['success_message'] : 'Formulář byl úspěšně odeslán. Děkujeme!') ?></strong></p>
      </div>
    <?php else: ?>
      <?php if (!empty($errors)): ?>
        <div id="form-errors" class="status-message status-message--error" role="alert" aria-atomic="true">
          <ul>
            <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" novalidate class="form-stack"<?php if (!empty($errors)): ?> aria-describedby="form-errors"<?php endif; ?>>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <?= honeypotField() ?>

        <fieldset class="form-fieldset">
          <legend><?= h((string)$form['title']) ?></legend>

          <?php foreach ($fields as $field): ?>
            <?php
              $name = h((string)$field['name']);
              $label = h((string)$field['label']);
              $required = (int)$field['is_required'];
              $value = h((string)($formData[$field['name']] ?? ''));
              $fieldId = 'field-' . $name;
              $placeholder = h((string)($field['placeholder'] ?? ''));
            ?>

            <?php if ($field['field_type'] === 'checkbox'): ?>
              <div class="field">
                <label>
                  <input type="checkbox" name="<?= $name ?>" value="1"<?= ($formData[$field['name']] ?? '') === '1' ? ' checked' : '' ?><?= $required ? ' required aria-required="true"' : '' ?>>
                  <?= $label ?><?= $required ? ' <span aria-hidden="true">*</span>' : '' ?>
                </label>
              </div>

            <?php elseif ($field['field_type'] === 'select'): ?>
              <div class="field">
                <label for="<?= $fieldId ?>"><?= $label ?><?= $required ? ' <span aria-hidden="true">*</span>' : '' ?></label>
                <select id="<?= $fieldId ?>" name="<?= $name ?>" class="form-control"<?= $required ? ' required aria-required="true"' : '' ?>>
                  <option value="">Vyberte možnost</option>
                  <?php foreach (explode('|', (string)($field['options'] ?? '')) as $opt): ?>
                    <?php $opt = trim($opt); if ($opt === '') continue; ?>
                    <option value="<?= h($opt) ?>"<?= ($formData[$field['name']] ?? '') === $opt ? ' selected' : '' ?>><?= h($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

            <?php elseif ($field['field_type'] === 'textarea'): ?>
              <div class="field">
                <label for="<?= $fieldId ?>"><?= $label ?><?= $required ? ' <span aria-hidden="true">*</span>' : '' ?></label>
                <textarea id="<?= $fieldId ?>" name="<?= $name ?>" class="form-control"<?= $required ? ' required aria-required="true"' : '' ?><?= $placeholder !== '' ? ' placeholder="' . $placeholder . '"' : '' ?>><?= $value ?></textarea>
              </div>

            <?php else: ?>
              <?php
                $inputType = match ($field['field_type']) {
                    'email' => 'email',
                    'tel' => 'tel',
                    'number' => 'number',
                    'date' => 'date',
                    default => 'text',
                };
                $autocomplete = match ($field['field_type']) {
                    'email' => ' autocomplete="email"',
                    'tel' => ' autocomplete="tel"',
                    default => '',
                };
              ?>
              <div class="field">
                <label for="<?= $fieldId ?>"><?= $label ?><?= $required ? ' <span aria-hidden="true">*</span>' : '' ?></label>
                <input type="<?= $inputType ?>" id="<?= $fieldId ?>" name="<?= $name ?>" class="form-control"
                       value="<?= $value ?>"<?= $required ? ' required aria-required="true"' : '' ?><?= $autocomplete ?><?= $placeholder !== '' ? ' placeholder="' . $placeholder . '"' : '' ?>>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>

          <div class="field">
            <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
            <input type="text" id="captcha" name="captcha" class="form-control form-control--compact" required
                   aria-required="true" inputmode="numeric" autocomplete="off" aria-describedby="captcha-help">
            <small id="captcha-help" class="field-help">Krátké ověření proti spamu. Zadejte jen výsledek příkladu.</small>
          </div>

          <div class="button-row button-row--start">
            <button type="submit" class="button-primary">Odeslat formulář</button>
          </div>
        </fieldset>
      </form>
    <?php endif; ?>
  </section>
</div>
