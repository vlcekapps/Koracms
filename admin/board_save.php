<?php

require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu úřední desky nemáte potřebné oprávnění.');
requireModuleEnabled('board');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$formId = $id;
$existingDocument = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT * FROM cms_board WHERE id = ? AND deleted_at IS NULL");
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
    adminEditorFormFlashStore('board', $documentId, $_POST);
    $params = ['err' => $errorCode];
    if ($documentId !== null) {
        $params['id'] = (string)$documentId;
    }
    header('Location: ' . internalRedirectTarget(BASE_URL . appendUrlQuery('/admin/board_form.php', $params), BASE_URL . '/admin/board.php'));
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
$publishAtSql = validateDateTimeLocal($publishAtInput);

$unpublishAtInput = trim((string)($_POST['unpublish_at'] ?? ''));
$unpublishAtSql = validateDateTimeLocal($unpublishAtInput);

if (array_key_exists('board_image_delete', $_POST)
    || (isset($_POST['confirm_board_image_delete']) && $_POST['confirm_board_image_delete'] !== '1')) {
    $redirectToForm($id, 'image_confirm');
}
if ($publishAtInput !== '' && $publishAtSql === null) {
    $redirectToForm($id, 'publish_at');
}
if ($unpublishAtInput !== '' && $unpublishAtSql === null) {
    $redirectToForm($id, 'unpublish_at');
}
if ($publishAtSql !== null && $unpublishAtSql !== null && $unpublishAtSql <= $publishAtSql) {
    $redirectToForm($id, 'publish_range');
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
    if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
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

if ($categoryId !== null) {
    $categoryCheck = $pdo->prepare("SELECT id FROM cms_board_categories WHERE id = ?");
    $categoryCheck->execute([$categoryId]);
    if (!$categoryCheck->fetch()) {
        $redirectToForm($id, 'category');
    }
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

$imageUpload = ['uploaded' => false, 'filename' => ''];
$storedFileUpload = ['uploaded' => false, 'filename' => ''];
$noticeDocument = null;
$saveError = 'save';
try {
    $pdo->beginTransaction();
    if ($id !== null) {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $current = $pdo->prepare('SELECT * FROM cms_board WHERE id = ? AND deleted_at IS NULL' . $lock);
        $current->execute([$id]);
        $existingDocument = $current->fetch() ?: null;
        if ($existingDocument === null) {
            throw new RuntimeException('Položka vývěsky již není dostupná pro úpravu.');
        }
    }
    $oldFiles = $existingDocument ?? [];
    $boardImageFilename = (string)($existingDocument['image_file'] ?? '');
    // Upload must not remove the old file before its database owner is committed.
    $imageUpload = uploadBoardImage($_FILES['board_image'] ?? [], '');
    if ($imageUpload['error'] !== '') {
        $saveError = 'image';
        throw new RuntimeException((string)$imageUpload['error']);
    }
    if ($imageUpload['uploaded']) {
        $boardImageFilename = (string)$imageUpload['filename'];
    }
    if (($_POST['confirm_board_image_delete'] ?? '') === '1' && !$imageUpload['uploaded']) {
        $boardImageFilename = '';
    }

    $newFilename = null;
    $newOriginalName = null;
    $newFileSize = null;

    if (koraUploadHasFile($_FILES['file'] ?? null)) {
        $storedFileUpload = uploadBoardStoredFile(
            is_array($_FILES['file'] ?? null) ? $_FILES['file'] : [],
            ''
        );
        if ((string)$storedFileUpload['error'] !== '') {
            $saveError = 'file';
            throw new RuntimeException((string)$storedFileUpload['error']);
        }
        if (!empty($storedFileUpload['uploaded'])) {
            $newFilename = (string)$storedFileUpload['filename'];
            $newOriginalName = (string)$storedFileUpload['original_name'];
            $newFileSize = (int)$storedFileUpload['file_size'];
        }
    }

    if ($id !== null) {
        $oldPath = boardPublicPath($existingDocument);
        $oldPublic = boardIsPubliclyReachable($existingDocument);
        $oldAttachmentChecksum = boardAttachmentChecksum($existingDocument);
        $oldFilename = trim((string)($existingDocument['filename'] ?? ''));

        if (($existingDocument['preview_token'] ?? '') === '') {
            $previewToken = bin2hex(random_bytes(16));
            $pdo->prepare("UPDATE cms_board SET preview_token = ? WHERE id = ?")->execute([$previewToken, $id]);
        }

        $oldSnapshot = $boardRevisionSnapshot($existingDocument);
        $requestedStatus = trim($_POST['article_status'] ?? '');
        if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
            $requestedStatus = $existingDocument['status'] ?? 'published';
        }
        if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
            $requestedStatus = (($existingDocument['status'] ?? '') === 'published') ? 'published' : 'pending';
        }
        // Při první publikaci aktualizovat created_at
        $publishingNow = $requestedStatus === 'published' && ($existingDocument['status'] ?? '') !== 'published';

        $set = "title=?, slug=?, board_type=?, excerpt=?, description=?, category_id=?,
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
        $documentStmt = $pdo->prepare("SELECT * FROM cms_board WHERE id = ?");
        $documentStmt->execute([$id]);
        $updatedDocument = $documentStmt->fetch() ?: [
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
            'category_id' => $categoryId,
            'posted_date' => $postedDate,
            'removal_date' => $removalDate,
            'filename' => $newFilename ?? (string)($existingDocument['filename'] ?? ''),
            'original_name' => $newOriginalName ?? (string)($existingDocument['original_name'] ?? ''),
            'file_size' => $newFileSize ?? (int)($existingDocument['file_size'] ?? 0),
            'is_published' => $isPublished,
            'status' => $requestedStatus,
            'publish_at' => $publishAtSql,
            'unpublish_at' => $unpublishAtSql,
            'deleted_at' => null,
        ];
        $newPublic = boardIsPubliclyReachable($updatedDocument);
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
        saveRevision($pdo, 'board', $id, $oldSnapshot, $newSnapshot, true);
        if ($oldPublic && $newPublic) {
            $newPath = boardPublicPath($updatedDocument);
            upsertPathRedirect($pdo, $oldPath, $newPath, 301, true);
            if ($oldPath !== $newPath) {
                recordBoardPublicationEvent($pdo, $updatedDocument, 'url_changed', currentUserId(), true);
            }
        }
        if (!$oldPublic && $newPublic) {
            recordBoardPublicationEvent($pdo, $updatedDocument, 'published', currentUserId(), true);
            $noticeDocument = $updatedDocument;
        } elseif ($oldPublic && !$newPublic) {
            $eventType = !empty($removalDate) && (string)$removalDate < date('Y-m-d') ? 'removed' : 'hidden';
            recordBoardPublicationEvent($pdo, $updatedDocument, $eventType, currentUserId(), true);
        } elseif ($oldPublic && $newPublic) {
            $newAttachmentChecksum = boardAttachmentChecksum($updatedDocument);
            $newFilenameStored = trim((string)($updatedDocument['filename'] ?? ''));
            if ($oldFilename !== $newFilenameStored || $oldAttachmentChecksum !== $newAttachmentChecksum) {
                recordBoardPublicationEvent($pdo, $updatedDocument, 'attachment_changed', currentUserId(), true);
            }
        }
        logAction('board_edit', "id={$id} name={$title} slug={$slug} type={$boardType} pinned={$isPinned}");
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
        $documentStmt = $pdo->prepare("SELECT * FROM cms_board WHERE id = ?");
        $documentStmt->execute([$id]);
        $createdDocument = $documentStmt->fetch() ?: [
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
            'category_id' => $categoryId,
            'posted_date' => $postedDate,
            'removal_date' => $removalDate,
            'filename' => $newFilename ?? '',
            'original_name' => $newOriginalName ?? '',
            'file_size' => $newFileSize ?? 0,
            'is_published' => $visible,
            'status' => $status,
            'publish_at' => $publishAtSql,
            'unpublish_at' => $unpublishAtSql,
            'deleted_at' => null,
        ];
        if (boardIsPubliclyReachable($createdDocument)) {
            recordBoardPublicationEvent($pdo, $createdDocument, 'published', currentUserId(), true);
            $noticeDocument = $createdDocument;
        }
        logAction('board_add', "id={$id} name={$title} slug={$slug} type={$boardType} status={$status} pinned={$isPinned}");
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($imageUpload['uploaded']) {
        deleteBoardImageFile((string)$imageUpload['filename']);
    }
    if ($storedFileUpload['uploaded']) {
        deleteBoardStoredFile((string)$storedFileUpload['filename']);
    }
    koraLog('error', 'board save failed', ['board_id' => $formId, 'exception' => $exception]);
    $redirectToForm($formId, $saveError);
}
deleteUnusedBoardFiles($pdo, $oldFiles);
// SMTP is not part of the content transaction; never claim a committed save failed.
try {
    if ($noticeDocument !== null) {
        $sentNotifications = notifyBoardSubscribers($pdo, $noticeDocument);
        if ($sentNotifications > 0) {
            logAction('board_notify', "id={$id} sent={$sentNotifications}");
        }
    }
    if ($formId === null && $requestedStatus === 'pending') {
        notifyPendingContent('Vývěska', $title, '/admin/board.php');
    }
} catch (Throwable $exception) {
    koraLog('warning', 'board notification failed', ['board_id' => $id, 'exception' => $exception]);
}

// Uvolnění zámku obsahu po úspěšném uložení
releaseContentLock('board', $id);

header('Location: ' . BASE_URL . '/admin/board.php');
exit;
