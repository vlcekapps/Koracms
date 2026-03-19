<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('podcast')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$slug     = trim($_GET['slug'] ?? '');

if ($slug === '') { header('Location: ' . BASE_URL . '/podcast/index.php'); exit; }

$showStmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE slug = ?");
$showStmt->execute([$slug]);
$show = $showStmt->fetch();
if (!$show) { header('Location: ' . BASE_URL . '/podcast/index.php'); exit; }

$episodes = $pdo->prepare(
    "SELECT * FROM cms_podcasts
     WHERE show_id = ? AND status = 'published' AND (publish_at IS NULL OR publish_at <= NOW())
     ORDER BY episode_num DESC, created_at DESC"
);
$episodes->execute([$show['id']]);
$episodes = $episodes->fetchAll();

$feedUrl = BASE_URL . '/podcast/feed.php?slug=' . rawurlencode($show['slug']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta([
    'title'       => h($show['title']) . ' – ' . $siteName,
    'description' => $show['description'],
    'url'         => BASE_URL . '/podcast/show.php?slug=' . rawurlencode($show['slug']),
]) ?>
  <title><?= h($show['title']) ?> – <?= h($siteName) ?></title>
  <link rel="alternate" type="application/rss+xml"
        title="<?= h($show['title']) ?> – RSS feed"
        href="<?= h($feedUrl) ?>">
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('podcast') ?>
</header>

<main id="obsah">
  <p><a href="<?= h(BASE_URL) ?>/podcast/index.php"><span aria-hidden="true">←</span> Všechny podcasty</a></p>

  <div style="display:flex;gap:1.5rem;align-items:flex-start;margin-bottom:1.5rem">
    <?php if (!empty($show['cover_image'])): ?>
      <img src="<?= h(BASE_URL) ?>/uploads/podcasts/covers/<?= h($show['cover_image']) ?>"
           alt="<?= h($show['title']) ?>"
           style="width:140px;height:140px;object-fit:cover;flex-shrink:0">
    <?php endif; ?>
    <div>
      <h2 style="margin:.2rem 0"><?= h($show['title']) ?></h2>
      <?php if (!empty($show['author'])): ?>
        <p style="margin:0;color:#666"><?= h($show['author']) ?></p>
      <?php endif; ?>
      <?php if (!empty($show['description'])): ?>
        <p><?= h($show['description']) ?></p>
      <?php endif; ?>
      <p style="font-size:.85rem">
        <a href="<?= h($feedUrl) ?>"><span aria-hidden="true">📡</span> RSS feed</a>
        <?php if (!empty($show['website_url'])): ?>
          &nbsp;·&nbsp;<a href="<?= h($show['website_url']) ?>" target="_blank" rel="noopener">Web</a>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <?php if (empty($episodes)): ?>
    <p>Zatím žádné epizody.</p>
  <?php else: ?>
    <?php foreach ($episodes as $ep): ?>
      <article id="ep-<?= (int)$ep['id'] ?>" style="border-top:1px solid #ddd;padding:1rem 0">
        <header>
          <?php if ($ep['episode_num']): ?>
            <p style="margin:0;font-size:.85rem;color:#666">Epizoda <?= (int)$ep['episode_num'] ?></p>
          <?php endif; ?>
          <h3 style="margin:.2rem 0"><?= h($ep['title']) ?></h3>
          <?php $displayDate = !empty($ep['publish_at']) ? $ep['publish_at'] : $ep['created_at']; ?>
          <p style="margin:0;font-size:.85rem;color:#666">
            <time datetime="<?= h(str_replace(' ', 'T', $displayDate)) ?>"><?= formatCzechDate($displayDate) ?></time>
            <?php if ($ep['duration'] !== ''): ?> · <?= h($ep['duration']) ?><?php endif; ?>
          </p>
        </header>

        <?php
        $audioSrc = '';
        if ($ep['audio_file'] !== '') {
            $audioSrc = BASE_URL . '/uploads/podcasts/' . rawurlencode($ep['audio_file']);
        } elseif ($ep['audio_url'] !== '') {
            $audioSrc = $ep['audio_url'];
        }
        ?>
        <?php if ($audioSrc !== ''): ?>
          <audio controls style="width:100%;margin:.75rem 0"
                 aria-label="Přehrát epizodu <?= h($ep['title']) ?>">
            <source src="<?= h($audioSrc) ?>">
            Váš prohlížeč nepodporuje přehrávání audia.
            <a href="<?= h($audioSrc) ?>">Stáhnout epizodu</a>
          </audio>
        <?php endif; ?>

        <?php if (!empty($ep['description'])): ?>
          <div><?= $ep['description'] ?></div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?= siteFooter() ?>
</body>
</html>
