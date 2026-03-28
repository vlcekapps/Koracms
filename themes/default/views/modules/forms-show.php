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

      <?php
      $displayStateData = [];
      foreach ($fields as $visibilityField) {
          $visibilityType = normalizeFormFieldType((string)($visibilityField['field_type'] ?? 'text'));
          $visibilityName = (string)($visibilityField['name'] ?? '');
          $visibilityDefault = (string)($visibilityField['default_value'] ?? '');

          if ($visibilityType === 'hidden') {
              $displayStateData[$visibilityName] = $visibilityDefault;
              continue;
          }

          if ($visibilityType === 'checkbox_group') {
              $displayStateData[$visibilityName] = array_key_exists($visibilityName, $formData)
                  ? (array)$formData[$visibilityName]
                  : ($visibilityDefault !== '' ? formFieldOptionsList(str_replace(',', '|', $visibilityDefault)) : []);
              continue;
          }

          if (in_array($visibilityType, ['checkbox', 'consent'], true)) {
              $displayStateData[$visibilityName] = (string)($formData[$visibilityName] ?? $visibilityDefault);
              continue;
          }

          $displayStateData[$visibilityName] = (string)($formData[$visibilityName] ?? $visibilityDefault);
      }
      ?>

      <form method="post" enctype="multipart/form-data" novalidate class="form-stack" data-conditional-form<?php if (!empty($errors)): ?> aria-describedby="form-errors"<?php endif; ?>>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <?php if ((int)($form['use_honeypot'] ?? 1) === 1): ?>
          <?= honeypotField() ?>
        <?php endif; ?>

        <fieldset class="form-fieldset">
          <legend><?= h((string)$form['title']) ?></legend>
          <div class="form-fields-grid">

          <?php foreach ($fields as $field): ?>
            <?php
              $name = h((string)$field['name']);
              $label = h((string)$field['label']);
              $required = (int)$field['is_required'];
              $fieldType = normalizeFormFieldType((string)($field['field_type'] ?? 'text'));
              $defaultValue = (string)($field['default_value'] ?? '');
              $rawValue = $formData[$field['name']] ?? (
                $fieldType === 'checkbox_group'
                  ? ($defaultValue !== '' ? formFieldOptionsList(str_replace(',', '|', $defaultValue)) : [])
                  : $defaultValue
              );
              $value = h((string)$rawValue);
              $fieldId = 'field-' . $name;
              $placeholder = h((string)($field['placeholder'] ?? ''));
              $helpText = trim((string)($field['help_text'] ?? ''));
              $describedBy = $helpText !== '' ? $fieldId . '-help' : '';
              $optionList = formFieldOptionsList((string)($field['options'] ?? ''));
              $showIfField = trim((string)($field['show_if_field'] ?? ''));
              $showIfValue = trim((string)($field['show_if_value'] ?? ''));
              $showIfOperator = normalizeFormConditionOperator((string)($field['show_if_operator'] ?? ''), $showIfValue);
              $isConditionallyVisible = formFieldConditionMatches($field, $displayStateData);
              $fieldLayoutWidth = normalizeFormFieldLayoutWidth((string)($field['layout_width'] ?? 'full'));
              $fieldClass = 'field form-field form-field--' . $fieldLayoutWidth . ' js-conditional-field';
              $conditionalAttributes = $showIfField !== ''
                ? ' data-show-if-field="' . h($showIfField) . '" data-show-if-operator="' . h($showIfOperator) . '" data-show-if-value="' . h($showIfValue) . '"'
                : '';
              $conditionalHidden = !$isConditionallyVisible ? ' hidden aria-hidden="true"' : '';
            ?>

            <?php if ($fieldType === 'hidden'): ?>
              <input type="hidden" name="<?= $name ?>" value="<?= h((string)$defaultValue) ?>">

            <?php elseif ($fieldType === 'checkbox_group'): ?>
              <fieldset class="<?= h($fieldClass) ?> form-fieldset"<?= $conditionalAttributes ?><?= $conditionalHidden ?>>
                <legend><?= $label ?><?= $required ? ' <span aria-hidden="true">*</span>' : '' ?></legend>
                <?php foreach ($optionList as $index => $opt): ?>
                  <?php
                    $checkboxId = $fieldId . '-' . $index;
                    $isChecked = is_array($rawValue) && in_array($opt, $rawValue, true);
                  ?>
                  <div>
                    <label for="<?= $checkboxId ?>">
                      <input type="checkbox" id="<?= $checkboxId ?>" name="<?= $name ?>[]" value="<?= h($opt) ?>"<?= $isChecked ? ' checked' : '' ?><?= $describedBy !== '' ? ' aria-describedby="' . h($describedBy) . '"' : '' ?><?= $required && $index === 0 ? ' aria-required="true"' : '' ?><?= !$isConditionallyVisible ? ' disabled' : '' ?>>
                      <?= h($opt) ?>
                    </label>
                  </div>
                <?php endforeach; ?>
                <?php if ($helpText !== ''): ?>
                  <small id="<?= h($describedBy) ?>" class="field-help"><?= h($helpText) ?></small>
                <?php endif; ?>
              </fieldset>

            <?php elseif (in_array($fieldType, ['checkbox', 'consent'], true)): ?>
              <div class="<?= h($fieldClass) ?>"<?= $conditionalAttributes ?><?= $conditionalHidden ?>>
                <label>
                  <input type="checkbox" name="<?= $name ?>" value="1"<?= ((string)$rawValue) === '1' ? ' checked' : '' ?><?= $required ? ' required aria-required="true"' : '' ?><?= $describedBy !== '' ? ' aria-describedby="' . h($describedBy) . '"' : '' ?><?= !$isConditionallyVisible ? ' disabled' : '' ?>>
                  <?= $label ?><?= $required ? ' <span aria-hidden="true">*</span>' : '' ?>
                </label>
                <?php if ($helpText !== ''): ?>
                  <small id="<?= h($describedBy) ?>" class="field-help"><?= h($helpText) ?></small>
                <?php endif; ?>
              </div>

            <?php elseif ($fieldType === 'select'): ?>
              <div class="<?= h($fieldClass) ?>"<?= $conditionalAttributes ?><?= $conditionalHidden ?>>
                <label for="<?= $fieldId ?>"><?= $label ?><?= $required ? ' <span aria-hidden="true">*</span>' : '' ?></label>
                <select id="<?= $fieldId ?>" name="<?= $name ?>" class="form-control"<?= $required ? ' required aria-required="true"' : '' ?><?= $describedBy !== '' ? ' aria-describedby="' . h($describedBy) . '"' : '' ?><?= !$isConditionallyVisible ? ' disabled' : '' ?>>
                  <option value="">Vyberte možnost</option>
                  <?php foreach ($optionList as $opt): ?>
                    <option value="<?= h($opt) ?>"<?= ($formData[$field['name']] ?? '') === $opt ? ' selected' : '' ?>><?= h($opt) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ($helpText !== ''): ?>
                  <small id="<?= h($describedBy) ?>" class="field-help"><?= h($helpText) ?></small>
                <?php endif; ?>
              </div>

            <?php elseif ($fieldType === 'radio'): ?>
              <fieldset class="<?= h($fieldClass) ?> form-fieldset"<?= $conditionalAttributes ?><?= $conditionalHidden ?>>
                <legend><?= $label ?><?= $required ? ' <span aria-hidden="true">*</span>' : '' ?></legend>
                <?php foreach ($optionList as $index => $opt): ?>
                  <?php $radioId = $fieldId . '-' . $index; ?>
                  <div>
                    <label for="<?= $radioId ?>">
                      <input type="radio" id="<?= $radioId ?>" name="<?= $name ?>" value="<?= h($opt) ?>"<?= ($rawValue ?? '') === $opt ? ' checked' : '' ?><?= $required ? ' required aria-required="true"' : '' ?><?= $describedBy !== '' ? ' aria-describedby="' . h($describedBy) . '"' : '' ?><?= !$isConditionallyVisible ? ' disabled' : '' ?>>
                      <?= h($opt) ?>
                    </label>
                  </div>
                <?php endforeach; ?>
                <?php if ($helpText !== ''): ?>
                  <small id="<?= h($describedBy) ?>" class="field-help"><?= h($helpText) ?></small>
                <?php endif; ?>
              </fieldset>

            <?php elseif ($fieldType === 'textarea'): ?>
              <div class="<?= h($fieldClass) ?>"<?= $conditionalAttributes ?><?= $conditionalHidden ?>>
                <label for="<?= $fieldId ?>"><?= $label ?><?= $required ? ' <span aria-hidden="true">*</span>' : '' ?></label>
                <textarea id="<?= $fieldId ?>" name="<?= $name ?>" class="form-control"<?= $required ? ' required aria-required="true"' : '' ?><?= $placeholder !== '' ? ' placeholder="' . $placeholder . '"' : '' ?><?= $describedBy !== '' ? ' aria-describedby="' . h($describedBy) . '"' : '' ?><?= !$isConditionallyVisible ? ' disabled' : '' ?>><?= $value ?></textarea>
                <?php if ($helpText !== ''): ?>
                  <small id="<?= h($describedBy) ?>" class="field-help"><?= h($helpText) ?></small>
                <?php endif; ?>
              </div>

            <?php elseif ($fieldType === 'file'): ?>
              <div class="<?= h($fieldClass) ?>"<?= $conditionalAttributes ?><?= $conditionalHidden ?>>
                <label for="<?= $fieldId ?>"><?= $label ?><?= $required ? ' <span aria-hidden="true">*</span>' : '' ?></label>
                <input type="file" id="<?= $fieldId ?>" name="<?= $name ?>" class="form-control"
                       <?= $required ? ' required aria-required="true"' : '' ?>
                       <?= trim((string)($field['accept_types'] ?? '')) !== '' ? ' accept="' . h((string)$field['accept_types']) . '"' : '' ?>
                       <?= formFieldAllowsMultipleFiles($field) ? ' multiple' : '' ?>
                       <?= $describedBy !== '' ? ' aria-describedby="' . h($describedBy) . '"' : '' ?>
                       <?= !$isConditionallyVisible ? ' disabled' : '' ?>>
                <?php if ($helpText !== ''): ?>
                  <small id="<?= h($describedBy) ?>" class="field-help"><?= h($helpText) ?></small>
                <?php endif; ?>
              </div>

            <?php else: ?>
              <?php
                $inputType = match ($fieldType) {
                    'email' => 'email',
                    'tel' => 'tel',
                    'number' => 'number',
                    'date' => 'date',
                    'url' => 'url',
                    default => 'text',
                };
                $autocomplete = match ($fieldType) {
                    'email' => ' autocomplete="email"',
                    'tel' => ' autocomplete="tel"',
                    'url' => ' autocomplete="url"',
                    default => '',
                };
              ?>
              <div class="<?= h($fieldClass) ?>"<?= $conditionalAttributes ?><?= $conditionalHidden ?>>
                <label for="<?= $fieldId ?>"><?= $label ?><?= $required ? ' <span aria-hidden="true">*</span>' : '' ?></label>
                <input type="<?= $inputType ?>" id="<?= $fieldId ?>" name="<?= $name ?>" class="form-control"
                       value="<?= $value ?>"<?= $required ? ' required aria-required="true"' : '' ?><?= $autocomplete ?><?= $placeholder !== '' ? ' placeholder="' . $placeholder . '"' : '' ?><?= $describedBy !== '' ? ' aria-describedby="' . h($describedBy) . '"' : '' ?><?= !$isConditionallyVisible ? ' disabled' : '' ?>>
                <?php if ($helpText !== ''): ?>
                  <small id="<?= h($describedBy) ?>" class="field-help"><?= h($helpText) ?></small>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>

          <div class="field form-field form-field--full">
            <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
            <input type="text" id="captcha" name="captcha" class="form-control form-control--compact" required
                   aria-required="true" inputmode="numeric" autocomplete="off" aria-describedby="captcha-help">
            <small id="captcha-help" class="field-help">Krátké ověření proti spamu. Zadejte jen výsledek příkladu.</small>
          </div>
          </div>

          <div class="button-row button-row--start">
            <button type="submit" class="button-primary"><?= h(trim((string)($form['submit_label'] ?? '')) !== '' ? (string)$form['submit_label'] : 'Odeslat formulář') ?></button>
          </div>
        </fieldset>
      </form>

      <script nonce="<?= h(cspNonce()) ?>">
      (function () {
        const forms = document.querySelectorAll('[data-conditional-form]');
        forms.forEach((form) => {
          const conditionalFields = Array.from(form.querySelectorAll('.js-conditional-field[data-show-if-field]'));
          if (conditionalFields.length === 0) {
            return;
          }

          const controlsForName = (fieldName) => Array.from(form.elements).filter((element) => {
            return element instanceof HTMLElement && (element.name === fieldName || element.name === fieldName + '[]');
          });

          const currentValues = (fieldName) => {
            return controlsForName(fieldName).flatMap((element) => {
              if (!(element instanceof HTMLInputElement || element instanceof HTMLSelectElement || element instanceof HTMLTextAreaElement)) {
                return [];
              }

              if (element instanceof HTMLInputElement && (element.type === 'checkbox' || element.type === 'radio')) {
                return element.checked ? [element.value] : [];
              }

              if (element instanceof HTMLInputElement && element.type === 'file') {
                return element.files && element.files.length > 0 ? ['__uploaded__'] : [];
              }

              return element.value !== '' ? [element.value] : [];
            });
          };

          const updateVisibility = () => {
            conditionalFields.forEach((wrapper) => {
              const controller = wrapper.getAttribute('data-show-if-field') || '';
              const expected = wrapper.getAttribute('data-show-if-value') || '';
              const operator = wrapper.getAttribute('data-show-if-operator') || 'filled';
              const values = currentValues(controller);
              const expectedValues = expected === '' ? [] : expected.split('|').map((item) => item.trim()).filter(Boolean);
              const scalarValue = values.length > 0 ? values[0] : '';
              const isVisible = (() => {
                switch (operator) {
                  case 'filled':
                    return values.length > 0;
                  case 'empty':
                    return values.length === 0;
                  case 'equals':
                    return values.includes(expected);
                  case 'not_equals':
                    return !values.includes(expected);
                  case 'contains':
                    return expectedValues.some((item) => values.includes(item) || scalarValue === item);
                  case 'not_contains':
                    return !expectedValues.some((item) => values.includes(item) || scalarValue === item);
                  default:
                    return expected === '' ? values.length > 0 : values.includes(expected);
                }
              })();

              wrapper.hidden = !isVisible;
              wrapper.setAttribute('aria-hidden', isVisible ? 'false' : 'true');

              wrapper.querySelectorAll('input, select, textarea').forEach((control) => {
                if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) {
                  return;
                }

                if (!isVisible) {
                  if (!control.disabled) {
                    control.dataset.conditionalDisabled = '1';
                    control.disabled = true;
                  }
                  return;
                }

                if (control.dataset.conditionalDisabled === '1') {
                  control.disabled = false;
                  delete control.dataset.conditionalDisabled;
                }
              });
            });
          };

          form.addEventListener('change', updateVisibility);
          form.addEventListener('input', updateVisibility);
          updateVisibility();
        });
      }());
      </script>
    <?php endif; ?>
  </section>
</div>
