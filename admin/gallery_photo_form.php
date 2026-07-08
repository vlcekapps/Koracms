<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$albumId = inputInt('get', 'album_id');
$errorKey = trim($_GET['err'] ?? '');
$errorMap = [
    'slug' => 'Slug fotografie už používá jiná fotografie. U pole Slug adresy je konkrétní nápověda.',
    'license_url' => 'Adresa licence fotografie není použitelná. U pole Adresa licence je konkrétní nápověda.',
    'taken_at' => 'Datum pořízení fotografie není použitelné. U pole Datum pořízení je konkrétní nápověda.',
];
$formError = $errorMap[$errorKey] ?? '';
$fieldErrorMap = [
    'slug' => ['slug'],
    'license_url' => ['license_url'],
    'taken_at' => ['taken_at'],
];
$fieldErrorMessages = [
    'slug' => 'Zadejte jiný jedinečný slug z malých písmen, číslic a pomlček, nebo pole nechte prázdné pro automatické vytvoření.',
    'license_url' => 'Zadejte úplnou adresu licence začínající http:// nebo https://, nebo pole nechte prázdné.',
    'taken_at' => 'Vyberte skutečné kalendářní datum v poli Datum pořízení, nebo pole nechte prázdné.',
];

$photo = null;
if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_gallery_photos WHERE id = ?");
    $stmt->execute([$id]);
    $photo = $stmt->fetch();
    if ($photo) {
        $photo = hydrateGalleryPhotoPresentation($photo);
        $albumId = (int)$photo['album_id'];
    } elseif ($albumId !== null) {
        header('Location: ' . BASE_URL . '/admin/gallery_photos.php?album_id=' . $albumId);
        exit;
    } else {
        header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
        exit;
    }
}

if ($albumId === null) {
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}

$albumStmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ?");
$albumStmt->execute([$albumId]);
$album = $albumStmt->fetch();
if (!$album) {
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}
$album = hydrateGalleryAlbumPresentation($album);

$pageTitle = $id ? 'Upravit fotografii' : 'Nahrát fotografie do alba';
adminHeader($pageTitle);
?>

<p>
  <a href="<?= BASE_URL ?>/admin/gallery_photos.php?album_id=<?= (int)$album['id'] ?>"><span aria-hidden="true">←</span> Zpět na fotografie v albu</a>
</p>

<?php if ($formError !== ''): ?>
  <div id="form-errors" class="error" role="alert" aria-atomic="true">
    <p><?= h($formError) ?></p>
  </div>
<?php endif; ?>

