<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo = db_connect();
$id  = inputInt('post', 'id');

$name             = trim($_POST['name'] ?? '');
$slug             = trim($_POST['slug'] ?? '');
$description      = trim($_POST['description'] ?? '');
$categoryId       = inputInt('post', 'category_id');
$capacity         = max(1, (int)($_POST['capacity'] ?? 1));
$locationIds      = array_filter(array_map('intval', $_POST['location_ids'] ?? []));
$slotMode         = in_array($_POST['slot_mode'] ?? '', ['slots', 'range', 'duration']) ? $_POST['slot_mode'] : 'slots';
$slotDurationMin  = max(1, (int)($_POST['slot_duration_min'] ?? 60));
$minAdvanceHours  = max(0, (int)($_POST['min_advance_hours'] ?? 0));
$maxAdvanceDays   = max(1, (int)($_POST['max_advance_days'] ?? 30));
$cancellationHours = max(0, (int)($_POST['cancellation_hours'] ?? 0));
$requiresApproval = isset($_POST['requires_approval']) ? 1 : 0;
$allowGuests      = isset($_POST['allow_guests']) ? 1 : 0;
$maxConcurrent    = max(1, (int)($_POST['max_concurrent'] ?? 1));

// Slug: auto-generate if empty
if ($slug === '' && $name !== '') {
    $slug = slugify($name);
}
$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
$slug = trim($slug, '-');

// Validation
if ($name === '') {
    $redir = 'res_resource_form.php?err=name' . ($id ? '&id=' . $id : '');
    header('Location: ' . $redir);
    exit;
}
if ($slug === '') {
    $redir = 'res_resource_form.php?err=slug' . ($id ? '&id=' . $id : '');
    header('Location: ' . $redir);
    exit;
}
if ($capacity < 1) {
    $redir = 'res_resource_form.php?err=capacity' . ($id ? '&id=' . $id : '');
    header('Location: ' . $redir);
    exit;
}

// Slug uniqueness
$stmtSlug = $pdo->prepare("SELECT id FROM cms_res_resources WHERE slug = ? AND id != ?");
$stmtSlug->execute([$slug, $id ?? 0]);
if ($stmtSlug->fetch()) {
    $redir = 'res_resource_form.php?err=slug' . ($id ? '&id=' . $id : '');
    header('Location: ' . $redir);
    exit;
}

