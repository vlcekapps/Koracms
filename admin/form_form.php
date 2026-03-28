<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

$pdo = db_connect();
$id = inputInt('get', 'id');

$form = null;
$fields = [];
if ($id !== null) {
    $form = $pdo->prepare("SELECT * FROM cms_forms WHERE id = ?")->execute([$id]) ? $pdo->prepare("SELECT * FROM cms_forms WHERE id = ?") : null;
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

adminHeader($form ? 'Upravit formulář' : 'Nový formulář');
?>

<?php if ($err === 'required'): ?>
  <p role="alert" class="error" id="form-error">Název formuláře je povinný.</p>
<?php elseif ($err === 'slug'): ?>
  <p role="alert" class="error" id="form-error">Slug formuláře je už obsazený.</p>
<?php endif; ?>

<form method="post" action="form_save.php" novalidate<?= $err !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($form): ?>
    <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje</legend>

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
      <small id="slug-help">Necháte-li prázdné, vytvoří se automaticky z názvu.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="description">Popis formuláře</label>
      <textarea id="description" name="description" rows="3" style="width:100%;max-width:500px"><?= h((string)($form['description'] ?? '')) ?></textarea>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="success_message">Zpráva po odeslání</label>
      <textarea id="success_message" name="success_message" rows="2" style="width:100%;max-width:500px"
                aria-describedby="success-help"><?= h((string)($form['success_message'] ?? 'Formulář byl úspěšně odeslán. Děkujeme!')) ?></textarea>
      <small id="success-help">Zobrazí se uživateli po úspěšném odeslání.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label>
        <input type="checkbox" name="is_active" value="1"<?= !$form || (int)($form['is_active'] ?? 1) === 1 ? ' checked' : '' ?>>
        Formulář je aktivní
      </label>
    </div>
  </fieldset>

  <?php if ($form): ?>
  <fieldset>
    <legend>Pole formuláře</legend>
    <p>Definujte pole formuláře. Podporované typy: text, email, tel, textarea, select, checkbox, number, date.</p>

    <div id="fields-container">
      <?php foreach ($fields as $i => $field): ?>
        <div class="field-row" style="border:1px solid #d6d6d6;border-radius:8px;padding:.75rem;margin-bottom:.75rem;background:#fafafa">
          <input type="hidden" name="fields[<?= $i ?>][id]" value="<?= (int)$field['id'] ?>">
          <div style="display:grid;grid-template-columns:1fr 8rem 1fr 5rem;gap:.5rem;align-items:end">
            <div>
              <label for="field-label-<?= $i ?>">Popisek <span aria-hidden="true">*</span></label>
              <input type="text" id="field-label-<?= $i ?>" name="fields[<?= $i ?>][label]" required
                     value="<?= h((string)$field['label']) ?>">
            </div>
            <div>
              <label for="field-type-<?= $i ?>">Typ</label>
              <select id="field-type-<?= $i ?>" name="fields[<?= $i ?>][field_type]">
                <?php foreach (['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'number', 'date'] as $type): ?>
                  <option value="<?= $type ?>"<?= $field['field_type'] === $type ? ' selected' : '' ?>><?= $type ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="field-options-<?= $i ?>">Možnosti <small>(oddělte |)</small></label>
              <input type="text" id="field-options-<?= $i ?>" name="fields[<?= $i ?>][options]"
                     value="<?= h((string)($field['options'] ?? '')) ?>"
                     aria-describedby="field-options-help-<?= $i ?>">
              <small id="field-options-help-<?= $i ?>" class="sr-only">Pro typ select oddělte možnosti znakem |</small>
            </div>
            <div>
              <label for="field-sort-<?= $i ?>">Pořadí</label>
              <input type="number" id="field-sort-<?= $i ?>" name="fields[<?= $i ?>][sort_order]" min="0"
                     value="<?= (int)$field['sort_order'] ?>" style="width:5rem">
            </div>
          </div>
          <div style="margin-top:.5rem;display:flex;gap:1rem;align-items:center">
            <label><input type="checkbox" name="fields[<?= $i ?>][is_required]" value="1"<?= (int)$field['is_required'] ? ' checked' : '' ?>> Povinné pole</label>
            <label><input type="checkbox" name="fields[<?= $i ?>][delete]" value="1"> Smazat pole</label>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <h3>Přidat nové pole</h3>
    <div style="border:1px dashed #b8b0a4;border-radius:8px;padding:.75rem;background:#fff">
      <div style="display:grid;grid-template-columns:1fr 8rem 1fr 5rem;gap:.5rem;align-items:end">
        <div>
          <label for="new-field-label">Popisek</label>
          <input type="text" id="new-field-label" name="new_field_label">
        </div>
        <div>
          <label for="new-field-type">Typ</label>
          <select id="new-field-type" name="new_field_type">
            <?php foreach (['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'number', 'date'] as $type): ?>
              <option value="<?= $type ?>"><?= $type ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="new-field-options">Možnosti <small>(oddělte |)</small></label>
          <input type="text" id="new-field-options" name="new_field_options">
        </div>
        <div>
          <label for="new-field-sort">Pořadí</label>
          <input type="number" id="new-field-sort" name="new_field_sort" min="0" value="0" style="width:5rem">
        </div>
      </div>
      <div style="margin-top:.5rem">
        <label><input type="checkbox" name="new_field_required" value="1"> Povinné pole</label>
      </div>
    </div>
  </fieldset>
  <?php else: ?>
    <p><em>Po vytvoření formuláře budete moci definovat pole.</em></p>
  <?php endif; ?>

  <div style="margin-top:1rem">
    <button type="submit" class="btn btn-primary"><?= $form ? 'Uložit formulář' : 'Vytvořit formulář' ?></button>
    <a href="forms.php" class="btn">Zpět na seznam</a>
  </div>
</form>

<?php adminFooter(); ?>
