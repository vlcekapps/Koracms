<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu souborů ke stažení nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo  = db_connect();
    $stmt = $pdo->prepare("SELECT filename, image_file FROM cms_downloads WHERE id = ?");
    $stmt->execute([$id]);
    $download = $stmt->fetch();
    if ($download) {
        deleteDownloadStoredFile((string)($download['filename'] ?? ''));
        deleteDownloadImageFile((string)($download['image_file'] ?? ''));
    }
    $pdo->prepare("DELETE FROM cms_downloads WHERE id = ?")->execute([$id]);
    logAction('download_delete', "id={$id}");
}

header('Location: ' . BASE_URL . '/admin/downloads.php');
exit;
