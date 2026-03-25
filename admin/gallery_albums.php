<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');

$whereSql = '';
$params = [];
if ($q !== '') {
    $whereSql = "WHERE a.name LIKE ? OR a.slug LIKE ? OR a.description LIKE ? OR COALESCE(p.name, '') LIKE ?";
    $params = [
        '%' . $q . '%',
        '%' . $q . '%',
        '%' . $q . '%',
        '%' . $q . '%',
    ];
}

$stmt = $pdo->prepare(
    "SELECT a.id, a.name, a.slug, a.parent_id, a.description, a.cover_photo_id,
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

adminHeader('Galerie – Alba');
?>

<p><a href="<?= BASE_URL ?>/admin/gallery_album_form.php" class="btn">+ Nové album</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v albech</label>
    <input type="search" id="q" name="q" placeholder="Hledat v albech..." value="<?= h($q) ?>" style="width:320px">
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== ''): ?>
    <a href="gallery_albums.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($albums)): ?>
  <p>
    <?php if ($q !== ''): ?>
      Pro zvolený filtr tu teď nejsou žádná alba.
    <?php else: ?>
      Zatím tu nejsou žádná alba. <a href="<?= BASE_URL ?>/admin/gallery_album_form.php">Přidat první album</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <table>
    <caption>Seznam alb</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Nadřazené album</th>
        <th scope="col">Fotek</th>
        <th scope="col">Podsložek</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($albums as $album): ?>
        <tr>
          <td>
            <?= $album['parent_id'] ? '— ' : '' ?><strong><?= h($album['name']) ?></strong><br>
            <small style="color:#555"><?= h(parse_url((string)$album['public_path'], PHP_URL_PATH) ?: (string)$album['public_path']) ?></small>
            <?php if ($album['excerpt'] !== ''): ?>
              <br><small style="color:#555"><?= h($album['excerpt']) ?></small>
            <?php endif; ?>
          </td>
          <td><?= $album['parent_name'] !== null ? h((string)$album['parent_name']) : '(kořen)' ?></td>
          <td><?= (int)$album['photo_count'] ?></td>
          <td><?= (int)$album['sub_count'] ?></td>
          <td class="actions">
            <a href="<?= BASE_URL ?>/admin/gallery_photos.php?album_id=<?= (int)$album['id'] ?>" class="btn">Fotografie</a>
            <a href="<?= BASE_URL ?>/admin/gallery_album_form.php?id=<?= (int)$album['id'] ?>" class="btn">Upravit</a>
            <a href="<?= h((string)$album['public_path']) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
            <form method="post" action="<?= BASE_URL ?>/admin/gallery_album_delete.php"
                  onsubmit="return confirm('Smazat album „<?= h(addslashes($album['name'])) ?>“ včetně všech fotografií a podsložek?')">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$album['id'] ?>">
              <button type="submit" class="btn btn-danger">Smazat</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>



<?php adminFooter(); ?>
