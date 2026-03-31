<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu jídelních lístků nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$type = in_array($_POST['type'] ?? '', ['food', 'beverage'], true) ? (string)$_POST['type'] : 'food';
$title = trim($_POST['title'] ?? '');
$submittedSlug = trim($_POST['slug'] ?? '');
$description = trim($_POST['description'] ?? '');
$content = trim($_POST['content'] ?? '');
$validFrom = trim($_POST['valid_from'] ?? '') ?: null;
$validTo = trim($_POST['valid_to'] ?? '') ?: null;
$fallback = BASE_URL . '/admin/food_form.php' . ($id ? '?id=' . $id : '');
$isValidDate = static function (string $value): bool {
    $dateTime = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    $hasErrors = is_array($errors)
        && (((int)($errors['warning_count'] ?? 0)) > 0 || ((int)($errors['error_count'] ?? 0)) > 0);

    return $dateTime !== false && !$hasErrors && $dateTime->format('Y-m-d') === $value;
};

if ($validFrom !== null && !$isValidDate($validFrom)) {
    header('Location: ' . appendUrlQuery($fallback, ['err' => 'valid_from']));
    exit;
}
if ($validTo !== null && !$isValidDate($validTo)) {
    header('Location: ' . appendUrlQuery($fallback, ['err' => 'valid_to']));
    exit;
}
if ($validFrom !== null && $validTo !== null && $validTo < $validFrom) {
    header('Location: ' . appendUrlQuery($fallback, ['err' => 'valid_range']));
    exit;
}

if ($title === '') {
    header('Location: ' . appendUrlQuery($fallback, ['err' => 'required']));
    exit;
}

$existingCard = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT * FROM cms_food_cards WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingCard = $existingStmt->fetch() ?: null;
    if (!$existingCard) {
        header('Location: ' . BASE_URL . '/admin/food.php');
        exit;
    }
}

$slug = foodCardSlug($submittedSlug !== '' ? $submittedSlug : $title);
if ($slug === '') {
    header('Location: ' . appendUrlQuery($fallback, ['err' => 'slug']));
    exit;
}

$uniqueSlug = uniqueFoodCardSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    header('Location: ' . appendUrlQuery($fallback, ['err' => 'slug']));
    exit;
}
$slug = $uniqueSlug;

$canApproveContent = currentUserHasCapability('content_approve_shared');
$isCurrent = $canApproveContent && isset($_POST['is_current']) ? 1 : 0;
$isPublished = $canApproveContent && isset($_POST['is_published']) ? 1 : 0;

if ($isCurrent) {
    $excludeId = $id ?? 0;
    $pdo->prepare(
        "UPDATE cms_food_cards SET is_current = 0 WHERE type = ? AND id != ?"
    )->execute([$type, $excludeId]);
}

if ($existingCard) {
    $oldSnapshot = foodRevisionSnapshot($existingCard);
    $oldPath = foodCardPublicPath($existingCard);

    if ($canApproveContent) {
        $pdo->prepare(
            "UPDATE cms_food_cards
             SET type = ?, title = ?, slug = ?, description = ?, content = ?,
                 valid_from = ?, valid_to = ?, is_current = ?, is_published = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([
            $type,
            $title,
            $slug,
            $description,
            $content,
            $validFrom,
            $validTo,
            $isCurrent,
            $isPublished,
            $id,
        ]);
    } else {
        $pdo->prepare(
            "UPDATE cms_food_cards
             SET type = ?, title = ?, slug = ?, description = ?, content = ?,
                 valid_from = ?, valid_to = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([
            $type,
            $title,
            $slug,
            $description,
            $content,
            $validFrom,
            $validTo,
                $id,
        ]);
    }

    saveRevision($pdo, 'food', $id, $oldSnapshot, foodRevisionSnapshot([
        'type' => $type,
        'title' => $title,
        'slug' => $slug,
        'description' => $description,
        'content' => $content,
        'valid_from' => $validFrom,
        'valid_to' => $validTo,
        'is_current' => $canApproveContent ? $isCurrent : (int)($existingCard['is_current'] ?? 0),
        'is_published' => $canApproveContent ? $isPublished : (int)($existingCard['is_published'] ?? 0),
        'status' => (string)($existingCard['status'] ?? 'published'),
    ]));
    upsertPathRedirect($pdo, $oldPath, foodCardPublicPath(['id' => $id, 'slug' => $slug]));
    logAction('food_edit', "id={$id} type={$type} slug={$slug}");
} else {
    $status = $canApproveContent ? 'published' : 'pending';
    $visible = $canApproveContent ? $isPublished : 0;
    $current = $canApproveContent ? $isCurrent : 0;
    $pdo->prepare(
        "INSERT INTO cms_food_cards
         (type, title, slug, description, content, valid_from, valid_to, is_current, is_published, status, author_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $type,
        $title,
        $slug,
        $description,
        $content,
        $validFrom,
        $validTo,
        $current,
        $visible,
        $status,
        currentUserId(),
    ]);
    $id = (int)$pdo->lastInsertId();
    logAction('food_add', "id={$id} title={$title} type={$type} slug={$slug} status={$status}");
    if ($status === 'pending') {
        notifyPendingContent('Jídelníček', $title, '/admin/food.php');
    }
}

header('Location: ' . BASE_URL . '/admin/food.php');
exit;
