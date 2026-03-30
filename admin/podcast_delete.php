<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');
verifyCsrf();

$id     = inputInt('post', 'id');
$showId = inputInt('post', 'show_id');
$redirect = internalRedirectTarget((string)($_POST['redirect'] ?? ''), $showId ? BASE_URL . '/admin/podcast.php?show_id=' . (int)$showId : BASE_URL . '/admin/podcast_shows.php');

if ($id !== null) {
    $pdo  = db_connect();
    $stmt = $pdo->prepare(
        "SELECT p.audio_file, p.image_file, p.show_id, p.slug, s.slug AS show_slug
         FROM cms_podcasts p
         INNER JOIN cms_podcast_shows s ON s.id = p.show_id
         WHERE p.id = ?"
    );
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
        $pdo->prepare("DELETE FROM cms_redirects WHERE new_path = ?")->execute([podcastEpisodePublicPath([
            'id' => $id,
            'slug' => (string)($episode['slug'] ?? ''),
            'show_slug' => (string)($episode['show_slug'] ?? ''),
        ])]);
    }
    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'podcast_episode' AND entity_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_podcasts WHERE id = ?")->execute([$id]);
    logAction('podcast_delete', "id={$id}");
}

header('Location: ' . $redirect);
exit;
