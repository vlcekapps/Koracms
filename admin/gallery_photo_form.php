<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$albumId = inputInt('get', 'album_id');
$errorKey = trim($_GET['err'] ?? '');
$errorMap = [
    'slug' => 'Zadaný slug už používá jiná fotografie. Zvolte prosím jiný.',
];
$formError = $errorMap[$errorKey] ?? '';

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
  <div id="form-errors" class="error" role="alert">
    <p><?= h($formError) ?></p>
  </div>
<?php endif; ?>

<?php if ($id !== null && $photo !== null): ?>
  <form method="post" action="<?= BASE_URL ?>/admin/gallery_photo_save.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$photo['id'] ?>">
    <input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
    <input type="hidden" name="mode" value="edit">

    <div style="margin-bottom:1rem">
      <img src="<?= h((string)$photo['thumb_url']) ?>"
           alt="<?= h((string)$photo['label']) ?>"
           style="max-width:300px;height:auto;display:block;">
    </div>

    <fieldset>
      <legend>Údaje o fotografii</legend>

      <label for="title">Titulek fotografie</label>
      <input type="text" id="title" name="title" maxlength="255"
             value="<?= h((string)$photo['title']) ?>"<?= $formError !== '' ? ' aria-describedby="form-errors"' : '' ?>>

      <label for="slug">Slug adresy</label>
      <input type="text" id="slug" name="slug" maxlength="255"
             value="<?= h((string)$photo['slug']) ?>" inputmode="url" autocapitalize="off" spellcheck="false" aria-describedby="gallery-photo-slug-help<?= $formError !== '' ? ' form-errors' : '' ?>">
      <small id="gallery-photo-slug-help" class="field-help">Adresa se vyplní automaticky podle titulku fotografie. Pokud ji upravíte ručně, použijte malá písmena, číslice a pomlčky.</small>

      <label for="sort_order">Pořadí</label>
      <input type="number" id="sort_order" name="sort_order" min="0" value="<?= (int)$photo['sort_order'] ?>">

      <div style="margin-top:1.5rem">
        <button type="submit">Uložit změny</button>
        <a href="<?= BASE_URL ?>/admin/gallery_photos.php?album_id=<?= (int)$album['id'] ?>" style="margin-left:1rem">Zrušit</a>
        <a href="<?= h((string)$photo['public_path']) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
      </div>
    </fieldset>
  </form>

  <script>
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
      <small id="gallery-photos-help" class="field-help">Můžete vybrat více fotografií najednou. Povolené jsou JPEG, PNG, GIF a WebP do 10 MB na soubor.</small>

      <p style="margin:.75rem 0 0;color:#555">Slug se při hromadném nahrání vytvoří automaticky z názvu souboru.</p>

      <div style="margin-top:1.5rem">
        <button type="submit">Nahrát fotografie</button>
        <a href="<?= BASE_URL ?>/admin/gallery_photos.php?album_id=<?= (int)$album['id'] ?>" style="margin-left:1rem">Zrušit</a>
      </div>
    </fieldset>
  </form>
<?php endif; ?>

<?php adminFooter(); ?>
