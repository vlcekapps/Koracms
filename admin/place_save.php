<?php

require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu míst nemáte potřebné oprávnění.');
requireModuleEnabled('places');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$redirectTarget = internalRedirectTarget((string)($_POST['redirect'] ?? ''), BASE_URL . '/admin/places.php');
$name = trim((string)($_POST['name'] ?? ''));
$submittedSlug = trim((string)($_POST['slug'] ?? ''));
$placeKind = normalizePlaceKind((string)($_POST['place_kind'] ?? 'sight'));
$category = trim((string)($_POST['category'] ?? ''));
$locality = trim((string)($_POST['locality'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$excerpt = trim((string)($_POST['excerpt'] ?? ''));
$description = (string)($_POST['description'] ?? '');
$url = trim((string)($_POST['url'] ?? ''));
$latitudeInput = trim((string)($_POST['latitude'] ?? ''));
$longitudeInput = trim((string)($_POST['longitude'] ?? ''));
$openingHours = trim((string)($_POST['opening_hours'] ?? ''));
$contactPhone = trim((string)($_POST['contact_phone'] ?? ''));
$contactEmail = trim((string)($_POST['contact_email'] ?? ''));
$metaTitle = trim((string)($_POST['meta_title'] ?? ''));
$metaDescription = trim((string)($_POST['meta_description'] ?? ''));
$isPublished = isset($_POST['is_published']) ? 1 : 0;

$redirectBase = BASE_URL . '/admin/place_form.php';
$redirectToForm = static function (string $errorCode) use ($redirectBase, $id, $redirectTarget) {
    adminEditorFormFlashStore('place', $id, $_POST);
    $query = ['err' => $errorCode];
    if ($id !== null) {
        $query['id'] = (string)$id;
    }
    if ($redirectTarget !== '') {
        $query['redirect'] = $redirectTarget;
    }

    header('Location: ' . $redirectBase . '?' . http_build_query($query));
    exit;
};

if (array_key_exists('place_image_delete', $_POST)
    || (array_key_exists('confirm_place_image_delete', $_POST) && $_POST['confirm_place_image_delete'] !== '1')) {
    $redirectToForm('image_confirm');
}
$deleteImage = ($_POST['confirm_place_image_delete'] ?? '') === '1';

if ($name === '') {
    $redirectToForm('required');
}

$existingPlace = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT * FROM cms_places WHERE id = ? AND deleted_at IS NULL");
    $existingStmt->execute([$id]);
    $existingPlace = $existingStmt->fetch() ?: null;
    if (!$existingPlace) {
        $redirectToForm('missing');
    }
}

$slug = placeSlug($submittedSlug !== '' ? $submittedSlug : $name);
if ($slug === '') {
    $redirectToForm('slug');
}

$uniqueSlug = uniquePlaceSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    $redirectToForm('slug');
}
$slug = $uniqueSlug;

$normalizedUrl = '';
if ($url !== '') {
    $normalizedUrl = normalizePlaceUrl($url);
    if ($normalizedUrl === '') {
        $redirectToForm('url');
    }
}

if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    $redirectToForm('email');
}

$latitude = null;
$longitude = null;
if ($latitudeInput !== '' || $longitudeInput !== '') {
    if ($latitudeInput === '' || $longitudeInput === '') {
        $redirectToForm('coordinates');
    }

    if (!is_numeric($latitudeInput) || !is_numeric($longitudeInput)) {
        $redirectToForm('coordinates');
    }

    $latitude = round((float)$latitudeInput, 7);
    $longitude = round((float)$longitudeInput, 7);
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        $redirectToForm('coordinates');
    }
}

