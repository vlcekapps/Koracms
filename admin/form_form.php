<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

function adminFormBuilderFieldTypes(): array
{
    return [
        'text' => 'Krátký text',
        'email' => 'E-mail',
        'tel' => 'Telefon',
        'textarea' => 'Delší text',
        'select' => 'Výběr',
        'checkbox' => 'Zaškrtávací pole',
        'number' => 'Číslo',
        'date' => 'Datum',
    ];
}

$pdo = db_connect();
$id = inputInt('get', 'id');

$form = null;
$fields = [];
$fieldTypes = adminFormBuilderFieldTypes();
if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_forms WHERE id = ?");
    $stmt->execute([$id]);
    $form = $stmt->fetch() ?: null;

    if ($form) {
        $fStmt = $pdo->prepare("SELECT * FROM cms_form_fields WHERE form_id = ? ORDER BY sort_order, id");
        $fStmt->execute([$id]);
        $fields = $fStmt->fetchAll();
    }
}

$err = trim($_GET['err'] ?? '');
$pageTitle = $form
    ? 'Upravit formulář – ' . mb_strimwidth((string)$form['title'], 0, 60, '…', 'UTF-8')
    : 'Nový formulář';
$newFieldDefaultSort = $fields !== [] ? (max(array_map(static fn(array $field): int => (int)$field['sort_order'], $fields)) + 1) : 0;

adminHeader($pageTitle);
?>

<?php if ($err === 'required'): ?>
  <p role="alert" class="error" id="form-error">Název formuláře je povinný.</p>
<?php elseif ($err === 'slug'): ?>
  <p role="alert" class="error" id="form-error">Slug formuláře je už obsazený.</p>
<?php endif; ?>

<div class="button-row">
  <a href="forms.php" class="btn">Zpět na formuláře</a>
  <?php if ($form): ?>
    <a href="form_submissions.php?id=<?= (int)$form['id'] ?>" class="btn">Odpovědi formuláře</a>
    <?php if ((int)($form['is_active'] ?? 0) === 1): ?>
      <a href="<?= h(formPublicPath($form)) ?>" class="btn" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($form): ?>
  <p class="section-subtitle">Upravte nastavení formuláře, jeho pole a text, který se zobrazí po úspěšném odeslání.</p>
<?php else: ?>
  <p class="section-subtitle">Nejdřív vytvořte základ formuláře. Hned po uložení budete moci přidat jeho pole a nastavit jejich pořadí.</p>
<?php endif; ?>

