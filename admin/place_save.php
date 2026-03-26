<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu míst nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$name = trim($_POST['name'] ?? '');
$submittedSlug = trim($_POST['slug'] ?? '');
$placeKind = normalizePlaceKind((string)($_POST['place_kind'] ?? 'sight'));
$category = trim($_POST['category'] ?? '');
$locality = trim($_POST['locality'] ?? '');
$address = trim($_POST['address'] ?? '');
$excerpt = trim($_POST['excerpt'] ?? '');
$description = $_POST['description'] ?? '';
$url = trim($_POST['url'] ?? '');
$latitudeInput = trim($_POST['latitude'] ?? '');
$longitudeInput = trim($_POST['longitude'] ?? '');
$openingHours = trim($_POST['opening_hours'] ?? '');
$contactPhone = trim($_POST['contact_phone'] ?? '');
$contactEmail = trim($_POST['contact_email'] ?? '');
$isPublished = isset($_POST['is_published']) ? 1 : 0;

if ($name === '') {
    header('Location: place_form.php?err=required' . ($id ? '&id=' . $id : ''));
    exit;
}

$existingPlace = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT id, image_file FROM cms_places WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingPlace = $existingStmt->fetch() ?: null;
    if (!$existingPlace) {
        header('Location: ' . BASE_URL . '/admin/places.php');
        exit;
    }
}

$slug = placeSlug($submittedSlug !== '' ? $submittedSlug : $name);
if ($slug === '') {
    header('Location: place_form.php?err=slug' . ($id ? '&id=' . $id : ''));
    exit;
}

$uniqueSlug = uniquePlaceSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    header('Location: place_form.php?err=slug' . ($id ? '&id=' . $id : ''));
    exit;
}
$slug = $uniqueSlug;

$normalizedUrl = '';
if ($url !== '') {
    $normalizedUrl = normalizePlaceUrl($url);
    if ($normalizedUrl === '') {
        header('Location: place_form.php?err=url' . ($id ? '&id=' . $id : ''));
        exit;
    }
}

if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    header('Location: place_form.php?err=email' . ($id ? '&id=' . $id : ''));
    exit;
}

$latitude = null;
$longitude = null;
if ($latitudeInput !== '' || $longitudeInput !== '') {
    if ($latitudeInput === '' || $longitudeInput === '') {
        header('Location: place_form.php?err=coordinates' . ($id ? '&id=' . $id : ''));
        exit;
    }

    if (!is_numeric($latitudeInput) || !is_numeric($longitudeInput)) {
        header('Location: place_form.php?err=coordinates' . ($id ? '&id=' . $id : ''));
        exit;
    }

    $latitude = round((float)$latitudeInput, 7);
    $longitude = round((float)$longitudeInput, 7);
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        header('Location: place_form.php?err=coordinates' . ($id ? '&id=' . $id : ''));
        exit;
    }
}

$placeImageFilename = trim((string)($existingPlace['image_file'] ?? ''));
$imageUpload = uploadPlaceImage($_FILES['place_image'] ?? [], $placeImageFilename);
if ($imageUpload['error'] !== '') {
    header('Location: place_form.php?err=image' . ($id ? '&id=' . $id : ''));
    exit;
}
$placeImageFilename = $imageUpload['filename'];

if (isset($_POST['place_image_delete']) && empty($_FILES['place_image']['name']) && $placeImageFilename !== '') {
    deletePlaceImageFile($placeImageFilename);
    $placeImageFilename = '';
}

if ($id !== null) {
    $pdo->prepare(
        "UPDATE cms_places
         SET name = ?, slug = ?, place_kind = ?, category = ?, locality = ?, address = ?, excerpt = ?,
             description = ?, url = ?, image_file = ?, latitude = ?, longitude = ?, opening_hours = ?,
             contact_phone = ?, contact_email = ?, is_published = ?, updated_at = NOW()
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
        $isPublished,
        $id,
    ]);
    logAction('place_edit', "id={$id} name={$name} slug={$slug}");
} else {
    $status = currentUserHasCapability('content_approve_shared') ? 'published' : 'pending';
    $visible = currentUserHasCapability('content_approve_shared') ? $isPublished : 0;
    $pdo->prepare(
        "INSERT INTO cms_places (
            name, slug, place_kind, category, locality, address, excerpt, description, url, image_file,
            latitude, longitude, opening_hours, contact_phone, contact_email, is_published, status
         ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
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
        $visible,
        $status,
    ]);
    $id = (int)$pdo->lastInsertId();
    logAction('place_add', "id={$id} name={$name} slug={$slug} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/places.php');
exit;
