<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu událostí nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$title = trim((string)($_POST['title'] ?? ''));
$submittedSlug = trim((string)($_POST['slug'] ?? ''));
$eventKind = normalizeEventKind((string)($_POST['event_kind'] ?? 'general'));
$excerpt = trim((string)($_POST['excerpt'] ?? ''));
$description = (string)($_POST['description'] ?? '');
$programNote = (string)($_POST['program_note'] ?? '');
$location = trim((string)($_POST['location'] ?? ''));
$organizerName = trim((string)($_POST['organizer_name'] ?? ''));
$organizerEmailInput = trim((string)($_POST['organizer_email'] ?? ''));
$registrationUrlInput = trim((string)($_POST['registration_url'] ?? ''));
$priceNote = trim((string)($_POST['price_note'] ?? ''));
$accessibilityNote = trim((string)($_POST['accessibility_note'] ?? ''));
$isPublished = isset($_POST['is_published']) ? 1 : 0;
$adminNote = trim((string)($_POST['admin_note'] ?? ''));
$deleteImage = isset($_POST['event_image_delete']);

$redirectBase = BASE_URL . '/admin/event_form.php';
$redirectToForm = static function (string $errorCode) use ($redirectBase, $id): never {
    $query = $id !== null
        ? '?id=' . $id . '&err=' . rawurlencode($errorCode)
        : '?err=' . rawurlencode($errorCode);
    header('Location: ' . $redirectBase . $query);
    exit;
};

$unpublishAtInput = trim((string)($_POST['unpublish_at'] ?? ''));
$unpublishAtSql = null;
if ($unpublishAtInput !== '') {
    $dateTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $unpublishAtInput);
    if (!$dateTime || $dateTime->format('Y-m-d\TH:i') !== $unpublishAtInput) {
        $redirectToForm('unpublish_at');
    }
    $unpublishAtSql = $dateTime->format('Y-m-d H:i:s');
}

$eventDateInput = trim((string)($_POST['event_date'] ?? ''));
$eventTimeInput = trim((string)($_POST['event_time'] ?? '')) ?: '00:00';
$eventDateTime = null;
if ($eventDateInput !== '') {
    $eventDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $eventDateInput . ' ' . $eventTimeInput);
}

$eventEndInput = trim((string)($_POST['event_end_date'] ?? ''));
$eventEndTimeInput = trim((string)($_POST['event_end_time'] ?? '')) ?: '00:00';
$eventEndTime = null;
if ($eventEndInput !== '') {
    $eventEndTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $eventEndInput . ' ' . $eventEndTimeInput);
    if (!$eventEndTime || $eventEndTime->format('Y-m-d H:i') !== ($eventEndInput . ' ' . $eventEndTimeInput)) {
        $redirectToForm('dates');
    }
}

if ($title === '' || !$eventDateTime || $eventDateTime->format('Y-m-d H:i') !== ($eventDateInput . ' ' . $eventTimeInput)) {
    $redirectToForm('required');
}

$eventDate = $eventDateTime->format('Y-m-d H:i:s');
$eventEnd = null;
if ($eventEndTime) {
    $eventEnd = $eventEndTime->format('Y-m-d H:i:s');
    if ($eventEndTime < $eventDateTime) {
        $redirectToForm('dates');
    }
}

$organizerEmail = '';
if ($organizerEmailInput !== '') {
    $validatedEmail = filter_var($organizerEmailInput, FILTER_VALIDATE_EMAIL);
    if (!is_string($validatedEmail)) {
        $redirectToForm('organizer_email');
    }
    $organizerEmail = $validatedEmail;
}

$registrationUrl = normalizeDownloadExternalUrl($registrationUrlInput);
if ($registrationUrlInput !== '' && $registrationUrl === '') {
    $redirectToForm('registration_url');
}

$slug = eventSlug($submittedSlug !== '' ? $submittedSlug : $title);
if ($slug === '') {
    $redirectToForm('slug');
}

$uniqueSlug = uniqueEventSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    $redirectToForm('slug');
}
$slug = $uniqueSlug;

