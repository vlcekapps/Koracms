<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu úřední desky nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$existingDocument = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT * FROM cms_board WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingDocument = $existingStmt->fetch() ?: null;
    if (!$existingDocument) {
        header('Location: ' . BASE_URL . '/admin/board.php');
        exit;
    }
}

$boardCategoryName = static function (PDO $pdo, ?int $categoryId): string {
    if ($categoryId === null || $categoryId <= 0) {
        return '';
    }

    $stmt = $pdo->prepare("SELECT name FROM cms_board_categories WHERE id = ?");
    $stmt->execute([$categoryId]);

    return trim((string)($stmt->fetchColumn() ?? ''));
};

$boardRevisionSnapshot = static function (array $row) use ($pdo, $boardCategoryName): array {
    return [
        'title' => trim((string)($row['title'] ?? '')),
        'slug' => trim((string)($row['slug'] ?? '')),
        'board_type' => boardTypeLabel((string)($row['board_type'] ?? 'document')),
        'category' => $boardCategoryName($pdo, isset($row['category_id']) ? (int)$row['category_id'] : null),
        'excerpt' => trim((string)($row['excerpt'] ?? '')),
        'description' => trim((string)($row['description'] ?? '')),
        'posted_date' => trim((string)($row['posted_date'] ?? '')),
        'removal_date' => trim((string)($row['removal_date'] ?? '')),
        'contact_name' => trim((string)($row['contact_name'] ?? '')),
        'contact_phone' => trim((string)($row['contact_phone'] ?? '')),
        'contact_email' => trim((string)($row['contact_email'] ?? '')),
        'is_pinned' => (int)($row['is_pinned'] ?? 0) === 1 ? 'Ano' : 'Ne',
        'is_published' => (int)($row['is_published'] ?? 0) === 1 ? 'Ano' : 'Ne',
    ];
};

$redirectToForm = static function (?int $documentId, string $errorCode): void {
    $params = ['err' => $errorCode];
    if ($documentId !== null) {
        $params['id'] = (string)$documentId;
    }
    header('Location: ' . BASE_URL . appendUrlQuery('/admin/board_form.php', $params));
    exit;
};

$title = trim($_POST['title'] ?? '');
$submittedSlug = trim($_POST['slug'] ?? '');
$boardType = normalizeBoardType((string)($_POST['board_type'] ?? 'document'));
$categoryId = inputInt('post', 'category_id');
$excerpt = trim($_POST['excerpt'] ?? '');
$description = trim($_POST['description'] ?? '');
$postedDate = trim($_POST['posted_date'] ?? '');
$removalDate = trim($_POST['removal_date'] ?? '');
$contactName = trim($_POST['contact_name'] ?? '');
$contactPhone = trim($_POST['contact_phone'] ?? '');
$contactEmail = trim($_POST['contact_email'] ?? '');
$isPinned = isset($_POST['is_pinned']) ? 1 : 0;
$isPublished = isset($_POST['is_published']) ? 1 : 0;

$publishAtInput = trim((string)($_POST['publish_at'] ?? ''));
$publishAtSql = null;
if ($publishAtInput !== '') {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $publishAtInput);
    if ($dt && $dt->format('Y-m-d\TH:i') === $publishAtInput) {
        $publishAtSql = $dt->format('Y-m-d H:i:s');
    }
}
$unpublishAtInput = trim((string)($_POST['unpublish_at'] ?? ''));
$unpublishAtSql = null;
if ($unpublishAtInput !== '') {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $unpublishAtInput);
    if ($dt && $dt->format('Y-m-d\TH:i') === $unpublishAtInput) {
        $unpublishAtSql = $dt->format('Y-m-d H:i:s');
    }
}

if ($title === '' || $postedDate === '') {
    $redirectToForm($id, 'required');
}

$normalizeBoardDate = static function (string $value): ?string {
    $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($dateTime === false) {
        return null;
    }

    $errors = DateTimeImmutable::getLastErrors();
    if ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
        return null;
    }

    return $dateTime->format('Y-m-d') === $value
        ? $dateTime->format('Y-m-d')
        : null;
};

$postedDateSql = $normalizeBoardDate($postedDate);
if ($postedDateSql === null) {
    $redirectToForm($id, 'posted_date');
}
$postedDate = $postedDateSql;

if ($removalDate !== '') {
    $removalDateSql = $normalizeBoardDate($removalDate);
    if ($removalDateSql === null) {
        $redirectToForm($id, 'removal_date');
    }
    $removalDate = $removalDateSql;
}

if ($removalDate !== '' && $removalDate < $postedDate) {
    $redirectToForm($id, 'dates');
}
if ($removalDate === '') {
    $removalDate = null;
}

if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    $redirectToForm($id, 'contact_email');
}

$slug = boardSlug($submittedSlug !== '' ? $submittedSlug : $title);
if ($slug === '') {
    $redirectToForm($id, 'slug');
}

$uniqueSlug = uniqueBoardSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    $redirectToForm($id, 'slug');
}
$slug = $uniqueSlug;

$boardImageFilename = (string)($existingDocument['image_file'] ?? '');
$imageUpload = uploadBoardImage($_FILES['board_image'] ?? [], $boardImageFilename);
if ($imageUpload['error'] !== '') {
    $redirectToForm($id, 'image');
}
$boardImageFilename = $imageUpload['filename'];
if (isset($_POST['board_image_delete']) && empty($_FILES['board_image']['name']) && $boardImageFilename !== '') {
    deleteBoardImageFile($boardImageFilename);
    $boardImageFilename = '';
}

