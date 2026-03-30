<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu jídelních lístků nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$card = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_food_cards WHERE id = ?");
    $stmt->execute([$id]);
    $card = $stmt->fetch() ?: null;
    if (!$card) {
        header('Location: ' . BASE_URL . '/admin/food.php');
        exit;
    }
}

$defaultType = ($_GET['type'] ?? '') === 'beverage' ? 'beverage' : 'food';
$card = $card ?: [
    'type' => $defaultType,
    'title' => '',
    'slug' => '',
    'description' => '',
    'content' => '',
    'valid_from' => '',
    'valid_to' => '',
    'is_current' => 0,
    'is_published' => 1,
    'status' => 'published',
];

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$err = trim($_GET['err'] ?? '');
$formError = match ($err) {
    'required' => 'Vyplňte prosím název lístku.',
    'slug' => 'Slug lístku je povinný a musí být unikátní.',
    default => '',
};

$card = hydrateFoodCardPresentation($card);
$foodTypeLabel = $card['type'] === 'beverage' ? 'nápojový lístek' : 'jídelní lístek';

adminHeader($id ? 'Upravit ' . $foodTypeLabel : 'Nový ' . $foodTypeLabel);
?>

<?php if ($id !== null): ?>
  <p><a href="revisions.php?type=food&amp;id=<?= (int)$id ?>">Historie revizí</a></p>
<?php endif; ?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<p style="margin-top:0;font-size:.9rem">
  Vyplňte potřebné údaje k tomuto lístku a pak zvolte, jestli má být aktuální a zveřejněný. Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<p><a href="food.php"><span aria-hidden="true">←</span> Zpět na jídelní a nápojové lístky</a></p>

<form method="post" action="food_save.php" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Údaje o lístku</legend>

    <label for="type">Typ lístku <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <select id="type" name="type" style="width:auto">
      <option value="food"<?= $card['type'] === 'food' ? ' selected' : '' ?>>Jídelní lístek</option>
      <option value="beverage"<?= $card['type'] === 'beverage' ? ' selected' : '' ?>>Nápojový lístek</option>
    </select>

    <label for="title">Název <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           placeholder="např. Týdenní menu 17.–23. března 2026"
           value="<?= h((string)$card['title']) ?>">

    <label for="slug">Slug veřejné stránky <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           aria-describedby="food-slug-help"
           value="<?= h((string)$card['slug']) ?>">
    <small id="food-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Použijte malá písmena, číslice a pomlčky.</small>

    <label for="description">Krátká poznámka</label>
    <textarea id="description" name="description" rows="2" aria-describedby="food-description-help"
              style="min-height:0"><?= h((string)($card['description'] ?? '')) ?></textarea>
    <small id="food-description-help" class="field-help">Volitelné. Zobrazí se v archivu i na detailu lístku.</small>

    <label for="content">Obsah lístku</label>
    <textarea id="content" name="content" rows="18"<?= !$useWysiwyg ? ' aria-describedby="food-content-help"' : '' ?>><?= h((string)($card['content'] ?? '')) ?></textarea>
    <?php if (!$useWysiwyg): ?><small id="food-content-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small><?php endif; ?>
    <?php if (!$useWysiwyg): ?>
      <?php renderAdminContentReferencePicker('content'); ?>
    <?php endif; ?>

    <div style="display:flex;gap:2rem;flex-wrap:wrap;margin-top:1rem">
      <div>
        <label for="valid_from">Platí od</label>
        <input type="date" id="valid_from" name="valid_from" style="width:auto"
               value="<?= h((string)($card['valid_from'] ?? '')) ?>">
      </div>
      <div>
        <label for="valid_to">Platí do</label>
        <input type="date" id="valid_to" name="valid_to" style="width:auto" aria-describedby="food-valid-to-help"
               value="<?= h((string)($card['valid_to'] ?? '')) ?>">
        <small id="food-valid-to-help" class="field-help">Nechte prázdné, pokud má lístek platit bez data konce. Platnost se používá i pro rozlišení aktuálních, připravovaných a archivních lístků na webu.</small>
      </div>
    </div>
  </fieldset>

  <?php if (currentUserHasCapability('content_approve_shared')): ?>
  <fieldset style="margin-top:1.5rem;border:1px solid #ccc;padding:.75rem 1rem">
    <legend>Aktualita a zveřejnění</legend>

    <label style="font-weight:normal;margin-top:.25rem">
      <input type="checkbox" name="is_current" value="1" aria-describedby="food-current-help"
             <?= (int)($card['is_current'] ?? 0) === 1 ? 'checked' : '' ?>>
      <strong>Použít jako aktuální lístek</strong>
    </label>
    <small id="food-current-help" class="field-help" style="margin-left:1.4rem">Při uložení se automaticky odznačí předchozí aktuální lístek stejného typu.</small>

    <label style="font-weight:normal;margin-top:.75rem">
      <input type="checkbox" name="is_published" value="1" aria-describedby="food-published-help"
             <?= (int)($card['is_published'] ?? 1) === 1 ? 'checked' : '' ?>>
      Zveřejnit na webu
    </label>
    <small id="food-published-help" class="field-help" style="margin-left:1.4rem">Když volbu vypnete, lístek se neobjeví ani v aktuálním přehledu, ani v archivu.</small>
  </fieldset>
  <?php endif; ?>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id ? 'Uložit změny' : 'Přidat ' . $foodTypeLabel ?></button>
    <a href="food.php" style="margin-left:1rem">Zrušit</a>
    <?php if ($card['is_publicly_visible'] && (string)$card['slug'] !== ''): ?>
      <a href="<?= h((string)$card['public_path']) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
    <?php endif; ?>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    let slugManual = <?= !empty($card['slug']) ? 'true' : 'false' ?>;

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    slugInput?.addEventListener('input', function () {
        slugManual = this.value.trim() !== '';
    });

    titleInput?.addEventListener('input', function () {
        if (slugManual || !slugInput) {
            return;
        }
        slugInput.value = slugify(this.value);
    });
})();
</script>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script nonce="<?= cspNonce() ?>">
(function () {
    const textarea = document.getElementById('content');
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem';
    wrapper.style.minHeight = '350px';
    textarea.parentNode.insertBefore(wrapper, textarea);
    textarea.style.display = 'none';

    const quill = new Quill(wrapper, {
        theme: 'snow',
        modules: { toolbar: [
            [{ header: [2, 3, 4, false] }],
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote'],
            ['clean']
        ]}
    });

    quill.root.innerHTML = textarea.value;

    textarea.closest('form')?.addEventListener('submit', function () {
        textarea.value = quill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
