<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/rss+xml; charset=UTF-8');

$siteName = getSetting('site_name', 'Kora CMS');
$siteDesc = getSetting('site_description', '');
$pdo = db_connect();

echo '<?xml version="1.0" encoding="UTF-8"?>';
?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= h($siteName) ?></title>
  <link><?= h(siteUrl('/index.php')) ?></link>
  <description><?= h($siteDesc !== '' ? $siteDesc : $siteName) ?></description>
  <language>cs</language>
  <atom:link href="<?= h(siteUrl('/feed.php')) ?>" rel="self" type="application/rss+xml"/>

<?php if (isModuleEnabled('blog')):
    $articles = $pdo->query(
        "SELECT id, title, slug, perex, created_at FROM cms_articles
         WHERE status = 'published' AND (publish_at IS NULL OR publish_at <= NOW())
         ORDER BY created_at DESC LIMIT 20"
    )->fetchAll();
    foreach ($articles as $article): ?>
  <item>
    <title><?= htmlspecialchars((string)$article['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></title>
    <link><?= h(articlePublicUrl($article)) ?></link>
    <guid isPermaLink="true"><?= h(articlePublicUrl($article)) ?></guid>
    <pubDate><?= date('r', strtotime((string)$article['created_at'])) ?></pubDate>
    <?php if (!empty($article['perex'])): ?>
    <description><?= htmlspecialchars((string)$article['perex'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></description>
    <?php endif; ?>
  </item>
<?php endforeach; endif; ?>

<?php if (isModuleEnabled('news')):
    $newsItems = $pdo->query(
        "SELECT id, title, slug, content, created_at
         FROM cms_news
         WHERE status = 'published'
         ORDER BY created_at DESC
         LIMIT 20"
    )->fetchAll();
    foreach ($newsItems as $newsItem): ?>
  <item>
    <title><?= htmlspecialchars(newsTitleCandidate((string)($newsItem['title'] ?? ''), (string)($newsItem['content'] ?? '')), ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></title>
    <link><?= h(newsPublicUrl($newsItem)) ?></link>
    <guid isPermaLink="true"><?= h(newsPublicUrl($newsItem)) ?></guid>
    <pubDate><?= date('r', strtotime((string)$newsItem['created_at'])) ?></pubDate>
    <description><?= htmlspecialchars(newsExcerpt((string)$newsItem['content'], 300), ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></description>
  </item>
<?php endforeach; endif; ?>

</channel>
</rss>
