<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');
verifyCsrf();

$photoId = inputInt('post', 'id');
$albumId = inputInt('post', 'album_id');
$direction = (string)($_POST['direction'] ?? '');
$redirect = internalRedirectTarget((string)($_POST['redirect'] ?? ''), BASE_URL . '/admin/gallery_albums.php');

if ($photoId === null || $albumId === null || !in_array($direction, ['up', 'down'], true)) {
    header('Location: ' . ($redirect !== '' ? $redirect : (BASE_URL . '/admin/gallery_albums.php')));
    exit;
}

$pdo = db_connect();
$photoStmt = $pdo->prepare("SELECT id FROM cms_gallery_photos WHERE id = ? AND album_id = ?");
$photoStmt->execute([$photoId, $albumId]);
if (!$photoStmt->fetch()) {
    header('Location: ' . ($redirect !== '' ? $redirect : (BASE_URL . '/admin/gallery_photos.php?album_id=' . $albumId)));
    exit;
}

$photosStmt = $pdo->prepare(
    "SELECT id
     FROM cms_gallery_photos
     WHERE album_id = ?
     ORDER BY sort_order, id"
);
$photosStmt->execute([$albumId]);
$orderedIds = array_map(static fn($value): int => (int)$value, $photosStmt->fetchAll(PDO::FETCH_COLUMN));
$currentIndex = array_search($photoId, $orderedIds, true);

if ($currentIndex !== false) {
    $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
    if (isset($orderedIds[$targetIndex])) {
        $currentValue = $orderedIds[$currentIndex];
        $orderedIds[$currentIndex] = $orderedIds[$targetIndex];
        $orderedIds[$targetIndex] = $currentValue;

        $updateStmt = $pdo->prepare("UPDATE cms_gallery_photos SET sort_order = ? WHERE id = ?");
        foreach ($orderedIds as $position => $orderedId) {
            $updateStmt->execute([$position, $orderedId]);
        }
        logAction('gallery_photo_reorder', 'album_id=' . $albumId . ';id=' . $photoId . ';direction=' . $direction);
    }
}

$fallback = BASE_URL . '/admin/gallery_photos.php?album_id=' . $albumId;
header('Location: ' . ($redirect !== '' ? $redirect : $fallback));
exit;
