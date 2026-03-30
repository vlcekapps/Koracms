<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu míst nemáte potřebné oprávnění.');
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
$redirectToForm = static function (string $errorCode) use ($redirectBase, $id, $redirectTarget): never {
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

if ($name === '') {
    $redirectToForm('required');
}

$existingPlace = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT * FROM cms_places WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingPlace = $existingStmt->fetch() ?: null;
    if (!$existingPlace) {
        header('Location: ' . $redirectTarget);
        exit;
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

$placeImageFilename = trim((string)($existingPlace['image_file'] ?? ''));
$imageUpload = uploadPlaceImage($_FILES['place_image'] ?? [], $placeImageFilename);
if ($imageUpload['error'] !== '') {
    $redirectToForm('image');
}
$placeImageFilename = $imageUpload['filename'];

if (isset($_POST['place_image_delete']) && empty($_FILES['place_image']['name']) && $placeImageFilename !== '') {
    deletePlaceImageFile($placeImageFilename);
    $placeImageFilename = '';
}

if ($id !== null && $existingPlace) {
    $oldSnapshot = placeRevisionSnapshot($existingPlace);
    $oldPath = placePublicPath($existingPlace);

    $pdo->prepare(
        "UPDATE cms_places
         SET name = ?, slug = ?, place_kind = ?, category = ?, locality = ?, address = ?, excerpt = ?,
             description = ?, url = ?, image_file = ?, latitude = ?, longitude = ?, opening_hours = ?,
             contact_phone = ?, contact_email = ?, meta_title = ?, meta_description = ?, is_published = ?,
             updated_at = NOW()
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
        'status' => (string)($existingPlace['status'] ?? 'published'),
    ]));
    upsertPathRedirect($pdo, $oldPath, placePublicPath(['id' => $id, 'slug' => $slug]));
    logAction('place_edit', "id={$id} name={$name} slug={$slug}");
} else {
    $status = currentUserHasCapability('content_approve_shared') ? 'published' : 'pending';
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
    if ($status === 'pending') {
        notifyPendingContent('Místo', $name, '/admin/places.php');
    }
}

header('Location: ' . $redirectTarget);
exit;
