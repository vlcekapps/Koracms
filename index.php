<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$siteName = getSetting('site_name', 'Kora CMS');
$siteDesc = getSetting('site_description', '');

// Poslední novinky
$latestNews = [];
$homeNewsCount = (int)getSetting('home_news_count', '5');
if (isModuleEnabled('news') && $homeNewsCount > 0) {
    $stmt = db_connect()->prepare(
        "SELECT id, content, created_at FROM cms_news WHERE status = 'published' ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->execute([$homeNewsCount]);
    $latestNews = $stmt->fetchAll();
}

// Poslední články blogu
$latestArticles = [];
$homeBlogCount = (int)getSetting('home_blog_count', '5');
if (isModuleEnabled('blog') && $homeBlogCount > 0) {
    $stmt = db_connect()->prepare(
        "SELECT a.id, a.title, a.perex, a.image_file, a.created_at, c.name AS category,
                COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'')) AS author_name
         FROM cms_articles a
         LEFT JOIN cms_categories c ON c.id = a.category_id
         LEFT JOIN cms_users u ON u.id = a.author_id
         WHERE a.status = 'published' AND (a.publish_at IS NULL OR a.publish_at <= NOW())
         ORDER BY a.created_at DESC LIMIT ?"
    );
    $stmt->execute([$homeBlogCount]);
    $latestArticles = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['url' => BASE_URL . '/index.php']) ?>
  <title><?= h($siteName) ?></title>
  <style>
    .skip-link { position: absolute; left: -9999px; }
    .skip-link:focus { left: 1rem; top: 1rem; z-index: 9999;
      background: #fff; padding: .5rem 1rem; border: 2px solid #000; }
  </style>
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>

<header>
  <?php $logo = getSetting('site_logo', ''); ?>
  <?php if ($logo !== ''): ?>
    <img src="<?= BASE_URL ?>/uploads/site/<?= h($logo) ?>" alt="<?= h($siteName) ?>"
         style="max-height:80px" loading="lazy">
  <?php endif; ?>
  <h1><?= h($siteName) ?></h1>
  <?php if ($siteDesc !== ''): ?><p><?= h($siteDesc) ?></p><?php endif; ?>
  <?= siteNav('home') ?>
</header>

<main id="obsah">

  <?php $homeIntro = getSetting('home_intro', ''); ?>
  <?php if ($homeIntro !== ''): ?>
  <section aria-labelledby="uvod-nadpis">
    <h2 id="uvod-nadpis">Úvodní stránka</h2>
    <?= $homeIntro ?>
  </section>
  <?php endif; ?>

  <?php if (!empty($latestNews)): ?>
  <section aria-labelledby="novinky-nadpis">
    <h2 id="novinky-nadpis">Novinky</h2>
    <?php foreach ($latestNews as $item): ?>
      <article>
        <h3><time datetime="<?= h(str_replace(' ', 'T', $item['created_at'])) ?>"><?= formatCzechDate($item['created_at']) ?></time></h3>
        <p><?= h($item['content']) ?></p>
      </article>
    <?php endforeach; ?>
    <p><a href="<?= BASE_URL ?>/news/index.php">Všechny novinky <span aria-hidden="true">→</span></a></p>
  </section>
  <?php endif; ?>

  <?php if (!empty($latestArticles)): ?>
  <section aria-labelledby="blog-nadpis">
    <h2 id="blog-nadpis">Blog</h2>
    <?php foreach ($latestArticles as $a): ?>
      <article>
        <h3>
          <a href="<?= BASE_URL ?>/blog/article.php?id=<?= (int)$a['id'] ?>"><?= h($a['title']) ?></a>
        </h3>
        <?php if (!empty($a['image_file'])): ?>
          <a href="<?= BASE_URL ?>/blog/article.php?id=<?= (int)$a['id'] ?>">
            <img src="<?= BASE_URL ?>/uploads/articles/thumbs/<?= rawurlencode($a['image_file']) ?>"
                 alt="<?= h($a['title']) ?>" style="max-width:100%;height:auto" loading="lazy">
          </a>
        <?php endif; ?>
        <?php if (!empty($a['category'])): ?>
          <p><small>Kategorie: <?= h($a['category']) ?></small></p>
        <?php endif; ?>
        <?php if (!empty($a['perex'])): ?>
          <p><?= h($a['perex']) ?></p>
        <?php endif; ?>
        <p><a href="<?= BASE_URL ?>/blog/article.php?id=<?= (int)$a['id'] ?>">Číst dále <span aria-hidden="true">→</span></a></p>
      </article>
    <?php endforeach; ?>
    <p><a href="<?= BASE_URL ?>/blog/index.php">Všechny články <span aria-hidden="true">→</span></a></p>
  </section>
  <?php endif; ?>

  <?php if (isModuleEnabled('polls')):
    $pollStmt = db_connect()->prepare(
        "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
         FROM cms_polls p
         WHERE p.status = 'active'
           AND (p.start_date IS NULL OR p.start_date <= NOW())
           AND (p.end_date   IS NULL OR p.end_date > NOW())
         ORDER BY p.created_at DESC LIMIT 1"
    );
    $pollStmt->execute();
    $homePoll = $pollStmt->fetch();
  ?>
    <?php if ($homePoll): ?>
    <section aria-labelledby="nadpis-anketa" style="margin-top:2rem">
      <h2 id="nadpis-anketa">Anketa</h2>
      <p><strong><?= h($homePoll['question']) ?></strong></p>
      <p><?= (int)$homePoll['vote_count'] ?> hlasů</p>
      <p><a href="<?= BASE_URL ?>/polls/index.php?id=<?= (int)$homePoll['id'] ?>">Hlasovat <span aria-hidden="true">→</span></a></p>
    </section>
    <?php endif; ?>
  <?php endif; ?>

</main>

<?= siteFooter() ?>
</body>
</html>
