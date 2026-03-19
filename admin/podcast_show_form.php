<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo  = db_connect();
$id   = inputInt('get', 'id');
$show = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE id = ?");
    $stmt->execute([$id]);
    $show = $stmt->fetch();
    if (!$show) { header('Location: podcast_shows.php'); exit; }
}

adminHeader($id ? 'Upravit podcast' : 'Nový podcast');
?>

<form method="post" action="podcast_show_save.php" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <label for="title">Název podcastu <span aria-hidden="true">*</span></label>
  <input type="text" id="title" name="title" required maxlength="255"
         value="<?= h($show['title'] ?? '') ?>">

  <label for="slug">URL identifikátor (slug) <span aria-hidden="true">*</span>
    <small>(pouze malá písmena, číslice a pomlčky)</small>
  </label>
  <input type="text" id="slug" name="slug" required maxlength="100" pattern="[a-z0-9\-]+"
         value="<?= h($show['slug'] ?? '') ?>">

  <label for="author">Autor / vydavatel</label>
  <input type="text" id="author" name="author" maxlength="255"
         value="<?= h($show['author'] ?? '') ?>">

  <label for="language">Jazyk <small>(kód dle IETF, např. cs, en)</small></label>
  <input type="text" id="language" name="language" maxlength="10" style="width:6rem"
         value="<?= h($show['language'] ?? 'cs') ?>">

  <label for="category">Kategorie <small>(pro iTunes/Spotify, např. Technology)</small></label>
  <input type="text" id="category" name="category" maxlength="100"
         value="<?= h($show['category'] ?? '') ?>">

  <label for="website_url">Webová stránka podcastu <small>(nepovinné)</small></label>
  <input type="url" id="website_url" name="website_url" maxlength="500"
         value="<?= h($show['website_url'] ?? '') ?>">

  <label for="cover_image">
    Obrázek (cover art)
    <?php if (!empty($show['cover_image'])): ?>
      <small>(aktuální: <?= h($show['cover_image']) ?>)</small>
      <br><img src="<?= h(BASE_URL) ?>/uploads/podcasts/covers/<?= h($show['cover_image']) ?>"
               alt="" style="max-width:120px;margin:.5rem 0;display:block">
    <?php endif; ?>
  </label>
  <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp">

  <label for="description">Popis podcastu</label>
  <textarea id="description" name="description" rows="5"><?= h($show['description'] ?? '') ?></textarea>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id ? 'Uložit' : 'Vytvořit podcast' ?></button>
    <a href="podcast_shows.php" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<?php adminFooter(); ?>
