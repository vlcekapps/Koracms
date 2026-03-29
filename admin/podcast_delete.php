<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');
verifyCsrf();

$id     = inputInt('post', 'id');
$showId = inputInt('post', 'show_id');

if ($id !== null) {
    $pdo  = db_connect();
    $stmt = $pdo->prepare("SELECT audio_file, image_file, show_id FROM cms_podcasts WHERE id = ?");
    $stmt->execute([$id]);
    $episode = $stmt->fetch();
    if ($episode) {
        if (!empty($episode['audio_file'])) {
            deletePodcastAudioFile((string)$episode['audio_file']);
        }
        if (!empty($episode['image_file'])) {
            deletePodcastEpisodeImageFile((string)$episode['image_file']);
        }
        if ($showId === null) {
            $showId = (int)$episode['show_id'];
        }
    }
    $pdo->prepare("DELETE FROM cms_podcasts WHERE id = ?")->execute([$id]);
    logAction('podcast_delete', "id={$id}");
}

$redirect = $showId ? BASE_URL . '/admin/podcast.php?show_id=' . (int)$showId : BASE_URL . '/admin/podcast_shows.php';
header('Location: ' . $redirect);
exit;