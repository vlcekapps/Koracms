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
<?php
    try {
        $newsItems = $pdo->query(
            "SELECT id, slug, updated_at
             FROM cms_news
             WHERE status = 'published'
             ORDER BY created_at DESC"
        )->fetchAll();
        foreach ($newsItems as $newsItem):
?>
  <url>
    <loc><?= h(newsPublicUrl($newsItem)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime((string)$newsItem['updated_at'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
<?php endforeach; } catch (\PDOException $e) {}
endif; ?>

<?php if (isModuleEnabled('board')): ?>
  <url>
    <loc><?= h(siteUrl('/board/index.php')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
<?php
    try {
        $documents = $pdo->query(
            "SELECT id, slug, COALESCE(created_at, CONCAT(posted_date, ' 00:00:00')) AS sitemap_lastmod
             FROM cms_board
             WHERE status = 'published' AND is_published = 1
             ORDER BY posted_date DESC, id DESC"
        )->fetchAll();
        foreach ($documents as $document):
?>
  <url>
    <loc><?= h(boardPublicUrl($document)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime((string)$document['sitemap_lastmod'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
<?php endforeach; } catch (\PDOException $e) {}
endif; ?>

<?php if (isModuleEnabled('downloads')): ?>
  <url>
    <loc><?= h(siteUrl('/downloads/index.php')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
<?php
    try {
        $downloads = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_downloads
             WHERE status = 'published' AND is_published = 1
             ORDER BY sort_order, title"
        )->fetchAll();
        foreach ($downloads as $download):
?>
  <url>
    <loc><?= h(downloadPublicUrl($download)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime((string)$download['sitemap_lastmod'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
<?php endforeach; } catch (\PDOException $e) {}
endif; ?>

<?php if (isModuleEnabled('events')): ?>
  <url>
    <loc><?= h(siteUrl('/events/index.php')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
<?php
    try {
        $events = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at, event_date) AS sitemap_lastmod
             FROM cms_events
             WHERE status = 'published' AND is_published = 1
             ORDER BY event_date DESC"
        )->fetchAll();
        foreach ($events as $event):
?>
  <url>
    <loc><?= h(eventPublicUrl($event)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime((string)$event['sitemap_lastmod'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
<?php endforeach; } catch (\PDOException $e) {}
endif; ?>

<?php if (isModuleEnabled('gallery')): ?>
  <url>
    <loc><?= h(siteUrl('/gallery/index.php')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
<?php endif; ?>

<?php if (isModuleEnabled('places')): ?>
  <url>
    <loc><?= h(siteUrl('/places/index.php')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
<?php
    try {
        $places = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_places
             WHERE status = 'published' AND is_published = 1
             ORDER BY sort_order, name"
        )->fetchAll();
        foreach ($places as $place):
?>
  <url>
    <loc><?= h(placePublicUrl($place)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime((string)$place['sitemap_lastmod'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
<?php endforeach; } catch (\PDOException $e) {}
endif; ?>

</urlset>
