<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('blog')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage  = max(1, (int)getSetting('blog_per_page', '10'));

// Filtry
$katId  = inputInt('get', 'kat');
$tagSlug = trim($_GET['tag'] ?? '');

// Kategorie pro postranní panel
$categories = $pdo->query("SELECT id, name FROM cms_categories ORDER BY name")->fetchAll();

// Tagy pro postranní panel
$allTags = [];
try {
    $allTags = $pdo->query("SELECT name, slug FROM cms_tags ORDER BY name")->fetchAll();
} catch (\PDOException $e) {}

// Sestavení WHERE
$where  = "WHERE a.status = 'published' AND (a.publish_at IS NULL OR a.publish_at <= NOW())";
$params = [];

if ($katId !== null) {
    $where   .= " AND a.category_id = ?";
    $params[] = $katId;
}

if ($tagSlug !== '') {
    $where  .= " AND EXISTS (
        SELECT 1 FROM cms_article_tags at2
        JOIN cms_tags t ON t.id = at2.tag_id
        WHERE at2.article_id = a.id AND t.slug = ?
    )";
    $params[] = $tagSlug;
}

// Celkový počet
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM cms_articles a {$where}"
);
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = max(1, min($pages, (int)($_GET['strana'] ?? 1)));
$offset = ($page - 1) * $perPage;

// Články
$stmt = $pdo->prepare(
    "SELECT a.id, a.title, a.perex, a.image_file, a.created_at, c.name AS category,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name
     FROM cms_articles a
     LEFT JOIN cms_categories c ON c.id = a.category_id
     LEFT JOIN cms_users u ON u.id = a.author_id
     {$where}
     ORDER BY a.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$articles = $stmt->fetchAll();

// Název aktivního filtru
$pageTitle = 'Blog';
if ($katId !== null) {
    $pageTitle = 'Blog – ' . (array_column($categories, 'name', 'id')[$katId] ?? 'Kategorie');
} elseif ($tagSlug !== '') {
    $activeTag = array_filter($allTags, fn($t) => $t['slug'] === $tagSlug);
    $activeTag = reset($activeTag);
    if ($activeTag) $pageTitle = 'Blog – #' . $activeTag['name'];
}

// Paginační URL helper
$paginBase = 'index.php?' . ($katId !== null ? 'kat=' . $katId . '&' : '') . ($tagSlug !== '' ? 'tag=' . rawurlencode($tagSlug) . '&' : '');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => h($pageTitle) . ' – ' . h($siteName)]) ?>
  <title><?= h($pageTitle) ?> – <?= h($siteName) ?></title>
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?php $logo = getSetting('site_logo', ''); if ($logo !== ''): ?>
    <img src="<?= BASE_URL ?>/uploads/site/<?= h($logo) ?>" alt="<?= h($siteName) ?>"
         style="max-height:60px" loading="lazy">
  <?php endif; ?>
  <?= siteNav('blog') ?>
</header>

<main id="obsah">
  <h2><?= h($pageTitle) ?></h2>

  <?php if (!empty($categories)): ?>
  <nav aria-label="Kategorie blogu">
    <ul>
      <li><a href="index.php"<?= ($katId === null && $tagSlug === '') ? ' aria-current="page"' : '' ?>>Vše</a></li>
      <?php foreach ($categories as $cat): ?>
        <li>
          <a href="index.php?kat=<?= (int)$cat['id'] ?>"
             <?= $katId === (int)$cat['id'] ? 'aria-current="page"' : '' ?>>
            <?= h($cat['name']) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <?php endif; ?>

  <?php if (!empty($allTags)): ?>
  <nav aria-label="Tagy blogu">
    <?php foreach ($allTags as $t): ?>
      <a href="index.php?tag=<?= rawurlencode($t['slug']) ?>"
         <?= $tagSlug === $t['slug'] ? 'aria-current="page"' : '' ?>
         style="margin-right:.4rem">#<?= h($t['name']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>

  <?php if (empty($articles)): ?>
    <p>Žádné články.</p>
  <?php else: ?>
    <?php foreach ($articles as $a): ?>
      <article>
        <?php if (!empty($a['image_file'])): ?>
          <a href="article.php?id=<?= (int)$a['id'] ?>">
            <img src="<?= BASE_URL ?>/uploads/articles/thumbs/<?= rawurlencode($a['image_file']) ?>"
                 alt="<?= h($a['title']) ?>" style="max-width:100%;height:auto" loading="lazy">
          </a>
        <?php endif; ?>
        <h3>
          <a href="article.php?id=<?= (int)$a['id'] ?>"><?= h($a['title']) ?></a>
        </h3>
        <?php if (!empty($a['category'])): ?>
          <p><small>Kategorie: <a href="index.php?kat=<?= (int)$katId ?>"><?= h($a['category']) ?></a></small></p>
        <?php endif; ?>
        <?php if (!empty($a['perex'])): ?>
          <p><?= h($a['perex']) ?></p>
        <?php endif; ?>
        <p>
          <time datetime="<?= h(str_replace(' ', 'T', $a['created_at'])) ?>"><?= formatCzechDate($a['created_at']) ?></time>
          <?= !empty($a['author_name']) ? ' · ' . h($a['author_name']) : '' ?>
          · <a href="article.php?id=<?= (int)$a['id'] ?>">Číst dále <span aria-hidden="true">→</span></a>
        </p>
      </article>
    <?php endforeach; ?>

    <?php if ($pages > 1): ?>
    <nav aria-label="Stránkování blogu">
      <ul style="list-style:none;display:flex;gap:.5rem;padding:0;flex-wrap:wrap">
        <?php if ($page > 1): ?>
          <li><a href="<?= $paginBase ?>strana=<?= $page - 1 ?>" rel="prev"><span aria-hidden="true">‹</span> Novější</a></li>
        <?php endif; ?>
        <?php for ($p = 1; $p <= $pages; $p++): ?>
          <li>
            <?php if ($p === $page): ?>
              <span aria-current="page"><?= $p ?></span>
            <?php else: ?>
              <a href="<?= $paginBase ?>strana=<?= $p ?>"><?= $p ?></a>
            <?php endif; ?>
          </li>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
          <li><a href="<?= $paginBase ?>strana=<?= $page + 1 ?>" rel="next">Starší <span aria-hidden="true">›</span></a></li>
        <?php endif; ?>
      </ul>
    </nav>
    <?php endif; ?>

  <?php endif; ?>

</main>

<?= siteFooter() ?>
</body>
</html>
