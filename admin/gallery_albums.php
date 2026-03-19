<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();

// Načteme všechna alba (flat list) + počty
$allAlbums = $pdo->query(
    "SELECT a.id, a.name, a.parent_id,
            (SELECT COUNT(*) FROM cms_gallery_photos p WHERE p.album_id  = a.id) AS photo_count,
            (SELECT COUNT(*) FROM cms_gallery_albums s WHERE s.parent_id = a.id) AS sub_count,
            p.name AS parent_name
     FROM cms_gallery_albums a
     LEFT JOIN cms_gallery_albums p ON p.id = a.parent_id
     ORDER BY a.parent_id IS NOT NULL, p.name, a.name"
)->fetchAll();

adminHeader('Galerie – Alba');
?>

<p>
  <a href="<?= BASE_URL ?>/admin/gallery_album_form.php" class="btn">+ Nové album</a>
</p>

<?php if (empty($allAlbums)): ?>
  <p>Zatím nebylo vytvořeno žádné album.</p>
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
      <?php foreach ($allAlbums as $a): ?>
        <tr>
          <td><?= $a['parent_id'] ? '— ' : '' ?><?= h($a['name']) ?></td>
          <td><?= $a['parent_name'] !== null ? h($a['parent_name']) : '(kořen)' ?></td>
          <td><?= (int)$a['photo_count'] ?></td>
          <td><?= (int)$a['sub_count'] ?></td>
          <td class="actions">
            <a href="<?= BASE_URL ?>/admin/gallery_photos.php?album_id=<?= (int)$a['id'] ?>" class="btn">Fotografie</a>
            <a href="<?= BASE_URL ?>/admin/gallery_album_form.php?id=<?= (int)$a['id'] ?>" class="btn">Upravit</a>
            <form method="post" action="<?= BASE_URL ?>/admin/gallery_album_delete.php"
                  onsubmit="return confirm('Smazat album „<?= h(addslashes($a['name'])) ?>" včetně všech fotografií a podsložek?')">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <button type="submit" class="btn btn-danger">Smazat</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