$newFilename = null;
$newOriginalName = null;
$newFileSize = null;

if (!empty($_FILES['file']['name'])) {
    $tmpPath = (string)($_FILES['file']['tmp_name'] ?? '');
    $mime = $tmpPath !== '' ? (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath) : '';

    $allowed = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
        'application/vnd.oasis.opendocument.presentation' => 'odp',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'text/plain' => 'txt',
    ];

    if ($tmpPath === '' || !isset($allowed[$mime])) {
        $redirectToForm($id, 'file');
    }

    $uploadDir = __DIR__ . '/../uploads/board/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        $redirectToForm($id, 'file');
    }

    $storedName = uniqid('board_', true) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmpPath, $uploadDir . $storedName)) {
        $redirectToForm($id, 'file');
    }

    if ($existingDocument && !empty($existingDocument['filename'])) {
        $oldFile = $uploadDir . basename((string)$existingDocument['filename']);
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    $newFilename = $storedName;
    $newOriginalName = basename((string)$_FILES['file']['name']);
    $newFileSize = (int)($_FILES['file']['size'] ?? 0);
}

if ($id !== null) {
    if ($existingDocument && ($existingDocument['preview_token'] ?? '') === '') {
        $previewToken = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE cms_board SET preview_token = ? WHERE id = ?")->execute([$previewToken, $id]);
    }

    $oldSnapshot = $existingDocument ? $boardRevisionSnapshot($existingDocument) : [];
    $oldPath = $existingDocument ? boardPublicPath($existingDocument) : '';
    $requestedStatus = trim($_POST['article_status'] ?? '');
    if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
        $requestedStatus = $existingDocument['status'] ?? 'published';
    }
    if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
        $requestedStatus = (($existingDocument['status'] ?? '') === 'published') ? 'published' : 'pending';
    }
    // Při první publikaci aktualizovat created_at
    $publishingNow = $requestedStatus === 'published' && ($existingDocument['status'] ?? '') !== 'published';

    $set = "title = ?, slug = ?, board_type = ?, excerpt = ?, description = ?, category_id = ?,
            posted_date = ?, removal_date = ?, image_file = ?, contact_name = ?, contact_phone = ?,
            contact_email = ?, is_pinned = ?, is_published = ?, publish_at = ?, unpublish_at = ?, status = ?, author_id = COALESCE(author_id, ?)";
    if ($publishingNow) {
        $set .= ", created_at = NOW()";
    }
    $params = [
        $title,
        $slug,
        $boardType,
        $excerpt,
        $description,
        $categoryId,
        $postedDate,
        $removalDate,
        $boardImageFilename,
        $contactName,
        $contactPhone,
        $contactEmail,
        $isPinned,
        $isPublished,
        $publishAtSql,
        $unpublishAtSql,
        $requestedStatus,
        currentUserId(),
    ];

    if ($newFilename !== null) {
        $set .= ", filename = ?, original_name = ?, file_size = ?";
        $params[] = $newFilename;
        $params[] = $newOriginalName;
        $params[] = $newFileSize;
    }

    $params[] = $id;
    $pdo->prepare("UPDATE cms_board SET {$set} WHERE id = ?")->execute($params);
    $newSnapshot = $boardRevisionSnapshot([
        'title' => $title,
        'slug' => $slug,
        'board_type' => $boardType,
        'category_id' => $categoryId,
        'excerpt' => $excerpt,
        'description' => $description,
        'posted_date' => $postedDate,
        'removal_date' => $removalDate,
        'contact_name' => $contactName,
        'contact_phone' => $contactPhone,
        'contact_email' => $contactEmail,
        'is_pinned' => $isPinned,
        'is_published' => $isPublished,
    ]);
    saveRevision($pdo, 'board', $id, $oldSnapshot, $newSnapshot);
    upsertPathRedirect($pdo, $oldPath, boardPublicPath(['id' => $id, 'slug' => $slug]));
    logAction('board_edit', "id={$id} title={$title} slug={$slug} type={$boardType} pinned={$isPinned}");
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

    $previewToken = bin2hex(random_bytes(16));
    $pdo->prepare(
        "INSERT INTO cms_board
         (title, slug, board_type, excerpt, description, category_id, posted_date, removal_date,
          image_file, contact_name, contact_phone, contact_email, filename, original_name, file_size,
          is_pinned, is_published, publish_at, unpublish_at, status, author_id, preview_token)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $title,
        $slug,
        $boardType,
        $excerpt,
        $description,
        $categoryId,
        $postedDate,
        $removalDate,
        $boardImageFilename,
        $contactName,
        $contactPhone,
        $contactEmail,
        $newFilename ?? '',
        $newOriginalName ?? '',
        $newFileSize ?? 0,
        $isPinned,
        $visible,
        $publishAtSql,
        $unpublishAtSql,
        $status,
        currentUserId(),
        $previewToken,
    ]);

    $id = (int)$pdo->lastInsertId();
    logAction('board_add', "id={$id} title={$title} slug={$slug} type={$boardType} status={$status} pinned={$isPinned}");
    if ($status === 'pending') {
        notifyPendingContent('Vývěska', $title, '/admin/board.php');
    }
}

// Uvolnění zámku obsahu po úspěšném uložení
if ($id !== null) {
    releaseContentLock('board', $id);
}

header('Location: ' . BASE_URL . '/admin/board.php');
exit;
