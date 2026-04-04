<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');
verifyCsrf();

$id     = inputInt('post', 'id');
$showId = inputInt('post', 'show_id');
$redirect = internalRedirectTarget((string)($_POST['redirect'] ?? ''), $showId ? BASE_URL . '/admin/podcast.php?show_id=' . (int)$showId : BASE_URL . '/admin/podcast_shows.php');

if ($id !== null) {
    $pdo = db_connect();
    $pdo->prepare("UPDATE cms_podcasts SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([$id]);
    logAction('podcast_delete', "id={$id} soft=true");
}

header('Location: ' . $redirect);
exit;
