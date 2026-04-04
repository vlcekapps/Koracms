<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
$redirect = internalRedirectTarget((string)($_POST['redirect'] ?? ''), BASE_URL . '/admin/podcast_shows.php');
if ($id !== null) {
    $pdo = db_connect();
    // Soft delete pořadu i jeho epizod
    $pdo->prepare("UPDATE cms_podcasts SET deleted_at = NOW() WHERE show_id = ? AND deleted_at IS NULL")->execute([$id]);
    $pdo->prepare("UPDATE cms_podcast_shows SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL")->execute([$id]);
    logAction('podcast_show_delete', "id={$id} soft=true");
}

header('Location: ' . $redirect);
exit;
