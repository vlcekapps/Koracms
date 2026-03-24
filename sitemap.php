<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/xml; charset=UTF-8');

$pdo  = db_connect();

echo '<?xml version="1.0" encoding="UTF-8"?>';
?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= h(siteUrl('/index.php')) ?></loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

<?php
// Statické stránky
try {
    $pages = $pdo->query(
        "SELECT slug, updated_at FROM cms_pages WHERE is_published = 1 ORDER BY nav_order, title"
    )->fetchAll();
    foreach ($pages as $p):
?>
  <url>
    <loc><?= h(siteUrl('/page.php?slug=' . rawurlencode($p['slug']))) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($p['updated_at'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
<?php endforeach; } catch (\PDOException $e) {} ?>

<?php if (isModuleEnabled('blog')): ?>
  <url>
    <loc><?= h(siteUrl('/blog/index.php')) ?></loc>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
<?php
    try {
        $articles = $pdo->query(
            "SELECT id, slug, updated_at FROM cms_articles
             WHERE status = 'published' AND (publish_at IS NULL OR publish_at <= NOW())
             ORDER BY created_at DESC"
        )->fetchAll();
        foreach ($articles as $a):
?>
  <url>
    <loc><?= h(articlePublicUrl($a)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($a['updated_at'])) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
<?php endforeach; } catch (\PDOException $e) {}
endif; ?>

<?php if (isModuleEnabled('news')): ?>
  <url>
    <loc><?= h(siteUrl('/news/index.php')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
<?php endif; ?>

<?php if (isModuleEnabled('gallery')): ?>
  <url>
    <loc><?= h(siteUrl('/gallery/index.php')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
<?php endif; ?>

</urlset>
