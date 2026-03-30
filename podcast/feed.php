<?php
require_once __DIR__ . '/../db.php';

if (!isModuleEnabled('podcast')) {
    http_response_code(404);
    exit;
}

$pdo = db_connect();
$slug = podcastShowSlug(trim((string)($_GET['slug'] ?? '')));
if ($slug === '') {
    http_response_code(400);
    exit;
}

$showStmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE slug = ? LIMIT 1");
$showStmt->execute([$slug]);
$show = $showStmt->fetch() ?: null;
if (!$show) {
    http_response_code(404);
    exit;
}
$show = hydratePodcastShowPresentation($show);
if (empty($show['is_public']) && !currentUserHasCapability('content_manage_shared')) {
    http_response_code(404);
    exit;
}
$feedEpisodeLimit = (int)$show['feed_episode_limit'];

$episodesStmt = $pdo->prepare(
    "SELECT p.*, s.slug AS show_slug, s.title AS show_title, s.cover_image AS show_cover_image
     FROM cms_podcasts p
     INNER JOIN cms_podcast_shows s ON s.id = p.show_id
     WHERE p.show_id = ?
       AND COALESCE(p.block_from_feed, 0) = 0
       AND " . podcastEpisodePublicVisibilitySql('p') . "
     ORDER BY COALESCE(p.publish_at, p.created_at) DESC, COALESCE(p.episode_num, 0) DESC, p.id DESC
     LIMIT {$feedEpisodeLimit}"
);
$episodesStmt->execute([(int)$show['id']]);
$episodes = array_map(
    static fn(array $episode): array => hydratePodcastEpisodePresentation($episode),
    $episodesStmt->fetchAll()
);

$showUrl = $show['website_url'] !== '' ? (string)$show['website_url'] : $show['public_url'];
$selfUrl = siteUrl('/podcast/feed.php?slug=' . rawurlencode((string)$show['slug']));
$coverUrl = (string)($show['cover_url'] !== ''
    ? siteUrl(str_starts_with((string)$show['cover_url'], BASE_URL) ? substr((string)$show['cover_url'], strlen(BASE_URL)) : (string)$show['cover_url'])
    : '');
$showSubtitle = (string)($show['feed_subtitle'] ?? '');
$showSummary = (string)($show['feed_summary'] ?? '');
$buildDateSource = $episodes[0]['display_date'] ?? ($show['updated_at'] ?? $show['created_at'] ?? 'now');
$buildDate = date(DATE_RSS, strtotime((string)$buildDateSource));

