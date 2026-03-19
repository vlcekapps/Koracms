<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/rss+xml; charset=UTF-8');

$siteName = getSetting('site_name', 'Kora CMS');
$siteDesc = getSetting('site_description', '');
$base     = BASE_URL;
$pdo      = db_connect();

echo '<?xml version="1.0" encoding="UTF-8"?>';
?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= h($siteName) ?></title>
  <link><?= h($base) ?>/index.php</link>
  <description><?= h($siteDesc !== '' ? $siteDesc : $siteName) ?></description>
  <language>cs</language>
  <atom:link href="<?= h($base) ?>/feed.php" rel="self" type="application/rss+xml"/>

<?php if (isModuleEnabled('blog')):
    $articles = $pdo->query(
        "SELECT id, title, perex, created_at FROM cms_articles
         WHERE publish_at IS NULL OR publish_at <= NOW()
         ORDER BY created_at DESC LIMIT 20"
    )->fetchAll();
    foreach ($articles as $a): ?>
  <item>
    <title><?= htmlspecialchars($a['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></title>
    <link><?= h($base) ?>/blog/article.php?id=<?= (int)$a['id'] ?></link>
    <guid isPermaLink="true"><?= h($base) ?>/blog/article.php?id=<?= (int)$a['id'] ?></guid>
    <pubDate><?= date('r', strtotime($a['created_at'])) ?></pubDate>
    <?php if (!empty($a['perex'])): ?>
    <description><?= htmlspecialchars($a['perex'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></description>
    <?php endif; ?>
  </item>
<?php endforeach; endif; ?>

<?php if (isModuleEnabled('news')):
    $newsItems = $pdo->query(
        "SELECT id, content, created_at FROM cms_news ORDER BY created_at DESC LIMIT 20"
    )->fetchAll();
    foreach ($newsItems as $n): ?>
  <item>
    <title><?= h($siteName) ?> – novinka <?= date('j.n.Y', strtotime($n['created_at'])) ?></title>
    <link><?= h($base) ?>/news/index.php</link>
    <guid isPermaLink="false"><?= h($base) ?>/news/item/<?= (int)$n['id'] ?></guid>
    <pubDate><?= date('r', strtotime($n['created_at'])) ?></pubDate>
    <description><?= htmlspecialchars(mb_substr($n['content'], 0, 300), ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></description>
  </item>
<?php endforeach; endif; ?>

</channel>
</rss>
