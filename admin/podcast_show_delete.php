<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
$redirect = internalRedirectTarget((string)($_POST['redirect'] ?? ''), BASE_URL . '/admin/podcast_shows.php');
if ($id !== null) {
    $pdo = db_connect();

    $showStmt = $pdo->prepare("SELECT id, slug, cover_image FROM cms_podcast_shows WHERE id = ?");
    $showStmt->execute([$id]);
    $show = $showStmt->fetch() ?: null;

    $eps = $pdo->prepare("SELECT id, slug, audio_file, image_file FROM cms_podcasts WHERE show_id = ?");
    $eps->execute([$id]);
    $episodes = $eps->fetchAll();
    foreach ($episodes as $ep) {
        if (!empty($ep['audio_file'])) {
            deletePodcastAudioFile((string)$ep['audio_file']);
        }
        if (!empty($ep['image_file'])) {
            deletePodcastEpisodeImageFile((string)$ep['image_file']);
        }
    }

    if (!empty($show['cover_image'])) {
        deletePodcastCoverFile((string)$show['cover_image']);
    }

    if ($show) {
        $pdo->prepare("DELETE FROM cms_redirects WHERE new_path = ?")->execute([podcastShowPublicPath($show)]);
    }
    if ($episodes !== []) {
        $episodeIds = array_map(static fn(array $episode): int => (int)$episode['id'], $episodes);
        $placeholders = implode(',', array_fill(0, count($episodeIds), '?'));
        $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'podcast_episode' AND entity_id IN ({$placeholders})")->execute($episodeIds);
        foreach ($episodes as $episode) {
            if ($show) {
                $pdo->prepare("DELETE FROM cms_redirects WHERE new_path = ?")->execute([podcastEpisodePublicPath([
                    'id' => (int)$episode['id'],
                    'slug' => (string)$episode['slug'],
                    'show_slug' => (string)$show['slug'],
                ])]);
            }
        }
    }

    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'podcast_show' AND entity_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_podcasts WHERE show_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_podcast_shows WHERE id = ?")->execute([$id]);
    logAction('podcast_show_delete', "id={$id}");
}

header('Location: ' . $redirect);
exit;
