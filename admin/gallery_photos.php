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
$sortFilter = in_array($_GET['sort'] ?? '', ['position', 'newest', 'title'], true)
    ? (string)$_GET['sort']
    : 'position';
$metadataFilter = in_array($_GET['metadata'] ?? '', ['all', 'missing_alt'], true)
    ? (string)$_GET['metadata']
    : 'all';

$whereSql = 'WHERE deleted_at IS NULL AND album_id = ?';
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
if ($metadataFilter === 'missing_alt') {
    $whereSql .= " AND TRIM(COALESCE(alt_text, '')) = ''";
}

$orderBySql = match ($sortFilter) {
    'newest' => 'ORDER BY id DESC',
    'title' => 'ORDER BY COALESCE(NULLIF(title, \'\'), filename), id',
    default => 'ORDER BY sort_order, id',
};

$photosStmt = $pdo->prepare(
    "SELECT * FROM cms_gallery_photos {$whereSql} {$orderBySql}"
);
$photosStmt->execute($params);
$photos = array_map(
    static fn (array $photo): array => hydrateGalleryPhotoPresentation($photo),
    $photosStmt->fetchAll()
);

adminHeader('Galerie – ' . $album['name']);

$currentUrl = BASE_URL . '/admin/gallery_photos.php?' . http_build_query(array_filter([
    'album_id' => (int)$album['id'],
    'q' => $q,
    'status' => $statusFilter !== 'all' ? $statusFilter : null,
    'metadata' => $metadataFilter !== 'all' ? $metadataFilter : null,
    'sort' => $sortFilter !== 'position' ? $sortFilter : null,
], static fn ($value): bool => $value !== null && $value !== ''));
$reorderDisabled = $sortFilter !== 'position';
$reorderDisabledReason = 'Rychlé přesuny fungují při řazení podle pořadí.';
?>

<p><a href="<?= BASE_URL ?>/admin/gallery_albums.php"><span aria-hidden="true">←</span> Zpět na seznam alb</a></p>

<p><a href="<?= BASE_URL ?>/admin/gallery_photo_form.php?album_id=<?= (int)$album['id'] ?>" class="btn">+ Přidat fotografie</a></p>

<form method="get" class="button-row button-row--baseline admin-stack-sm">
  <input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
  <div>
    <label for="q" class="visually-hidden">Hledat ve fotografiích</label>
    <input type="search" id="q" name="q" placeholder="Hledat ve fotografiích..." value="<?= h($q) ?>" class="admin-search-input">
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
  <div>
    <label for="metadata">Metadata</label>
    <select id="metadata" name="metadata">
      <option value="all"<?= $metadataFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="missing_alt"<?= $metadataFilter === 'missing_alt' ? ' selected' : '' ?>>Chybí alt text</option>
    </select>
  </div>
  <div>
    <label for="sort">Řazení</label>
    <select id="sort" name="sort">
      <option value="position"<?= $sortFilter === 'position' ? ' selected' : '' ?>>Podle pořadí</option>
      <option value="newest"<?= $sortFilter === 'newest' ? ' selected' : '' ?>>Nejnovější první</option>
      <option value="title"<?= $sortFilter === 'title' ? ' selected' : '' ?>>Podle názvu</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all' || $metadataFilter !== 'all' || $sortFilter !== 'position'): ?>
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
  <?= bulkActions('gallery_photos', BASE_URL . '/admin/gallery_photos.php?album_id=' . (int)$album['id'], 'Hromadné akce s fotografiemi', 'fotografie') ?>
  <table>
    <caption>Fotografie v albu „<?= h($album['name']) ?>”</caption>
    <thead>
      <tr>
        <th scope="col"><label for="gallery-photos-check-all" class="sr-only">Vybrat vše</label><input type="checkbox" id="gallery-photos-check-all" class="bulk-select-all"></th>
        <th scope="col">Náhled</th>
        <th scope="col">Fotografie</th>
        <th scope="col">Pořadí</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($photos as $photo): ?>
        <tr>
          <td><label for="gallery-photo-select-<?= (int)$photo['id'] ?>" class="sr-only">Vybrat <?= h((string)$photo['label']) ?></label><input type="checkbox" id="gallery-photo-select-<?= (int)$photo['id'] ?>" name="ids[]" value="<?= (int)$photo['id'] ?>" form="bulk-form"></td>
          <td>
            <img src="<?= h((string)$photo['thumb_url']) ?>"
                 alt="<?= h((string)$photo['label']) ?>"
                 class="admin-thumb">
          </td>
          <td>
            <strong><?= h((string)$photo['label']) ?></strong><br>
            <small class="table-meta"><?= h(parse_url((string)$photo['public_path'], PHP_URL_PATH) ?: (string)$photo['public_path']) ?></small>
            <?php if ((string)($photo['alt_text'] ?? '') === ''): ?>
              <br><small class="table-meta"><strong>Chybí alt text</strong> – veřejně se použije popisek, titulek nebo název souboru.</small>
            <?php endif; ?>
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
            <a href="<?= BASE_URL ?>/admin/revisions.php?type=gallery_photo&amp;id=<?= (int)$photo['id'] ?>">Historie revizí</a>
            <?php if ((int)($photo['is_published'] ?? 1) === 1 && ($photo['status'] ?? 'published') === 'published'): ?>
              <a href="<?= h((string)$photo['public_path']) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
            <?php endif; ?>
            <form method="post" action="<?= BASE_URL ?>/admin/gallery_photo_reorder.php">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$photo['id'] ?>">
              <input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
              <input type="hidden" name="direction" value="up">
              <input type="hidden" name="redirect" value="<?= h($currentUrl) ?>">
              <button type="submit" class="btn"<?= $reorderDisabled ? ' disabled aria-disabled="true"' : '' ?>>Nahoru<?php if ($reorderDisabled): ?><span class="sr-only"> – <?= h($reorderDisabledReason) ?></span><?php endif; ?></button>
            </form>
            <form method="post" action="<?= BASE_URL ?>/admin/gallery_photo_reorder.php">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$photo['id'] ?>">
              <input type="hidden" name="album_id" value="<?= (int)$album['id'] ?>">
              <input type="hidden" name="direction" value="down">
              <input type="hidden" name="redirect" value="<?= h($currentUrl) ?>">
              <button type="submit" class="btn"<?= $reorderDisabled ? ' disabled aria-disabled="true"' : '' ?>>Dolů<?php if ($reorderDisabled): ?><span class="sr-only"> – <?= h($reorderDisabledReason) ?></span><?php endif; ?></button>
            </form>
            <?php if (($photo['status'] ?? 'published') === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
              <form action="<?= BASE_URL ?>/admin/approve.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="module" value="gallery_photos">
                <input type="hidden" name="id" value="<?= (int)$photo['id'] ?>">
                <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/gallery_photos.php?album_id=<?= (int)$album['id'] ?>">
                <button type="submit" class="btn btn-success">Schválit</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= BASE_URL ?>/admin/gallery_photo_delete.php"
                  data-confirm="Smazat tuto fotografii?">
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
  <?= bulkCheckboxJs() ?>
<?php endif; ?>

<?php adminFooter(); ?>
