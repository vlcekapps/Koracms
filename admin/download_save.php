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
$projectUrlInput = trim((string)($_POST['project_url'] ?? ''));
$releaseDateInput = trim((string)($_POST['release_date'] ?? ''));
$requirements = trim((string)($_POST['requirements'] ?? ''));
$checksumInput = trim((string)($_POST['checksum_sha256'] ?? ''));
$seriesKeyInput = trim((string)($_POST['series_key'] ?? ''));
$externalUrlInput = trim((string)($_POST['external_url'] ?? ''));
$isPublished = isset($_POST['is_published']) ? 1 : 0;
$isFeatured = isset($_POST['is_featured']) ? 1 : 0;
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

$seriesKey = normalizeDownloadSeriesKey($seriesKeyInput);
if ($seriesKeyInput !== '' && $seriesKey === '') {
    $redirectWithError('series');
}

$checksumSha256 = normalizeDownloadChecksum($checksumInput);
if ($checksumInput !== '' && $checksumSha256 === '') {
    $redirectWithError('checksum');
}

$projectUrl = normalizeDownloadExternalUrl($projectUrlInput);
if ($projectUrlInput !== '' && $projectUrl === '') {
    $redirectWithError('project_url');
}

$releaseDate = null;
if ($releaseDateInput !== '') {
    $releaseDateTime = DateTimeImmutable::createFromFormat('Y-m-d', $releaseDateInput);
    if (!$releaseDateTime || $releaseDateTime->format('Y-m-d') !== $releaseDateInput) {
        $redirectWithError('release_date');
    }
    $releaseDate = $releaseDateTime->format('Y-m-d');
}

$existing = [
    'filename' => '',
    'original_name' => '',
    'file_size' => 0,
    'image_file' => '',
    'checksum_sha256' => '',
];
$existingDownload = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT * FROM cms_downloads WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingDownload = $existingStmt->fetch();
    if (!$existingDownload) {
        header('Location: ' . BASE_URL . '/admin/downloads.php');
        exit;
    }
    $existing = array_merge($existing, $existingDownload);
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
if ($checksumSha256 === '') {
    $checksumSha256 = normalizeDownloadChecksum((string)$existing['checksum_sha256']);
}

if ($deleteStoredFile && $storedFilename !== '') {
    deleteDownloadStoredFile($storedFilename);
    $storedFilename = '';
    $originalName = '';
    $fileSize = 0;
    if ($checksumInput === '') {
        $checksumSha256 = '';
    }
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
    $targetPath = $directory . $newStoredFilename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        $redirectWithError('file');
    }

    $newChecksum = hash_file('sha256', $targetPath);
    if (!is_string($newChecksum) || $newChecksum === '') {
        @unlink($targetPath);
        $redirectWithError('file');
    }

    if ($storedFilename !== '' && $storedFilename !== $newStoredFilename) {
        deleteDownloadStoredFile($storedFilename);
    }

    $storedFilename = $newStoredFilename;
    $originalName = basename((string)$fileField['name']);
    $fileSize = (int)($fileField['size'] ?? 0);
    $checksumSha256 = normalizeDownloadChecksum($newChecksum);
}

if ($storedFilename === '' && $externalUrl === '') {
    $redirectWithError('source');
}

if ($storedFilename === '' && $checksumInput === '') {
    $checksumSha256 = '';
}

if ($id !== null) {
    $oldSnapshot = $existingDownload ? downloadRevisionSnapshot($existingDownload) : [];
    $oldPath = $existingDownload ? downloadPublicPath($existingDownload) : '';

    $requestedStatus = trim($_POST['article_status'] ?? '');
    if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
        $requestedStatus = $existingDownload['status'] ?? 'published';
    }
    if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
        $requestedStatus = (($existingDownload['status'] ?? '') === 'published') ? 'published' : 'pending';
    }

    // Při první publikaci aktualizovat created_at
    $publishingNow = $requestedStatus === 'published' && ($existingDownload['status'] ?? '') !== 'published';
    $createdAtClause = $publishingNow ? ', created_at = NOW()' : '';

    $stmt = $pdo->prepare(
        "UPDATE cms_downloads
         SET title = ?, slug = ?, download_type = ?, dl_category_id = ?, excerpt = ?, description = ?,
             image_file = ?, version_label = ?, platform_label = ?, license_label = ?, project_url = ?,
             release_date = ?, requirements = ?, checksum_sha256 = ?, series_key = ?, external_url = ?,
             filename = ?, original_name = ?, file_size = ?, is_featured = ?, is_published = ?,
             status = ?, author_id = COALESCE(author_id, ?), updated_at = NOW(){$createdAtClause}
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
        $projectUrl,
        $releaseDate,
        $requirements,
        $checksumSha256,
        $seriesKey,
        $externalUrl,
        $storedFilename,
        $originalName,
        $fileSize,
        $isFeatured,
        $isPublished,
        $requestedStatus,
        currentUserId(),
        $id,
    ]);

    saveRevision($pdo, 'download', $id, $oldSnapshot, downloadRevisionSnapshot([
        'title' => $title,
        'slug' => $uniqueSlug,
        'download_type' => $downloadType,
        'dl_category_id' => $dlCategoryId,
        'excerpt' => $excerpt,
        'description' => $description,
        'version_label' => $versionLabel,
        'platform_label' => $platformLabel,
        'license_label' => $licenseLabel,
        'project_url' => $projectUrl,
        'release_date' => $releaseDate,
        'requirements' => $requirements,
        'checksum_sha256' => $checksumSha256,
        'series_key' => $seriesKey,
        'external_url' => $externalUrl,
        'is_featured' => $isFeatured,
        'is_published' => $isPublished,
    ]));
    upsertPathRedirect($pdo, $oldPath, downloadPublicPath(['id' => $id, 'slug' => $uniqueSlug]));
    logAction('download_edit', "id={$id} title={$title} slug={$uniqueSlug} featured={$isFeatured}");
} else {
    $requestedStatus = trim($_POST['article_status'] ?? '');
    if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
        $requestedStatus = 'draft';
    }
    if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
        $requestedStatus = 'pending';
    }
    $status = $requestedStatus;
    $authorId = currentUserId();
    $stmt = $pdo->prepare(
        "INSERT INTO cms_downloads
         (title, slug, download_type, dl_category_id, excerpt, description, image_file, version_label,
          platform_label, license_label, project_url, release_date, requirements, checksum_sha256,
          series_key, external_url, filename, original_name, file_size, is_featured,
          is_published, status, author_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
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
        $projectUrl,
        $releaseDate,
        $requirements,
        $checksumSha256,
        $seriesKey,
        $externalUrl,
        $storedFilename,
        $originalName,
        $fileSize,
        $isFeatured,
        currentUserHasCapability('content_approve_shared') ? $isPublished : 0,
        $status,
        $authorId,
    ]);
    logAction('download_add', "title={$title} status={$status} featured={$isFeatured}");
    if ($status === 'pending') {
        notifyPendingContent('Soubor ke stažení', $title, '/admin/downloads.php');
    }
}

header('Location: ' . BASE_URL . '/admin/downloads.php');
exit;
