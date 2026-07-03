<?php

require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
$isHeadRequest = requireReadOnlyHttpMethod();
requireCapability('content_manage_shared', 'Přístup odepřen. Pro export výsledků anket nemáte potřebné oprávnění.');
requireModuleEnabled('polls');

$pdo = db_connect();
$pollId = inputInt('get', 'id');
if ($pollId === null) {
    http_response_code(404);
    sendNoStoreNoIndexHeaders();
    sendNoSniffHeader();
    header('Content-Type: text/plain; charset=UTF-8');
    if (!$isHeadRequest) {
        echo 'Anketa nebyla nalezena.';
    }
    exit;
}

$pollStmt = $pdo->prepare(
    "SELECT p.*,
            (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS selection_count,
            (SELECT COUNT(*) FROM cms_poll_vote_sessions WHERE poll_id = p.id) AS voter_count
     FROM cms_polls p
     WHERE p.id = ? AND p.deleted_at IS NULL
     LIMIT 1"
);
$pollStmt->execute([$pollId]);
$poll = $pollStmt->fetch();
if (!$poll) {
    http_response_code(404);
    sendNoStoreNoIndexHeaders();
    sendNoSniffHeader();
    header('Content-Type: text/plain; charset=UTF-8');
    if (!$isHeadRequest) {
        echo 'Anketa nebyla nalezena.';
    }
    exit;
}

$poll = hydratePollPresentation($poll);
$optionsStmt = $pdo->prepare(
    "SELECT o.option_text, COUNT(v.id) AS selection_count
     FROM cms_poll_options o
     LEFT JOIN cms_poll_votes v ON v.option_id = o.id
     WHERE o.poll_id = ?
     GROUP BY o.id, o.option_text, o.sort_order
     ORDER BY o.sort_order, o.id"
);
$optionsStmt->execute([(int)$poll['id']]);
$options = $optionsStmt->fetchAll();

$selectionCount = (int)($poll['selection_count'] ?? 0);
$voterCount = (int)($poll['voter_count'] ?? 0);
if ($voterCount === 0 && $selectionCount > 0) {
    $fallbackStmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_hash) FROM cms_poll_votes WHERE poll_id = ?");
    $fallbackStmt->execute([(int)$poll['id']]);
    $voterCount = (int)$fallbackStmt->fetchColumn();
}

$multipleMode = pollAllowsMultipleChoices($poll);
$percentageDenominator = $multipleMode ? $voterCount : $selectionCount;
$filename = 'anketa-' . (int)$poll['id'] . '-vysledky.csv';

sendAdminAttachmentHeaders('text/csv; charset=UTF-8', $filename);
if ($isHeadRequest) {
    exit;
}

$output = fopen('php://output', 'wb');
if (!is_resource($output)) {
    exit;
}

fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Otázka', (string)$poll['question']]);
fputcsv($output, ['Typ hlasování', (string)($poll['vote_mode_label'] ?? 'Jedna možnost')]);
fputcsv($output, ['Viditelnost výsledků', (string)($poll['results_visibility_label'] ?? 'Po hlasování')]);
fputcsv($output, ['Hlasujících', $voterCount]);
fputcsv($output, ['Vybraných odpovědí', $selectionCount]);
fputcsv($output, []);
fputcsv($output, ['Možnost', 'Počet výběrů', 'Procento z hlasujících']);

foreach ($options as $option) {
    $optionSelections = (int)($option['selection_count'] ?? 0);
    fputcsv($output, [
        (string)($option['option_text'] ?? ''),
        $optionSelections,
        pollResultPercentage($optionSelections, $percentageDenominator),
    ]);
}
