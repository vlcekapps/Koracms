<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('gallery')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$albumId = inputInt('get', 'id');
if ($albumId === null) {
    header('Location: ' . BASE_URL . '/gallery/index.php');
    exit;
}

$pdo = db_connect();
$stmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ?");
$stmt->execute([$albumId]);
$album = $stmt->fetch();
if (!$album) {
    header('Location: ' . BASE_URL . '/gallery/index.php');
    exit;
}

$siteName = getSetting('site_name', 'Kora CMS');
$trail    = gallery_breadcrumb($albumId);

// Podsložky
$subAlbums = $pdo->prepare(
    "SELECT a.id, a.name, a.description,
            (SELECT COUNT(*) FROM cms_gallery_photos  p WHERE p.album_id  = a.id) AS photo_count,
            (SELECT COUNT(*) FROM cms_gallery_albums  s WHERE s.parent_id = a.id) AS sub_count
     FROM cms_gallery_albums a
     WHERE a.parent_id = ?
     ORDER BY a.name"
);
$subAlbums->execute([$albumId]);
$subAlbums = $subAlbums->fetchAll();

// Fotografie v tomto albu
$photos = $pdo->prepare(
    "SELECT id, filename, title FROM cms_gallery_photos
     WHERE album_id = ?
     ORDER BY sort_order, id"
);
$photos->execute([$albumId]);
$photos = $photos->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($album['name']) ?> – Galerie – <?= h($siteName) ?></title>
  <style>
    .skip-link { position: absolute; left: -9999px; }
    .skip-link:focus { left: 1rem; top: 1rem; z-index: 9999;
      background: #fff; padding: .5rem 1rem; border: 2px solid #000; }
    .breadcrumb { list-style: none; padding: 0; margin: .5rem 0 1.5rem;
      display: flex; flex-wrap: wrap; gap: .25rem; }
    .breadcrumb li + li::before { content: '»'; margin-right: .25rem; color: #555; }
    .gallery-grid { display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 1.5rem; list-style: none; padding: 0; margin: 1rem 0 2rem; }
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
    .photo-grid { display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 1rem; list-style: none; padding: 0; margin: 1rem 0; }
    .photo-item figure { margin: 0; }
    .photo-item__img { width: 100%; aspect-ratio: 1;
      object-fit: cover; display: block; background: #eee; }
    .photo-item figcaption { font-size: .8rem; padding: .3rem 0;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  </style>
</head>
<body>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('gallery') ?>
</header>

<main id="obsah">
  <nav aria-label="Drobečková navigace">
    <ol class="breadcrumb">
      <li><a href="<?= BASE_URL ?>/gallery/index.php">Galerie</a></li>
      <?php foreach ($trail as $i => $crumb):
        $isLast = ($i === count($trail) - 1);
      ?>
        <li <?= $isLast ? 'aria-current="page"' : '' ?>>
          <?php if (!$isLast): ?>
            <a href="<?= BASE_URL ?>/gallery/album.php?id=<?= (int)$crumb['id'] ?>"><?= h($crumb['name']) ?></a>
          <?php else: ?>
            <?= h($crumb['name']) ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </nav>

  <h2><?= h($album['name']) ?></h2>
  <?php if (!empty($album['description'])): ?>
    <p><?= h($album['description']) ?></p>
  <?php endif; ?>

  <?php if (!empty($subAlbums)): ?>
    <h3>Podsložky</h3>
    <ul class="gallery-grid" role="list">
      <?php foreach ($subAlbums as $sub):
        $coverUrl = gallery_cover_url((int)$sub['id']);
      ?>
        <li class="gallery-card">
          <a href="<?= BASE_URL ?>/gallery/album.php?id=<?= (int)$sub['id'] ?>">
            <?php if ($coverUrl !== ''): ?>
              <img class="gallery-card__img" src="<?= h($coverUrl) ?>" alt="">
            <?php else: ?>
              <div class="gallery-card__placeholder" aria-hidden="true">Bez náhledu</div>
            <?php endif; ?>
            <p class="gallery-card__name"><?= h($sub['name']) ?></p>
            <p class="gallery-card__meta">
              <?= (int)$sub['photo_count'] ?> <?= (int)$sub['photo_count'] === 1 ? 'fotka' : ((int)$sub['photo_count'] < 5 ? 'fotky' : 'fotek') ?>
              <?php if ((int)$sub['sub_count'] > 0): ?>
                · <?= (int)$sub['sub_count'] ?> <?= (int)$sub['sub_count'] === 1 ? 'podsložka' : ((int)$sub['sub_count'] < 5 ? 'podsložky' : 'podsložek') ?>
              <?php endif; ?>
            </p>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (!empty($photos)): ?>
    <h3>Fotografie</h3>
    <ul class="photo-grid" role="list">
      <?php foreach ($photos as $photo):
        $label = $photo['title'] !== '' ? $photo['title']
               : pathinfo($photo['filename'], PATHINFO_FILENAME);
      ?>
        <li class="photo-item">
          <figure>
            <a href="<?= BASE_URL ?>/gallery/photo.php?id=<?= (int)$photo['id'] ?>">
              <img class="photo-item__img"
                   src="<?= BASE_URL ?>/uploads/gallery/thumbs/<?= rawurlencode($photo['filename']) ?>"
                   alt="<?= h($label) ?>">
            </a>
            <?php if ($photo['title'] !== ''): ?>
              <figcaption><?= h($photo['title']) ?></figcaption>
            <?php endif; ?>
          </figure>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (empty($subAlbums) && empty($photos)): ?>
    <p>Toto album je zatím prázdné.</p>
  <?php endif; ?>
</main>

<footer>
  <p>© <?= date('Y') ?> <?= h($siteName) ?></p>
</footer>
</body>
</html>
