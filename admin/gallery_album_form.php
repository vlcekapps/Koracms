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

$pageTitle = $id ? 'Upravit album' : 'Nové album';
adminHeader($pageTitle);
?>

<p><a href="<?= BASE_URL ?>/admin/gallery_albums.php"><span aria-hidden="true">←</span> Zpět na seznam alb</a></p>

<?php if ($id !== null): ?>
  <?php $albumPublicPath = galleryAlbumPublicPath($album); ?>
  <p><a href="<?= h($albumPublicPath) ?>" target="_blank" rel="noopener noreferrer">Zobrazit veřejnou stránku alba</a></p>
<?php endif; ?>

<?php if ($formError !== ''): ?>
  <div id="form-errors" class="error" role="alert">
    <p><?= h($formError) ?></p>
  </div>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>/admin/gallery_album_save.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="id" value="<?= (int)$album['id'] ?>">

  <fieldset>
    <legend>Album</legend>

    <label for="name">Název alba <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" required aria-required="true"
           maxlength="255" value="<?= h((string)$album['name']) ?>"<?= $formError !== '' ? ' aria-describedby="form-errors"' : '' ?>>

    <label for="slug">Slug adresy</label>
    <input type="text" id="slug" name="slug" maxlength="255" aria-describedby="gallery-album-slug-help<?= $formError !== '' ? ' form-errors' : '' ?>"
           value="<?= h((string)$album['slug']) ?>" inputmode="url" autocapitalize="off" spellcheck="false">
    <small id="gallery-album-slug-help" class="field-help">Veřejná adresa bude vypadat například jako <code>/gallery/album/moje-fotografie</code>.</small>

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

    <button type="submit" style="margin-top:1rem"><?= $id ? 'Uložit změny' : 'Vytvořit album' ?></button>
  </fieldset>
</form>

<script>
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
