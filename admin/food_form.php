<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo  = db_connect();
$id   = inputInt('get', 'id');
$card = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_food_cards WHERE id = ?");
    $stmt->execute([$id]);
    $card = $stmt->fetch();
    if (!$card) { header('Location: food.php'); exit; }
}

// Předvolený typ z URL parametru (pro tlačítko "+ Nový jídelní/nápojový lístek")
$defaultType = ($_GET['type'] ?? '') === 'beverage' ? 'beverage' : 'food';
$cardType    = $card ? $card['type'] : $defaultType;

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';

adminHeader($card ? 'Upravit lístek' : 'Nový lístek');
?>

<form method="post" action="food_save.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($card): ?>
    <input type="hidden" name="id" value="<?= (int)$card['id'] ?>">
  <?php endif; ?>

  <label for="type">Typ lístku <span aria-hidden="true">*</span></label>
  <select id="type" name="type" style="width:auto">
    <option value="food"     <?= $cardType === 'food'     ? 'selected' : '' ?>>Jídelní lístek</option>
    <option value="beverage" <?= $cardType === 'beverage' ? 'selected' : '' ?>>Nápojový lístek</option>
  </select>

  <label for="title">Název <span aria-hidden="true">*</span></label>
  <input type="text" id="title" name="title" required maxlength="255"
         placeholder="např. Týdenní menu 17.–23. března 2026"
         value="<?= h($card['title'] ?? '') ?>">

  <label for="description">Krátká poznámka <small>(nepovinná – zobrazí se v archivu pod názvem)</small></label>
  <textarea id="description" name="description" rows="2"
            style="min-height:0"><?= h($card['description'] ?? '') ?></textarea>

  <label for="content">Obsah lístku</label>
  <textarea id="content" name="content" rows="18"><?= h($card['content'] ?? '') ?></textarea>

  <div style="display:flex;gap:2rem;flex-wrap:wrap;margin-top:1rem">
    <div>
      <label for="valid_from">Platí od</label>
      <input type="date" id="valid_from" name="valid_from" style="width:auto"
             value="<?= h($card['valid_from'] ?? '') ?>">
    </div>
    <div>
      <label for="valid_to">Platí do <small>(prázdné = bez omezení)</small></label>
      <input type="date" id="valid_to" name="valid_to" style="width:auto"
             value="<?= h($card['valid_to'] ?? '') ?>">
    </div>
  </div>

  <?php if (isSuperAdmin()): ?>
  <fieldset style="margin-top:1.5rem;border:1px solid #ccc;padding:.75rem 1rem">
    <legend>Publikování</legend>

    <label style="font-weight:normal;margin-top:.25rem">
      <input type="checkbox" name="is_current" value="1"
             <?= ($card['is_current'] ?? 0) ? 'checked' : '' ?>>
      <strong>Označit jako aktuální lístek</strong>
      <small style="color:#666;display:block;margin-left:1.4rem">
        Při uložení se automaticky odznačí předchozí aktuální lístek stejného typu.
      </small>
    </label>

    <label style="font-weight:normal;margin-top:.75rem">
      <input type="checkbox" name="is_published" value="1"
             <?= ($card['is_published'] ?? 1) ? 'checked' : '' ?>>
      Zobrazit v archivu
    </label>
  </fieldset>
  <?php endif; ?>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $card ? 'Uložit změny' : 'Přidat lístek' ?></button>
    <a href="food.php" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
(function () {
    const ta = document.getElementById('content');
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem';
    wrapper.style.minHeight = '350px';
    ta.parentNode.insertBefore(wrapper, ta);
    ta.style.display = 'none';

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

    quill.root.innerHTML = ta.value;

    ta.closest('form').addEventListener('submit', function () {
        ta.value = quill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
