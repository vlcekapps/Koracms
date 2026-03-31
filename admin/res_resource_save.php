<?php
require_once __DIR__ . '/../db.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu zdrojů rezervací nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$formUrl = BASE_URL . '/admin/res_resource_form.php';
$listUrl = BASE_URL . '/admin/res_resources.php';

$name = trim($_POST['name'] ?? '');
$slug = trim($_POST['slug'] ?? '');
$description = trim($_POST['description'] ?? '');
$categoryId = inputInt('post', 'category_id');
$capacity = max(1, (int)($_POST['capacity'] ?? 1));
$locationIds = array_filter(array_map('intval', $_POST['location_ids'] ?? []));
$slotMode = in_array($_POST['slot_mode'] ?? '', ['slots', 'range', 'duration'], true) ? (string)$_POST['slot_mode'] : 'slots';
$slotDurationMin = max(1, (int)($_POST['slot_duration_min'] ?? 60));
$minAdvanceHours = max(0, (int)($_POST['min_advance_hours'] ?? 0));
$maxAdvanceDays = max(1, (int)($_POST['max_advance_days'] ?? 30));
$cancellationHours = max(0, (int)($_POST['cancellation_hours'] ?? 0));
$requiresApproval = isset($_POST['requires_approval']) ? 1 : 0;
$allowGuests = isset($_POST['allow_guests']) ? 1 : 0;
$maxConcurrent = max(1, (int)($_POST['max_concurrent'] ?? 1));
$hoursData = $_POST['hours'] ?? [];
$slotsData = $_POST['slots'] ?? [];
$blockedIds = $_POST['blocked_ids'] ?? [];
$blockedDates = $_POST['blocked_dates'] ?? [];
$blockedReasons = $_POST['blocked_reasons'] ?? [];

$redirectToForm = static function (?int $resourceId, string $error = '') use ($formUrl): string {
    $params = [];
    if ($resourceId !== null) {
        $params['id'] = $resourceId;
    }
    if ($error !== '') {
        $params['err'] = $error;
    }

    return appendUrlQuery($formUrl, $params);
};
$dateTimeHasErrors = static function ($errors): bool {
    return is_array($errors)
        && (((int)($errors['warning_count'] ?? 0)) > 0 || ((int)($errors['error_count'] ?? 0)) > 0);
};
$isValidTime = static function (string $value) use ($dateTimeHasErrors): bool {
    $dateTime = DateTime::createFromFormat('H:i', $value);
    $errors = DateTime::getLastErrors();

    return $dateTime !== false
        && !$dateTimeHasErrors($errors)
        && $dateTime->format('H:i') === $value;
};
$isValidDate = static function (string $value) use ($dateTimeHasErrors): bool {
    $dateTime = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    return $dateTime !== false
        && !$dateTimeHasErrors($errors)
        && $dateTime->format('Y-m-d') === $value;
};

if ($slug === '' && $name !== '') {
    $slug = reservationResourceSlug($name);
}
$slug = reservationResourceSlug($slug);

if ($name === '') {
    header('Location: ' . $redirectToForm($id, 'name'));
    exit;
}
if ($slug === '') {
    header('Location: ' . $redirectToForm($id, 'slug'));
    exit;
}
if ($capacity < 1) {
    header('Location: ' . $redirectToForm($id, 'capacity'));
    exit;
}

$stmtSlug = $pdo->prepare('SELECT id FROM cms_res_resources WHERE slug = ? AND id != ?');
$stmtSlug->execute([$slug, $id ?? 0]);
if ($stmtSlug->fetch()) {
    header('Location: ' . $redirectToForm($id, 'slug'));
    exit;
}

for ($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++) {
    $isClosed = isset($hoursData[$dayOfWeek]['is_closed']);
    $openTime = trim((string)($hoursData[$dayOfWeek]['open_time'] ?? '09:00'));
    $closeTime = trim((string)($hoursData[$dayOfWeek]['close_time'] ?? '17:00'));

    if ($isClosed) {
        continue;
    }

    if (!$isValidTime($openTime) || !$isValidTime($closeTime) || $openTime >= $closeTime) {
        header('Location: ' . $redirectToForm($id, 'hours'));
        exit;
    }
}

if ($slotMode === 'slots') {
    for ($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++) {
        $startTimes = $slotsData[$dayOfWeek]['start_time'] ?? [];
        $endTimes = $slotsData[$dayOfWeek]['end_time'] ?? [];

        for ($slotIndex = 0; $slotIndex < count($startTimes); $slotIndex++) {
            $startTime = trim((string)($startTimes[$slotIndex] ?? ''));
            $endTime = trim((string)($endTimes[$slotIndex] ?? ''));

            if ($startTime === '' && $endTime === '') {
                continue;
            }

            if (!$isValidTime($startTime) || !$isValidTime($endTime) || $startTime >= $endTime) {
                header('Location: ' . $redirectToForm($id, 'slots'));
                exit;
            }
        }
    }
}

for ($blockedIndex = 0; $blockedIndex < count($blockedDates); $blockedIndex++) {
    $blockedDate = trim((string)($blockedDates[$blockedIndex] ?? ''));
    if ($blockedDate === '') {
        continue;
    }

    if (!$isValidDate($blockedDate)) {
        header('Location: ' . $redirectToForm($id, 'blocked_date'));
        exit;
    }
}

$action = $id !== null ? 'res_resource_edit' : 'res_resource_add';

