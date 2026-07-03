<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu galerie nemáte potřebné oprávnění.');
requireModuleEnabled('gallery');
verifyCsrf();

$pdo = db_connect();
$mode = trim($_POST['mode'] ?? '');
$albumId = inputInt('post', 'album_id');

$redirectToAlbumPhotos = static function (?int $targetAlbumId) {
    $location = BASE_URL . '/admin/gallery_albums.php';
    if ($targetAlbumId !== null) {
        $location = BASE_URL . '/admin/gallery_photos.php?album_id=' . $targetAlbumId;
    }
    header('Location: ' . $location);
    exit;
};

if ($albumId === null) {
    $redirectToAlbumPhotos(null);
}

$albumStmt = $pdo->prepare("SELECT * FROM cms_gallery_albums WHERE id = ?");
$albumStmt->execute([$albumId]);
$album = $albumStmt->fetch() ?: null;
if ($album === null) {
    $redirectToAlbumPhotos(null);
}

$redirectToPhotoForm = static function (int $photoId, int $targetAlbumId, string $error): void {
    header('Location: ' . BASE_URL . appendUrlQuery('/admin/gallery_photo_form.php', [
        'id' => (string)$photoId,
        'album_id' => (string)$targetAlbumId,
        'err' => $error,
    ]));
    exit;
};

