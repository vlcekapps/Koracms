<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

$pdo = db_connect();
$id = inputInt('get', 'id');

$form = null;
$fields = [];
$fieldTypes = formFieldTypeDefinitions();
$fieldLayoutWidths = formFieldLayoutWidthDefinitions();
$conditionOperators = formConditionOperatorDefinitions();
$successBehaviors = formSuccessBehaviorDefinitions();
$presetKey = '';
$presetDefinition = null;
if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_forms WHERE id = ?");
    $stmt->execute([$id]);
    $form = $stmt->fetch() ?: null;

    if ($form) {
        $fStmt = $pdo->prepare("SELECT * FROM cms_form_fields WHERE form_id = ? ORDER BY sort_order, id");
        $fStmt->execute([$id]);
        $fields = $fStmt->fetchAll();
    }
} else {
    $requestedPreset = trim((string)($_GET['preset'] ?? ''));
    $presetDefinition = formPresetDefinition($requestedPreset);
    if ($presetDefinition !== null) {
        $presetKey = $requestedPreset;
    }
}

$err = trim($_GET['err'] ?? '');
$pageTitle = $form
    ? 'Upravit formulář – ' . mb_strimwidth((string)$form['title'], 0, 60, '…', 'UTF-8')
    : ($presetDefinition !== null ? 'Nový formulář – ' . (string)$presetDefinition['label'] : 'Nový formulář');
$newFieldDefaultSort = $fields !== [] ? (max(array_map(static fn(array $field): int => (int)$field['sort_order'], $fields)) + 1) : 0;
$formDefaults = $presetDefinition['form'] ?? [];
$presetFields = (array)($presetDefinition['fields'] ?? []);
$fieldSourceForOptions = $fields !== [] ? $fields : $presetFields;
$emailFieldOptions = formEmailFieldOptions($fieldSourceForOptions);
$submitterConfirmationSubjectValue = (string)($form['submitter_confirmation_subject'] ?? ($formDefaults['submitter_confirmation_subject'] ?? ''));
$submitterConfirmationMessageValue = (string)($form['submitter_confirmation_message'] ?? ($formDefaults['submitter_confirmation_message'] ?? ''));
$successBehaviorValue = normalizeFormSuccessBehavior(
    (string)($form['success_behavior'] ?? ($formDefaults['success_behavior'] ?? '')),
    (string)($form['redirect_url'] ?? ($formDefaults['redirect_url'] ?? ''))
);
$successPrimaryLabelValue = (string)($form['success_primary_label'] ?? ($formDefaults['success_primary_label'] ?? ''));
$successPrimaryUrlValue = (string)($form['success_primary_url'] ?? ($formDefaults['success_primary_url'] ?? ''));
$successSecondaryLabelValue = (string)($form['success_secondary_label'] ?? ($formDefaults['success_secondary_label'] ?? ''));
$successSecondaryUrlValue = (string)($form['success_secondary_url'] ?? ($formDefaults['success_secondary_url'] ?? ''));
$conditionalFieldOptions = [];
foreach ($fieldSourceForOptions as $candidateField) {
    $candidateName = trim((string)($candidateField['name'] ?? ''));
    if ($candidateName === '') {
        continue;
    }
    $candidateLabel = trim((string)($candidateField['label'] ?? ''));
    $conditionalFieldOptions[$candidateName] = $candidateLabel !== '' ? $candidateLabel : $candidateName;
}
$formPreviewData = [
    'title' => (string)($form['title'] ?? ($formDefaults['title'] ?? '')),
    'success_message' => (string)($form['success_message'] ?? ($formDefaults['success_message'] ?? '')),
];
$submitterConfirmationPreview = formSubmitterConfirmationPreview(
    $formPreviewData,
    $fieldSourceForOptions,
    $submitterConfirmationSubjectValue,
    $submitterConfirmationMessageValue
);

adminHeader($pageTitle);
?>

<?php if ($err === 'required'): ?>
  <p role="alert" class="error" id="form-error">Název formuláře je povinný.</p>
<?php elseif ($err === 'slug'): ?>
  <p role="alert" class="error" id="form-error">Slug formuláře je už obsazený.</p>
<?php elseif ($err === 'notification_email'): ?>
  <p role="alert" class="error" id="form-error">Zadejte platnou e-mailovou adresu pro notifikaci, nebo pole nechte prázdné.</p>
<?php elseif ($err === 'submitter_email_field'): ?>
  <p role="alert" class="error" id="form-error">Pro potvrzovací e-mail vyberte pole s e-mailovou adresou odesílatele.</p>
<?php endif; ?>

<div class="button-row">
  <a href="forms.php" class="btn">Zpět na formuláře</a>
  <?php if (!$form && $presetDefinition !== null): ?>
    <a href="form_form.php" class="btn">Běžný nový formulář</a>
  <?php endif; ?>
  <?php if ($form): ?>
    <a href="form_submissions.php?id=<?= (int)$form['id'] ?>" class="btn">Odpovědi formuláře</a>
    <?php if ((int)($form['is_active'] ?? 0) === 1): ?>
      <a href="<?= h(formPublicPath($form)) ?>" class="btn" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($form): ?>
  <p class="section-subtitle">Upravte nastavení formuláře, jeho pole, sekce, rozložení a to, co se má stát po úspěšném odeslání.</p>
