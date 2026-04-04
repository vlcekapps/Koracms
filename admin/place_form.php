<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu míst nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$backUrl = internalRedirectTarget((string)($_GET['redirect'] ?? ''), BASE_URL . '/admin/places.php');
$place = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_places WHERE id = ?");
    $stmt->execute([$id]);
    $place = $stmt->fetch() ?: null;
    if (!$place) {
        header('Location: ' . $backUrl);
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
    'meta_title' => '',
    'meta_description' => '',
    'is_published' => 1,
    'status' => 'published',
];
$place = hydratePlacePresentation($place);

$categories = $pdo->query(
    "SELECT DISTINCT category FROM cms_places WHERE category <> '' ORDER BY category"
)->fetchAll(\PDO::FETCH_COLUMN);
$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$err = trim((string)($_GET['err'] ?? ''));
$formError = match ($err) {
    'required' => 'Vyplňte prosím povinné pole názvu místa.',
    'slug' => 'Slug místa je povinný a musí být unikátní.',
    'url' => 'Webový odkaz musí mít platný formát.',
    'email' => 'Kontaktní e-mail nemá platný formát.',
    'coordinates' => 'Zeměpisnou šířku a délku vyplňte obě a ve správném číselném rozsahu.',
    'image' => 'Obrázek se nepodařilo nahrát. Použijte JPEG, PNG, GIF nebo WebP.',
    default => '',
};
$fieldErrorMap = [
    'required' => ['name'],
    'slug' => ['slug'],
    'url' => ['url'],
    'email' => ['contact_email'],
    'coordinates' => ['latitude', 'longitude'],
    'image' => ['place_image'],
];
$fieldErrorMessages = [
    'name' => 'Název místa je povinný.',
    'slug' => 'Slug místa je povinný a musí být unikátní.',
    'url' => 'Webový odkaz musí mít platný formát.',
    'contact_email' => 'Kontaktní e-mail nemá platný formát.',
    'coordinates' => 'Zeměpisnou šířku a délku vyplňte obě a ve správném číselném rozsahu.',
    'image' => 'Obrázek se nepodařilo nahrát. Použijte JPEG, PNG, GIF nebo WebP.',
];

adminHeader($id ? 'Upravit zajímavé místo' : 'Nové zajímavé místo');
?>

<?php if ($id !== null): ?>
  <p><a href="revisions.php?type=place&amp;id=<?= (int)$id ?>">Historie revizí</a></p>
<?php endif; ?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<p style="margin-top:0;font-size:.9rem">
  Vyplňte základní údaje o místě a nakonec zvolte, jestli se má zobrazit na webu. Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<p><a href="<?= h($backUrl) ?>"><span aria-hidden="true">←</span> Zpět na zajímavá místa</a></p>

