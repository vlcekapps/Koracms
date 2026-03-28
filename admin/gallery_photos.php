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
$statusFilter = in_array($_GET['status'] ?? '', ['all', 'pending', 'published', 'hidden'], true)
    ? (string)$_GET['status']
    : 'all';

$whereSql = 'WHERE album_id = ?';
$params = [$albumId];
if ($q !== '') {
    $whereSql .= ' AND (title LIKE ? OR slug LIKE ? OR filename LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($statusFilter === 'pending') {
    $whereSql .= " AND COALESCE(status,'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereSql .= " AND COALESCE(status,'published') = 'published' AND COALESCE(is_published, 1) = 1";
} elseif ($statusFilter === 'hidden') {
    $whereSql .= " AND COALESCE(status,'published') = 'published' AND COALESCE(is_published, 1) = 0";
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
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Publikováno</option>
      <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Čekající</option>
      <option value="hidden"<?= $statusFilter === 'hidden' ? ' selected' : '' ?>>Skryté</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="<?= BASE_URL ?>/admin/gallery_photos.php?album_id=<?= (int)$album['id'] ?>" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($photos)): ?>
  <p>
    <?php if ($q !== ''): ?>
      Pro zvolený filtr tu teď nejsou žádné fotografie.
    <?php else: ?>
      V tomto albu zatím nejsou žádné fotografie.
      <a href="<?= BASE_URL ?>/admin/gallery_photo_form.php?album_id=<?= (int)$album['id'] ?>">Přidat první fotografie</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <?= bulkFormOpen('gallery_photos', 'gallery_photos.php?album_id=' . (int)$album['id']) ?>
  <?= bulkActionBar() ?>
  <table>
    <caption>Fotografie v albu „<?= h($album['name']) ?>”</caption>
    <thead>
      <tr>
        <th scope=”col”><input type=”checkbox” class=”bulk-select-all” aria-label=”Vybrat vše”></th>
        <th scope=”col”>Náhled</th>
        <th scope=”col”>Fotografie</th>
        <th scope=”col”>Pořadí</th>
        <th scope=”col”>Stav</th>
        <th scope=”col”>Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($photos as $photo): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int)$photo['id'] ?>" class="bulk-checkbox" aria-label="Vybrat <?= h((string)$photo['label']) ?>"></td>
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
          <td>
            <?php $photoStatus = (string)($photo['status'] ?? 'published'); ?>
            <?php if ($photoStatus === 'pending'): ?>
              <strong class="status-badge status-badge--pending">Čeká</strong>
            <?php elseif ((int)($photo['is_published'] ?? 1) === 1): ?>
              Publikováno
            <?php else: ?>
              <strong>Skryto</strong>
            <?php endif; ?>
          </td>
          <td class="actions">
            <a href="<?= BASE_URL ?>/admin/gallery_photo_form.php?id=<?= (int)$photo['id'] ?>&amp;album_id=<?= (int)$album['id'] ?>" class="btn">Upravit</a>
            <?php if ((int)($photo['is_published'] ?? 1) === 1 && ($photo['status'] ?? 'published') === 'published'): ?>
              <a href="<?= h((string)$photo['public_path']) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
            <?php endif; ?>
            <?php if (($photo['status'] ?? 'published') === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
              <form action="<?= BASE_URL ?>/admin/approve.php" method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="module" value="gallery_photos">
                <input type="hidden" name="id" value="<?= (int)$photo['id'] ?>">
                <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/gallery_photos.php?album_id=<?= (int)$album['id'] ?>">
                <button type="submit" class="btn btn-success">Schválit</button>
              </form>
            <?php endif; ?>
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
  <?= bulkFormClose() ?>
  <?= bulkCheckboxJs() ?>
<?php endif; ?>

<?php adminFooter(); ?>
