<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();

    // Smazat audio soubory epizod
    $eps = $pdo->prepare("SELECT audio_file FROM cms_podcasts WHERE show_id = ?");
    $eps->execute([$id]);
    foreach ($eps->fetchAll() as $ep) {
        if (!empty($ep['audio_file'])) {
            deletePodcastAudioFile((string)$ep['audio_file']);
        }
    }

    // Smazat cover image
    $stmt = $pdo->prepare("SELECT cover_image FROM cms_podcast_shows WHERE id = ?");
    $stmt->execute([$id]);
    $cover = $stmt->fetchColumn();
    if ($cover) {
        deletePodcastCoverFile((string)$cover);
    }

    // Smazat epizody a show
    $pdo->prepare("DELETE FROM cms_podcasts WHERE show_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_podcast_shows WHERE id = ?")->execute([$id]);
    logAction('podcast_show_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
exit;
