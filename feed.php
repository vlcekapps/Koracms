<?php
require_once __DIR__ . '/db.php';

$isHeadRequest = requireReadOnlyHttpMethod();

$siteName = getSetting('site_name', 'Kora CMS');
$siteDesc = getSetting('site_description', '');
$pdo = db_connect();

$feedBlogSlug = trim((string)($_GET['blog'] ?? ''));
$feedCategoryInput = trim((string)($_GET['category'] ?? ''));
$feedTagInput = trim((string)($_GET['tag'] ?? ''));
$feedCategorySlug = blogCategorySlug($feedCategoryInput);
$feedTagSlug = blogTagSlug($feedTagInput);
$feedBlog = null;
$feedBlogId = null;
$feedCategory = null;
$feedTag = null;

$hasInvalidTaxonomyInput = ($feedCategoryInput !== '' && $feedCategorySlug === '')
    || ($feedTagInput !== '' && $feedTagSlug === '')
    || ($feedCategorySlug !== '' && $feedTagSlug !== '')
    || (($feedCategorySlug !== '' || $feedTagSlug !== '') && $feedBlogSlug === '');
if ($hasInvalidTaxonomyInput) {
    sendReadOnlyNotFoundResponse('Požadovaný RSS kanál nebyl nalezen.', $isHeadRequest);
}

if ($feedBlogSlug !== '') {
    $feedBlog = getBlogBySlug($feedBlogSlug);
    if (!$feedBlog) {
        $legacyBlog = getBlogByLegacySlug($feedBlogSlug);
        if ($legacyBlog) {
            header(
                'Location: ' . blogFeedPath($legacyBlog, $feedCategorySlug, $feedTagSlug),
                true,
                301
            );
            exit;
        }

        sendReadOnlyNotFoundResponse('Blog nebyl nalezen.', $isHeadRequest);
    }

    $feedBlogId = (int)$feedBlog['id'];
}

if ($feedBlogId !== null && $feedCategorySlug !== '') {
    $categoryStmt = $pdo->prepare(
        'SELECT id, name, slug, description, meta_title, meta_description
         FROM cms_categories
         WHERE blog_id = ? AND slug = ?
         LIMIT 1'
    );
    $categoryStmt->execute([$feedBlogId, $feedCategorySlug]);
    $feedCategory = $categoryStmt->fetch();
    if (!$feedCategory) {
        sendReadOnlyNotFoundResponse('Kategorie nebyla nalezena.', $isHeadRequest);
    }
}

if ($feedBlogId !== null && $feedTagSlug !== '') {
    $tagStmt = $pdo->prepare(
        'SELECT id, name, slug, description, meta_title, meta_description
         FROM cms_tags
         WHERE blog_id = ? AND slug = ?
         LIMIT 1'
    );
    $tagStmt->execute([$feedBlogId, $feedTagSlug]);
    $feedTag = $tagStmt->fetch();
    if (!$feedTag) {
        sendReadOnlyNotFoundResponse('Štítek nebyl nalezen.', $isHeadRequest);
    }
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

if (is_array($feedCategory)) {
    $channelTitle = 'Kategorie ' . (string)$feedCategory['name'] . ' – ' . (string)$feedBlog['name'];
    $channelLink = blogCategoryUrl($feedBlog, $feedCategory);
    $taxonomyDescription = trim((string)($feedCategory['meta_description'] ?? ''));
    if ($taxonomyDescription === '') {
        $taxonomyDescription = normalizePlainText((string)($feedCategory['description'] ?? ''));
    }
    if ($taxonomyDescription !== '') {
        $channelDescription = $taxonomyDescription;
    }
    $selfUrl = blogCategoryFeedUrl($feedBlog, $feedCategory);
} elseif (is_array($feedTag)) {
    $channelTitle = 'Štítek #' . (string)$feedTag['name'] . ' – ' . (string)$feedBlog['name'];
    $channelLink = blogTagUrl($feedBlog, $feedTag);
    $taxonomyDescription = trim((string)($feedTag['meta_description'] ?? ''));
    if ($taxonomyDescription === '') {
        $taxonomyDescription = normalizePlainText((string)($feedTag['description'] ?? ''));
    }
    if ($taxonomyDescription !== '') {
        $channelDescription = $taxonomyDescription;
    }
    $selfUrl = blogTagFeedUrl($feedBlog, $feedTag);
}
sendContentTypeNoSniffHeaders('application/rss+xml; charset=UTF-8');
if ($isHeadRequest) {
    exit;
}

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
    $articleWhere = [
        "a.status = 'published'",
        'a.deleted_at IS NULL',
        '(a.publish_at IS NULL OR a.publish_at <= NOW())',
    ];
    $articleParams = [];

    if ($feedBlogId !== null) {
        $articleWhere[] = 'a.blog_id = ?';
        $articleParams[] = $feedBlogId;
    }

    if (is_array($feedCategory)) {
        $categoryIds = categoryWithChildrenIds($pdo, (int)$feedCategory['id']);
        if ($categoryIds === []) {
            $categoryIds = [(int)$feedCategory['id']];
        }
        $categoryPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $articleWhere[] = "a.category_id IN ({$categoryPlaceholders})";
        foreach ($categoryIds as $categoryId) {
            $articleParams[] = $categoryId;
        }
    } elseif (is_array($feedTag)) {
        $articleWhere[] = 'EXISTS (
            SELECT 1
            FROM cms_article_tags at2
            WHERE at2.article_id = a.id AND at2.tag_id = ?
        )';
        $articleParams[] = (int)$feedTag['id'];
    }

$articleStmt = $pdo->prepare(
    "SELECT a.id, a.title, a.slug, a.perex, a.content, a.created_at, a.publish_at, b.slug AS blog_slug
         FROM cms_articles a
         LEFT JOIN cms_blogs b ON b.id = a.blog_id
         WHERE " . implode(' AND ', $articleWhere) . "
         ORDER BY COALESCE(a.publish_at, a.created_at) DESC, a.id DESC
         LIMIT ?"
);
$articleParams[] = $feedItemLimit;
$articleStmt->execute($articleParams);
$articles = $articleStmt->fetchAll();
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
