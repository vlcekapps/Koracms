<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/rss+xml; charset=UTF-8');

$siteName = getSetting('site_name', 'Kora CMS');
$siteDesc = getSetting('site_description', '');
$pdo = db_connect();

$feedBlogSlug = trim((string)($_GET['blog'] ?? ''));
$feedBlog = null;
$feedBlogId = null;

if ($feedBlogSlug !== '') {
    $feedBlog = getBlogBySlug($feedBlogSlug);
    if (!$feedBlog) {
        $legacyBlog = getBlogByLegacySlug($feedBlogSlug);
        if ($legacyBlog) {
            header('Location: ' . blogFeedPath($legacyBlog), true, 301);
            exit;
        }

        http_response_code(404);
        exit;
    }

    $feedBlogId = (int)$feedBlog['id'];
}

$isBlogFeed = $feedBlogId !== null;
$feedItemLimit = $isBlogFeed
    ? max(1, min(100, (int)($feedBlog['feed_item_limit'] ?? 20)))
    : 20;
$channelTitle = $isBlogFeed
    ? trim((string)($feedBlog['meta_title'] ?? '')) ?: ((string)$feedBlog['name'] . ' – ' . $siteName)
    : $siteName;
$channelLink = $isBlogFeed ? blogIndexUrl($feedBlog) : siteUrl('/index.php');
$channelDescription = $isBlogFeed
    ? trim((string)($feedBlog['rss_subtitle'] ?? ''))
    : trim((string)$siteDesc);
if ($channelDescription === '') {
    $channelDescription = $isBlogFeed
        ? trim((string)($feedBlog['meta_description'] ?? ''))
        : trim((string)$siteDesc);
}
if ($channelDescription === '') {
    $channelDescription = $isBlogFeed
        ? trim((string)($feedBlog['description'] ?? ''))
        : $channelTitle;
}
if ($channelDescription === '') {
    $channelDescription = $channelTitle;
}
$selfUrl = $isBlogFeed ? blogFeedUrl($feedBlog) : siteUrl('/feed.php');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= h($channelTitle) ?></title>
  <link><?= h($channelLink) ?></link>
  <description><?= h($channelDescription) ?></description>
  <language>cs</language>
  <atom:link href="<?= h($selfUrl) ?>" rel="self" type="application/rss+xml"/>

<?php if (isModuleEnabled('blog')): ?>
<?php
    if ($feedBlogId !== null) {
        $articleStmt = $pdo->prepare(
            "SELECT a.id, a.title, a.slug, a.perex, a.content, a.created_at, a.publish_at, b.slug AS blog_slug
             FROM cms_articles a
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             WHERE a.status = 'published'
               AND a.deleted_at IS NULL
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
               AND a.blog_id = ?
             ORDER BY COALESCE(a.publish_at, a.created_at) DESC, a.id DESC
             LIMIT ?"
        );
        $articleStmt->execute([$feedBlogId, $feedItemLimit]);
        $articles = $articleStmt->fetchAll();
    } else {
        $articleStmt = $pdo->prepare(
            "SELECT a.id, a.title, a.slug, a.perex, a.content, a.created_at, a.publish_at, b.slug AS blog_slug
             FROM cms_articles a
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             WHERE a.status = 'published'
               AND a.deleted_at IS NULL
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             ORDER BY COALESCE(a.publish_at, a.created_at) DESC, a.id DESC
             LIMIT ?"
        );
        $articleStmt->execute([$feedItemLimit]);
        $articles = $articleStmt->fetchAll();
    }
?>
<?php foreach ($articles as $article): ?>
  <item>
    <title><?= htmlspecialchars((string)$article['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></title>
    <link><?= h(articlePublicUrl($article)) ?></link>
    <guid isPermaLink="true"><?= h(articlePublicUrl($article)) ?></guid>
    <pubDate><?= date('r', strtotime((string)($article['publish_at'] ?? $article['created_at']))) ?></pubDate>
    <?php $articleDescription = trim((string)($article['perex'] ?? '')) !== '' ? (string)$article['perex'] : articleExcerpt((string)($article['content'] ?? ''), 300); ?>
    <?php if ($articleDescription !== ''): ?>
    <description><?= htmlspecialchars($articleDescription, ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></description>
    <?php endif; ?>
  </item>
<?php endforeach; ?>
<?php endif; ?>

<?php if (!$isBlogFeed && isModuleEnabled('news')): ?>
<?php
    $newsStmt = $pdo->prepare(
        "SELECT id, title, slug, content, created_at
         FROM cms_news
         WHERE " . newsPublicVisibilitySql() . "
         ORDER BY created_at DESC, id DESC
         LIMIT ?"
    );
    $newsStmt->execute([20]);
    $newsItems = $newsStmt->fetchAll();
?>
<?php foreach ($newsItems as $newsItem): ?>
  <item>
    <title><?= htmlspecialchars(newsTitleCandidate((string)($newsItem['title'] ?? ''), (string)($newsItem['content'] ?? '')), ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></title>
    <link><?= h(newsPublicUrl($newsItem)) ?></link>
    <guid isPermaLink="true"><?= h(newsPublicUrl($newsItem)) ?></guid>
    <pubDate><?= date('r', strtotime((string)$newsItem['created_at'])) ?></pubDate>
    <description><?= htmlspecialchars(newsExcerpt((string)$newsItem['content'], 300), ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></description>
  </item>
<?php endforeach; ?>
<?php endif; ?>

</channel>
</rss>
