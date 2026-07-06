<?php

require_once __DIR__ . '/../db.php';

$requestMethod = requireHttpMethods(['GET', 'HEAD', 'POST']);
requireLogin(BASE_URL . '/admin/login.php');
requireCapability('content_manage_shared', 'Přístup odepřen. Pro export výsledků anket nemáte potřebné oprávnění.');
requireModuleEnabled('polls');

/**
 * @return never
 */
function pollResultsExportNotFound(bool $isHeadRequest = false): void
{
    http_response_code(404);
    sendNoStoreNoIndexHeaders();
    sendNoSniffHeader();
    header('Content-Type: text/plain; charset=UTF-8');
    if (!$isHeadRequest) {
        echo 'Anketa nebyla nalezena.';
    }
    exit;
}

/**
 * @param array<string,mixed> $poll
 * @param list<array<string,mixed>> $options
 */
function renderPollResultsCsvExportForm(
    array $poll,
    array $options,
    int $selectionCount,
    int $voterCount,
    bool $confirmExportError = false
): void {
    require_once __DIR__ . '/layout.php';

    $exportErrorFields = $confirmExportError ? ['confirm_poll_results_csv_export'] : [];
    $exportConfirmErrorMessage = 'CSV export výsledků ankety nejde stáhnout bez potvrzení kontroly agregovaných výsledků. U pole Potvrzení stažení je konkrétní nápověda.';
    $pollId = (int)$poll['id'];

    adminHeader('Export výsledků ankety');
    ?>
    <p class="admin-description">
      Připraví CSV export agregovaných výsledků jedné ankety bez raw IP hashů nebo identifikátorů hlasujících.
    </p>

    <?php if ($confirmExportError): ?>
      <p id="poll-results-csv-export-form-error" class="error" role="alert" aria-atomic="true"><?= h($exportConfirmErrorMessage) ?></p>
    <?php endif; ?>

    <form method="post" novalidate<?= $confirmExportError ? ' aria-describedby="poll-results-csv-export-form-error"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="poll_id" value="<?= $pollId ?>">
      <fieldset class="admin-fieldset-card">
        <legend>Export výsledků</legend>
        <p id="poll-results-csv-export-review-help" class="field-help field-help--flush">
          CSV export obsahuje otázku ankety, typ hlasování, nastavení viditelnosti výsledků, počet hlasujících,
          počet vybraných odpovědí a agregované počty u jednotlivých možností. Neobsahuje IP hashe ani raw identifikátory hlasujících,
          přesto může prozrazovat interní nebo citlivé rozhodování návštěvníků podle tématu ankety.
        </p>
        <dl class="admin-summary-list">
          <div>
            <dt>Anketa</dt>
            <dd><?= h((string)$poll['question']) ?></dd>
          </div>
          <div>
            <dt>Typ hlasování</dt>
            <dd><?= h((string)($poll['vote_mode_label'] ?? 'Jedna možnost')) ?></dd>
          </div>
          <div>
            <dt>Viditelnost výsledků</dt>
            <dd><?= h((string)($poll['results_visibility_label'] ?? 'Po hlasování')) ?></dd>
          </div>
          <div>
            <dt>Hlasujících</dt>
            <dd><?= $voterCount ?></dd>
          </div>
          <div>
            <dt>Vybraných odpovědí</dt>
            <dd><?= $selectionCount ?></dd>
          </div>
          <div>
            <dt>Možností v exportu</dt>
            <dd><?= count($options) ?></dd>
          </div>
        </dl>
        <label for="confirm_poll_results_csv_export" class="admin-checkbox-label">
          <input
            type="checkbox"
            id="confirm_poll_results_csv_export"
            name="confirm_poll_results_csv_export"
            value="1"
            required
            aria-required="true"<?= adminFieldAttributes('confirm_poll_results_csv_export', $exportErrorFields, [], ['poll-results-csv-export-review-help'], 'confirm-poll-results-csv-export-error') ?>>
          Potvrzuji, že jsem zkontroloval(a) téma ankety, agregované výsledky a mám oprávnění CSV stáhnout.
        </label>
        <?php adminRenderFieldError('confirm_poll_results_csv_export', $exportErrorFields, [], 'Před stažením CSV exportu potvrďte, že rozumíte obsahu agregovaných výsledků a máte oprávnění soubor stáhnout.', 'confirm-poll-results-csv-export-error'); ?>
        <div class="admin-field-row">
          <button type="submit" class="btn" data-confirm="Stáhnout CSV export výsledků ankety? Soubor může prozrazovat citlivé agregované rozhodování návštěvníků.">Stáhnout CSV export</button>
        </div>
      </fieldset>
    </form>

    <p><a href="polls_form.php?id=<?= $pollId ?>"><span aria-hidden="true">←</span> Zpět na anketu</a></p>
    <?php
    adminFooter();
}

$isHeadRequest = $requestMethod === 'HEAD';
$pollId = $requestMethod === 'POST' ? inputInt('post', 'poll_id') : inputInt('get', 'id');
if ($pollId === null) {
    pollResultsExportNotFound($isHeadRequest);
}

$pdo = db_connect();
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
    pollResultsExportNotFound($isHeadRequest);
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

if ($isHeadRequest) {
    sendAdminNoStoreHeaders();
    sendNoSniffHeader();
    header('Content-Type: text/html; charset=UTF-8');
    exit;
}

if ($requestMethod !== 'POST') {
    renderPollResultsCsvExportForm($poll, $options, $selectionCount, $voterCount);
    exit;
}

verifyCsrf();
$confirmPollResultsCsvExport = isset($_POST['confirm_poll_results_csv_export'])
    && (string)$_POST['confirm_poll_results_csv_export'] === '1';
if (!$confirmPollResultsCsvExport) {
    renderPollResultsCsvExportForm($poll, $options, $selectionCount, $voterCount, true);
    exit;
}

$multipleMode = pollAllowsMultipleChoices($poll);
$percentageDenominator = $multipleMode ? $voterCount : $selectionCount;
$filename = 'anketa-' . (int)$poll['id'] . '-vysledky.csv';

sendAdminAttachmentHeaders('text/csv; charset=UTF-8', $filename);
logAction('poll_results_export_csv', 'poll_id=' . (int)$poll['id']);

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
