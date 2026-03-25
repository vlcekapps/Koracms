<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu anket nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');

$redirectToForm = static function (?int $pollId, string $errorCode): void {
    $params = ['err' => $errorCode];
    if ($pollId !== null) {
        $params['id'] = (string)$pollId;
    }
    header('Location: ' . BASE_URL . appendUrlQuery('/admin/polls_form.php', $params));
    exit;
};

$question = trim($_POST['question'] ?? '');
$submittedSlug = trim($_POST['slug'] ?? '');
$description = trim($_POST['description'] ?? '');
$status = in_array($_POST['status'] ?? '', ['active', 'closed'], true) ? (string)$_POST['status'] : 'active';

$startDate = null;
if (!empty($_POST['start_date'])) {
    $startDate = trim((string)$_POST['start_date']) . ' ' . trim((string)($_POST['start_time'] ?? '00:00')) . ':00';
}
$endDate = null;
if (!empty($_POST['end_date'])) {
    $endDate = trim((string)$_POST['end_date']) . ' ' . trim((string)($_POST['end_time'] ?? '00:00')) . ':00';
}

if ($startDate !== null && strtotime($startDate) === false) {
    $startDate = null;
}
if ($endDate !== null && strtotime($endDate) === false) {
    $endDate = null;
}
if ($startDate !== null && $endDate !== null && $endDate <= $startDate) {
    $redirectToForm($id, 'range');
}

$optionTexts = $_POST['options'] ?? [];
$optionIds = $_POST['option_ids'] ?? [];
$validOptions = [];
foreach ($optionTexts as $index => $text) {
    $normalizedText = trim((string)$text);
    if ($normalizedText === '') {
        continue;
    }
    $validOptions[] = [
        'id' => (int)($optionIds[$index] ?? 0),
        'text' => $normalizedText,
        'sort' => count($validOptions),
    ];
}

if ($question === '' || count($validOptions) < 2) {
    $redirectToForm($id, 'required');
}
if (count($validOptions) > 10) {
    $redirectToForm($id, 'max_options');
}

$existingPoll = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT id FROM cms_polls WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingPoll = $existingStmt->fetch() ?: null;
    if (!$existingPoll) {
        header('Location: ' . BASE_URL . '/admin/polls.php');
        exit;
    }
}

$slug = pollSlug($submittedSlug !== '' ? $submittedSlug : $question);
if ($slug === '') {
    $redirectToForm($id, 'slug');
}

$uniqueSlug = uniquePollSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    $redirectToForm($id, 'slug');
}
$slug = $uniqueSlug;

if ($existingPoll) {
    $pdo->prepare(
        "UPDATE cms_polls
         SET question = ?, slug = ?, description = ?, status = ?, start_date = ?, end_date = ?
         WHERE id = ?"
    )->execute([$question, $slug, $description ?: null, $status, $startDate, $endDate, $id]);

    $existingOptionsStmt = $pdo->prepare(
        "SELECT o.id, (SELECT COUNT(*) FROM cms_poll_votes WHERE option_id = o.id) AS vote_count
         FROM cms_poll_options o
         WHERE o.poll_id = ?"
    );
    $existingOptionsStmt->execute([$id]);
    $existingMap = [];
    foreach ($existingOptionsStmt->fetchAll() as $row) {
        $existingMap[(int)$row['id']] = (int)$row['vote_count'];
    }

    $submittedExistingIds = [];
    foreach ($validOptions as $option) {
        if ($option['id'] > 0) {
            $submittedExistingIds[] = $option['id'];
        }
    }

    foreach ($existingMap as $existingId => $voteCount) {
        if (!in_array($existingId, $submittedExistingIds, true) && $voteCount > 0) {
            $redirectToForm($id, 'has_votes');
        }
    }

    foreach ($existingMap as $existingId => $voteCount) {
        if (!in_array($existingId, $submittedExistingIds, true) && $voteCount === 0) {
            $pdo->prepare("DELETE FROM cms_poll_options WHERE id = ? AND poll_id = ?")->execute([$existingId, $id]);
        }
    }

    $updateOptionStmt = $pdo->prepare(
        "UPDATE cms_poll_options SET option_text = ?, sort_order = ? WHERE id = ? AND poll_id = ?"
    );
    $insertOptionStmt = $pdo->prepare(
        "INSERT INTO cms_poll_options (poll_id, option_text, sort_order) VALUES (?, ?, ?)"
    );

    foreach ($validOptions as $option) {
        if ($option['id'] > 0 && isset($existingMap[$option['id']])) {
            $updateOptionStmt->execute([$option['text'], $option['sort'], $option['id'], $id]);
        } else {
            $insertOptionStmt->execute([$id, $option['text'], $option['sort']]);
        }
    }

    logAction('poll_edit', "id={$id} question={$question} slug={$slug} status={$status}");
} else {
    $pdo->prepare(
        "INSERT INTO cms_polls (question, slug, description, status, start_date, end_date)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$question, $slug, $description ?: null, $status, $startDate, $endDate]);

    $id = (int)$pdo->lastInsertId();
    $insertOptionStmt = $pdo->prepare(
        "INSERT INTO cms_poll_options (poll_id, option_text, sort_order) VALUES (?, ?, ?)"
    );
    foreach ($validOptions as $option) {
        $insertOptionStmt->execute([$id, $option['text'], $option['sort']]);
    }

    logAction('poll_add', "id={$id} question={$question} slug={$slug} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/polls.php');
exit;
