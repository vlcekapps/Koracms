<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$statusFilter = in_array($_GET['status'] ?? '', ['all', 'pending', 'published', 'hidden'], true)
    ? (string)$_GET['status']
    : 'all';

$whereParts = [];
$params = [];
if ($q !== '') {
    $whereParts[] = '(a.name LIKE ? OR a.slug LIKE ? OR a.description LIKE ? OR COALESCE(p.name, \'\') LIKE ?)';
    $params = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];
}
if ($statusFilter === 'pending') {
    $whereParts[] = "COALESCE(a.status,'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereParts[] = "COALESCE(a.status,'published') = 'published' AND COALESCE(a.is_published, 1) = 1";
} elseif ($statusFilter === 'hidden') {
    $whereParts[] = "COALESCE(a.status,'published') = 'published' AND COALESCE(a.is_published, 1) = 0";
}
$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$stmt = $pdo->prepare(
    "SELECT a.id, a.name, a.slug, a.parent_id, a.description, a.cover_photo_id,
            COALESCE(a.status,'published') AS status, COALESCE(a.is_published, 1) AS is_published,
            (SELECT COUNT(*) FROM cms_gallery_photos gp WHERE gp.album_id = a.id) AS photo_count,
            (SELECT COUNT(*) FROM cms_gallery_albums gs WHERE gs.parent_id = a.id) AS sub_count,
            p.name AS parent_name
     FROM cms_gallery_albums a
     LEFT JOIN cms_gallery_albums p ON p.id = a.parent_id
     {$whereSql}
     ORDER BY a.parent_id IS NOT NULL, COALESCE(p.name, ''), a.name"
);
$stmt->execute($params);
$albums = array_map(
    static fn(array $album): array => hydrateGalleryAlbumPresentation($album),
    $stmt->fetchAll()
);

adminHeader('Alba galerie');
?>

<p><a href="<?= BASE_URL ?>/admin/gallery_album_form.php" class="btn">+ Nové album</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v albech</label>
    <input type="search" id="q" name="q" placeholder="Hledat v albech..." value="<?= h($q) ?>" style="width:320px">
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
    <a href="gallery_albums.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($albums)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádná alba.
    <?php else: ?>
      Zatím tu nejsou žádná alba. <a href="<?= BASE_URL ?>/admin/gallery_album_form.php">Přidat první album</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <form method="post" action="<?= BASE_URL ?>/admin/bulk.php" id="bulk-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="module" value="gallery_albums">
    <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/gallery_albums.php">
    <fieldset style="margin:0 0 .85rem;border:1px solid #d6d6d6;border-radius:10px;padding:.85rem 1rem">
      <legend>Hromadné akce s vybranými alby</legend>
      <p id="bulk-status" class="field-help" aria-live="polite" style="margin-top:0">Zatím není vybrané žádné album.</p>
      <div class="button-row">
        <button type="submit" name="action" value="delete" class="btn btn-danger bulk-action-btn"
                disabled onclick="return confirm('Smazat vybraná alba včetně fotografií?')">Smazat vybrané</button>
        <button type="submit" name="action" value="export_zip" class="btn bulk-action-btn"
                disabled formaction="<?= BASE_URL ?>/admin/gallery_export_zip.php">Exportovat do ZIP</button>
      </div>
    </fieldset>
  </form>

  <table>
    <caption>Přehled alb</caption>
    <thead>
      <tr>
        <th scope="col"><input type="checkbox" id="check-all" aria-label="Vybrat vše"></th>
        <th scope="col">Název</th>
        <th scope="col">Nadřazené</th>
        <th scope="col">Fotek</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($albums as $album): ?>
        <tr<?= ($album['status'] ?? 'published') === 'pending' ? ' class="table-row--pending"' : '' ?>>
          <td><input type="checkbox" name="ids[]" value="<?= (int)$album['id'] ?>" form="bulk-form" aria-label="Vybrat <?= h((string)$album['name']) ?>"></td>
          <td>
            <?= $album['parent_id'] ? '— ' : '' ?><strong><?= h($album['name']) ?></strong>
            <br><small style="color:#555"><?= h(parse_url((string)$album['public_path'], PHP_URL_PATH) ?: (string)$album['public_path']) ?></small>
          </td>
          <td><?= $album['parent_name'] !== null ? h((string)$album['parent_name']) : '–' ?></td>
          <td><?= (int)$album['photo_count'] ?><?= (int)$album['sub_count'] > 0 ? ' <small>(+' . (int)$album['sub_count'] . ' podalb)</small>' : '' ?></td>
          <td>
            <?php if (($album['status'] ?? 'published') === 'pending'): ?>
              <strong class="status-badge status-badge--pending">Čeká</strong>
            <?php elseif ((int)($album['is_published'] ?? 1) === 1): ?>
              Publikováno
            <?php else: ?>
              <strong>Skryto</strong>
            <?php endif; ?>
          </td>
          <td class="actions">
            <a href="<?= BASE_URL ?>/admin/gallery_photos.php?album_id=<?= (int)$album['id'] ?>">Fotografie</a>
            <a href="<?= BASE_URL ?>/admin/gallery_album_form.php?id=<?= (int)$album['id'] ?>">Upravit</a>
            <?php if ((int)($album['is_published'] ?? 1) === 1 && ($album['status'] ?? 'published') === 'published'): ?>
              <a href="<?= h((string)$album['public_path']) ?>" target="_blank" rel="noopener noreferrer">Web</a>
            <?php endif; ?>
            <?php if (($album['status'] ?? 'published') === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
              <form action="<?= BASE_URL ?>/admin/approve.php" method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="module" value="gallery_albums">
                <input type="hidden" name="id" value="<?= (int)$album['id'] ?>">
                <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/gallery_albums.php">
                <button type="submit" class="btn btn-success">Schválit</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <script nonce="<?= cspNonce() ?>">
  (function(){
    var form = document.getElementById('bulk-form');
    var checkAll = document.getElementById('check-all');
    var checkboxes = document.querySelectorAll('input[name="ids[]"]');
    var statusEl = document.getElementById('bulk-status');
    var buttons = form.querySelectorAll('.bulk-action-btn');

    function update() {
      var checked = document.querySelectorAll('input[name="ids[]"]:checked').length;
      buttons.forEach(function(btn) { btn.disabled = checked === 0; });
      if (checked === 0) statusEl.textContent = 'Zatím není vybrané žádné album.';
      else if (checked === 1) statusEl.textContent = 'Vybráno 1 album.';
      else if (checked <= 4) statusEl.textContent = 'Vybrána ' + checked + ' alba.';
      else statusEl.textContent = 'Vybráno ' + checked + ' alb.';
      if (checkAll) checkAll.checked = checked === checkboxes.length && checked > 0;
    }

    if (checkAll) checkAll.addEventListener('change', function() {
      checkboxes.forEach(function(cb) { cb.checked = checkAll.checked; });
      update();
    });
    checkboxes.forEach(function(cb) { cb.addEventListener('change', update); });
  })();
  </script>
<?php endif; ?>

<?php adminFooter(); ?>