$existingEvent = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT * FROM cms_events WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingEvent = $existingStmt->fetch() ?: null;
    if (!$existingEvent) {
        header('Location: ' . BASE_URL . '/admin/events.php');
        exit;
    }
}

$imageFilename = (string)($existingEvent['image_file'] ?? '');
$imageUpload = uploadEventImage($_FILES['event_image'] ?? [], $imageFilename);
if ($imageUpload['error'] !== '') {
    $redirectToForm('image');
}
$imageFilename = $imageUpload['filename'];
if ($deleteImage && $imageFilename !== '') {
    deleteEventImageFile($imageFilename);
    $imageFilename = '';
}

if ($id !== null && $existingEvent) {
    if (($existingEvent['preview_token'] ?? '') === '') {
        $previewToken = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE cms_events SET preview_token = ? WHERE id = ?")->execute([$previewToken, $id]);
    }

    $oldSnapshot = eventRevisionSnapshot($existingEvent);
    $oldPath = eventPublicPath($existingEvent);

    $stmt = $pdo->prepare(
        "UPDATE cms_events
         SET title = ?, slug = ?, event_kind = ?, excerpt = ?, description = ?, program_note = ?,
             location = ?, organizer_name = ?, organizer_email = ?, registration_url = ?, price_note = ?,
             accessibility_note = ?, image_file = ?, event_date = ?, event_end = ?, is_published = ?,
             unpublish_at = ?, admin_note = ?, updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([
        $title,
        $slug,
        $eventKind,
        $excerpt,
        $description,
        $programNote,
        $location,
        $organizerName,
        $organizerEmail,
        $registrationUrl,
        $priceNote,
        $accessibilityNote,
        $imageFilename,
        $eventDate,
        $eventEnd,
        $isPublished,
        $unpublishAtSql,
        $adminNote,
        $id,
    ]);

    saveRevision($pdo, 'event', $id, $oldSnapshot, eventRevisionSnapshot([
        'title' => $title,
        'slug' => $slug,
        'event_kind' => $eventKind,
        'excerpt' => $excerpt,
        'description' => $description,
        'program_note' => $programNote,
        'location' => $location,
        'event_date' => $eventDate,
        'event_end' => $eventEnd,
        'organizer_name' => $organizerName,
        'organizer_email' => $organizerEmail,
        'registration_url' => $registrationUrl,
        'price_note' => $priceNote,
        'accessibility_note' => $accessibilityNote,
        'unpublish_at' => $unpublishAtSql,
        'admin_note' => $adminNote,
        'is_published' => $isPublished,
    ]));
    upsertPathRedirect($pdo, $oldPath, eventPublicPath(['id' => $id, 'slug' => $slug]));
    logAction('event_edit', "id={$id} title={$title} slug={$slug} kind={$eventKind}");
} else {
    $status = currentUserHasCapability('content_approve_shared') ? 'published' : 'pending';
    $visible = currentUserHasCapability('content_approve_shared') ? $isPublished : 0;

    $previewToken = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare(
        "INSERT INTO cms_events (
            title, slug, event_kind, excerpt, description, program_note, location,
            organizer_name, organizer_email, registration_url, price_note, accessibility_note,
            image_file, event_date, event_end, is_published, unpublish_at, admin_note, status, preview_token
         ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $title,
        $slug,
        $eventKind,
        $excerpt,
        $description,
        $programNote,
        $location,
        $organizerName,
        $organizerEmail,
        $registrationUrl,
        $priceNote,
        $accessibilityNote,
        $imageFilename,
        $eventDate,
        $eventEnd,
        $visible,
        $unpublishAtSql,
        $adminNote,
        $status,
        $previewToken,
    ]);
    $id = (int)$pdo->lastInsertId();
    logAction('event_add', "id={$id} title={$title} slug={$slug} kind={$eventKind} status={$status}");
    if ($status === 'pending') {
        notifyPendingContent('Událost', $title, '/admin/events.php');
    }
}

header('Location: ' . BASE_URL . '/admin/events.php');
exit;