$imageUpload = ['filename' => '', 'uploaded' => false, 'error' => ''];
try {
    $pdo->beginTransaction();
    if ($id !== null) {
        $lockedStmt = $pdo->prepare('SELECT * FROM cms_places WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $lockedStmt->execute([$id]);
        $existingPlace = $lockedStmt->fetch() ?: null;
        if ($existingPlace === null) {
            $pdo->rollBack();
            $redirectToForm('missing');
        }
    }
    $oldImageFilename = (string)($existingPlace['image_file'] ?? '');
    // Stage a new upload without giving the upload helper ownership of the old file.
    $imageUpload = uploadPlaceImage($_FILES['place_image'] ?? []);
    if ($imageUpload['error'] !== '') {
        $pdo->rollBack();
        $redirectToForm('image');
    }
    $placeImageFilename = $imageUpload['uploaded'] ? (string)$imageUpload['filename'] : $oldImageFilename;
    if ($deleteImage && !$imageUpload['uploaded']) {
        $placeImageFilename = '';
    }

    if ($id !== null) {
        $oldSnapshot = placeRevisionSnapshot($existingPlace);
        $oldPath = placePublicPath($existingPlace);

        $requestedStatus = trim($_POST['article_status'] ?? '');
        if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
            $requestedStatus = $existingPlace['status'] ?? 'published';
        }
        if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
            $requestedStatus = (($existingPlace['status'] ?? '') === 'published') ? 'published' : 'pending';
        }

        // Při první publikaci aktualizovat created_at
        $publishingNow = $requestedStatus === 'published' && ($existingPlace['status'] ?? '') !== 'published';
        $createdAtClause = $publishingNow ? ', created_at = NOW()' : '';

        $pdo->prepare(
            "UPDATE cms_places
         SET name = ?, slug = ?, place_kind = ?, category = ?, locality = ?, address = ?, excerpt = ?,
             description = ?, url = ?, image_file = ?, latitude = ?, longitude = ?, opening_hours = ?,
             contact_phone = ?, contact_email = ?, meta_title = ?, meta_description = ?, is_published = ?,
             status = ?, updated_at = NOW(){$createdAtClause}
         WHERE id = ?"
        )->execute([
            $name,
            $slug,
            $placeKind,
            $category,
            $locality,
            $address,
            $excerpt,
            $description,
            $normalizedUrl,
            $placeImageFilename,
            $latitude,
            $longitude,
            $openingHours,
            $contactPhone,
            $contactEmail,
            $metaTitle,
            $metaDescription,
            $isPublished,
            $requestedStatus,
            $id,
        ]);

        saveRevision($pdo, 'place', $id, $oldSnapshot, placeRevisionSnapshot([
            'name' => $name,
            'slug' => $slug,
            'place_kind' => $placeKind,
            'category' => $category,
            'excerpt' => $excerpt,
            'description' => $description,
            'url' => $normalizedUrl,
            'address' => $address,
            'locality' => $locality,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'opening_hours' => $openingHours,
            'contact_phone' => $contactPhone,
            'contact_email' => $contactEmail,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'is_published' => $isPublished,
            'status' => $requestedStatus,
        ]), true);
        upsertPathRedirect($pdo, $oldPath, placePublicPath(['id' => $id, 'slug' => $slug]), 301, true);
        logAction('place_edit', "id={$id} name={$name} slug={$slug}");
    } else {
        $requestedStatus = trim($_POST['article_status'] ?? '');
        if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
            $requestedStatus = 'draft';
        }
        if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
            $requestedStatus = 'pending';
        }
        $status = $requestedStatus;
        $visible = currentUserHasCapability('content_approve_shared') ? $isPublished : 0;
        $pdo->prepare(
            "INSERT INTO cms_places (
            name, slug, place_kind, category, locality, address, excerpt, description, url, image_file,
            latitude, longitude, opening_hours, contact_phone, contact_email, meta_title, meta_description,
            is_published, status
         ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $name,
            $slug,
            $placeKind,
            $category,
            $locality,
            $address,
            $excerpt,
            $description,
            $normalizedUrl,
            $placeImageFilename,
            $latitude,
            $longitude,
            $openingHours,
            $contactPhone,
            $contactEmail,
            $metaTitle,
            $metaDescription,
            $visible,
            $status,
        ]);
        $id = (int)$pdo->lastInsertId();
        logAction('place_add', "id={$id} name={$name} slug={$slug} status={$status}");
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($imageUpload['uploaded']) {
        deletePlaceImageFile((string)$imageUpload['filename']);
    }
    koraLog('error', 'place save failed', ['place_id' => $id, 'exception' => $exception]);
    $redirectToForm('save');
}

if ($oldImageFilename !== '' && $oldImageFilename !== $placeImageFilename) {
    deleteUnusedPlaceImageFile($pdo, $oldImageFilename);
}
if ($existingPlace === null && $requestedStatus === 'pending') {
    notifyPendingContent('Místo', $name, '/admin/places.php');
}

header('Location: ' . $redirectTarget);
exit;
