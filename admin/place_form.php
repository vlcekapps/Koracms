<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu míst nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$place = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_places WHERE id = ?");
    $stmt->execute([$id]);
    $place = $stmt->fetch() ?: null;
    if (!$place) {
        header('Location: places.php');
        exit;
    }
}

$place = $place ?: [
    'name' => '',
    'slug' => '',
    'place_kind' => 'sight',
    'category' => '',
    'excerpt' => '',
    'description' => '',
    'url' => '',
    'image_file' => '',
    'address' => '',
    'locality' => '',
    'latitude' => '',
    'longitude' => '',
    'contact_phone' => '',
    'contact_email' => '',
    'opening_hours' => '',
    'sort_order' => 0,
    'is_published' => 1,
    'status' => 'published',
];

$categories = $pdo->query(
    "SELECT DISTINCT category FROM cms_places WHERE category <> '' ORDER BY category"
)->fetchAll(\PDO::FETCH_COLUMN);
$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$err = trim($_GET['err'] ?? '');
$formError = match ($err) {
    'required' => 'Vyplňte prosím povinné pole názvu místa.',
    'slug' => 'Slug místa je povinný a musí být unikátní.',
    'url' => 'Webový odkaz musí mít platný formát.',
    'email' => 'Kontaktní e-mail nemá platný formát.',
    'coordinates' => 'Zeměpisnou šířku a délku vyplňte obě a ve správném číselném rozsahu.',
    'image' => 'Obrázek se nepodařilo nahrát. Použijte JPEG, PNG, GIF, WebP nebo SVG.',
    default => '',
};

adminHeader($id ? 'Upravit zajímavé místo' : 'Nové zajímavé místo');
?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<p style="margin-top:0;font-size:.9rem">
  Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<p><a href="places.php"><span aria-hidden="true">←</span> Zpět na zajímavá místa</a></p>