<form method="post" action="place_save.php" enctype="multipart/form-data" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="redirect" value="<?= h($backUrl) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje místa</legend>

    <label for="name">Název <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="name" name="name" required aria-required="true" maxlength="255"
           <?= adminFieldAttributes('name', $err, $fieldErrorMap) ?>
           value="<?= h((string)$place['name']) ?>">
    <?php adminRenderFieldError('name', $err, $fieldErrorMap, $fieldErrorMessages['name']); ?>

    <label for="slug">Slug veřejné stránky <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           <?= adminFieldAttributes('slug', $err, $fieldErrorMap, ['place-slug-help']) ?>
           value="<?= h((string)$place['slug']) ?>">
    <small id="place-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Použijte malá písmena, číslice a pomlčky.</small>
    <?php adminRenderFieldError('slug', $err, $fieldErrorMap, $fieldErrorMessages['slug']); ?>

    <label for="place_kind">Typ místa</label>
    <select id="place_kind" name="place_kind">
      <?php foreach (placeKindOptions() as $kindKey => $kindMeta): ?>
        <option value="<?= h((string)$kindKey) ?>"<?= normalizePlaceKind((string)$place['place_kind']) === (string)$kindKey ? ' selected' : '' ?>>
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
    <small id="place-category-help" class="field-help">Volitelné. Pomůže s filtrováním a orientací ve veřejném adresáři míst.</small>

    <label for="locality">Lokalita / obec</label>
    <input type="text" id="locality" name="locality" maxlength="255"
           value="<?= h((string)$place['locality']) ?>">

    <label for="address">Adresa</label>
    <input type="text" id="address" name="address" maxlength="255"
           value="<?= h((string)$place['address']) ?>">

    <label for="excerpt">Krátké shrnutí / perex</label>
    <textarea id="excerpt" name="excerpt" rows="3" aria-describedby="place-excerpt-help"><?= h((string)$place['excerpt']) ?></textarea>
    <small id="place-excerpt-help" class="field-help">Zobrazí se ve výpisu míst, ve vyhledávání a jako úvod detailu.</small>

    <label for="description">Detailní popis</label>
    <?php if ($useWysiwyg): ?>
      <div id="editor-description" style="min-height:220px"><?= (string)$place['description'] ?></div>
      <input type="hidden" id="description" name="description" value="<?= h((string)$place['description']) ?>">
    <?php else: ?>
      <textarea id="description" name="description" rows="8" aria-describedby="place-description-help"><?= h((string)$place['description']) ?></textarea>
      <small id="place-description-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
      <?php renderAdminContentReferencePicker('description'); ?>
    <?php endif; ?>
  </fieldset>

  <fieldset>
    <legend>Poloha a kontakt</legend>

    <label for="url">Web / externí odkaz</label>
    <input type="url" id="url" name="url" maxlength="500"
           <?= adminFieldAttributes('url', $err, $fieldErrorMap) ?>
           value="<?= h((string)$place['url']) ?>">
    <?php adminRenderFieldError('url', $err, $fieldErrorMap, $fieldErrorMessages['url']); ?>

    <div style="display:flex;gap:1rem;flex-wrap:wrap">
      <div style="flex:1 1 12rem">
        <label for="latitude">Zeměpisná šířka</label>
        <input type="text" id="latitude" name="latitude" inputmode="decimal"
               <?= adminFieldAttributes('latitude', $err, $fieldErrorMap, ['place-coordinates-help'], 'place-coordinates-error') ?>
               value="<?= h((string)$place['latitude']) ?>">
      </div>
      <div style="flex:1 1 12rem">
        <label for="longitude">Zeměpisná délka</label>
        <input type="text" id="longitude" name="longitude" inputmode="decimal"
               <?= adminFieldAttributes('longitude', $err, $fieldErrorMap, ['place-coordinates-help'], 'place-coordinates-error') ?>
               value="<?= h((string)$place['longitude']) ?>">
      </div>
    </div>
    <small id="place-coordinates-help" class="field-help">Pokud vyplníte obě souřadnice, na veřejné stránce se zobrazí odkaz do map.</small>
    <?php if (adminFieldHasError('latitude', $err, $fieldErrorMap)): ?>
      <small id="place-coordinates-error" class="field-help field-error"><?= h($fieldErrorMessages['coordinates']) ?></small>
    <?php endif; ?>

    <label for="opening_hours">Otevírací doba / praktické poznámky</label>
    <textarea id="opening_hours" name="opening_hours" rows="4"><?= h((string)$place['opening_hours']) ?></textarea>

    <label for="contact_phone">Telefon</label>
    <input type="text" id="contact_phone" name="contact_phone" maxlength="100"
           value="<?= h((string)$place['contact_phone']) ?>">

    <label for="contact_email">E-mail</label>
    <input type="email" id="contact_email" name="contact_email" maxlength="255"
           <?= adminFieldAttributes('contact_email', $err, $fieldErrorMap) ?>
           value="<?= h((string)$place['contact_email']) ?>">
    <?php adminRenderFieldError('contact_email', $err, $fieldErrorMap, $fieldErrorMessages['contact_email']); ?>
  </fieldset>

  <fieldset>
    <legend>SEO a sdílení</legend>

    <label for="meta_title">Meta titulek</label>
    <input type="text" id="meta_title" name="meta_title" maxlength="160"
           value="<?= h((string)$place['meta_title']) ?>">

    <label for="meta_description">Meta popis</label>
    <textarea id="meta_description" name="meta_description" rows="3" aria-describedby="place-meta-help"><?= h((string)$place['meta_description']) ?></textarea>
    <small id="place-meta-help" class="field-help">Volitelné. Pokud pole necháte prázdná, veřejná stránka použije název a perex místa.</small>
  </fieldset>

  <fieldset>
    <legend>Obrázek a zveřejnění</legend>

    <label for="place_image">Hlavní obrázek</label>
    <?php if (!empty($place['image_file'])): ?>
      <div style="margin:.5rem 0">
        <img src="<?= h((string)$place['image_url']) ?>" alt="Náhled obrázku"
             style="display:block;max-width:320px;width:100%;border-radius:12px;border:1px solid #d0d7de">
      </div>
    <?php endif; ?>
    <input type="file" id="place_image" name="place_image" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
           <?= adminFieldAttributes('place_image', $err, $fieldErrorMap, array_filter(['place-image-help', !empty($place['image_file']) ? 'place-image-current' : ''])) ?>
           >
    <small id="place-image-help" class="field-help">Hodí se pro fotku místa, ilustrační snímek nebo plakát k lokalitě.</small>
    <?php adminRenderFieldError('place_image', $err, $fieldErrorMap, $fieldErrorMessages['image']); ?>
    <?php if (!empty($place['image_file'])): ?>
      <small id="place-image-current" class="field-help">Aktuální obrázek je nahraný. Nahrajte nový, pokud ho chcete nahradit.</small>
    <?php endif; ?>
    <?php if (!empty($place['image_file'])): ?>
      <label for="place_image_delete" style="font-weight:normal;margin-top:.35rem">
        <input type="checkbox" id="place_image_delete" name="place_image_delete" value="1">
        Smazat aktuální obrázek
      </label>
    <?php endif; ?>

    <label style="font-weight:normal;margin-top:1rem">
      <input type="checkbox" name="is_published" value="1" aria-describedby="place-published-help"
             <?= (int)$place['is_published'] === 1 ? 'checked' : '' ?>>
      Zveřejnit na webu
    </label>
    <small id="place-published-help" class="field-help" style="margin-top:.2rem">Když volbu vypnete, místo zůstane uložené jen v administraci a skryje se i jeho obrázek.</small>
  </fieldset>

  <fieldset style="margin-top:1rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Stav publikace</legend>
    <label for="article_status">Stav</label>
    <select id="article_status" name="article_status">
      <option value="draft"<?= ($place['status'] ?? '') === 'draft' ? ' selected' : '' ?>>Koncept</option>
      <?php if (currentUserHasCapability('content_approve_shared')): ?>
        <option value="published"<?= ($place['status'] ?? 'published') === 'published' ? ' selected' : '' ?>>Publikováno</option>
      <?php endif; ?>
      <option value="pending"<?= ($place['status'] ?? '') === 'pending' ? ' selected' : '' ?>>Čeká na schválení</option>
    </select>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id ? 'Uložit změny' : 'Přidat zajímavé místo' ?></button>
    <a href="<?= h($backUrl) ?>" style="margin-left:1rem">Zrušit</a>
    <?php if (($place['status'] ?? 'published') === 'published' && (int)($place['is_published'] ?? 1) === 1 && !empty($place['slug'])): ?>
      <a href="<?= h(placePublicPath($place)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
    <?php endif; ?>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
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
<script nonce="<?= cspNonce() ?>">
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