try {
    $pdo->beginTransaction();

    if ($id !== null) {
        $existingStmt = $pdo->prepare('SELECT id FROM cms_res_resources WHERE id = ?');
        $existingStmt->execute([$id]);
        if (!$existingStmt->fetch()) {
            $pdo->rollBack();
            header('Location: ' . $listUrl);
            exit;
        }

        $pdo->prepare(
            'UPDATE cms_res_resources
             SET name = ?, slug = ?, description = ?, category_id = ?,
                 capacity = ?, slot_mode = ?, slot_duration_min = ?,
                 min_advance_hours = ?, max_advance_days = ?, cancellation_hours = ?,
                 requires_approval = ?, allow_guests = ?, max_concurrent = ?
             WHERE id = ?'
        )->execute([
            $name,
            $slug,
            $description !== '' ? $description : null,
            $categoryId,
            $capacity,
            $slotMode,
            $slotDurationMin,
            $minAdvanceHours,
            $maxAdvanceDays,
            $cancellationHours,
            $requiresApproval,
            $allowGuests,
            $maxConcurrent,
            $id,
        ]);
    } else {
        $pdo->prepare(
            'INSERT INTO cms_res_resources
                (name, slug, description, category_id, capacity, slot_mode, slot_duration_min,
                 min_advance_hours, max_advance_days, cancellation_hours, requires_approval, allow_guests, max_concurrent, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        )->execute([
            $name,
            $slug,
            $description !== '' ? $description : null,
            $categoryId,
            $capacity,
            $slotMode,
            $slotDurationMin,
            $minAdvanceHours,
            $maxAdvanceDays,
            $cancellationHours,
            $requiresApproval,
            $allowGuests,
            $maxConcurrent,
        ]);

        $id = (int)$pdo->lastInsertId();
    }

    $pdo->prepare('DELETE FROM cms_res_resource_locations WHERE resource_id = ?')->execute([$id]);
    if ($locationIds !== []) {
        $insertLocation = $pdo->prepare('INSERT INTO cms_res_resource_locations (resource_id, location_id) VALUES (?, ?)');
        foreach ($locationIds as $locationId) {
            $insertLocation->execute([$id, $locationId]);
        }
    }

    $pdo->prepare('DELETE FROM cms_res_hours WHERE resource_id = ?')->execute([$id]);
    $insertHours = $pdo->prepare(
        'INSERT INTO cms_res_hours (resource_id, day_of_week, is_closed, open_time, close_time)
         VALUES (?, ?, ?, ?, ?)'
    );
    for ($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++) {
        $isClosed = isset($hoursData[$dayOfWeek]['is_closed']) ? 1 : 0;
        $openTime = trim((string)($hoursData[$dayOfWeek]['open_time'] ?? '09:00'));
        $closeTime = trim((string)($hoursData[$dayOfWeek]['close_time'] ?? '17:00'));
        $insertHours->execute([$id, $dayOfWeek, $isClosed, $openTime, $closeTime]);
    }

    $pdo->prepare('DELETE FROM cms_res_slots WHERE resource_id = ?')->execute([$id]);
    if ($slotMode === 'slots') {
        $insertSlot = $pdo->prepare(
            'INSERT INTO cms_res_slots (resource_id, day_of_week, start_time, end_time, max_bookings)
             VALUES (?, ?, ?, ?, ?)'
        );
        for ($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++) {
            $startTimes = $slotsData[$dayOfWeek]['start_time'] ?? [];
            $endTimes = $slotsData[$dayOfWeek]['end_time'] ?? [];
            $maxBookings = $slotsData[$dayOfWeek]['max_bookings'] ?? [];

            for ($slotIndex = 0; $slotIndex < count($startTimes); $slotIndex++) {
                $startTime = trim((string)($startTimes[$slotIndex] ?? ''));
                $endTime = trim((string)($endTimes[$slotIndex] ?? ''));
                $limit = max(1, (int)($maxBookings[$slotIndex] ?? 1));

                if ($startTime === '' || $endTime === '') {
                    continue;
                }

                $insertSlot->execute([$id, $dayOfWeek, $startTime, $endTime, $limit]);
            }
        }
    }

    $deletedBlockedIdsRaw = trim((string)($_POST['deleted_blocked_ids'] ?? ''));
    if ($deletedBlockedIdsRaw !== '') {
        $deletedIds = array_filter(array_map('intval', explode(',', $deletedBlockedIdsRaw)));
        if ($deletedIds !== []) {
            $placeholders = implode(',', array_fill(0, count($deletedIds), '?'));
            $params = $deletedIds;
            $params[] = $id;
            $pdo->prepare("DELETE FROM cms_res_blocked WHERE id IN ({$placeholders}) AND resource_id = ?")->execute($params);
        }
    }

    $insertBlocked = $pdo->prepare(
        'INSERT INTO cms_res_blocked (resource_id, blocked_date, reason) VALUES (?, ?, ?)'
    );
    $updateBlocked = $pdo->prepare(
        'UPDATE cms_res_blocked SET blocked_date = ?, reason = ? WHERE id = ? AND resource_id = ?'
    );

    for ($blockedIndex = 0; $blockedIndex < count($blockedDates); $blockedIndex++) {
        $blockedDate = trim((string)($blockedDates[$blockedIndex] ?? ''));
        $blockedReason = trim((string)($blockedReasons[$blockedIndex] ?? ''));
        $blockedId = (int)($blockedIds[$blockedIndex] ?? 0);

        if ($blockedDate === '') {
            continue;
        }

        if ($blockedId > 0) {
            $updateBlocked->execute([$blockedDate, $blockedReason !== '' ? $blockedReason : null, $blockedId, $id]);
        } else {
            $insertBlocked->execute([$id, $blockedDate, $blockedReason !== '' ? $blockedReason : null]);
        }
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . $redirectToForm($id, 'save'));
    exit;
}

logAction($action, 'id=' . $id . ', name=' . mb_substr($name, 0, 80));

header('Location: ' . $listUrl);
exit;
