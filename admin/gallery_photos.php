<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$albumId = inputInt('get', 'album_id');
if ($albumId === null) {
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}

$pdo  = db_connect();
$stmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ?");
$stmt->execute([$albumId]);
$album = $stmt->fetch();
if (!$album) {
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}

$photos = $pdo->prepare(
    "SELECT * FROM cms_gallery_photos WHERE album_id = ? ORDER BY sort_order, id"
);
$photos->execute([$albumId]);
$photos = $photos->fetchAll();

adminHeader('Galerie – ' . $album['name']);
?>

<p>
  <a href="<?= BASE_URL ?>/admin/gallery_albums.php"><span aria-hidden="true">←</span> Zpět na seznam alb</a>
</p>

<p>
  <a href="<?= BASE_URL ?>/admin/gallery_photo_form.php?album_id=<?= $albumId ?>" class="btn">
    + Přidat fotografie
  </a>
</p>

<?php if (empty($photos)): ?>
  <p>V tomto albu nejsou žádné fotografie.</p>
<?php else: ?>
  <table>
    <caption>Fotografie v albu „<?= h($album['name']) ?>"</caption>
    <thead>
      <tr>
        <th scope="col">Náhled</th>
        <th scope="col">Titulek</th>
        <th scope="col">Pořadí</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($photos as $photo):
        $label = $photo['title'] !== '' ? $photo['title'] : $photo['filename'];
      ?>
        <tr>
          <td>
            <img src="<?= BASE_URL ?>/uploads/gallery/thumbs/<?= rawurlencode($photo['filename']) ?>"
                 alt="<?= h($label) ?>"
                 style="width:80px;height:60px;object-fit:cover;">
          </td>
          <td><?= h($label) ?></td>
          <td><?= (int)$photo['sort_order'] ?></td>
          <td class="actions">
            <a href="<?= BASE_URL ?>/admin/gallery_photo_form.php?id=<?= (int)$photo['id'] ?>&amp;album_id=<?= $albumId ?>"
               class="btn">Upravit</a>
            <form method="post" action="<?= BASE_URL ?>/admin/gallery_photo_delete.php"
                  onsubmit="return confirm('Smazat tuto fotografii?')">
              <input type="hidden" name="csrf_token"  value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id"          value="<?= (int)$photo['id'] ?>">
              <input type="hidden" name="album_id"    value="<?= $albumId ?>">
              <button type="submit" class="btn btn-danger">Smazat</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
