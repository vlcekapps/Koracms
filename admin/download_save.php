<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu souborů ke stažení nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$title = trim((string)($_POST['title'] ?? ''));
$slugInput = trim((string)($_POST['slug'] ?? ''));
$downloadType = normalizeDownloadType(trim((string)($_POST['download_type'] ?? 'document')));
$dlCategoryId = inputInt('post', 'dl_category_id');
$excerpt = trim((string)($_POST['excerpt'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$versionLabel = trim((string)($_POST['version_label'] ?? ''));
$platformLabel = trim((string)($_POST['platform_label'] ?? ''));
$licenseLabel = trim((string)($_POST['license_label'] ?? ''));
$externalUrlInput = trim((string)($_POST['external_url'] ?? ''));
$sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
$isPublished = isset($_POST['is_published']) ? 1 : 0;
$deleteStoredFile = isset($_POST['file_delete']);
$deleteImage = isset($_POST['download_image_delete']);

$redirectBase = BASE_URL . '/admin/download_form.php';
$redirectWithError = static function (string $errorCode) use ($redirectBase, $id): never {
    $query = $id !== null
        ? '?id=' . $id . '&err=' . rawurlencode($errorCode)
        : '?err=' . rawurlencode($errorCode);
    header('Location: ' . $redirectBase . $query);
    exit;
};

if ($title === '') {
    $redirectWithError('required');
}

$resolvedSlug = downloadSlug($slugInput !== '' ? $slugInput : $title);
if ($resolvedSlug === '') {
    $redirectWithError('slug');
}

$existing = [
    'filename' => '',
    'original_name' => '',
    'file_size' => 0,
    'image_file' => '',
];
if ($id !== null) {
    $existingStmt = $pdo->prepare(
        "SELECT filename, original_name, file_size, image_file
         FROM cms_downloads
         WHERE id = ?"
    );
    $existingStmt->execute([$id]);
    $existingRow = $existingStmt->fetch();
    if (!$existingRow) {
        header('Location: ' . BASE_URL . '/admin/downloads.php');
        exit;
    }
    $existing = array_merge($existing, $existingRow);
}

$uniqueSlug = uniqueDownloadSlug($pdo, $resolvedSlug, $id);
if ($uniqueSlug !== $resolvedSlug) {
    $redirectWithError('slug_taken');
}

$externalUrl = normalizeDownloadExternalUrl($externalUrlInput);
if ($externalUrlInput !== '' && $externalUrl === '') {
    $redirectWithError('url');
}

$imageFilename = (string)$existing['image_file'];
$imageUpload = uploadDownloadImage($_FILES['download_image'] ?? [], $imageFilename);
if ($imageUpload['error'] !== '') {
    $redirectWithError('image');
}
$imageFilename = $imageUpload['filename'];
if ($deleteImage && $imageFilename !== '') {
    deleteDownloadImageFile($imageFilename);
    $imageFilename = '';
}

$storedFilename = (string)$existing['filename'];
$originalName = (string)$existing['original_name'];
$fileSize = (int)$existing['file_size'];

if ($deleteStoredFile && $storedFilename !== '') {
    deleteDownloadStoredFile($storedFilename);
    $storedFilename = '';
    $originalName = '';
    $fileSize = 0;
}

$allowedExtensions = [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'odt', 'ods', 'odp', 'zip', '7z', 'tar', 'gz', 'bz2',
    'txt', 'exe', 'msi', 'apk', 'jar', 'dmg', 'pkg', 'deb', 'rpm', 'appimage',
];

$fileField = $_FILES['file'] ?? null;
if (is_array($fileField) && ($fileField['name'] ?? '') !== '' && (int)($fileField['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if ((int)($fileField['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $redirectWithError('file');
    }

    $tmpPath = (string)($fileField['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $redirectWithError('file');
    }

    $extension = strtolower(pathinfo((string)$fileField['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        $redirectWithError('file');
    }

    $directory = __DIR__ . '/../uploads/downloads/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        $redirectWithError('file');
    }

    $newStoredFilename = uniqid('dl_', true) . '.' . $extension;
    if (!move_uploaded_file($tmpPath, $directory . $newStoredFilename)) {
        $redirectWithError('file');
    }

    if ($storedFilename !== '' && $storedFilename !== $newStoredFilename) {
        deleteDownloadStoredFile($storedFilename);
    }

    $storedFilename = $newStoredFilename;
    $originalName = basename((string)$fileField['name']);
    $fileSize = (int)($fileField['size'] ?? 0);
}

if ($storedFilename === '' && $externalUrl === '') {
    $redirectWithError('source');
}

if ($id !== null) {
    $stmt = $pdo->prepare(
        "UPDATE cms_downloads
         SET title = ?, slug = ?, download_type = ?, dl_category_id = ?, excerpt = ?, description = ?,
             image_file = ?, version_label = ?, platform_label = ?, license_label = ?, external_url = ?,
             filename = ?, original_name = ?, file_size = ?, sort_order = ?, is_published = ?,
             author_id = COALESCE(author_id, ?), updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([
        $title,
        $uniqueSlug,
        $downloadType,
        $dlCategoryId,
        $excerpt,
        $description,
        $imageFilename,
        $versionLabel,
        $platformLabel,
        $licenseLabel,
        $externalUrl,
        $storedFilename,
        $originalName,
        $fileSize,
        $sortOrder,
        $isPublished,
        currentUserId(),
        $id,
    ]);
    logAction('download_edit', "id={$id}");
} else {
    $status = currentUserHasCapability('content_approve_shared') ? 'published' : 'pending';
    $authorId = currentUserId();
    $stmt = $pdo->prepare(
        "INSERT INTO cms_downloads
         (title, slug, download_type, dl_category_id, excerpt, description, image_file, version_label,
          platform_label, license_label, external_url, filename, original_name, file_size,
          sort_order, is_published, status, author_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $title,
        $uniqueSlug,
        $downloadType,
        $dlCategoryId,
        $excerpt,
        $description,
        $imageFilename,
        $versionLabel,
        $platformLabel,
        $licenseLabel,
        $externalUrl,
        $storedFilename,
        $originalName,
        $fileSize,
        $sortOrder,
        currentUserHasCapability('content_approve_shared') ? $isPublished : 0,
        $status,
        $authorId,
    ]);
    logAction('download_add', "title={$title} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/downloads.php');
exit;
