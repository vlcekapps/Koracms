<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo         = db_connect();
$id          = inputInt('post', 'id');
$type        = in_array($_POST['type'] ?? '', ['food', 'beverage']) ? $_POST['type'] : 'food';
$title       = trim($_POST['title']       ?? '');
$description = trim($_POST['description'] ?? '');
$content     = trim($_POST['content']     ?? '');
$validFrom   = trim($_POST['valid_from']  ?? '') ?: null;
$validTo     = trim($_POST['valid_to']    ?? '') ?: null;

// Sanitace datumů
if ($validFrom !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom)) $validFrom = null;
if ($validTo   !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validTo))   $validTo   = null;

if ($title === '') {
    header('Location: food_form.php' . ($id ? "?id={$id}" : ''));
    exit;
}

$isSA        = isSuperAdmin();
$isCurrent   = $isSA && isset($_POST['is_current'])   ? 1 : 0;
$isPublished = $isSA && isset($_POST['is_published'])  ? 1 : 0;
$status      = $isSA ? 'published' : 'pending';

// ── Pokud se tato karta označuje jako aktuální, odznačit předchozí ──────────
if ($isCurrent) {
    $excludeId = $id ?? 0;
    $pdo->prepare(
        "UPDATE cms_food_cards SET is_current = 0 WHERE type = ? AND id != ?"
    )->execute([$type, $excludeId]);
}

// ── Uložení ─────────────────────────────────────────────────────────────────
if ($id !== null) {
    // Při editaci: pouze superadmin smí měnit is_current a is_published
    if ($isSA) {
        $pdo->prepare(
            "UPDATE cms_food_cards
             SET type=?, title=?, description=?, content=?,
                 valid_from=?, valid_to=?, is_current=?, is_published=?,
                 updated_at=NOW()
             WHERE id=?"
        )->execute([$type, $title, $description, $content,
                    $validFrom, $validTo, $isCurrent, $isPublished, $id]);
    } else {
        $pdo->prepare(
            "UPDATE cms_food_cards
             SET type=?, title=?, description=?, content=?,
                 valid_from=?, valid_to=?, updated_at=NOW()
             WHERE id=?"
        )->execute([$type, $title, $description, $content,
                    $validFrom, $validTo, $id]);
    }
    logAction('food_edit', "id={$id} type={$type}");
} else {
    $pdo->prepare(
        "INSERT INTO cms_food_cards
         (type, title, description, content, valid_from, valid_to,
          is_current, is_published, status, author_id)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $type, $title, $description, $content, $validFrom, $validTo,
        $isSA ? $isCurrent   : 0,
        $isSA ? $isPublished : 0,
        $status,
        currentUserId()
    ]);
    logAction('food_add', "title={$title} type={$type} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/food.php');
exit;
