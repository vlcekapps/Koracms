<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('podcast')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    header('Location: ' . BASE_URL . '/podcast/index.php');
    exit;
}

$showStmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE slug = ?");
$showStmt->execute([$slug]);
$show = $showStmt->fetch();
if (!$show) {
    header('Location: ' . BASE_URL . '/podcast/index.php');
    exit;
}

$episodesStmt = $pdo->prepare(
    "SELECT * FROM cms_podcasts
     WHERE show_id = ? AND status = 'published' AND (publish_at IS NULL OR publish_at <= NOW())
     ORDER BY episode_num DESC, created_at DESC"
);
$episodesStmt->execute([$show['id']]);
$episodes = $episodesStmt->fetchAll();

$feedUrl = BASE_URL . '/podcast/feed.php?slug=' . rawurlencode($show['slug']);

renderPublicPage([
    'title' => $show['title'] . ' – ' . $siteName,
    'meta' => [
        'title' => $show['title'] . ' – ' . $siteName,
        'description' => $show['description'],
        'url' => BASE_URL . '/podcast/show.php?slug=' . rawurlencode($show['slug']),
    ],
    'extra_head_html' => '  <link rel="alternate" type="application/rss+xml" title="'
        . h($show['title']) . ' – RSS feed" href="' . h($feedUrl) . '">' . PHP_EOL,
    'view' => 'modules/podcast-show',
    'view_data' => [
        'show' => $show,
        'episodes' => $episodes,
        'feedUrl' => $feedUrl,
    ],
    'current_nav' => 'podcast',
    'body_class' => 'page-podcast-show',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/podcast_show_form.php?id=' . (int)$show['id'],
]);
