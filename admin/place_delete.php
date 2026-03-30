<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu míst nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
$redirectTarget = internalRedirectTarget((string)($_POST['redirect'] ?? ''), BASE_URL . '/admin/places.php');

if ($id !== null) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT image_file FROM cms_places WHERE id = ?");
    $stmt->execute([$id]);
    $imageFile = trim((string)($stmt->fetchColumn() ?: ''));
    $pdo->prepare("DELETE FROM cms_places WHERE id = ?")->execute([$id]);
    if ($imageFile !== '') {
        deletePlaceImageFile($imageFile);
    }
    logAction('place_delete', "id={$id}");
}

header('Location: ' . $redirectTarget);
exit;
