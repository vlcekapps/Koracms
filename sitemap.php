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

<?php if (isModuleEnabled('faq')): ?>
  <url>
    <loc><?= h(siteUrl('/faq/index.php')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
<?php
    try {
        $faqs = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_faqs
             WHERE COALESCE(status,'published') = 'published' AND is_published = 1
             ORDER BY sort_order, id"
        )->fetchAll();
        foreach ($faqs as $faq):
?>
  <url>
    <loc><?= h(faqPublicUrl($faq)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime((string)$faq['sitemap_lastmod'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
<?php endforeach; } catch (\PDOException $e) {}
endif; ?>

<?php if (isModuleEnabled('food')): ?>
  <url>
    <loc><?= h(siteUrl('/food/index.php')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
  <url>
    <loc><?= h(siteUrl('/food/archive.php')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.5</priority>
  </url>
<?php
    try {
        $foodCards = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at, valid_from) AS sitemap_lastmod
             FROM cms_food_cards
             WHERE status = 'published' AND is_published = 1
             ORDER BY COALESCE(valid_from, created_at) DESC, id DESC"
        )->fetchAll();
        foreach ($foodCards as $foodCard):
?>
  <url>
    <loc><?= h(foodCardPublicUrl($foodCard)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime((string)$foodCard['sitemap_lastmod'])) ?></lastmod>
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

<?php if (isModuleEnabled('podcast')): ?>
  <url>
    <loc><?= h(siteUrl('/podcast/index.php')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
<?php
    try {
        $podcastShows = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_podcast_shows
             ORDER BY updated_at DESC, title ASC"
        )->fetchAll();
        foreach ($podcastShows as $podcastShow):
?>
  <url>
    <loc><?= h(podcastShowPublicUrl($podcastShow)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime((string)$podcastShow['sitemap_lastmod'])) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.5</priority>
  </url>
<?php endforeach; } catch (\PDOException $e) {}

    try {
        $podcastEpisodes = $pdo->query(
            "SELECT p.id, p.slug, s.slug AS show_slug,
                    COALESCE(p.publish_at, p.updated_at, p.created_at) AS sitemap_lastmod
             FROM cms_podcasts p
             INNER JOIN cms_podcast_shows s ON s.id = p.show_id
             WHERE p.status = 'published' AND (p.publish_at IS NULL OR p.publish_at <= NOW())
             ORDER BY COALESCE(p.publish_at, p.created_at) DESC, p.id DESC"
        )->fetchAll();
        foreach ($podcastEpisodes as $podcastEpisode):
?>
  <url>
    <loc><?= h(podcastEpisodePublicUrl($podcastEpisode)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime((string)$podcastEpisode['sitemap_lastmod'])) ?></lastmod>
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
<?php
    try {
        $galleryAlbums = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_gallery_albums
             ORDER BY updated_at DESC, id DESC"
        )->fetchAll();
        foreach ($galleryAlbums as $galleryAlbum):
?>
  <url>
    <loc><?= h(galleryAlbumPublicUrl($galleryAlbum)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime((string)$galleryAlbum['sitemap_lastmod'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
<?php endforeach; } catch (\PDOException $e) {}

    try {
        $galleryPhotos = $pdo->query(
            "SELECT id, slug, created_at AS sitemap_lastmod
             FROM cms_gallery_photos
             ORDER BY created_at DESC, id DESC"
        )->fetchAll();
        foreach ($galleryPhotos as $galleryPhoto):
?>
  <url>
    <loc><?= h(galleryPhotoPublicUrl($galleryPhoto)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime((string)$galleryPhoto['sitemap_lastmod'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.4</priority>
  </url>
<?php endforeach; } catch (\PDOException $e) {}
endif; ?>

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

<?php if (isModuleEnabled('polls')): ?>
  <url>
    <loc><?= h(siteUrl('/polls/index.php')) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
<?php
    try {
        $polls = $pdo->query(
            "SELECT id, slug, COALESCE(updated_at, created_at) AS sitemap_lastmod
             FROM cms_polls
             WHERE (
                    (status = 'active' AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date > NOW()))
                    OR status = 'closed'
                    OR (end_date IS NOT NULL AND end_date <= NOW())
                  )
             ORDER BY COALESCE(start_date, created_at) DESC, id DESC"
        )->fetchAll();
        foreach ($polls as $poll):
?>
  <url>
    <loc><?= h(pollPublicUrl($poll)) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime((string)$poll['sitemap_lastmod'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
<?php endforeach; } catch (\PDOException $e) {}
endif; ?>

</urlset>
