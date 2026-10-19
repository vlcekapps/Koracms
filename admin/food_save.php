<?php

require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu jídelních lístků nemáte potřebné oprávnění.');
requireModuleEnabled('food');
requireHttpMethods(['POST']);
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$textField = static fn (string $key): string => is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
$type = in_array($_POST['type'] ?? '', ['food', 'beverage'], true) ? (string)$_POST['type'] : 'food';
$title = $textField('title');
$submittedSlug = $textField('slug');
$description = $textField('description');
$content = $textField('content');
$validFromInput = $textField('valid_from');
$validToInput = $textField('valid_to');
$validFrom = $validFromInput !== '' ? $validFromInput : null;
$validTo = $validToInput !== '' ? $validToInput : null;
$ordersEnabled = isset($_POST['orders_enabled']) ? 1 : 0;
$orderEmail = $textField('order_email');
$orderInstructions = $textField('order_instructions');
$orderFulfillmentModes = normalizeFoodOrderFulfillmentModes($_POST['order_fulfillment_modes'] ?? []);
$orderRequestedAtEnabled = isset($_POST['order_requested_at_enabled']) ? 1 : 0;
$fallback = BASE_URL . '/admin/food_form.php' . ($id !== null ? '?id=' . $id : '');
$redirectToForm = static function (string $error) use ($fallback, $id): void {
    adminEditorFormFlashStore('food', $id, $_POST);
    header('Location: ' . appendUrlQuery($fallback, ['err' => $error]));
    exit;
};
$isValidDate = static function (string $value): bool {
    $dateTime = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    $hasErrors = is_array($errors)
        && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

    return $dateTime !== false && !$hasErrors && $dateTime->format('Y-m-d') === $value;
};

if ($validFrom !== null && !$isValidDate($validFrom)) {
    $redirectToForm('valid_from');
}
if ($validTo !== null && !$isValidDate($validTo)) {
    $redirectToForm('valid_to');
}
if ($validFrom !== null && $validTo !== null && $validTo < $validFrom) {
    $redirectToForm('valid_range');
}
if ($orderEmail !== '' && !filter_var($orderEmail, FILTER_VALIDATE_EMAIL)) {
    $redirectToForm('order_email');
}
if ($title === '') {
    $redirectToForm('required');
}

$slug = foodCardSlug($submittedSlug !== '' ? $submittedSlug : $title);
if ($slug === '') {
    $redirectToForm('slug');
}
$uniqueSlug = uniqueFoodCardSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    $redirectToForm('slug');
}
$slug = $uniqueSlug;
$canApproveContent = currentUserHasCapability('content_approve_shared');
$isCurrent = $canApproveContent && isset($_POST['is_current']) ? 1 : 0;
$isPublished = $canApproveContent && isset($_POST['is_published']) ? 1 : 0;
$wasNew = $id === null;
$status = $canApproveContent ? 'published' : 'pending';

try {
    $pdo->beginTransaction();
    $existingCard = null;
    if ($id !== null) {
        $existingStmt = $pdo->prepare('SELECT * FROM cms_food_cards WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $existingStmt->execute([$id]);
        $existingCard = $existingStmt->fetch() ?: null;
        if ($existingCard === null) {
            $pdo->rollBack();
            $redirectToForm('missing');
        }
    }

    if ($isCurrent) {
        $pdo->prepare('UPDATE cms_food_cards SET is_current = 0 WHERE type = ? AND id != ?')->execute([$type, $id ?? 0]);
    }
    $values = [$type, $title, $slug, $description, $content, $validFrom, $validTo, $ordersEnabled,
        $orderEmail, $orderInstructions, implode(',', $orderFulfillmentModes), $orderRequestedAtEnabled];
    if ($existingCard !== null) {
        $publicationSql = $canApproveContent ? ', is_current = ?, is_published = ?' : '';
        if ($canApproveContent) {
            $values[] = $isCurrent;
            $values[] = $isPublished;
        }
        $values[] = $id;
        $pdo->prepare(
            "UPDATE cms_food_cards SET type = ?, title = ?, slug = ?, description = ?, content = ?,
             valid_from = ?, valid_to = ?, orders_enabled = ?, order_email = ?, order_instructions = ?,
             order_fulfillment_modes = ?, order_requested_at_enabled = ?{$publicationSql}, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL"
        )->execute($values);
        $savedStmt = $pdo->prepare('SELECT * FROM cms_food_cards WHERE id = ?');
        $savedStmt->execute([$id]);
        $savedCard = $savedStmt->fetch();
        saveRevision($pdo, 'food', $id, foodRevisionSnapshot($existingCard), foodRevisionSnapshot($savedCard), true);
        upsertPathRedirect($pdo, foodCardPublicPath($existingCard), foodCardPublicPath($savedCard), 301, true);
    } else {
        $pdo->prepare(
            'INSERT INTO cms_food_cards (type, title, slug, description, content, valid_from, valid_to,
             orders_enabled, order_email, order_instructions, order_fulfillment_modes, order_requested_at_enabled,
             is_current, is_published, status, author_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([...$values, $isCurrent, $isPublished, $status, currentUserId()]);
        $id = (int)$pdo->lastInsertId();
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    koraLog('warning', 'food card save failed', ['id' => $id, 'exception' => $exception]);
    $isSlugConflict = $exception instanceof PDOException && (int)($exception->errorInfo[1] ?? 0) === 1062;
    $redirectToForm($isSlugConflict ? 'slug' : 'save');
}

adminEditorFormFlashTake('food', $wasNew ? null : $id);
logAction($wasNew ? 'food_add' : 'food_edit', "id={$id} type={$type} slug={$slug}");
if ($wasNew && $status === 'pending') {
    try {
        notifyPendingContent('Jídelníček', $title, '/admin/food.php');
    } catch (Throwable $exception) {
        koraLog('warning', 'food pending notification failed', ['id' => $id, 'exception' => $exception]);
    }
}
header('Location: ' . BASE_URL . '/admin/food.php');
exit;
