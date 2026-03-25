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

if ($validFrom !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom)) {
    $validFrom = null;
}
if ($validTo !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validTo)) {
    $validTo = null;
}

if ($title === '') {
    header('Location: ' . BASE_URL . '/admin/food_form.php' . ($id ? '?id=' . $id . '&err=required' : '?err=required'));
    exit;
}

$existingCard = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT id, status FROM cms_food_cards WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingCard = $existingStmt->fetch() ?: null;
    if (!$existingCard) {
        header('Location: ' . BASE_URL . '/admin/food.php');
        exit;
    }
}

$slug = foodCardSlug($submittedSlug !== '' ? $submittedSlug : $title);
if ($slug === '') {
    header('Location: ' . BASE_URL . '/admin/food_form.php' . ($id ? '?id=' . $id . '&err=slug' : '?err=slug'));
    exit;
}

$uniqueSlug = uniqueFoodCardSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    header('Location: ' . BASE_URL . '/admin/food_form.php' . ($id ? '?id=' . $id . '&err=slug' : '?err=slug'));
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
}

header('Location: ' . BASE_URL . '/admin/food.php');
exit;