<?php if ($id !== null): ?>
  <form method="post" action="<?= BASE_URL ?>/admin/gallery_photo_save.php" novalidate<?= $formError !== '' ? ' aria-describedby="form-errors"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$photo['id'] ?>">
    <input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
    <input type="hidden" name="mode" value="edit">

    <div class="admin-stack-sm">
      <img src="<?= h((string)$photo['thumb_url']) ?>"
           alt="<?= h((string)$photo['label']) ?>"
           class="admin-image-preview admin-image-preview--medium">
    </div>

    <fieldset>
      <legend>Údaje o fotografii</legend>

      <label for="title">Titulek fotografie</label>
      <input type="text" id="title" name="title" maxlength="255"
             value="<?= h((string)$photo['title']) ?>">

      <label for="slug">Slug adresy</label>
      <input type="text" id="slug" name="slug" maxlength="255"
             <?= adminFieldAttributes('slug', $errorKey, $fieldErrorMap, ['gallery-photo-slug-help']) ?>
             value="<?= h((string)$photo['slug']) ?>" inputmode="url" autocapitalize="off" spellcheck="false">
      <small id="gallery-photo-slug-help" class="field-help">Adresa se vyplní automaticky podle titulku fotografie. Pokud ji upravíte ručně, použijte malá písmena, číslice a pomlčky.</small>
      <?php adminRenderFieldError('slug', $errorKey, $fieldErrorMap, $fieldErrorMessages['slug']); ?>

      <label for="sort_order">Pořadí</label>
      <input type="number" id="sort_order" name="sort_order" min="0" value="<?= (int)$photo['sort_order'] ?>">
      <small class="field-help">Pořadí můžete rychle upravit i přímo v přehledu fotografií pomocí tlačítek Nahoru a Dolů.</small>

      <fieldset class="admin-fieldset-spaced">
        <legend>Popis a práva</legend>

        <label for="alt_text">Alt text obrázku</label>
        <input type="text" id="alt_text" name="alt_text" maxlength="255"
               value="<?= h((string)($photo['alt_text'] ?? '')) ?>" aria-describedby="gallery-photo-alt-help">
        <small id="gallery-photo-alt-help" class="field-help">Popište význam fotografie pro čtečky obrazovky. Pokud zůstane prázdný, CMS použije popisek nebo titulek.</small>

        <label for="caption">Viditelný popisek</label>
        <textarea id="caption" name="caption" rows="3"><?= h((string)($photo['caption'] ?? '')) ?></textarea>

        <label for="description">Delší popis fotografie</label>
        <textarea id="description" name="description" rows="4"><?= h((string)($photo['description'] ?? '')) ?></textarea>

        <label for="credit">Kredit autora</label>
        <input type="text" id="credit" name="credit" maxlength="255" value="<?= h((string)($photo['credit'] ?? '')) ?>">

        <label for="license_label">Licence</label>
        <input type="text" id="license_label" name="license_label" maxlength="100" value="<?= h((string)($photo['license_label'] ?? '')) ?>">

        <label for="license_url">Adresa licence</label>
        <input type="url" id="license_url" name="license_url" maxlength="255"
               value="<?= h((string)($photo['license_url'] ?? '')) ?>"
               placeholder="https://creativecommons.org/licenses/by/4.0/"<?= adminFieldAttributes('license_url', $errorKey, $fieldErrorMap, ['gallery-photo-license-url-help']) ?>>
        <small id="gallery-photo-license-url-help" class="field-help">Volitelné. Použijte úplnou adresu začínající <code>http://</code> nebo <code>https://</code>.</small>
        <?php adminRenderFieldError('license_url', $errorKey, $fieldErrorMap, $fieldErrorMessages['license_url']); ?>

        <label for="taken_at">Datum pořízení</label>
        <input type="date" id="taken_at" name="taken_at"
               value="<?= h((string)($photo['taken_at'] ?? '')) ?>"<?= adminFieldAttributes('taken_at', $errorKey, $fieldErrorMap, ['gallery-photo-taken-at-help']) ?>>
        <small id="gallery-photo-taken-at-help" class="field-help">Volitelné. Vyberte skutečné datum pořízení fotografie.</small>
        <?php adminRenderFieldError('taken_at', $errorKey, $fieldErrorMap, $fieldErrorMessages['taken_at']); ?>

        <label for="location_label">Místo pořízení</label>
        <input type="text" id="location_label" name="location_label" maxlength="255" value="<?= h((string)($photo['location_label'] ?? '')) ?>">
      </fieldset>

      <div class="admin-field-row">
        <label class="admin-checkbox-label">
          <input type="checkbox" name="is_published" value="1"<?= (int)($photo['is_published'] ?? 1) === 1 ? ' checked' : '' ?>>
          Publikováno (viditelné na webu)
        </label>
      </div>

      <div class="button-row admin-fieldset-spaced">
        <button type="submit">Uložit změny</button>
        <a href="<?= BASE_URL ?>/admin/gallery_photos.php?album_id=<?= (int)$album['id'] ?>">Zrušit</a>
        <a href="<?= BASE_URL ?>/admin/revisions.php?type=gallery_photo&amp;id=<?= (int)$photo['id'] ?>">Historie revizí</a>
        <a href="<?= h((string)$photo['public_path']) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
      </div>
    </fieldset>
  </form>

  <script nonce="<?= cspNonce() ?>">
  (() => {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    if (!titleInput || !slugInput) return;

    const transliteration = {
      'á':'a','č':'c','ď':'d','é':'e','ě':'e','í':'i','ň':'n','ó':'o','ř':'r','š':'s','ť':'t','ú':'u','ů':'u','ý':'y','ž':'z',
      'Á':'a','Č':'c','Ď':'d','É':'e','Ě':'e','Í':'i','Ň':'n','Ó':'o','Ř':'r','Š':'s','Ť':'t','Ú':'u','Ů':'u','Ý':'y','Ž':'z'
    };
    const slugify = (value) => value
      .split('')
      .map((char) => transliteration[char] ?? char)
      .join('')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');

    let slugTouched = slugInput.value.trim() !== '';
    slugInput.addEventListener('input', () => {
      slugTouched = slugInput.value.trim() !== '';
    });
    titleInput.addEventListener('input', () => {
      if (!slugTouched) {
        slugInput.value = slugify(titleInput.value);
      }
    });
  })();
  </script>
<?php else: ?>
  <form method="post" action="<?= BASE_URL ?>/admin/gallery_photo_save.php"
        enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
    <input type="hidden" name="mode" value="upload">

    <fieldset>
      <legend>Nahrání fotografií do alba</legend>

      <label for="photos">Vyberte fotografie</label>
      <input type="file" id="photos" name="photos[]"
             accept="image/jpeg,image/png,image/gif,image/webp"
             multiple required aria-required="true" aria-describedby="gallery-photos-help">
      <small id="gallery-photos-help" class="field-help">Můžete vybrat více fotografií najednou. Povolené jsou JPEG, PNG, GIF a WebP do <?= h(koraUploadMaxSizeLabel()) ?> na soubor.</small>

      <p class="admin-description admin-description--muted admin-action-row">Slug se při hromadném nahrání vytvoří automaticky z názvu souboru.</p>
      <?php if ((string)($album['default_credit'] ?? '') !== '' || (string)($album['default_license_label'] ?? '') !== ''): ?>
        <p class="admin-description admin-description--muted admin-action-row">Nově nahrané fotografie převezmou výchozí kredit nebo licenci nastavenou u alba.</p>
      <?php endif; ?>

      <div class="button-row admin-fieldset-spaced">
        <button type="submit">Nahrát fotografie</button>
        <a href="<?= BASE_URL ?>/admin/gallery_photos.php?album_id=<?= (int)$album['id'] ?>">Zrušit</a>
      </div>
    </fieldset>
  </form>
<?php endif; ?>

<?php adminFooter(); ?>