// ── INSERT or UPDATE resource ──
if ($id !== null) {
    $stmt = $pdo->prepare("SELECT id FROM cms_res_resources WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) { header('Location: res_resources.php'); exit; }

    $pdo->prepare(
        "UPDATE cms_res_resources SET name = ?, slug = ?, description = ?, category_id = ?,
                capacity = ?, slot_mode = ?, slot_duration_min = ?,
                min_advance_hours = ?, max_advance_days = ?, cancellation_hours = ?,
                requires_approval = ?, allow_guests = ?, max_concurrent = ?
         WHERE id = ?"
    )->execute([
        $name, $slug, $description ?: null, $categoryId,
        $capacity, $slotMode, $slotDurationMin,
        $minAdvanceHours, $maxAdvanceDays, $cancellationHours,
        $requiresApproval, $allowGuests, $maxConcurrent, $id
    ]);

    logAction('res_resource_edit', "id={$id}, name=" . mb_substr($name, 0, 80));
} else {
    $pdo->prepare(
        "INSERT INTO cms_res_resources
            (name, slug, description, category_id, capacity, slot_mode, slot_duration_min,
             min_advance_hours, max_advance_days, cancellation_hours, requires_approval, allow_guests, max_concurrent, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    )->execute([
        $name, $slug, $description ?: null, $categoryId,
        $capacity, $slotMode, $slotDurationMin,
        $minAdvanceHours, $maxAdvanceDays, $cancellationHours,
        $requiresApproval, $allowGuests, $maxConcurrent
    ]);

    $id = (int)$pdo->lastInsertId();
    logAction('res_resource_add', "id={$id}, name=" . mb_substr($name, 0, 80));
}

// ── Locations: DELETE + re-INSERT junction table ──
$pdo->prepare("DELETE FROM cms_res_resource_locations WHERE resource_id = ?")->execute([$id]);
if (!empty($locationIds)) {
    $stmtLoc = $pdo->prepare("INSERT INTO cms_res_resource_locations (resource_id, location_id) VALUES (?, ?)");
    foreach ($locationIds as $locId) {
        $stmtLoc->execute([$id, $locId]);
    }
}

// ── Opening hours: DELETE + re-INSERT ──
$pdo->prepare("DELETE FROM cms_res_hours WHERE resource_id = ?")->execute([$id]);

$stmtHours = $pdo->prepare(
    "INSERT INTO cms_res_hours (resource_id, day_of_week, is_closed, open_time, close_time)
     VALUES (?, ?, ?, ?, ?)"
);
$hoursData = $_POST['hours'] ?? [];
for ($d = 0; $d < 7; $d++) {
    $isClosed  = isset($hoursData[$d]['is_closed']) ? 1 : 0;
    $openTime  = $hoursData[$d]['open_time'] ?? '09:00';
    $closeTime = $hoursData[$d]['close_time'] ?? '17:00';
    $stmtHours->execute([$id, $d, $isClosed, $openTime, $closeTime]);
}

// ── Predefined slots: DELETE + re-INSERT (only for slot mode) ──
$pdo->prepare("DELETE FROM cms_res_slots WHERE resource_id = ?")->execute([$id]);

if ($slotMode === 'slots') {
    $slotsData = $_POST['slots'] ?? [];
    $stmtSlot = $pdo->prepare(
        "INSERT INTO cms_res_slots (resource_id, day_of_week, start_time, end_time, max_bookings)
         VALUES (?, ?, ?, ?, ?)"
    );
    for ($d = 0; $d < 7; $d++) {
        if (empty($slotsData[$d]['start_time'])) continue;
        $startTimes  = $slotsData[$d]['start_time'];
        $endTimes    = $slotsData[$d]['end_time'] ?? [];
        $maxBookings = $slotsData[$d]['max_bookings'] ?? [];
        for ($s = 0; $s < count($startTimes); $s++) {
            $st = trim($startTimes[$s] ?? '');
            $et = trim($endTimes[$s] ?? '');
            $mb = max(1, (int)($maxBookings[$s] ?? 1));
            if ($st !== '' && $et !== '') {
                $stmtSlot->execute([$id, $d, $st, $et, $mb]);
            }
        }
    }
}

// ── Blocked dates: handle deletions ──
$deletedBlockedIdsRaw = trim($_POST['deleted_blocked_ids'] ?? '');
if ($deletedBlockedIdsRaw !== '') {
    $deletedIds = array_filter(array_map('intval', explode(',', $deletedBlockedIdsRaw)));
    if (!empty($deletedIds)) {
        $placeholders = implode(',', array_fill(0, count($deletedIds), '?'));
        $params = $deletedIds;
        $params[] = $id;
        $pdo->prepare("DELETE FROM cms_res_blocked WHERE id IN ({$placeholders}) AND resource_id = ?")->execute($params);
    }
}

// ── Blocked dates: handle existing + new ──
$blockedIds     = $_POST['blocked_ids'] ?? [];
$blockedDates   = $_POST['blocked_dates'] ?? [];
$blockedReasons = $_POST['blocked_reasons'] ?? [];

$stmtInsertBlocked = $pdo->prepare(
    "INSERT INTO cms_res_blocked (resource_id, blocked_date, reason) VALUES (?, ?, ?)"
);
$stmtUpdateBlocked = $pdo->prepare(
    "UPDATE cms_res_blocked SET blocked_date = ?, reason = ? WHERE id = ? AND resource_id = ?"
);

for ($b = 0; $b < count($blockedDates); $b++) {
    $bDate   = trim($blockedDates[$b] ?? '');
    $bReason = trim($blockedReasons[$b] ?? '');
    $bId     = (int)($blockedIds[$b] ?? 0);

    if ($bDate === '') continue;

    if ($bId > 0) {
        $stmtUpdateBlocked->execute([$bDate, $bReason ?: null, $bId, $id]);
    } else {
        $stmtInsertBlocked->execute([$id, $bDate, $bReason ?: null]);
    }
}

header('Location: res_resources.php');
exit;
