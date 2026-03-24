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

$episodesStmt = $pdo->prepare(
    "SELECT p.*, s.slug AS show_slug, s.title AS show_title
     FROM cms_podcasts p
     INNER JOIN cms_podcast_shows s ON s.id = p.show_id
     WHERE p.show_id = ?
       AND p.status = 'published'
       AND (p.publish_at IS NULL OR p.publish_at <= NOW())
     ORDER BY COALESCE(p.publish_at, p.created_at) DESC, COALESCE(p.episode_num, 0) DESC, p.id DESC
     LIMIT 100"
);
$episodesStmt->execute([(int)$show['id']]);
$episodes = array_map(
    static fn(array $episode): array => hydratePodcastEpisodePresentation($episode),
    $episodesStmt->fetchAll()
);

$showUrl = $show['website_url'] !== '' ? (string)$show['website_url'] : $show['public_url'];
$selfUrl = siteUrl('/podcast/feed.php?slug=' . rawurlencode((string)$show['slug']));
$coverUrl = (string)($show['cover_image'] !== ''
    ? siteUrl('/uploads/podcasts/covers/' . rawurlencode((string)$show['cover_image']))
    : '');
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
    <language><?= htmlspecialchars((string)($show['language'] ?: 'cs'), ENT_XML1, 'UTF-8') ?></language>
    <lastBuildDate><?= $buildDate ?></lastBuildDate>
    <atom:link href="<?= htmlspecialchars($selfUrl, ENT_XML1, 'UTF-8') ?>" rel="self" type="application/rss+xml"/>
<?php if (!empty($show['author'])): ?>
    <itunes:author><?= htmlspecialchars((string)$show['author'], ENT_XML1, 'UTF-8') ?></itunes:author>
    <managingEditor><?= htmlspecialchars((string)$show['author'], ENT_XML1, 'UTF-8') ?></managingEditor>
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
    <itunes:explicit>no</itunes:explicit>
<?php foreach ($episodes as $episode):
    $audioSrc = '';
    $audioType = 'audio/mpeg';
    if ((string)$episode['audio_file'] !== '') {
        $audioSrc = siteUrl('/uploads/podcasts/' . rawurlencode((string)$episode['audio_file']));
        $extension = strtolower(pathinfo((string)$episode['audio_file'], PATHINFO_EXTENSION));
        $audioType = match ($extension) {
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'aac' => 'audio/aac',
            'm4a' => 'audio/mp4',
            default => 'audio/mpeg',
        };
    } elseif ((string)$episode['audio_url'] !== '') {
        $audioSrc = (string)$episode['audio_url'];
    }

    $pubDateSource = (string)($episode['display_date'] ?? $episode['created_at'] ?? 'now');
    $pubDate = date(DATE_RSS, strtotime($pubDateSource));
    $itemLink = (string)$episode['public_url'];
    $description = podcastEpisodeExcerpt($episode, 400);
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
<?php if ($audioSrc !== ''): ?>
      <enclosure url="<?= htmlspecialchars($audioSrc, ENT_XML1, 'UTF-8') ?>"
                 type="<?= htmlspecialchars($audioType, ENT_XML1, 'UTF-8') ?>"
                 length="0"/>
<?php endif; ?>
<?php if (!empty($episode['duration'])): ?>
      <itunes:duration><?= htmlspecialchars((string)$episode['duration'], ENT_XML1, 'UTF-8') ?></itunes:duration>
<?php endif; ?>
<?php if (!empty($episode['episode_num'])): ?>
      <itunes:episode><?= (int)$episode['episode_num'] ?></itunes:episode>
<?php endif; ?>
<?php if (!empty($show['author'])): ?>
      <itunes:author><?= htmlspecialchars((string)$show['author'], ENT_XML1, 'UTF-8') ?></itunes:author>
<?php endif; ?>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