<form method="post" action="place_save.php" enctype="multipart/form-data" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Místo</legend>

    <label for="name">Název <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="name" name="name" required aria-required="true" maxlength="255"
           value="<?= h((string)$place['name']) ?>">

    <label for="slug">Slug veřejné stránky <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           aria-describedby="place-slug-help"
           value="<?= h((string)$place['slug']) ?>">
    <small id="place-slug-help" class="field-help">Používejte malá písmena, číslice a pomlčky.</small>

    <label for="place_kind">Typ místa</label>
    <select id="place_kind" name="place_kind">
      <?php foreach (placeKindDefinitions() as $kindKey => $kindMeta): ?>
        <option value="<?= h($kindKey) ?>"<?= normalizePlaceKind((string)$place['place_kind']) === $kindKey ? ' selected' : '' ?>>
          <?= h((string)$kindMeta['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="category">Kategorie</label>
    <input type="text" id="category" name="category" maxlength="100" list="places-categories"
           aria-describedby="place-category-help"
           value="<?= h((string)$place['category']) ?>">
    <datalist id="places-categories">
      <?php foreach ($categories as $category): ?>
        <option value="<?= h((string)$category) ?>">
      <?php endforeach; ?>
    </datalist>
    <small id="place-category-help" class="field-help">Nepovinné pole.</small>

    <label for="locality">Lokalita / obec</label>
    <input type="text" id="locality" name="locality" maxlength="255"
           value="<?= h((string)($place['locality'] ?? '')) ?>">

    <label for="address">Adresa</label>
    <input type="text" id="address" name="address" maxlength="255"
           value="<?= h((string)($place['address'] ?? '')) ?>">

    <label for="excerpt">Krátké shrnutí / perex</label>
    <textarea id="excerpt" name="excerpt" rows="3" aria-describedby="place-excerpt-help"><?= h((string)($place['excerpt'] ?? '')) ?></textarea>
    <small id="place-excerpt-help" class="field-help">Zobrazí se ve výpisu míst, ve vyhledávání a jako úvod detailu.</small>

    <label for="description">Detailní popis</label>
    <?php if ($useWysiwyg): ?>
      <div id="editor-description" style="min-height:220px"><?= (string)($place['description'] ?? '') ?></div>
      <input type="hidden" id="description" name="description" value="<?= h((string)($place['description'] ?? '')) ?>">
    <?php else: ?>
      <textarea id="description" name="description" rows="8" aria-describedby="place-description-help"><?= h((string)($place['description'] ?? '')) ?></textarea>
      <small id="place-description-help" class="field-help">Podporuje HTML i Markdown syntaxi.</small>
    <?php endif; ?>
  </fieldset>

  <fieldset>
    <legend>Praktické informace</legend>

    <label for="url">Web / externí odkaz</label>
    <input type="url" id="url" name="url" maxlength="500"
           value="<?= h((string)($place['url'] ?? '')) ?>">

    <div style="display:flex;gap:1rem;flex-wrap:wrap">
      <div style="flex:1 1 12rem">
        <label for="latitude">Zeměpisná šířka</label>
        <input type="text" id="latitude" name="latitude" inputmode="decimal" aria-describedby="place-coordinates-help"
               value="<?= h((string)($place['latitude'] ?? '')) ?>">
      </div>
      <div style="flex:1 1 12rem">
        <label for="longitude">Zeměpisná délka</label>
        <input type="text" id="longitude" name="longitude" inputmode="decimal" aria-describedby="place-coordinates-help"
               value="<?= h((string)($place['longitude'] ?? '')) ?>">
      </div>
    </div>
    <small id="place-coordinates-help" class="field-help">Pokud vyplníte obě souřadnice, na veřejné stránce se zobrazí odkaz do map.</small>

    <label for="opening_hours">Otevírací doba / praktické poznámky</label>
    <textarea id="opening_hours" name="opening_hours" rows="4"><?= h((string)($place['opening_hours'] ?? '')) ?></textarea>

    <label for="contact_phone">Telefon</label>
    <input type="text" id="contact_phone" name="contact_phone" maxlength="100"
           value="<?= h((string)($place['contact_phone'] ?? '')) ?>">

    <label for="contact_email">E-mail</label>
    <input type="email" id="contact_email" name="contact_email" maxlength="255"
           value="<?= h((string)($place['contact_email'] ?? '')) ?>">
  </fieldset>

  <fieldset>
    <legend>Obrázek a zobrazení</legend>

    <label for="place_image">Hlavní obrázek</label>
    <?php if (!empty($place['image_file'])): ?>
      <div style="margin:.5rem 0">
        <img src="<?= h(placeImageUrl($place)) ?>" alt=""
             style="display:block;max-width:320px;width:100%;border-radius:12px;border:1px solid #d0d7de">
      </div>
    <?php endif; ?>
    <input type="file" id="place_image" name="place_image" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*"
           aria-describedby="place-image-help<?= !empty($place['image_file']) ? ' place-image-current' : '' ?>">
    <small id="place-image-help" class="field-help">Hodí se pro fotku místa, ilustrační snímek nebo plakát akce v lokalitě.</small>
    <?php if (!empty($place['image_file'])): ?>
      <small id="place-image-current" class="field-help">Aktuální obrázek je už nahraný.</small>
    <?php endif; ?>
    <?php if (!empty($place['image_file'])): ?>
      <label for="place_image_delete" style="font-weight:normal;margin-top:.35rem">
        <input type="checkbox" id="place_image_delete" name="place_image_delete" value="1">
        Smazat aktuální obrázek
      </label>
    <?php endif; ?>

    <label for="sort_order">Pořadí</label>
    <input type="number" id="sort_order" name="sort_order" min="0" style="width:8rem"
           value="<?= (int)($place['sort_order'] ?? 0) ?>">

    <label style="font-weight:normal;margin-top:1rem">
      <input type="checkbox" name="is_published" value="1"
             <?= (int)($place['is_published'] ?? 1) === 1 ? 'checked' : '' ?>>
      Zobrazit na webu
    </label>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id ? 'Uložit změny' : 'Přidat zajímavé místo' ?></button>
    <a href="places.php" style="margin-left:1rem">Zrušit</a>
    <?php if (($place['status'] ?? 'published') === 'published' && (int)($place['is_published'] ?? 1) === 1 && !empty($place['slug'])): ?>
      <a href="<?= h(placePublicPath($place)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
    <?php endif; ?>
  </div>
</form>

<script>
(function () {
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    let slugManual = <?= !empty($place['slug']) ? 'true' : 'false' ?>;

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    slugInput?.addEventListener('input', function () {
        slugManual = this.value.trim() !== '';
    });

    nameInput?.addEventListener('input', function () {
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
<script>
(function () {
    const textarea = document.getElementById('description');
    const wrapper = document.getElementById('editor-description');
    const quill = new Quill(wrapper, { theme: 'snow' });
    quill.root.innerHTML = textarea.value;
    textarea.closest('form')?.addEventListener('submit', function () {
        textarea.value = quill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
