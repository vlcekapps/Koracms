<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo     = db_connect();
$id      = inputInt('get', 'id');
$albumId = inputInt('get', 'album_id');

$photo = null;
if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_gallery_photos WHERE id = ?");
    $stmt->execute([$id]);
    $photo = $stmt->fetch();
    if ($photo) $albumId = (int)$photo['album_id'];
}

if ($albumId === null) {
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ?");
$stmt->execute([$albumId]);
$album = $stmt->fetch();
if (!$album) {
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}

$pageTitle = $id ? 'Upravit fotografii' : 'Přidat fotografie';
adminHeader($pageTitle);
?>

<p>
  <a href="<?= BASE_URL ?>/admin/gallery_photos.php?album_id=<?= $albumId ?>"><span aria-hidden="true">←</span> Zpět na fotografie alba</a>
</p>

<?php if ($id && $photo): ?>
  <!-- Úprava existující fotografie (titulek + pořadí) -->
  <form method="post" action="<?= BASE_URL ?>/admin/gallery_photo_save.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id"         value="<?= (int)$photo['id'] ?>">
    <input type="hidden" name="album_id"   value="<?= $albumId ?>">
    <input type="hidden" name="mode"       value="edit">

    <div style="margin-bottom:1rem">
      <img src="<?= BASE_URL ?>/uploads/gallery/thumbs/<?= rawurlencode($photo['filename']) ?>"
           alt="<?= h($photo['title'] ?: $photo['filename']) ?>"
           style="max-width:300px;height:auto;display:block;">
    </div>

    <fieldset>
      <legend>Vlastnosti fotografie</legend>

      <label for="title">Titulek fotografie</label>
      <input type="text" id="title" name="title"
             maxlength="255" value="<?= h($photo['title']) ?>">

      <label for="sort_order">Pořadí</label>
      <input type="number" id="sort_order" name="sort_order"
             min="0" value="<?= (int)$photo['sort_order'] ?>">

      <button type="submit" style="margin-top:1rem">Uložit změny</button>
    </fieldset>
  </form>

<?php else: ?>
  <!-- Upload nových fotografií (lze vybrat více souborů najednou) -->
  <form method="post" action="<?= BASE_URL ?>/admin/gallery_photo_save.php"
        enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="album_id"   value="<?= $albumId ?>">
    <input type="hidden" name="mode"       value="upload">

    <fieldset>
      <legend>Nahrání fotografií</legend>

      <label for="photos">
        Vyberte fotografie
        <small>(JPEG, PNG, GIF, WebP; max. 10 MB / soubor; lze vybrat více najednou)</small>
      </label>
      <input type="file" id="photos" name="photos[]"
             accept="image/jpeg,image/png,image/gif,image/webp"
             multiple required aria-required="true">

      <button type="submit" style="margin-top:1rem">Nahrát</button>
    </fieldset>
  </form>
<?php endif; ?>

<?php adminFooter(); ?>
