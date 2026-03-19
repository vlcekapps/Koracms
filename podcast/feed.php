<?php
/**
 * RSS 2.0 feed s iTunes namespace pro konkrétní podcast show.
 * URL: /podcast/feed.php?slug=nazev-podcastu
 */
require_once __DIR__ . '/../db.php';

if (!isModuleEnabled('podcast')) {
    http_response_code(404); exit;
}

$pdo  = db_connect();
$slug = trim($_GET['slug'] ?? '');

if ($slug === '') { http_response_code(400); exit; }

$showStmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE slug = ?");
$showStmt->execute([$slug]);
$show = $showStmt->fetch();
if (!$show) { http_response_code(404); exit; }

$episodes = $pdo->prepare(
    "SELECT * FROM cms_podcasts
     WHERE show_id = ? AND status = 'published' AND (publish_at IS NULL OR publish_at <= NOW())
     ORDER BY episode_num DESC, created_at DESC
     LIMIT 100"
);
$episodes->execute([$show['id']]);
$episodes = $episodes->fetchAll();

$siteName  = getSetting('site_name', 'Kora CMS');
$showUrl   = !empty($show['website_url']) ? $show['website_url'] : BASE_URL . '/podcast/show.php?slug=' . rawurlencode($show['slug']);
$selfUrl   = BASE_URL . '/podcast/feed.php?slug=' . rawurlencode($show['slug']);
$coverUrl  = !empty($show['cover_image'])
    ? BASE_URL . '/uploads/podcasts/covers/' . rawurlencode($show['cover_image'])
    : '';
$buildDate = !empty($episodes) ? date(DATE_RSS, strtotime($episodes[0]['created_at'])) : date(DATE_RSS);

header('Content-Type: application/rss+xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0"
     xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><?= htmlspecialchars($show['title'], ENT_XML1, 'UTF-8') ?></title>
    <link><?= htmlspecialchars($showUrl, ENT_XML1, 'UTF-8') ?></link>
    <description><?= htmlspecialchars($show['description'] ?? '', ENT_XML1, 'UTF-8') ?></description>
    <language><?= htmlspecialchars($show['language'] ?: 'cs', ENT_XML1, 'UTF-8') ?></language>
    <lastBuildDate><?= $buildDate ?></lastBuildDate>
    <atom:link href="<?= htmlspecialchars($selfUrl, ENT_XML1, 'UTF-8') ?>" rel="self" type="application/rss+xml"/>
<?php if (!empty($show['author'])): ?>
    <itunes:author><?= htmlspecialchars($show['author'], ENT_XML1, 'UTF-8') ?></itunes:author>
    <managingEditor><?= htmlspecialchars($show['author'], ENT_XML1, 'UTF-8') ?></managingEditor>
<?php endif; ?>
<?php if (!empty($show['category'])): ?>
    <itunes:category text="<?= htmlspecialchars($show['category'], ENT_XML1, 'UTF-8') ?>"/>
<?php endif; ?>
<?php if ($coverUrl !== ''): ?>
    <image>
      <url><?= htmlspecialchars($coverUrl, ENT_XML1, 'UTF-8') ?></url>
      <title><?= htmlspecialchars($show['title'], ENT_XML1, 'UTF-8') ?></title>
      <link><?= htmlspecialchars($showUrl, ENT_XML1, 'UTF-8') ?></link>
    </image>
    <itunes:image href="<?= htmlspecialchars($coverUrl, ENT_XML1, 'UTF-8') ?>"/>
<?php endif; ?>
    <itunes:explicit>no</itunes:explicit>
<?php foreach ($episodes as $ep):
    // Určení audio URL epizody
    $audioSrc = '';
    $audioType = 'audio/mpeg';
    if ($ep['audio_file'] !== '') {
        $audioSrc = BASE_URL . '/uploads/podcasts/' . rawurlencode($ep['audio_file']);
        $ext = strtolower(pathinfo($ep['audio_file'], PATHINFO_EXTENSION));
        $audioType = match($ext) {
            'ogg'  => 'audio/ogg',
            'wav'  => 'audio/wav',
            default => 'audio/mpeg',
        };
    } elseif ($ep['audio_url'] !== '') {
        $audioSrc = $ep['audio_url'];
    }

    $pubDate  = date(DATE_RSS, strtotime($ep['publish_at'] ?? $ep['created_at']));
    $itemLink = BASE_URL . '/podcast/show.php?slug=' . rawurlencode($show['slug']) . '#ep-' . (int)$ep['id'];
    $guid     = $itemLink;
    $desc     = strip_tags($ep['description'] ?? '');
?>
    <item>
      <title><?= htmlspecialchars($ep['title'], ENT_XML1, 'UTF-8') ?></title>
      <link><?= htmlspecialchars($itemLink, ENT_XML1, 'UTF-8') ?></link>
      <guid isPermaLink="true"><?= htmlspecialchars($guid, ENT_XML1, 'UTF-8') ?></guid>
      <pubDate><?= $pubDate ?></pubDate>
      <description><?= htmlspecialchars($desc, ENT_XML1, 'UTF-8') ?></description>
<?php if (!empty($ep['description'])): ?>
      <content:encoded><![CDATA[<?= $ep['description'] ?>]]></content:encoded>
<?php endif; ?>
<?php if ($audioSrc !== ''): ?>
      <enclosure url="<?= htmlspecialchars($audioSrc, ENT_XML1, 'UTF-8') ?>"
                 type="<?= htmlspecialchars($audioType, ENT_XML1, 'UTF-8') ?>"
                 length="0"/>
<?php endif; ?>
<?php if (!empty($ep['duration'])): ?>
      <itunes:duration><?= htmlspecialchars($ep['duration'], ENT_XML1, 'UTF-8') ?></itunes:duration>
<?php endif; ?>
<?php if ($ep['episode_num']): ?>
      <itunes:episode><?= (int)$ep['episode_num'] ?></itunes:episode>
<?php endif; ?>
<?php if (!empty($show['author'])): ?>
      <itunes:author><?= htmlspecialchars($show['author'], ENT_XML1, 'UTF-8') ?></itunes:author>
<?php endif; ?>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