if ($mode === 'edit') {
    $id = inputInt('post', 'id');
    $title = trim($_POST['title'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    $altText = trim((string)($_POST['alt_text'] ?? ''));
    $caption = trim((string)($_POST['caption'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $credit = trim((string)($_POST['credit'] ?? ''));
    $licenseLabel = trim((string)($_POST['license_label'] ?? ''));
    $licenseUrlInput = trim((string)($_POST['license_url'] ?? ''));
    $licenseUrl = normalizeGalleryLicenseUrl($licenseUrlInput);
    $takenAt = trim((string)($_POST['taken_at'] ?? ''));
    $locationLabel = trim((string)($_POST['location_label'] ?? ''));
    $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
    $canApproveContent = currentUserHasCapability('content_approve_shared');
    $isPublished = isset($_POST['is_published']) ? 1 : 0;

    if ($id === null) {
        $redirectToAlbumPhotos($albumId);
    }

    $photoStmt = $pdo->prepare("SELECT * FROM cms_gallery_photos WHERE id = ? AND album_id = ?");
    $photoStmt->execute([$id, $albumId]);
    $existingPhoto = $photoStmt->fetch();
    if (!$existingPhoto) {
        $redirectToAlbumPhotos($albumId);
    }

    if ($licenseUrlInput !== '' && $licenseUrl === '') {
        $redirectToPhotoForm($id, $albumId, 'license_url');
    }
    if ($takenAt !== '') {
        $takenAtDate = DateTimeImmutable::createFromFormat('!Y-m-d', $takenAt);
        if (!$takenAtDate instanceof DateTimeImmutable || $takenAtDate->format('Y-m-d') !== $takenAt) {
            $redirectToPhotoForm($id, $albumId, 'taken_at');
        }
    }

    $slugCandidate = $slugInput !== ''
        ? $slugInput
        : ($title !== '' ? $title : pathinfo((string)$existingPhoto['filename'], PATHINFO_FILENAME));
    $normalizedSlug = galleryPhotoSlug($slugInput);
    $resolvedSlug = uniqueGalleryPhotoSlug($pdo, $slugCandidate, $id);
    if ($normalizedSlug !== '' && $normalizedSlug !== $resolvedSlug) {
        $redirectToPhotoForm($id, $albumId, 'slug');
    }

    $albumNameStmt = $pdo->prepare("SELECT name FROM cms_gallery_albums WHERE id = ?");
    $albumNameStmt->execute([$albumId]);
    $albumName = trim((string)$albumNameStmt->fetchColumn());
    $oldPath = galleryPhotoPublicPath($existingPhoto);
    $oldRevision = galleryPhotoRevisionSnapshot($existingPhoto, $albumName);

    $pdo->prepare(
        "UPDATE cms_gallery_photos
         SET title = ?, slug = ?, alt_text = ?, caption = ?, description = ?, credit = ?,
             license_label = ?, license_url = ?, taken_at = ?, location_label = ?,
             sort_order = ?, is_published = ?
         WHERE id = ?"
    )->execute([
        $title,
        $resolvedSlug,
        $altText,
        $caption,
        $description,
        $credit,
        $licenseLabel,
        $licenseUrl,
        $takenAt !== '' ? $takenAt : null,
        $locationLabel,
        $sortOrder,
        $canApproveContent ? $isPublished : (int)($existingPhoto['is_published'] ?? 1),
        $id,
    ]);

    $newRevision = galleryPhotoRevisionSnapshot([
        'title' => $title,
        'slug' => $resolvedSlug,
        'alt_text' => $altText,
        'caption' => $caption,
        'description' => $description,
        'credit' => $credit,
        'license_label' => $licenseLabel,
        'license_url' => $licenseUrl,
        'taken_at' => $takenAt,
        'location_label' => $locationLabel,
        'sort_order' => $sortOrder,
        'is_published' => $canApproveContent ? $isPublished : (int)($existingPhoto['is_published'] ?? 1),
        'status' => $existingPhoto['status'] ?? 'published',
    ], $albumName);
    saveRevision($pdo, 'gallery_photo', $id, $oldRevision, $newRevision);
    upsertPathRedirect($pdo, $oldPath, galleryPhotoPublicPath(['id' => $id, 'slug' => $resolvedSlug]));

    logAction('gallery_photo_edit', 'id=' . $id);
    $redirectToAlbumPhotos($albumId);
}

if ($mode !== 'upload') {
    $redirectToAlbumPhotos(null);
}

$files = $_FILES['photos'] ?? [];
$uploadedCount = 0;
$canApproveContent = currentUserHasCapability('content_approve_shared');
$uploadStatus = $canApproveContent ? 'published' : 'pending';
$uploadVisibility = $canApproveContent ? 1 : 0;
$sortOrderStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM cms_gallery_photos WHERE album_id = ?");
$sortOrderStmt->execute([$albumId]);
$nextSortOrder = (int)$sortOrderStmt->fetchColumn() + 1;

if (!empty($files['name'])) {
    $count = count($files['name']);
    for ($index = 0; $index < $count; $index++) {
        $error = $files['error'][$index];
        $tmpName = $files['tmp_name'][$index];
        $origName = $files['name'][$index];
        $size = $files['size'][$index];

        $photoUpload = uploadGalleryPhotoImage([
            'name' => $origName,
            'tmp_name' => $tmpName,
            'size' => $size,
            'error' => $error,
        ]);
        if (empty($photoUpload['uploaded'])) {
            continue;
        }

        $filename = (string)$photoUpload['filename'];
        $slugCandidate = pathinfo((string)$photoUpload['original_name'], PATHINFO_FILENAME);
        $resolvedSlug = uniqueGalleryPhotoSlug($pdo, $slugCandidate);
        $pdo->prepare(
            "INSERT INTO cms_gallery_photos
             (album_id, filename, title, slug, credit, license_label, license_url, sort_order, is_published, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $albumId,
            $filename,
            '',
            $resolvedSlug,
            trim((string)($album['default_credit'] ?? '')),
            trim((string)($album['default_license_label'] ?? '')),
            normalizeGalleryLicenseUrl((string)($album['default_license_url'] ?? '')),
            $nextSortOrder,
            $uploadVisibility,
            $uploadStatus,
        ]);
        $uploadedCount++;
        $nextSortOrder++;
    }
}

if ($uploadedCount > 0) {
    logAction('gallery_photo_upload', 'album_id=' . $albumId . ';count=' . $uploadedCount . ';status=' . $uploadStatus);
    if ($uploadStatus === 'pending') {
        notifyPendingContent('Fotografie galerie', 'Nové fotografie v albu', '/admin/gallery_photos.php?album_id=' . $albumId);
    }
}

$redirectToAlbumPhotos($albumId);
