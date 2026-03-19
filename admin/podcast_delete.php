<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id     = inputInt('post', 'id');
$showId = inputInt('post', 'show_id');

if ($id !== null) {
    $pdo  = db_connect();
    $stmt = $pdo->prepare("SELECT audio_file, show_id FROM cms_podcasts WHERE id = ?");
    $stmt->execute([$id]);
    $ep = $stmt->fetch();
    if ($ep) {
        if ($ep['audio_file']) @unlink(__DIR__ . '/../uploads/podcasts/' . $ep['audio_file']);
        if ($showId === null) $showId = (int)$ep['show_id'];
    }
    $pdo->prepare("DELETE FROM cms_podcasts WHERE id = ?")->execute([$id]);
    logAction('podcast_delete', "id={$id}");
}

$redirect = $showId ? BASE_URL . '/admin/podcast.php?show_id=' . (int)$showId : BASE_URL . '/admin/podcast_shows.php';
header('Location: ' . $redirect);
exit;
