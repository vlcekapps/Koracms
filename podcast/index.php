<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('podcast')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$shows = $pdo->query(
    "SELECT s.*, COUNT(e.id) AS episode_count
     FROM cms_podcast_shows s
     LEFT JOIN cms_podcasts e ON e.show_id = s.id
         AND e.status = 'published' AND (e.publish_at IS NULL OR e.publish_at <= NOW())
     GROUP BY s.id
     ORDER BY s.title ASC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Podcasty – ' . $siteName, 'url' => BASE_URL . '/podcast/index.php']) ?>
  <title>Podcasty – <?= h($siteName) ?></title>
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('podcast') ?>
</header>

<main id="obsah">
  <h2>Podcasty</h2>

  <?php if (empty($shows)): ?>
    <p>Zatím žádné podcasty.</p>
  <?php else: ?>
    <?php foreach ($shows as $show): ?>
      <article style="border-top:1px solid #ddd;padding:1rem 0;display:flex;gap:1rem;align-items:flex-start">
        <?php if (!empty($show['cover_image'])): ?>
          <img src="<?= h(BASE_URL) ?>/uploads/podcasts/covers/<?= h($show['cover_image']) ?>"
               alt="<?= h($show['title']) ?>"
               style="width:100px;height:100px;object-fit:cover;flex-shrink:0">
        <?php endif; ?>
        <div>
          <h3 style="margin:.2rem 0">
            <a href="<?= h(BASE_URL) ?>/podcast/show.php?slug=<?= h($show['slug']) ?>">
              <?= h($show['title']) ?>
            </a>
          </h3>
          <?php if (!empty($show['author'])): ?>
            <p style="margin:0;font-size:.85rem;color:#666"><?= h($show['author']) ?></p>
          <?php endif; ?>
          <?php if (!empty($show['description'])): ?>
            <p style="margin:.4rem 0"><?= h($show['description']) ?></p>
          <?php endif; ?>
          <p style="margin:.4rem 0;font-size:.85rem">
            <a href="<?= h(BASE_URL) ?>/podcast/show.php?slug=<?= h($show['slug']) ?>">
              <?= (int)$show['episode_count'] ?> epizod
            </a>
            &nbsp;·&nbsp;
            <a href="<?= h(BASE_URL) ?>/podcast/feed.php?slug=<?= h($show['slug']) ?>">RSS feed</a>
          </p>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?= siteFooter() ?>
</body>
</html>