<form method="post" action="form_save.php" novalidate<?= $err !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($form): ?>
    <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje formuláře</legend>

    <div style="margin-bottom:.75rem">
      <label for="title">Název formuláře <span aria-hidden="true">*</span></label>
      <input type="text" id="title" name="title" required aria-required="true"
             maxlength="255" value="<?= h((string)($form['title'] ?? '')) ?>" style="width:100%;max-width:500px">
    </div>

    <div style="margin-bottom:.75rem">
      <label for="slug">Slug (URL)</label>
      <input type="text" id="slug" name="slug" maxlength="255"
             value="<?= h((string)($form['slug'] ?? '')) ?>" style="width:100%;max-width:500px"
             aria-describedby="slug-help">
      <small id="slug-help" class="field-help">Necháte-li prázdné, adresa se vytvoří automaticky podle názvu formuláře.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="description">Popis formuláře</label>
      <textarea id="description" name="description" rows="3" style="width:100%;max-width:500px"><?= h((string)($form['description'] ?? '')) ?></textarea>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="success_message">Zpráva po odeslání</label>
      <textarea id="success_message" name="success_message" rows="2" style="width:100%;max-width:500px"
                aria-describedby="success-help"><?= h((string)($form['success_message'] ?? 'Formulář byl úspěšně odeslán. Děkujeme!')) ?></textarea>
      <small id="success-help" class="field-help">Zobrazí se návštěvníkovi po úspěšném odeslání formuláře.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="is_active">
        <input type="checkbox" id="is_active" name="is_active" value="1"<?= !$form || (int)($form['is_active'] ?? 1) === 1 ? ' checked' : '' ?> aria-describedby="is-active-help">
        Zveřejnit formulář na webu
      </label>
      <small id="is-active-help" class="field-help">Neaktivní formulář zůstane uložený, ale návštěvníci ho na webu neuvidí.</small>
    </div>
  </fieldset>

  <?php if ($form): ?>
  <fieldset>
    <legend>Pole formuláře</legend>
    <p class="field-help">U každého pole nastavte popisek, typ, pořadí a případné doplňující možnosti. Interní klíče se po uložení zachovávají, takže starší odpovědi zůstanou čitelné.</p>

    <div id="fields-container">
      <?php foreach ($fields as $i => $field): ?>
        <div class="field-row" style="border:1px solid #d6d6d6;border-radius:8px;padding:.75rem;margin-bottom:.75rem;background:#fafafa">
          <input type="hidden" name="fields[<?= $i ?>][id]" value="<?= (int)$field['id'] ?>">
          <div style="display:grid;grid-template-columns:minmax(12rem,1.4fr) minmax(9rem,.8fr) minmax(12rem,1fr) minmax(12rem,1fr) 5rem;gap:.5rem;align-items:end">
            <div>
              <label for="field-label-<?= $i ?>">Popisek <span aria-hidden="true">*</span></label>
              <input type="text" id="field-label-<?= $i ?>" name="fields[<?= $i ?>][label]" required
                     value="<?= h((string)$field['label']) ?>">
            </div>
            <div>
              <label for="field-type-<?= $i ?>">Typ</label>
              <select id="field-type-<?= $i ?>" name="fields[<?= $i ?>][field_type]">
                <?php foreach ($fieldTypes as $type => $typeLabel): ?>
                  <option value="<?= h($type) ?>"<?= $field['field_type'] === $type ? ' selected' : '' ?>><?= h($typeLabel) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="field-options-<?= $i ?>">Možnosti výběru</label>
              <input type="text" id="field-options-<?= $i ?>" name="fields[<?= $i ?>][options]"
                     value="<?= h((string)($field['options'] ?? '')) ?>"
                     aria-describedby="field-options-help-<?= $i ?>">
              <small id="field-options-help-<?= $i ?>" class="field-help">Použijte jen pro typ Výběr. Jednotlivé možnosti oddělte znakem |.</small>
            </div>
            <div>
              <label for="field-placeholder-<?= $i ?>">Zástupný text</label>
              <input type="text" id="field-placeholder-<?= $i ?>" name="fields[<?= $i ?>][placeholder]"
                     value="<?= h((string)($field['placeholder'] ?? '')) ?>"
                     aria-describedby="field-placeholder-help-<?= $i ?>">
              <small id="field-placeholder-help-<?= $i ?>" class="field-help">Volitelné. Zobrazí se v poli jako krátká nápověda.</small>
            </div>
            <div>
              <label for="field-sort-<?= $i ?>">Pořadí</label>
              <input type="number" id="field-sort-<?= $i ?>" name="fields[<?= $i ?>][sort_order]" min="0"
                     value="<?= (int)$field['sort_order'] ?>" style="width:5rem">
            </div>
          </div>
          <div style="margin-top:.5rem;display:flex;gap:1rem;align-items:center">
            <label><input type="checkbox" name="fields[<?= $i ?>][is_required]" value="1"<?= (int)$field['is_required'] ? ' checked' : '' ?>> Povinné pole</label>
            <label><input type="checkbox" name="fields[<?= $i ?>][delete]" value="1"> Odebrat pole po uložení</label>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <h3>Přidat nové pole</h3>
    <p class="field-help">Nové pole se po uložení přidá na konec formuláře. Pořadí pak můžete případně upravit přímo tady.</p>
    <div style="border:1px dashed #b8b0a4;border-radius:8px;padding:.75rem;background:#fff">
      <div style="display:grid;grid-template-columns:minmax(12rem,1.4fr) minmax(9rem,.8fr) minmax(12rem,1fr) minmax(12rem,1fr) 5rem;gap:.5rem;align-items:end">
        <div>
          <label for="new-field-label">Popisek</label>
          <input type="text" id="new-field-label" name="new_field_label">
        </div>
        <div>
          <label for="new-field-type">Typ</label>
          <select id="new-field-type" name="new_field_type">
            <?php foreach ($fieldTypes as $type => $typeLabel): ?>
              <option value="<?= h($type) ?>"><?= h($typeLabel) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="new-field-options">Možnosti výběru</label>
          <input type="text" id="new-field-options" name="new_field_options" aria-describedby="new-field-options-help">
          <small id="new-field-options-help" class="field-help">Použijte jen pro typ Výběr. Jednotlivé možnosti oddělte znakem |.</small>
        </div>
        <div>
          <label for="new-field-placeholder">Zástupný text</label>
          <input type="text" id="new-field-placeholder" name="new_field_placeholder" aria-describedby="new-field-placeholder-help">
          <small id="new-field-placeholder-help" class="field-help">Volitelné. Krátká nápověda zobrazená přímo v poli.</small>
        </div>
        <div>
          <label for="new-field-sort">Pořadí</label>
          <input type="number" id="new-field-sort" name="new_field_sort" min="0" value="<?= $newFieldDefaultSort ?>" style="width:5rem">
        </div>
      </div>
      <div style="margin-top:.5rem">
        <label><input type="checkbox" name="new_field_required" value="1"> Povinné pole</label>
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

<?php adminFooter(); ?>