header('Content-Type: application/rss+xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0"
     xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><?= htmlspecialchars((string)$show['title'], ENT_XML1, 'UTF-8') ?></title>
    <link><?= htmlspecialchars($showUrl, ENT_XML1, 'UTF-8') ?></link>
    <description><?= htmlspecialchars((string)($show['description_plain'] ?? ''), ENT_XML1, 'UTF-8') ?></description>
<?php if ($showSummary !== ''): ?>
    <itunes:summary><?= htmlspecialchars($showSummary, ENT_XML1, 'UTF-8') ?></itunes:summary>
<?php endif; ?>
<?php if ($showSubtitle !== ''): ?>
    <itunes:subtitle><?= htmlspecialchars($showSubtitle, ENT_XML1, 'UTF-8') ?></itunes:subtitle>
<?php endif; ?>
    <language><?= htmlspecialchars((string)($show['language'] ?: 'cs'), ENT_XML1, 'UTF-8') ?></language>
    <lastBuildDate><?= $buildDate ?></lastBuildDate>
    <atom:link href="<?= htmlspecialchars($selfUrl, ENT_XML1, 'UTF-8') ?>" rel="self" type="application/rss+xml"/>
<?php if (!empty($show['author'])): ?>
    <itunes:author><?= htmlspecialchars((string)$show['author'], ENT_XML1, 'UTF-8') ?></itunes:author>
<?php endif; ?>
<?php if (podcastFeedManagingEditor($show) !== ''): ?>
    <managingEditor><?= htmlspecialchars(podcastFeedManagingEditor($show), ENT_XML1, 'UTF-8') ?></managingEditor>
<?php endif; ?>
<?php if (!empty($show['owner_name']) || !empty($show['owner_email'])): ?>
    <itunes:owner>
<?php if (!empty($show['owner_name'])): ?>
      <itunes:name><?= htmlspecialchars((string)$show['owner_name'], ENT_XML1, 'UTF-8') ?></itunes:name>
<?php endif; ?>
<?php if (!empty($show['owner_email'])): ?>
      <itunes:email><?= htmlspecialchars((string)$show['owner_email'], ENT_XML1, 'UTF-8') ?></itunes:email>
<?php endif; ?>
    </itunes:owner>
<?php endif; ?>
<?php if (!empty($show['category'])): ?>
    <itunes:category text="<?= htmlspecialchars((string)$show['category'], ENT_XML1, 'UTF-8') ?>"/>
<?php endif; ?>
<?php if ($coverUrl !== ''): ?>
    <image>
      <url><?= htmlspecialchars($coverUrl, ENT_XML1, 'UTF-8') ?></url>
      <title><?= htmlspecialchars((string)$show['title'], ENT_XML1, 'UTF-8') ?></title>
      <link><?= htmlspecialchars($showUrl, ENT_XML1, 'UTF-8') ?></link>
    </image>
    <itunes:image href="<?= htmlspecialchars($coverUrl, ENT_XML1, 'UTF-8') ?>"/>
<?php endif; ?>
    <itunes:explicit><?= htmlspecialchars((string)$show['explicit_mode'], ENT_XML1, 'UTF-8') ?></itunes:explicit>
    <itunes:type><?= htmlspecialchars((string)$show['show_type'], ENT_XML1, 'UTF-8') ?></itunes:type>
<?php if (!empty($show['feed_complete'])): ?>
    <itunes:complete>yes</itunes:complete>
<?php endif; ?>
<?php foreach ($episodes as $episode):
    $audioSrc = '';
    $audioType = 'audio/mpeg';
    $enclosureLength = 0;
    if ((string)$episode['audio_file'] !== '') {
        $audioSrc = siteUrl(str_starts_with((string)$episode['audio_src'], BASE_URL) ? substr((string)$episode['audio_src'], strlen(BASE_URL)) : (string)$episode['audio_src']);
        $audioType = podcastAudioMimeType((string)$episode['audio_file']);
        $enclosureLength = podcastEpisodeEnclosureLength($episode);
    } elseif ((string)$episode['audio_url'] !== '') {
        $audioSrc = (string)$episode['audio_url'];
    }

    $pubDateSource = (string)($episode['display_date'] ?? $episode['created_at'] ?? 'now');
    $pubDate = date(DATE_RSS, strtotime($pubDateSource));
    $itemLink = (string)$episode['public_url'];
    $description = podcastEpisodeExcerpt($episode, 400);
    $itemSummary = (string)($episode['feed_summary'] ?? '');
    $itemSubtitle = (string)($episode['feed_subtitle'] ?? '');
    $itemExplicit = (string)($episode['explicit_mode'] === 'inherit' ? $show['explicit_mode'] : $episode['explicit_mode']);
    $episodeImageUrl = (string)(
        !empty($episode['image_url'])
            ? siteUrl(str_starts_with((string)$episode['image_url'], BASE_URL) ? substr((string)$episode['image_url'], strlen(BASE_URL)) : (string)$episode['image_url'])
            : $coverUrl
    );
?>
    <item>
      <title><?= htmlspecialchars((string)$episode['title'], ENT_XML1, 'UTF-8') ?></title>
      <link><?= htmlspecialchars($itemLink, ENT_XML1, 'UTF-8') ?></link>
      <guid isPermaLink="true"><?= htmlspecialchars($itemLink, ENT_XML1, 'UTF-8') ?></guid>
      <pubDate><?= $pubDate ?></pubDate>
      <description><?= htmlspecialchars($description, ENT_XML1, 'UTF-8') ?></description>
<?php if (!empty($episode['description'])): ?>
      <content:encoded><![CDATA[<?= (string)$episode['description'] ?>]]></content:encoded>
<?php endif; ?>
<?php if ($itemSummary !== ''): ?>
      <itunes:summary><?= htmlspecialchars($itemSummary, ENT_XML1, 'UTF-8') ?></itunes:summary>
<?php endif; ?>
<?php if ($itemSubtitle !== ''): ?>
      <itunes:subtitle><?= htmlspecialchars($itemSubtitle, ENT_XML1, 'UTF-8') ?></itunes:subtitle>
<?php endif; ?>
<?php if ($audioSrc !== ''): ?>
      <enclosure url="<?= htmlspecialchars($audioSrc, ENT_XML1, 'UTF-8') ?>"
                 type="<?= htmlspecialchars($audioType, ENT_XML1, 'UTF-8') ?>"
                 length="<?= $enclosureLength > 0 ? $enclosureLength : 0 ?>"/>
<?php endif; ?>
<?php if ($episodeImageUrl !== ''): ?>
      <itunes:image href="<?= htmlspecialchars($episodeImageUrl, ENT_XML1, 'UTF-8') ?>"/>
<?php endif; ?>
      <itunes:explicit><?= htmlspecialchars($itemExplicit, ENT_XML1, 'UTF-8') ?></itunes:explicit>
<?php if (!empty($episode['duration'])): ?>
      <itunes:duration><?= htmlspecialchars((string)$episode['duration'], ENT_XML1, 'UTF-8') ?></itunes:duration>
<?php endif; ?>
<?php if (!empty($episode['episode_num'])): ?>
      <itunes:episode><?= (int)$episode['episode_num'] ?></itunes:episode>
<?php endif; ?>
<?php if (!empty($episode['season_num'])): ?>
      <itunes:season><?= (int)$episode['season_num'] ?></itunes:season>
<?php endif; ?>
<?php if (!empty($episode['episode_type'])): ?>
      <itunes:episodeType><?= htmlspecialchars((string)$episode['episode_type'], ENT_XML1, 'UTF-8') ?></itunes:episodeType>
<?php endif; ?>
<?php if (!empty($show['author'])): ?>
      <itunes:author><?= htmlspecialchars((string)$show['author'], ENT_XML1, 'UTF-8') ?></itunes:author>
<?php endif; ?>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
