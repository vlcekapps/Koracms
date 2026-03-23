<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('podcast')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$shows = $pdo->query(
    "SELECT s.*, COUNT(e.id) AS episode_count
     FROM cms_podcast_shows s
     LEFT JOIN cms_podcasts e ON e.show_id = s.id
         AND e.status = 'published' AND (e.publish_at IS NULL OR e.publish_at <= NOW())
     GROUP BY s.id
     ORDER BY s.title ASC"
)->fetchAll();

renderPublicPage([
    'title' => 'Podcasty – ' . $siteName,
    'meta' => [
        'title' => 'Podcasty – ' . $siteName,
        'url' => BASE_URL . '/podcast/index.php',
    ],
    'view' => 'modules/podcast-index',
    'view_data' => [
        'shows' => $shows,
    ],
    'current_nav' => 'podcast',
    'body_class' => 'page-podcast-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/podcast_shows.php',
]);
