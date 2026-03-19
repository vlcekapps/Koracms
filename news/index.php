<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('news')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage  = max(1, (int)getSetting('news_per_page', '10'));

$total = (int)$pdo->query("SELECT COUNT(*) FROM cms_news WHERE status = 'published'")->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page  = max(1, min($pages, (int)($_GET['strana'] ?? 1)));
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT n.id, n.content, n.created_at,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name
     FROM cms_news n
     LEFT JOIN cms_users u ON u.id = n.author_id
     WHERE n.status = 'published' ORDER BY n.created_at DESC LIMIT ? OFFSET ?"
);
$stmt->execute([$perPage, $offset]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Novinky – ' . $siteName, 'url' => BASE_URL . '/news/index.php']) ?>
  <title>Novinky – <?= h($siteName) ?></title>
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('news') ?>
</header>

<main id="obsah">
  <h2>Novinky</h2>

  <?php if (empty($items)): ?>
    <p>Žádné novinky.</p>
  <?php else: ?>
    <?php foreach ($items as $n): ?>
      <article>
        <h3><time datetime="<?= h(str_replace(' ', 'T', $n['created_at'])) ?>"><?= formatCzechDate($n['created_at']) ?></time><?= !empty($n['author_name']) ? ' · ' . h($n['author_name']) : '' ?></h3>
        <p><?= h($n['content']) ?></p>
      </article>
      <hr>
    <?php endforeach; ?>

    <?php if ($pages > 1): ?>
    <nav aria-label="Stránkování novinek">
      <ul style="list-style:none; display:flex; gap:.5rem; padding:0">
        <?php if ($page > 1): ?>
          <li><a href="?strana=<?= $page - 1 ?>" rel="prev"><span aria-hidden="true">‹</span> Starší</a></li>
        <?php endif; ?>
        <?php for ($p = 1; $p <= $pages; $p++): ?>
          <li>
            <?php if ($p === $page): ?>
              <span aria-current="page"><?= $p ?></span>
            <?php else: ?>
              <a href="?strana=<?= $p ?>"><?= $p ?></a>
            <?php endif; ?>
          </li>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
          <li><a href="?strana=<?= $page + 1 ?>" rel="next">Novější <span aria-hidden="true">›</span></a></li>
        <?php endif; ?>
      </ul>
    </nav>
    <?php endif; ?>

  <?php endif; ?>
</main>

<?= siteFooter() ?>
</body>
</html>
