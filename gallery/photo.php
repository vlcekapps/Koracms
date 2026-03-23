<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('gallery')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$photoId = inputInt('get', 'id');
if ($photoId === null) {
    header('Location: ' . BASE_URL . '/gallery/index.php');
    exit;
}

$pdo  = db_connect();
$stmt = $pdo->prepare("SELECT * FROM cms_gallery_photos WHERE id = ?");
$stmt->execute([$photoId]);
$photo = $stmt->fetch();
if (!$photo) {
    header('Location: ' . BASE_URL . '/gallery/index.php');
    exit;
}

$albumId = (int)$photo['album_id'];

// Album
$stmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ?");
$stmt->execute([$albumId]);
$album = $stmt->fetch();

// Předchozí / Následující v rámci alba
$stmtPrev = $pdo->prepare(
    "SELECT id FROM cms_gallery_photos
     WHERE album_id = ? AND (sort_order < ? OR (sort_order = ? AND id < ?))
     ORDER BY sort_order DESC, id DESC LIMIT 1"
);
$stmtPrev->execute([$albumId, $photo['sort_order'], $photo['sort_order'], $photoId]);
$prevId = $stmtPrev->fetchColumn();

$stmtNext = $pdo->prepare(
    "SELECT id FROM cms_gallery_photos
     WHERE album_id = ? AND (sort_order > ? OR (sort_order = ? AND id > ?))
     ORDER BY sort_order ASC, id ASC LIMIT 1"
);
$stmtNext->execute([$albumId, $photo['sort_order'], $photo['sort_order'], $photoId]);
$nextId = $stmtNext->fetchColumn();

$siteName  = getSetting('site_name', 'Kora CMS');
$trail     = gallery_breadcrumb($albumId);
$photoTitle = $photo['title'] !== '' ? $photo['title']
            : pathinfo($photo['filename'], PATHINFO_FILENAME);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($photoTitle) ?> – <?= h($album['name'] ?? 'Galerie') ?> – <?= h($siteName) ?></title>
  <style>
    .skip-link { position: absolute; left: -9999px; }
    .skip-link:focus { left: 1rem; top: 1rem; z-index: 9999;
      background: #fff; padding: .5rem 1rem; border: 2px solid #000; }
    .breadcrumb { list-style: none; padding: 0; margin: .5rem 0 1.5rem;
      display: flex; flex-wrap: wrap; gap: .25rem; }
    .breadcrumb li + li::before { content: '»'; margin-right: .25rem; color: #555; }
    .photo-detail { max-width: 960px; }
    .photo-detail__img { max-width: 100%; height: auto; display: block; }
    .photo-nav { display: flex; gap: 1rem; margin-top: 1rem; align-items: center;
      flex-wrap: wrap; }
    .photo-nav a { padding: .4rem .9rem; border: 1px solid #999;
      text-decoration: none; color: inherit; border-radius: 3px; }
    .photo-nav a:hover, .photo-nav a:focus { background: #f0f0f0; }
    .photo-nav__back { margin-left: auto; }
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
      <?php foreach ($trail as $crumb): ?>
        <li>
          <a href="<?= BASE_URL ?>/gallery/album.php?id=<?= (int)$crumb['id'] ?>"><?= h($crumb['name']) ?></a>
        </li>
      <?php endforeach; ?>
      <li aria-current="page"><?= h($photoTitle) ?></li>
    </ol>
  </nav>

  <h2><?= h($photoTitle) ?></h2>

  <div class="photo-detail">
    <figure>
      <img class="photo-detail__img"
           src="<?= BASE_URL ?>/uploads/gallery/<?= rawurlencode($photo['filename']) ?>"
           alt="<?= h($photoTitle) ?>">
      <?php if ($photo['title'] !== ''): ?>
        <figcaption><?= h($photo['title']) ?></figcaption>
      <?php endif; ?>
    </figure>

    <nav class="photo-nav" aria-label="Navigace mezi fotografiemi">
      <?php if ($prevId): ?>
        <a href="<?= BASE_URL ?>/gallery/photo.php?id=<?= (int)$prevId ?>"
           aria-label="Předchozí fotografie">« Předchozí</a>
      <?php else: ?>
        <span aria-hidden="true" style="color:#666">« Předchozí</span>
      <?php endif; ?>

      <?php if ($nextId): ?>
        <a href="<?= BASE_URL ?>/gallery/photo.php?id=<?= (int)$nextId ?>"
           aria-label="Následující fotografie">Následující »</a>
      <?php else: ?>
        <span aria-hidden="true" style="color:#666">Následující »</span>
      <?php endif; ?>

      <a class="photo-nav__back"
         href="<?= BASE_URL ?>/gallery/album.php?id=<?= $albumId ?>">
        Zpět do alba
      </a>
    </nav>
  </div>
</main>

<footer>
  <p>© <?= date('Y') ?> <?= h($siteName) ?></p>
</footer>
</body>
</html>