<?php else: ?>
  <p class="section-subtitle">Nejdřív vytvořte základ formuláře. Hned po uložení budete moci přidat jeho pole, sekce, podmínky zobrazení, rozložení polí, přílohy, potvrzovací e-mail i navazující kroky po odeslání.</p>
  <?php if ($presetDefinition !== null): ?>
    <div class="notice notice-info" style="margin-bottom:1rem">
      <p><strong>Šablona:</strong> <?= h((string)$presetDefinition['label']) ?></p>
      <p><?= h((string)($presetDefinition['description'] ?? '')) ?></p>
      <?php if (!empty($presetDefinition['fields'])): ?>
        <p class="field-help" style="margin-bottom:.5rem">Po prvním uložení se automaticky přidají tato pole:</p>
        <ul class="field-help" style="margin:0;padding-left:1.25rem">
          <?php foreach ($presetDefinition['fields'] as $presetField): ?>
            <li><?= h((string)$presetField['label']) ?> (<?= h(formFieldTypeLabel((string)$presetField['field_type'])) ?>)</li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<form method="post" action="form_save.php" novalidate<?= $err !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if (!$form && $presetKey !== ''): ?>
    <input type="hidden" name="preset" value="<?= h($presetKey) ?>">
  <?php endif; ?>
  <?php if ($form): ?>
    <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje formuláře</legend>

    <div style="margin-bottom:.75rem">
      <label for="title">Název formuláře <span aria-hidden="true">*</span></label>
      <input type="text" id="title" name="title" required aria-required="true"
             maxlength="255" value="<?= h((string)($form['title'] ?? ($formDefaults['title'] ?? ''))) ?>" style="width:100%;max-width:500px">
    </div>

    <div style="margin-bottom:.75rem">
      <label for="slug">Slug (URL)</label>
      <input type="text" id="slug" name="slug" maxlength="255"
             value="<?= h((string)($form['slug'] ?? ($formDefaults['slug'] ?? ''))) ?>" style="width:100%;max-width:500px"
             aria-describedby="slug-help">
      <small id="slug-help" class="field-help">Necháte-li prázdné, adresa se vytvoří automaticky podle názvu formuláře.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="description">Popis formuláře</label>
      <textarea id="description" name="description" rows="3" style="width:100%;max-width:500px"><?= h((string)($form['description'] ?? ($formDefaults['description'] ?? ''))) ?></textarea>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="submit_label">Text tlačítka pro odeslání</label>
      <input type="text" id="submit_label" name="submit_label" maxlength="100"
             value="<?= h((string)($form['submit_label'] ?? ($formDefaults['submit_label'] ?? 'Odeslat formulář'))) ?>" style="width:100%;max-width:500px"
             aria-describedby="submit-label-help">
      <small id="submit-label-help" class="field-help">Například „Odeslat hlášení“, „Nahlásit chybu“ nebo „Poslat zprávu“.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="notification_email">E-mail pro notifikaci</label>
      <input type="email" id="notification_email" name="notification_email" maxlength="255"
             value="<?= h((string)($form['notification_email'] ?? ($formDefaults['notification_email'] ?? ''))) ?>" style="width:100%;max-width:500px"
             aria-describedby="notification-email-help" autocomplete="email">
      <small id="notification-email-help" class="field-help">Volitelné. Když pole necháte prázdné, použije se hlavní administrátorský nebo kontaktní e-mail webu.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="notification_subject">Předmět notifikačního e-mailu</label>
      <input type="text" id="notification_subject" name="notification_subject" maxlength="255"
             value="<?= h((string)($form['notification_subject'] ?? ($formDefaults['notification_subject'] ?? ''))) ?>" style="width:100%;max-width:500px"
             aria-describedby="notification-subject-help">
      <small id="notification-subject-help" class="field-help">Volitelné. Když pole necháte prázdné, použije se výchozí předmět podle názvu formuláře.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="is_active">
        <input type="checkbox" id="is_active" name="is_active" value="1"<?= ((int)($form['is_active'] ?? ($formDefaults['is_active'] ?? 1)) === 1) ? ' checked' : '' ?> aria-describedby="is-active-help">
        Zveřejnit formulář na webu
      </label>
      <small id="is-active-help" class="field-help">Neaktivní formulář zůstane uložený, ale návštěvníci ho na webu neuvidí.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="use_honeypot">
        <input type="checkbox" id="use_honeypot" name="use_honeypot" value="1"<?= ((int)($form['use_honeypot'] ?? ($formDefaults['use_honeypot'] ?? 1)) === 1) ? ' checked' : '' ?> aria-describedby="use-honeypot-help">
        Použít antispam honeypot
      </label>
      <small id="use-honeypot-help" class="field-help">Doporučeno. Přidá skryté antispam pole, které běžný návštěvník nevidí, ale automatické roboty ho často vyplní.</small>
    </div>
  </fieldset>

  <fieldset>
    <legend>Potvrzení odesílateli</legend>

    <div style="margin-bottom:.75rem">
      <label for="submitter_confirmation_enabled">
        <input type="checkbox" id="submitter_confirmation_enabled" name="submitter_confirmation_enabled" value="1"<?= ((int)($form['submitter_confirmation_enabled'] ?? ($formDefaults['submitter_confirmation_enabled'] ?? 0)) === 1) ? ' checked' : '' ?> aria-describedby="submitter-confirmation-enabled-help">
        Poslat odesílateli potvrzovací e-mail
      </label>
      <small id="submitter-confirmation-enabled-help" class="field-help">Volitelné. Po odeslání formuláře odešle potvrzení na e-mail vybraný v jednom z polí formuláře.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="submitter_email_field">Pole s e-mailovou adresou odesílatele</label>
      <select id="submitter_email_field" name="submitter_email_field" aria-describedby="submitter-email-field-help" style="width:100%;max-width:500px">
        <option value="">Nevybráno</option>
        <?php foreach ($emailFieldOptions as $fieldName => $fieldLabel): ?>
          <option value="<?= h($fieldName) ?>"<?= (string)($form['submitter_email_field'] ?? ($formDefaults['submitter_email_field'] ?? '')) === $fieldName ? ' selected' : '' ?>><?= h($fieldLabel) ?> (<?= h($fieldName) ?>)</option>
        <?php endforeach; ?>
      </select>
      <small id="submitter-email-field-help" class="field-help">Vyberte e-mailové pole, na které má přijít potvrzení. Když tu ještě žádné není, nejdřív ho přidejte mezi pole formuláře.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="submitter_confirmation_subject">Předmět potvrzovacího e-mailu</label>
      <input type="text" id="submitter_confirmation_subject" name="submitter_confirmation_subject" maxlength="255"
             value="<?= h($submitterConfirmationSubjectValue) ?>" style="width:100%;max-width:500px"
             aria-describedby="submitter-confirmation-subject-help">
      <small id="submitter-confirmation-subject-help" class="field-help">Volitelné. Když pole necháte prázdné, použije se výchozí předmět podle názvu formuláře. Ukázku vidíte hned níže.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="submitter_confirmation_message">Text potvrzovacího e-mailu</label>
      <textarea id="submitter_confirmation_message" name="submitter_confirmation_message" rows="6" style="width:100%;max-width:700px"
                aria-describedby="submitter-confirmation-message-help"><?= h($submitterConfirmationMessageValue) ?></textarea>
      <small id="submitter-confirmation-message-help" class="field-help">Můžete použít zástupné proměnné <code>{{site_name}}</code>, <code>{{form_title}}</code>, <code>{{success_message}}</code>, <code>{{submission_date}}</code> a také <code>{{field:nazev_pole}}</code> podle interního klíče pole.</small>
    </div>

    <section id="submitter-confirmation-preview" aria-labelledby="submitter-confirmation-preview-title" style="margin-top:1rem;border:1px solid #d6d6d6;border-radius:8px;padding:1rem;background:#fafafa">
      <h3 id="submitter-confirmation-preview-title" style="margin-top:0">Ukázka potvrzovacího e-mailu</h3>
      <p class="field-help" style="margin-top:0">Ukázka používá názvy a vzorová data z aktuálně uložených nebo předpřipravených polí formuláře.</p>

      <div style="margin-bottom:.75rem">
        <strong>Předmět</strong>
        <div id="submitter-confirmation-subject-preview" style="margin-top:.35rem;padding:.65rem .75rem;border:1px solid #d6d6d6;border-radius:6px;background:#fff"><?= nl2br(h($submitterConfirmationPreview['subject'])) ?></div>
      </div>

      <div style="margin-bottom:.75rem">
        <strong>Text zprávy</strong>
        <pre id="submitter-confirmation-message-preview" style="margin:.35rem 0 0;padding:.75rem;border:1px solid #d6d6d6;border-radius:6px;background:#fff;white-space:pre-wrap;font:inherit"><?= h($submitterConfirmationPreview['message']) ?></pre>
      </div>

      <details>
        <summary>Dostupné proměnné a jejich ukázkové hodnoty</summary>
        <ul style="margin:.75rem 0 0;padding-left:1.25rem">
          <?php foreach ($submitterConfirmationPreview['placeholder_map'] as $token => $sampleValue): ?>
            <li><code><?= h($token) ?></code> = <?= h($sampleValue !== '' ? $sampleValue : '—') ?></li>
          <?php endforeach; ?>
        </ul>
      </details>
    </section>
  </fieldset>

  <fieldset>
    <legend>Po odeslání formuláře</legend>
    <p class="field-help">Tady určíte, co návštěvník uvidí po úspěšném odeslání a jaké další kroky mu formulář nabídne.</p>

    <div style="margin-bottom:.75rem">
      <label for="success_message">Zpráva po odeslání</label>
      <textarea id="success_message" name="success_message" rows="2" style="width:100%;max-width:700px"
                aria-describedby="success-help"><?= h((string)($form['success_message'] ?? ($formDefaults['success_message'] ?? 'Formulář byl úspěšně odeslán. Děkujeme!'))) ?></textarea>
      <small id="success-help" class="field-help">Zobrazí se návštěvníkovi tehdy, když po odeslání zůstane na stránce formuláře.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="success_behavior">Režim po odeslání</label>
      <select id="success_behavior" name="success_behavior" aria-describedby="success-behavior-help" style="width:100%;max-width:500px">
        <?php foreach ($successBehaviors as $successBehaviorKey => $successBehaviorDefinition): ?>
          <option value="<?= h($successBehaviorKey) ?>"<?= $successBehaviorValue === $successBehaviorKey ? ' selected' : '' ?>><?= h((string)$successBehaviorDefinition['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <small id="success-behavior-help" class="field-help">Přesměrování použijte tehdy, když má návštěvník po odeslání pokračovat na jiné interní stránce. Jinak může zůstat na místě a uvidí potvrzení i volitelná tlačítka.</small>
    </div>

    <div data-after-submit-redirect style="margin-bottom:.75rem">
      <label for="redirect_url">Kam přesměrovat po odeslání</label>
      <input type="text" id="redirect_url" name="redirect_url" maxlength="500"
             value="<?= h((string)($form['redirect_url'] ?? ($formDefaults['redirect_url'] ?? ''))) ?>" style="width:100%;max-width:500px"
             aria-describedby="redirect-url-help" placeholder="/dekujeme">
      <small id="redirect-url-help" class="field-help">Zadejte interní cestu v rámci webu, například <code>/dekujeme</code>. Použije se jen v režimu přesměrování.</small>
    </div>

    <div data-after-submit-actions style="display:grid;grid-template-columns:minmax(14rem,1fr) minmax(18rem,1.2fr);gap:.75rem;align-items:end;margin-top:.5rem">
      <div>
        <label for="success_primary_label">Primární tlačítko po odeslání</label>
        <input type="text" id="success_primary_label" name="success_primary_label" maxlength="120"
               value="<?= h($successPrimaryLabelValue) ?>"
               aria-describedby="success-primary-label-help">
        <small id="success-primary-label-help" class="field-help">Volitelné. Když vyplníte jen adresu, použije se text <code>Pokračovat</code>.</small>
      </div>
      <div>
        <label for="success_primary_url">Adresa primárního tlačítka</label>
        <input type="text" id="success_primary_url" name="success_primary_url" maxlength="500"
               value="<?= h($successPrimaryUrlValue) ?>" placeholder="/"
               aria-describedby="success-primary-url-help">
        <small id="success-primary-url-help" class="field-help">Jen interní cesta v rámci webu, například <code>/</code> nebo <code>/forms/nahlaseni-chyby</code>.</small>
      </div>
      <div>
        <label for="success_secondary_label">Sekundární tlačítko po odeslání</label>
        <input type="text" id="success_secondary_label" name="success_secondary_label" maxlength="120"
               value="<?= h($successSecondaryLabelValue) ?>"
               aria-describedby="success-secondary-label-help">
        <small id="success-secondary-label-help" class="field-help">Volitelné. Když vyplníte jen adresu, použije se text <code>Další krok</code>.</small>
      </div>
      <div>
        <label for="success_secondary_url">Adresa sekundárního tlačítka</label>
        <input type="text" id="success_secondary_url" name="success_secondary_url" maxlength="500"
               value="<?= h($successSecondaryUrlValue) ?>" placeholder="/kontakt"
               aria-describedby="success-secondary-url-help">
        <small id="success-secondary-url-help" class="field-help">Volitelné. Hodí se pro návrat na domovskou stránku, další formulář nebo nápovědu.</small>
      </div>
    </div>
  </fieldset>

  <?php if ($form): ?>
  <fieldset>
    <legend>Pole a sekce formuláře</legend>
    <p class="field-help">U každého pole nastavte popisek, typ, šířku, řádek, pořadí a případné doplňující možnosti. Typ <strong>Sekce formuláře</strong> vytvoří nový blok s vlastním mezititulkem a nápovědou. Interní klíče se po uložení zachovají, takže starší odpovědi zůstanou čitelné i po úpravě formuláře.</p>

    <div id="fields-container">
      <?php foreach ($fields as $i => $field): ?>
        <div class="field-row" style="border:1px solid #d6d6d6;border-radius:8px;padding:.75rem;margin-bottom:.75rem;background:#fafafa">
          <input type="hidden" name="fields[<?= $i ?>][id]" value="<?= (int)$field['id'] ?>">
          <div style="display:grid;grid-template-columns:minmax(12rem,1.4fr) minmax(10rem,.9fr) minmax(12rem,1fr) minmax(12rem,1fr) minmax(11rem,.9fr) 6rem;gap:.5rem;align-items:end">
            <div>
              <label for="field-label-<?= $i ?>">Popisek <span aria-hidden="true">*</span></label>
              <input type="text" id="field-label-<?= $i ?>" name="fields[<?= $i ?>][label]" required
                     value="<?= h((string)$field['label']) ?>">
            </div>
            <div>
              <label for="field-type-<?= $i ?>">Typ</label>
              <select id="field-type-<?= $i ?>" name="fields[<?= $i ?>][field_type]">
                <?php foreach ($fieldTypes as $type => $definition): ?>
                  <option value="<?= h($type) ?>"<?= normalizeFormFieldType((string)$field['field_type']) === $type ? ' selected' : '' ?>><?= h((string)$definition['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="field-options-<?= $i ?>">Možnosti výběru</label>
              <input type="text" id="field-options-<?= $i ?>" name="fields[<?= $i ?>][options]"
                     value="<?= h((string)($field['options'] ?? '')) ?>"
                     aria-describedby="field-options-help-<?= $i ?>">
              <small id="field-options-help-<?= $i ?>" class="field-help">Použijte pro typ Výběr, Jedna volba nebo Více voleb. Jednotlivé možnosti oddělte znakem |.</small>
            </div>
            <div>
              <label for="field-placeholder-<?= $i ?>">Zástupný text</label>
              <input type="text" id="field-placeholder-<?= $i ?>" name="fields[<?= $i ?>][placeholder]"
                     value="<?= h((string)($field['placeholder'] ?? '')) ?>"
                     aria-describedby="field-placeholder-help-<?= $i ?>">
              <small id="field-placeholder-help-<?= $i ?>" class="field-help">Volitelné. Zobrazí se v poli jako krátká nápověda.</small>
            </div>
            <div>
              <label for="field-help-text-<?= $i ?>">Nápověda k poli</label>
              <input type="text" id="field-help-text-<?= $i ?>" name="fields[<?= $i ?>][help_text]"
                     value="<?= h((string)($field['help_text'] ?? '')) ?>"
                     aria-describedby="field-help-text-help-<?= $i ?>">
              <small id="field-help-text-help-<?= $i ?>" class="field-help">Volitelné. Zobrazí se pod polem jako vysvětlení nebo instrukce. U sekce funguje jako úvodní text celého bloku.</small>
            </div>
            <div>
              <label for="field-sort-<?= $i ?>">Pořadí</label>
              <input type="number" id="field-sort-<?= $i ?>" name="fields[<?= $i ?>][sort_order]" min="0"
                     value="<?= (int)$field['sort_order'] ?>" style="width:5rem">
            </div>
          </div>
          <div style="display:grid;grid-template-columns:minmax(12rem,1fr) minmax(12rem,1fr) minmax(10rem,.8fr) minmax(11rem,.9fr);gap:.5rem;align-items:end;margin-top:.5rem">
            <div>
              <label for="field-default-value-<?= $i ?>">Výchozí hodnota</label>
              <input type="text" id="field-default-value-<?= $i ?>" name="fields[<?= $i ?>][default_value]"
                     value="<?= h((string)($field['default_value'] ?? '')) ?>"
                     aria-describedby="field-default-value-help-<?= $i ?>">
              <small id="field-default-value-help-<?= $i ?>" class="field-help">Použijte hlavně pro skryté pole. U běžných polí jde o předvyplněný text.</small>
            </div>
            <div>
              <label for="field-accept-<?= $i ?>">Povolené typy souborů</label>
              <input type="text" id="field-accept-<?= $i ?>" name="fields[<?= $i ?>][accept_types]"
                     value="<?= h((string)($field['accept_types'] ?? '')) ?>"
                     aria-describedby="field-accept-help-<?= $i ?>">
              <small id="field-accept-help-<?= $i ?>" class="field-help">Jen pro pole Soubor. Například <code>.png,.jpg,.pdf</code> nebo <code>image/*</code>.</small>
            </div>
            <div>
              <label for="field-max-size-<?= $i ?>">Max. velikost souboru (MB)</label>
              <input type="number" id="field-max-size-<?= $i ?>" name="fields[<?= $i ?>][max_file_size_mb]" min="1" max="100"
                     value="<?= (int)($field['max_file_size_mb'] ?? 10) ?>" style="width:7rem"
                     aria-describedby="field-max-size-help-<?= $i ?>">
              <small id="field-max-size-help-<?= $i ?>" class="field-help">Jen pro pole Soubor. Výchozí hodnota je 10 MB.</small>
            </div>
            <div>
              <label for="field-layout-width-<?= $i ?>">Šířka pole</label>
              <select id="field-layout-width-<?= $i ?>" name="fields[<?= $i ?>][layout_width]" aria-describedby="field-layout-width-help-<?= $i ?>">
                <?php foreach ($fieldLayoutWidths as $layoutWidth => $definition): ?>
                  <option value="<?= h($layoutWidth) ?>"<?= normalizeFormFieldLayoutWidth((string)($field['layout_width'] ?? 'full')) === $layoutWidth ? ' selected' : '' ?>><?= h((string)$definition['label']) ?></option>
                <?php endforeach; ?>
              </select>
              <small id="field-layout-width-help-<?= $i ?>" class="field-help">Určuje šířku pole na veřejné stránce formuláře.</small>
            </div>
          </div>
          <div data-condition-row style="display:grid;grid-template-columns:minmax(12rem,1fr) minmax(12rem,.9fr) minmax(12rem,1fr);gap:.5rem;align-items:end;margin-top:.5rem">
            <div>
              <label for="field-show-if-field-<?= $i ?>">Zobrazit jen když</label>
              <select id="field-show-if-field-<?= $i ?>" name="fields[<?= $i ?>][show_if_field]" aria-describedby="field-show-if-field-help-<?= $i ?>">
                <option value="">Pole je vždy viditelné</option>
                <?php foreach ($conditionalFieldOptions as $candidateName => $candidateLabel): ?>
                  <option value="<?= h($candidateName) ?>"<?= (string)($field['show_if_field'] ?? '') === $candidateName ? ' selected' : '' ?>><?= h($candidateLabel) ?> (<?= h($candidateName) ?>)</option>
                <?php endforeach; ?>
              </select>
              <small id="field-show-if-field-help-<?= $i ?>" class="field-help">Vyberte pole, které má řídit zobrazení tohoto pole.</small>
            </div>
            <div>
              <label for="field-show-if-operator-<?= $i ?>">Podmínka</label>
              <select id="field-show-if-operator-<?= $i ?>" name="fields[<?= $i ?>][show_if_operator]" data-condition-operator aria-describedby="field-show-if-operator-help-<?= $i ?>">
                <?php foreach ($conditionOperators as $operator => $definition): ?>
                  <option value="<?= h($operator) ?>"<?= normalizeFormConditionOperator((string)($field['show_if_operator'] ?? ''), (string)($field['show_if_value'] ?? '')) === $operator ? ' selected' : '' ?> data-requires-value="<?= !empty($definition['requires_value']) ? '1' : '0' ?>"><?= h((string)$definition['label']) ?></option>
                <?php endforeach; ?>
              </select>
              <small id="field-show-if-operator-help-<?= $i ?>" class="field-help">Vyberte, jak se má vyhodnotit hodnota řídicího pole.</small>
            </div>
            <div>
              <label for="field-show-if-value-<?= $i ?>">Požadovaná hodnota</label>
              <input type="text" id="field-show-if-value-<?= $i ?>" name="fields[<?= $i ?>][show_if_value]" data-condition-value
                     value="<?= h((string)($field['show_if_value'] ?? '')) ?>"
                     aria-describedby="field-show-if-value-help-<?= $i ?>">
              <small id="field-show-if-value-help-<?= $i ?>" class="field-help">Pro více hodnot použijte oddělovač <code>|</code>. Pro checkbox nebo souhlas použijte <code>1</code>.</small>
            </div>
          </div>
          <div style="margin-top:.5rem;display:flex;gap:1rem;align-items:center">
            <label><input type="checkbox" name="fields[<?= $i ?>][is_required]" value="1"<?= (int)$field['is_required'] ? ' checked' : '' ?>> Povinné pole</label>
            <label><input type="checkbox" name="fields[<?= $i ?>][allow_multiple]" value="1"<?= (int)($field['allow_multiple'] ?? 0) === 1 ? ' checked' : '' ?>> Povolit více souborů</label>
            <label><input type="checkbox" name="fields[<?= $i ?>][start_new_row]" value="1"<?= (int)($field['start_new_row'] ?? 0) === 1 ? ' checked' : '' ?>> Začít na novém řádku</label>
            <label><input type="checkbox" name="fields[<?= $i ?>][delete]" value="1"> Odebrat pole po uložení</label>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <h3>Přidat nové pole</h3>
    <p class="field-help">Nové pole se po uložení přidá na konec formuláře. Typ <strong>Sekce formuláře</strong> použijte pro nový blok s mezititulkem a nápovědou. Pořadí, šířku i řádek pak můžete případně upravit přímo tady.</p>
    <div style="border:1px dashed #b8b0a4;border-radius:8px;padding:.75rem;background:#fff">
      <div style="display:grid;grid-template-columns:minmax(12rem,1.4fr) minmax(10rem,.9fr) minmax(12rem,1fr) minmax(12rem,1fr) minmax(11rem,.9fr) 6rem;gap:.5rem;align-items:end">
        <div>
          <label for="new-field-label">Popisek</label>
          <input type="text" id="new-field-label" name="new_field_label">
        </div>
        <div>
          <label for="new-field-type">Typ</label>
          <select id="new-field-type" name="new_field_type">
            <?php foreach ($fieldTypes as $type => $definition): ?>
              <option value="<?= h($type) ?>"><?= h((string)$definition['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="new-field-options">Možnosti výběru</label>
          <input type="text" id="new-field-options" name="new_field_options" aria-describedby="new-field-options-help">
          <small id="new-field-options-help" class="field-help">Použijte pro typ Výběr, Jedna volba nebo Více voleb. Jednotlivé možnosti oddělte znakem |.</small>
        </div>
        <div>
          <label for="new-field-placeholder">Zástupný text</label>
          <input type="text" id="new-field-placeholder" name="new_field_placeholder" aria-describedby="new-field-placeholder-help">
          <small id="new-field-placeholder-help" class="field-help">Volitelné. Krátká nápověda zobrazená přímo v poli.</small>
        </div>
        <div>
          <label for="new-field-help-text">Nápověda k poli</label>
          <input type="text" id="new-field-help-text" name="new_field_help_text" aria-describedby="new-field-help-text-help">
          <small id="new-field-help-text-help" class="field-help">Volitelné. Zobrazí se pod polem jako vysvětlení nebo instrukce. U sekce funguje jako úvodní text celého bloku.</small>
        </div>
        <div>
          <label for="new-field-sort">Pořadí</label>
          <input type="number" id="new-field-sort" name="new_field_sort" min="0" value="<?= $newFieldDefaultSort ?>" style="width:5rem">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:minmax(12rem,1fr) minmax(12rem,1fr) minmax(10rem,.8fr) minmax(11rem,.9fr);gap:.5rem;align-items:end;margin-top:.5rem">
        <div>
          <label for="new-field-default-value">Výchozí hodnota</label>
          <input type="text" id="new-field-default-value" name="new_field_default_value" aria-describedby="new-field-default-value-help">
          <small id="new-field-default-value-help" class="field-help">Použijte hlavně pro skryté pole. U běžných polí jde o předvyplněný text.</small>
        </div>
        <div>
          <label for="new-field-accept">Povolené typy souborů</label>
          <input type="text" id="new-field-accept" name="new_field_accept_types" aria-describedby="new-field-accept-help">
          <small id="new-field-accept-help" class="field-help">Jen pro pole Soubor. Například <code>.png,.jpg,.pdf</code> nebo <code>image/*</code>.</small>
        </div>
        <div>
          <label for="new-field-max-size">Max. velikost souboru (MB)</label>
          <input type="number" id="new-field-max-size" name="new_field_max_file_size_mb" min="1" max="100" value="10" style="width:7rem"
                 aria-describedby="new-field-max-size-help">
          <small id="new-field-max-size-help" class="field-help">Jen pro pole Soubor. Výchozí hodnota je 10 MB.</small>
        </div>
        <div>
          <label for="new-field-layout-width">Šířka pole</label>
          <select id="new-field-layout-width" name="new_field_layout_width" aria-describedby="new-field-layout-width-help">
            <?php foreach ($fieldLayoutWidths as $layoutWidth => $definition): ?>
              <option value="<?= h($layoutWidth) ?>"<?= $layoutWidth === 'full' ? ' selected' : '' ?>><?= h((string)$definition['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <small id="new-field-layout-width-help" class="field-help">Určuje šířku pole na veřejné stránce formuláře.</small>
        </div>
      </div>
      <div data-condition-row style="display:grid;grid-template-columns:minmax(12rem,1fr) minmax(12rem,.9fr) minmax(12rem,1fr);gap:.5rem;align-items:end;margin-top:.5rem">
        <div>
          <label for="new-field-show-if-field">Zobrazit jen když</label>
          <select id="new-field-show-if-field" name="new_field_show_if_field" aria-describedby="new-field-show-if-field-help">
            <option value="">Pole je vždy viditelné</option>
            <?php foreach ($conditionalFieldOptions as $candidateName => $candidateLabel): ?>
              <option value="<?= h($candidateName) ?>"><?= h($candidateLabel) ?> (<?= h($candidateName) ?>)</option>
            <?php endforeach; ?>
          </select>
          <small id="new-field-show-if-field-help" class="field-help">Volitelné. Vyberte pole, které má řídit zobrazení nového pole.</small>
        </div>
        <div>
          <label for="new-field-show-if-operator">Podmínka</label>
          <select id="new-field-show-if-operator" name="new_field_show_if_operator" data-condition-operator aria-describedby="new-field-show-if-operator-help">
            <?php foreach ($conditionOperators as $operator => $definition): ?>
              <option value="<?= h($operator) ?>"<?= $operator === 'filled' ? ' selected' : '' ?> data-requires-value="<?= !empty($definition['requires_value']) ? '1' : '0' ?>"><?= h((string)$definition['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <small id="new-field-show-if-operator-help" class="field-help">Vyberte, jak se má vyhodnotit hodnota řídicího pole.</small>
        </div>
        <div>
          <label for="new-field-show-if-value">Požadovaná hodnota</label>
          <input type="text" id="new-field-show-if-value" name="new_field_show_if_value" data-condition-value aria-describedby="new-field-show-if-value-help">
          <small id="new-field-show-if-value-help" class="field-help">Pro více hodnot použijte oddělovač <code>|</code>. Pro checkbox nebo souhlas použijte <code>1</code>.</small>
        </div>
      </div>
      <div style="margin-top:.5rem">
        <label><input type="checkbox" name="new_field_required" value="1"> Povinné pole</label>
        <label style="margin-left:1rem"><input type="checkbox" name="new_field_allow_multiple" value="1"> Povolit více souborů</label>
        <label style="margin-left:1rem"><input type="checkbox" name="new_field_start_new_row" value="1"> Začít na novém řádku</label>
      </div>
    </div>
  </fieldset>
  <?php else: ?>
    <p><em>Po vytvoření formuláře budete moci přidat jeho pole, jejich pořadí i potvrzovací zprávu.</em></p>
  <?php endif; ?>

  <div class="button-row" style="margin-top:1rem">
    <button type="submit" class="btn btn-primary"><?= $form ? 'Uložit změny' : 'Vytvořit formulář' ?></button>
    <a href="forms.php" class="btn">Zrušit</a>
  </div>
</form>

<script nonce="<?= h(cspNonce()) ?>">
(function () {
  const subjectInput = document.getElementById('submitter_confirmation_subject');
  const messageInput = document.getElementById('submitter_confirmation_message');
  const titleInput = document.getElementById('title');
  const successMessageInput = document.getElementById('success_message');
  const subjectPreview = document.getElementById('submitter-confirmation-subject-preview');
  const messagePreview = document.getElementById('submitter-confirmation-message-preview');
  const previewMap = <?= json_encode($submitterConfirmationPreview['placeholder_map'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const defaultSubjectTemplate = <?= json_encode(defaultFormSubmitterConfirmationSubjectTemplate(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const defaultMessageTemplate = <?= json_encode(defaultFormSubmitterConfirmationMessageTemplate(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  const renderTemplate = (template, map) => {
    let output = template;
    Object.entries(map).forEach(([token, value]) => {
      output = output.split(token).join(value);
    });
    return output;
  };

  const updateConfirmationPreview = () => {
    if (!subjectPreview || !messagePreview) {
      return;
    }

    const dynamicMap = {
      ...previewMap,
      '{{form_title}}': titleInput && titleInput.value.trim() !== '' ? titleInput.value.trim() : (previewMap['{{form_title}}'] || ''),
      '{{success_message}}': successMessageInput && successMessageInput.value.trim() !== '' ? successMessageInput.value.trim() : (previewMap['{{success_message}}'] || '')
    };

    const subjectTemplate = subjectInput && subjectInput.value.trim() !== '' ? subjectInput.value : defaultSubjectTemplate;
    const messageTemplate = messageInput && messageInput.value.trim() !== '' ? messageInput.value : defaultMessageTemplate;

    subjectPreview.textContent = renderTemplate(subjectTemplate, dynamicMap);
    messagePreview.textContent = renderTemplate(messageTemplate, dynamicMap);
  };

  [subjectInput, messageInput, titleInput, successMessageInput].forEach((input) => {
    if (!input) {
      return;
    }
    input.addEventListener('input', updateConfirmationPreview);
    input.addEventListener('change', updateConfirmationPreview);
  });
  updateConfirmationPreview();

  const successBehaviorSelect = document.getElementById('success_behavior');
  const redirectRow = document.querySelector('[data-after-submit-redirect]');
  const actionGrid = document.querySelector('[data-after-submit-actions]');
  const syncSuccessBehavior = () => {
    if (!successBehaviorSelect || !redirectRow || !actionGrid) {
      return;
    }

    const isRedirect = successBehaviorSelect.value === 'redirect';
    redirectRow.hidden = !isRedirect;
    redirectRow.setAttribute('aria-hidden', isRedirect ? 'false' : 'true');
    actionGrid.hidden = isRedirect;
    actionGrid.setAttribute('aria-hidden', isRedirect ? 'true' : 'false');

    redirectRow.querySelectorAll('input, select, textarea').forEach((control) => {
      if (control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement) {
        control.disabled = !isRedirect;
      }
    });

    actionGrid.querySelectorAll('input, select, textarea').forEach((control) => {
      if (control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement) {
        control.disabled = isRedirect;
      }
    });
  };

  if (successBehaviorSelect) {
    successBehaviorSelect.addEventListener('change', syncSuccessBehavior);
    syncSuccessBehavior();
  }

  document.querySelectorAll('[data-condition-row]').forEach((row) => {
    const controllerSelect = row.querySelector('select[name$="[show_if_field]"], select[name="new_field_show_if_field"]');
    const operatorSelect = row.querySelector('[data-condition-operator]');
    const valueInput = row.querySelector('[data-condition-value]');
    if (!controllerSelect || !operatorSelect || !valueInput) {
      return;
    }

    const syncConditionRow = () => {
      const hasController = controllerSelect.value.trim() !== '';
      const selectedOption = operatorSelect.options[operatorSelect.selectedIndex];
      const requiresValue = selectedOption ? selectedOption.dataset.requiresValue === '1' : true;

      operatorSelect.disabled = !hasController;
      valueInput.disabled = !hasController || !requiresValue;

      if (!hasController) {
        valueInput.value = '';
        valueInput.placeholder = 'Nejdřív vyberte řídicí pole';
      } else if (!requiresValue) {
        valueInput.value = '';
        valueInput.placeholder = 'Tato podmínka nevyžaduje konkrétní hodnotu';
      } else if (valueInput.getAttribute('placeholder') === '' || valueInput.getAttribute('placeholder') === 'Tato podmínka nevyžaduje konkrétní hodnotu' || valueInput.getAttribute('placeholder') === 'Nejdřív vyberte řídicí pole') {
        valueInput.placeholder = 'Např. Vysoká|Kritická';
      }
    };

    controllerSelect.addEventListener('change', syncConditionRow);
    operatorSelect.addEventListener('change', syncConditionRow);
    syncConditionRow();
  });
}());
</script>

<?php adminFooter(); ?>
