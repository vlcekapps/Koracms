<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');

$albumId = inputInt('get', 'album_id');
if ($albumId === null) {
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}

$pdo = db_connect();
$albumStmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ?");
$albumStmt->execute([$albumId]);
$album = $albumStmt->fetch();
if (!$album) {
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}
$album = hydrateGalleryAlbumPresentation($album);

$q = trim($_GET['q'] ?? '');
$whereSql = 'WHERE album_id = ?';
$params = [$albumId];
if ($q !== '') {
    $whereSql .= ' AND (title LIKE ? OR slug LIKE ? OR filename LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$photosStmt = $pdo->prepare(
    "SELECT * FROM cms_gallery_photos {$whereSql} ORDER BY sort_order, id"
);
$photosStmt->execute($params);
$photos = array_map(
    static fn(array $photo): array => hydrateGalleryPhotoPresentation($photo),
    $photosStmt->fetchAll()
);

adminHeader('Galerie – ' . $album['name']);
?>

<p><a href="<?= BASE_URL ?>/admin/gallery_albums.php"><span aria-hidden="true">←</span> Zpět na seznam alb</a></p>

<p><a href="<?= BASE_URL ?>/admin/gallery_photo_form.php?album_id=<?= (int)$album['id'] ?>" class="btn">+ Přidat fotografie</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
  <div>
    <label for="q" class="visually-hidden">Hledat ve fotografiích</label>
    <input type="search" id="q" name="q" placeholder="Hledat ve fotografiích..." value="<?= h($q) ?>" style="width:320px">
  </div>
  <button type="submit" class="btn">Filtrovat</button>
  <?php if ($q !== ''): ?>
    <a href="<?= BASE_URL ?>/admin/gallery_photos.php?album_id=<?= (int)$album['id'] ?>" class="btn">Zrušit</a>
  <?php endif; ?>
</form>

<?php if (empty($photos)): ?>
  <p><?= $q !== '' ? 'Pro zadaný filtr nebyly nalezeny žádné fotografie.' : 'V tomto albu nejsou žádné fotografie.' ?></p>
<?php else: ?>
  <table>
    <caption>Fotografie v albu „<?= h($album['name']) ?>“</caption>
    <thead>
      <tr>
        <th scope="col">Náhled</th>
        <th scope="col">Fotografie</th>
        <th scope="col">Pořadí</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($photos as $photo): ?>
        <tr>
          <td>
            <img src="<?= h((string)$photo['thumb_url']) ?>"
                 alt="<?= h((string)$photo['label']) ?>"
                 style="width:80px;height:60px;object-fit:cover;">
          </td>
          <td>
            <strong><?= h((string)$photo['label']) ?></strong><br>
            <small style="color:#555"><?= h(parse_url((string)$photo['public_path'], PHP_URL_PATH) ?: (string)$photo['public_path']) ?></small>
          </td>
          <td><?= (int)$photo['sort_order'] ?></td>
          <td class="actions">
            <a href="<?= BASE_URL ?>/admin/gallery_photo_form.php?id=<?= (int)$photo['id'] ?>&amp;album_id=<?= (int)$album['id'] ?>" class="btn">Upravit</a>
            <a href="<?= h((string)$photo['public_path']) ?>" target="_blank" rel="noopener noreferrer">Veřejná stránka</a>
            <form method="post" action="<?= BASE_URL ?>/admin/gallery_photo_delete.php"
                  onsubmit="return confirm('Smazat tuto fotografii?')">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$photo['id'] ?>">
              <input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
              <button type="submit" class="btn btn-danger">Smazat</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<style>.visually-hidden{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}</style>

<?php adminFooter(); ?>
