<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('gallery')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$albums = $pdo->query(
    "SELECT a.id, a.name, a.description,
            (SELECT COUNT(*) FROM cms_gallery_photos  p WHERE p.album_id  = a.id) AS photo_count,
            (SELECT COUNT(*) FROM cms_gallery_albums  s WHERE s.parent_id = a.id) AS sub_count
     FROM cms_gallery_albums a
     WHERE a.parent_id IS NULL
     ORDER BY a.name"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Galerie – <?= h($siteName) ?></title>
  <style>
    .skip-link { position: absolute; left: -9999px; }
    .skip-link:focus { left: 1rem; top: 1rem; z-index: 9999;
      background: #fff; padding: .5rem 1rem; border: 2px solid #000; }
    .gallery-grid { display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 1.5rem; list-style: none; padding: 0; margin: 1.5rem 0; }
    .gallery-card a { display: block; text-decoration: none; color: inherit; }
    .gallery-card a:hover .gallery-card__name,
    .gallery-card a:focus .gallery-card__name { text-decoration: underline; }
    .gallery-card__img { width: 100%; aspect-ratio: 4/3;
      object-fit: cover; display: block; background: #eee; }
    .gallery-card__placeholder { width: 100%; aspect-ratio: 4/3;
      display: flex; align-items: center; justify-content: center;
      background: #eee; color: #555; font-size: .85rem; }
    .gallery-card__name { font-weight: bold; margin: .5rem 0 .2rem; }
    .gallery-card__meta { font-size: .85rem; color: #555; margin: 0; }
  </style>
</head>
<body>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('gallery') ?>
</header>

<main id="obsah">
  <h2>Galerie</h2>

  <?php if (empty($albums)): ?>
    <p>Zatím zde nejsou žádná alba.</p>
  <?php else: ?>
    <ul class="gallery-grid" role="list">
      <?php foreach ($albums as $album):
        $coverUrl = gallery_cover_url((int)$album['id']);
      ?>
        <li class="gallery-card">
          <a href="<?= BASE_URL ?>/gallery/album.php?id=<?= (int)$album['id'] ?>">
            <?php if ($coverUrl !== ''): ?>
              <img class="gallery-card__img"
                   src="<?= h($coverUrl) ?>"
                   alt="">
            <?php else: ?>
              <div class="gallery-card__placeholder" aria-hidden="true">Bez náhledu</div>
            <?php endif; ?>
            <p class="gallery-card__name"><?= h($album['name']) ?></p>
            <p class="gallery-card__meta">
              <?= (int)$album['photo_count'] ?> <?= (int)$album['photo_count'] === 1 ? 'fotka' : ((int)$album['photo_count'] < 5 ? 'fotky' : 'fotek') ?>
              <?php if ((int)$album['sub_count'] > 0): ?>
                · <?= (int)$album['sub_count'] ?> <?= (int)$album['sub_count'] === 1 ? 'podsložka' : ((int)$album['sub_count'] < 5 ? 'podsložky' : 'podsložek') ?>
              <?php endif; ?>
            </p>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</main>

<footer>
  <p>© <?= date('Y') ?> <?= h($siteName) ?></p>
</footer>
</body>
</html>
