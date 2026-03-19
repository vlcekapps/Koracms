<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo = db_connect();
$id  = inputInt('post', 'id');

$question    = trim($_POST['question'] ?? '');
$description = trim($_POST['description'] ?? '');
$status      = in_array($_POST['status'] ?? '', ['active', 'closed']) ? $_POST['status'] : 'active';

// Dates
$startDate = null;
if (!empty($_POST['start_date'])) {
    $startDate = $_POST['start_date'] . ' ' . ($_POST['start_time'] ?? '00:00') . ':00';
}
$endDate = null;
if (!empty($_POST['end_date'])) {
    $endDate = $_POST['end_date'] . ' ' . ($_POST['end_time'] ?? '00:00') . ':00';
}

// Options
$optionTexts = $_POST['options'] ?? [];
$optionIds   = $_POST['option_ids'] ?? [];
$validOptions = [];
foreach ($optionTexts as $i => $text) {
    $text = trim($text);
    if ($text !== '') {
        $validOptions[] = [
            'id'   => (int)($optionIds[$i] ?? 0),
            'text' => $text,
            'sort' => count($validOptions),
        ];
    }
}

// Validation
if ($question === '' || count($validOptions) < 2) {
    $redir = 'polls_form.php?err=required' . ($id ? '&id=' . $id : '');
    header('Location: ' . $redir);
    exit;
}
if (count($validOptions) > 10) {
    $redir = 'polls_form.php?err=max_options' . ($id ? '&id=' . $id : '');
    header('Location: ' . $redir);
    exit;
}

if ($id !== null) {
    // ── Update ──
    $stmt = $pdo->prepare("SELECT id FROM cms_polls WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) { header('Location: polls.php'); exit; }

    $pdo->prepare(
        "UPDATE cms_polls SET question = ?, description = ?, status = ?, start_date = ?, end_date = ? WHERE id = ?"
    )->execute([$question, $description ?: null, $status, $startDate, $endDate, $id]);

    // Get existing options with vote counts
    $existing = $pdo->prepare(
        "SELECT o.id, (SELECT COUNT(*) FROM cms_poll_votes WHERE option_id = o.id) AS vote_count
         FROM cms_poll_options o WHERE o.poll_id = ?"
    );
    $existing->execute([$id]);
    $existingMap = [];
    foreach ($existing->fetchAll() as $row) {
        $existingMap[(int)$row['id']] = (int)$row['vote_count'];
    }

    // Determine which existing options are being kept
    $submittedExistingIds = [];
    foreach ($validOptions as $opt) {
        if ($opt['id'] > 0) $submittedExistingIds[] = $opt['id'];
    }

    // Check if any removed options have votes
    foreach ($existingMap as $eid => $vc) {
        if (!in_array($eid, $submittedExistingIds) && $vc > 0) {
            header('Location: polls_form.php?err=has_votes&id=' . $id);
            exit;
        }
    }

    // Delete removed options (only those with 0 votes)
    foreach ($existingMap as $eid => $vc) {
        if (!in_array($eid, $submittedExistingIds) && $vc === 0) {
            $pdo->prepare("DELETE FROM cms_poll_options WHERE id = ? AND poll_id = ?")->execute([$eid, $id]);
        }
    }

    // Update existing / insert new options
    $stmtUpdate = $pdo->prepare("UPDATE cms_poll_options SET option_text = ?, sort_order = ? WHERE id = ? AND poll_id = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO cms_poll_options (poll_id, option_text, sort_order) VALUES (?, ?, ?)");

    foreach ($validOptions as $opt) {
        if ($opt['id'] > 0 && isset($existingMap[$opt['id']])) {
            $stmtUpdate->execute([$opt['text'], $opt['sort'], $opt['id'], $id]);
        } else {
            $stmtInsert->execute([$id, $opt['text'], $opt['sort']]);
        }
    }

    logAction('poll_edit', "id={$id}, question=" . mb_substr($question, 0, 80));

} else {
    // ── Create ──
    $pdo->prepare(
        "INSERT INTO cms_polls (question, description, status, start_date, end_date) VALUES (?, ?, ?, ?, ?)"
    )->execute([$question, $description ?: null, $status, $startDate, $endDate]);

    $pollId = (int)$pdo->lastInsertId();

    $stmtInsert = $pdo->prepare("INSERT INTO cms_poll_options (poll_id, option_text, sort_order) VALUES (?, ?, ?)");
    foreach ($validOptions as $opt) {
        $stmtInsert->execute([$pollId, $opt['text'], $opt['sort']]);
    }

    logAction('poll_add', "id={$pollId}, question=" . mb_substr($question, 0, 80));
}

header('Location: polls.php');
exit;
