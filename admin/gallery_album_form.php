<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$errorKey = trim($_GET['err'] ?? '');
$errorMap = [
    'required' => 'Vyplňte prosím název alba.',
    'slug' => 'Zadaný slug už používá jiné album. Zvolte prosím jiný.',
    'parent' => 'Zvolené nadřazené album není platné.',
];
$formError = $errorMap[$errorKey] ?? '';

$album = [
    'id' => null,
    'name' => '',
    'slug' => '',
    'description' => '',
    'parent_id' => null,
    'cover_photo_id' => null,
];
if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $album = $row;
    } else {
        header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
        exit;
    }
}

$allAlbums = $pdo->query("SELECT id, name, slug, parent_id FROM cms_gallery_albums ORDER BY name")->fetchAll();
$forbidden = [];
if ($id !== null) {
    $forbidden = [$id];
    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($allAlbums as $candidateAlbum) {
            if (!in_array((int)$candidateAlbum['id'], $forbidden, true) && in_array((int)$candidateAlbum['parent_id'], $forbidden, true)) {
                $forbidden[] = (int)$candidateAlbum['id'];
                $changed = true;
            }
        }
    }
}

$photos = [];
if ($id !== null) {
    $stmt = $pdo->prepare(
        "SELECT id, filename, title, slug FROM cms_gallery_photos WHERE album_id = ? ORDER BY sort_order, id"
    );
    $stmt->execute([$id]);
    $photos = array_map(
        static fn(array $photo): array => hydrateGalleryPhotoPresentation($photo),
        $stmt->fetchAll()
    );
}

$pageTitle = $id ? 'Upravit album galerie' : 'Nové album galerie';
adminHeader($pageTitle);
?>

<p><a href="<?= BASE_URL ?>/admin/gallery_albums.php"><span aria-hidden="true">←</span> Zpět na alba galerie</a></p>

<?php if ($formError !== ''): ?>
  <div id="form-errors" class="error" role="alert">
    <p><?= h($formError) ?></p>
  </div>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>/admin/gallery_album_save.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="id" value="<?= (int)$album['id'] ?>">

  <fieldset>
    <legend>Údaje o albu</legend>

    <label for="name">Název alba <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" required aria-required="true"
           maxlength="255" value="<?= h((string)$album['name']) ?>"<?= $formError !== '' ? ' aria-describedby="form-errors"' : '' ?>>

    <label for="slug">Slug adresy</label>
    <input type="text" id="slug" name="slug" maxlength="255" aria-describedby="gallery-album-slug-help<?= $formError !== '' ? ' form-errors' : '' ?>"
           value="<?= h((string)$album['slug']) ?>" inputmode="url" autocapitalize="off" spellcheck="false">
    <small id="gallery-album-slug-help" class="field-help">Adresa se vyplní automaticky podle názvu alba. Pokud ji upravíte ručně, použijte malá písmena, číslice a pomlčky.</small>

    <label for="description">Popis</label>
    <textarea id="description" name="description" rows="4"><?= h((string)($album['description'] ?? '')) ?></textarea>

    <label for="parent_id">Nadřazené album</label>
    <select id="parent_id" name="parent_id">
      <option value="">— Nejvyšší úroveň —</option>
      <?php foreach ($allAlbums as $candidateAlbum): ?>
        <?php if (in_array((int)$candidateAlbum['id'], $forbidden, true)) continue; ?>
        <option value="<?= (int)$candidateAlbum['id'] ?>"<?= (string)$album['parent_id'] === (string)$candidateAlbum['id'] ? ' selected' : '' ?>>
          <?= h((string)$candidateAlbum['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <?php if (!empty($photos)): ?>
      <label for="cover_photo_id">Náhledová fotka alba</label>
      <select id="cover_photo_id" name="cover_photo_id">
        <option value="">— Automaticky (první fotka) —</option>
        <?php foreach ($photos as $photo): ?>
          <option value="<?= (int)$photo['id'] ?>"<?= (string)$album['cover_photo_id'] === (string)$photo['id'] ? ' selected' : '' ?>>
            <?= h((string)$photo['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>

    <div style="margin-top:.75rem">
      <label>
        <input type="checkbox" name="is_published" value="1"<?= (int)($album['is_published'] ?? 1) === 1 ? ' checked' : '' ?>>
        Publikováno (viditelné na webu)
      </label>
    </div>

    <div style="margin-top:1.5rem">
      <button type="submit"><?= $id ? 'Uložit změny' : 'Vytvořit album' ?></button>
      <a href="<?= BASE_URL ?>/admin/gallery_albums.php" style="margin-left:1rem">Zrušit</a>
      <?php if ($id !== null): ?>
        <a href="<?= BASE_URL ?>/admin/revisions.php?type=gallery_album&amp;id=<?= (int)$album['id'] ?>" style="margin-left:1rem">Historie revizí</a>
        <?php $albumPublicPath = galleryAlbumPublicPath($album); ?>
        <a href="<?= h($albumPublicPath) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
      <?php endif; ?>
    </div>
  </fieldset>
</form>

<script nonce="<?= cspNonce() ?>">
(() => {
  const nameInput = document.getElementById('name');
  const slugInput = document.getElementById('slug');
  if (!nameInput || !slugInput) return;

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
  nameInput.addEventListener('input', () => {
    if (!slugTouched) {
      slugInput.value = slugify(nameInput.value);
    }
  });
})();
</script>

<?php adminFooter(); ?>
