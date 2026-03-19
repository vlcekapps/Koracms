<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id  = inputInt('get', 'id');

$album = ['id' => null, 'name' => '', 'description' => '', 'parent_id' => null, 'cover_photo_id' => null];
if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) $album = $row;
}

// Všechna alba jako možné nadřazené (kromě sebe a svých potomků)
$allAlbums = $pdo->query("SELECT id, name, parent_id FROM cms_gallery_albums ORDER BY name")->fetchAll();

// Sestavíme seznam alb, která NESMÍ být jako parent (aktuální album + jeho potomci)
$forbidden = [];
if ($id !== null) {
    $forbidden = [$id];
    $changed   = true;
    while ($changed) {
        $changed = false;
        foreach ($allAlbums as $a) {
            if (!in_array((int)$a['id'], $forbidden, true) && in_array((int)$a['parent_id'], $forbidden, true)) {
                $forbidden[] = (int)$a['id'];
                $changed = true;
            }
        }
    }
}

// Fotografie pro výběr náhledové fotky (jen pokud upravujeme existující album)
$photos = [];
if ($id !== null) {
    $stmt = $pdo->prepare(
        "SELECT id, filename, title FROM cms_gallery_photos WHERE album_id = ? ORDER BY sort_order, id"
    );
    $stmt->execute([$id]);
    $photos = $stmt->fetchAll();
}

$pageTitle = $id ? 'Upravit album' : 'Nové album';
adminHeader($pageTitle);
?>

<p><a href="<?= BASE_URL ?>/admin/gallery_albums.php"><span aria-hidden="true">←</span> Zpět na seznam alb</a></p>

<form method="post" action="<?= BASE_URL ?>/admin/gallery_album_save.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="id"         value="<?= (int)$album['id'] ?>">

  <label for="name">Název alba <span aria-hidden="true">*</span></label>
  <input type="text" id="name" name="name" required aria-required="true"
         maxlength="255" value="<?= h($album['name']) ?>">

  <label for="description">Popis</label>
  <textarea id="description" name="description" rows="4"><?= h($album['description'] ?? '') ?></textarea>

  <label for="parent_id">Nadřazené album</label>
  <select id="parent_id" name="parent_id">
    <option value="">— Nejvyšší úroveň —</option>
    <?php foreach ($allAlbums as $a):
      if (in_array((int)$a['id'], $forbidden, true)) continue;
    ?>
      <option value="<?= (int)$a['id'] ?>"
        <?= (string)$album['parent_id'] === (string)$a['id'] ? 'selected' : '' ?>>
        <?= h($a['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <?php if (!empty($photos)): ?>
    <label for="cover_photo_id">Náhledová fotka alba</label>
    <select id="cover_photo_id" name="cover_photo_id">
      <option value="">— Automaticky (první fotka) —</option>
      <?php foreach ($photos as $p):
        $label = $p['title'] !== '' ? $p['title'] : $p['filename'];
      ?>
        <option value="<?= (int)$p['id'] ?>"
          <?= (string)$album['cover_photo_id'] === (string)$p['id'] ? 'selected' : '' ?>>
          <?= h($label) ?>
        </option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>

  <button type="submit" style="margin-top:1rem"><?= $id ? 'Uložit změny' : 'Vytvořit album' ?></button>
</form>

<?php adminFooter(); ?>
