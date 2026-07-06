<?php
require_once __DIR__ . '/layout.php';

$isCsvExport = isset($_GET['export']) && $_GET['export'] === 'csv';
$requestMethod = requireHttpMethods($isCsvExport ? ['GET', 'HEAD', 'POST'] : ['GET', 'HEAD']);
$pdo = db_connect();
$formId = inputInt('get', 'id');
requireCapability('content_manage_shared', 'Přístup odepřen.');

if ($formId === null) {
    header('Location: ' . BASE_URL . '/admin/forms.php');
    exit;
}

$formStmt = $pdo->prepare("SELECT * FROM cms_forms WHERE id = ?");
$formStmt->execute([$formId]);
$form = $formStmt->fetch() ?: null;

if (!$form) {
    header('Location: ' . BASE_URL . '/admin/forms.php');
    exit;
}

$fieldsStmt = $pdo->prepare("SELECT * FROM cms_form_fields WHERE form_id = ? ORDER BY sort_order, id");
$fieldsStmt->execute([$formId]);
$fields = $fieldsStmt->fetchAll();

$fieldDefinitions = [];
foreach ($fields as $field) {
    if (!formFieldStoresSubmissionValue($field)) {
        continue;
    }
    $fieldDefinitions[(string)$field['name']] = $field;
}

$statusDefinitions = formSubmissionStatusDefinitions();
$priorityDefinitions = formSubmissionPriorityDefinitions();
$allowedStatusFilters = array_merge(['all'], array_keys($statusDefinitions));
$statusFilter = trim((string)($_GET['status'] ?? 'new'));
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'new';
}
$allowedPriorityFilters = array_merge(['all'], array_keys($priorityDefinitions));
$priorityFilter = trim((string)($_GET['priority'] ?? 'all'));
if (!in_array($priorityFilter, $allowedPriorityFilters, true)) {
    $priorityFilter = 'all';
}
$assignedFilter = trim((string)($_GET['assigned'] ?? 'all'));
if (!in_array($assignedFilter, ['all', 'mine', 'unassigned'], true)) {
    $assignedFilter = 'all';
}
$githubFilter = trim((string)($_GET['github'] ?? 'all'));
if (!in_array($githubFilter, ['all', 'linked', 'unlinked'], true)) {
    $githubFilter = 'all';
}

$query = trim((string)($_GET['q'] ?? ''));
$statusCounts = formSubmissionStatusCounts($pdo, $formId);
$allSubmissionCount = array_sum($statusCounts);

$where = 'WHERE s.form_id = ?';
$params = [$formId];
if ($statusFilter !== 'all') {
    $where .= ' AND s.status = ?';
    $params[] = $statusFilter;
}
if ($priorityFilter !== 'all') {
    $where .= ' AND s.priority = ?';
    $params[] = $priorityFilter;
}
if ($assignedFilter === 'mine' && currentUserId() !== null) {
    $where .= ' AND s.assigned_user_id = ?';
    $params[] = currentUserId();
} elseif ($assignedFilter === 'unassigned') {
    $where .= ' AND s.assigned_user_id IS NULL';
}
if ($githubFilter === 'linked') {
    $where .= " AND COALESCE(s.github_issue_url, '') <> ''";
} elseif ($githubFilter === 'unlinked') {
    $where .= " AND COALESCE(s.github_issue_url, '') = ''";
}
if ($query !== '') {
    $where .= " AND (
        s.reference_code LIKE ?
        OR s.data LIKE ?
        OR COALESCE(s.internal_note, '') LIKE ?
    )";
    $needle = '%' . $query . '%';
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
}

$submissionsStmt = $pdo->prepare(
    "SELECT s.*,
            u.email AS assigned_email,
            u.first_name AS assigned_first_name,
            u.last_name AS assigned_last_name,
            u.nickname AS assigned_nickname,
            u.role AS assigned_role,
            u.is_superadmin AS assigned_is_superadmin
     FROM cms_form_submissions s
     LEFT JOIN cms_users u ON u.id = s.assigned_user_id
     {$where}
     ORDER BY FIELD(s.status, 'new', 'in_progress', 'resolved', 'closed'),
              FIELD(s.priority, 'critical', 'high', 'medium', 'low'),
              s.created_at DESC,
              s.id DESC"
);
$submissionsStmt->execute($params);
$submissions = $submissionsStmt->fetchAll();
$submissionCount = count($submissions);

$currentParams = ['id' => $formId];
if ($statusFilter !== 'all') {
    $currentParams['status'] = $statusFilter;
}
if ($priorityFilter !== 'all') {
    $currentParams['priority'] = $priorityFilter;
}
if ($assignedFilter !== 'all') {
    $currentParams['assigned'] = $assignedFilter;
}
if ($githubFilter !== 'all') {
    $currentParams['github'] = $githubFilter;
}
if ($query !== '') {
    $currentParams['q'] = $query;
}
$currentRedirect = BASE_URL . appendUrlQuery('/admin/form_submissions.php', $currentParams);

$renderCsvExportForm = static function (bool $confirmExportError = false) use (
    $currentParams,
    $form,
    $submissionCount,
    $allSubmissionCount,
    $statusFilter,
    $priorityFilter,
    $assignedFilter,
    $githubFilter,
    $query
): void {
    $exportErrorFields = $confirmExportError ? ['confirm_form_submissions_csv_export'] : [];
    $exportConfirmErrorMessage = 'CSV export odpovědí formuláře nejde stáhnout bez potvrzení kontroly citlivosti exportu. U pole Potvrzení stažení je konkrétní nápověda.';
    $activeFilters = [];
    if ($statusFilter !== 'all') {
        $activeFilters[] = 'stav ' . formSubmissionStatusLabel($statusFilter);
    }
    if ($priorityFilter !== 'all') {
        $activeFilters[] = 'priorita ' . formSubmissionPriorityLabel($priorityFilter);
    }
    if ($assignedFilter === 'mine') {
        $activeFilters[] = 'jen moje přiřazené odpovědi';
    } elseif ($assignedFilter === 'unassigned') {
        $activeFilters[] = 'jen nepřiřazené odpovědi';
    }
    if ($githubFilter === 'linked') {
        $activeFilters[] = 'jen odpovědi s GitHub issue';
    } elseif ($githubFilter === 'unlinked') {
        $activeFilters[] = 'jen odpovědi bez GitHub issue';
    }
    if ($query !== '') {
        $activeFilters[] = 'vyhledávání „' . $query . '“';
    }
    $filterSummary = $activeFilters !== []
        ? 'Aktivní filtr: ' . implode(', ', $activeFilters) . '.'
        : 'Export zahrne aktuálně zobrazený základní výběr odpovědí.';

    adminHeader('Export odpovědí formuláře');
    ?>
    <p class="admin-description">
      Připraví CSV export odpovědí formuláře <strong><?= h((string)$form['title']) ?></strong>.
    </p>

    <?php if ($confirmExportError): ?>
      <p id="form-submissions-csv-export-form-error" class="error" role="alert" aria-atomic="true"><?= h($exportConfirmErrorMessage) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= h(appendUrlQuery('form_submissions.php', array_merge($currentParams, ['export' => 'csv']))) ?>" novalidate<?= $confirmExportError ? ' aria-describedby="form-submissions-csv-export-form-error"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <fieldset class="admin-fieldset-card">
        <legend>Export odpovědí formuláře</legend>
        <p id="form-submissions-csv-export-review-help" class="field-help field-help--flush">
          CSV export může obsahovat jména, e-maily, telefony, zprávy, interní poznámky, štítky, přiřazení a další údaje z odpovědí návštěvníků.
          Export zahrne <?= (int)$submissionCount ?> z <?= (int)$allSubmissionCount ?> odpovědí tohoto formuláře. <?= h($filterSummary) ?>
          Soubor ukládejte jen do oprávněného a bezpečného úložiště.
        </p>
        <label for="confirm_form_submissions_csv_export" class="admin-checkbox-label">
          <input type="checkbox" id="confirm_form_submissions_csv_export" name="confirm_form_submissions_csv_export" value="1" required aria-required="true"<?= adminFieldAttributes('confirm_form_submissions_csv_export', $exportErrorFields, [], ['form-submissions-csv-export-review-help'], 'confirm-form-submissions-csv-export-error') ?>>
          Potvrzuji, že jsem zkontroloval(a) citlivost CSV exportu odpovědí a mám oprávnění jej stáhnout.
        </label>
        <?php adminRenderFieldError('confirm_form_submissions_csv_export', $exportErrorFields, [], 'Před stažením CSV exportu potvrďte, že rozumíte citlivosti odpovědí formuláře a máte oprávnění soubor stáhnout.', 'confirm-form-submissions-csv-export-error'); ?>
        <div class="admin-field-row">
          <button type="submit" class="btn" data-confirm="Stáhnout CSV export odpovědí formuláře? Soubor může obsahovat osobní nebo provozní údaje.">Stáhnout CSV export</button>
        </div>
      </fieldset>
    </form>

    <p><a href="<?= h(appendUrlQuery('form_submissions.php', $currentParams)) ?>"><span aria-hidden="true">←</span> Zpět na odpovědi formuláře</a></p>
    <?php
    adminFooter();
};

if ($isCsvExport) {
    if ($requestMethod === 'HEAD') {
        header('Content-Type: text/html; charset=UTF-8');
        sendAdminNoStoreHeaders();
        exit;
    }

    if ($requestMethod !== 'POST') {
        $renderCsvExportForm();
        exit;
    }

    verifyCsrf();
    $confirmCsvExport = isset($_POST['confirm_form_submissions_csv_export'])
        && (string)$_POST['confirm_form_submissions_csv_export'] === '1';
    if (!$confirmCsvExport) {
        $renderCsvExportForm(true);
        exit;
    }

    sendAdminAttachmentHeaders(
        'text/csv; charset=utf-8',
        'formular-' . (string)$form['slug'] . '-' . date('Y-m-d') . '.csv'
    );
    logAction('form_submissions_export_csv', 'form_id=' . $formId . ' count=' . $submissionCount);

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");

    $headerRow = ['Reference', 'Datum', 'Stav', 'Priorita', 'Štítky', 'Přiřazeno', 'Interní poznámka'];
    foreach ($fieldDefinitions as $field) {
        $headerRow[] = (string)($field['label'] ?? '');
    }
    fputcsv($out, $headerRow, ';');

    foreach ($submissions as $submission) {
        $data = json_decode((string)($submission['data'] ?? ''), true) ?: [];
        $assigneeLabel = trim((string)($submission['assigned_email'] ?? '')) !== ''
            ? formSubmissionAssigneeDisplayName([
                'email' => (string)($submission['assigned_email'] ?? ''),
                'first_name' => (string)($submission['assigned_first_name'] ?? ''),
                'last_name' => (string)($submission['assigned_last_name'] ?? ''),
                'nickname' => (string)($submission['assigned_nickname'] ?? ''),
                'role' => (string)($submission['assigned_role'] ?? ''),
                'is_superadmin' => (int)($submission['assigned_is_superadmin'] ?? 0),
            ])
            : '';

        $row = [
            formSubmissionReference($form, $submission),
            formatCzechDate((string)$submission['created_at']),
            formSubmissionStatusLabel((string)($submission['status'] ?? 'new')),
            formSubmissionPriorityLabel((string)($submission['priority'] ?? 'medium')),
            formSubmissionNormalizeLabels((string)($submission['labels'] ?? '')),
            $assigneeLabel,
            trim((string)($submission['internal_note'] ?? '')),
        ];

        foreach ($fieldDefinitions as $fieldName => $fieldDefinition) {
            $row[] = formSubmissionDisplayValueForField($fieldDefinition, $data[$fieldName] ?? '');
        }

        fputcsv($out, $row, ';');
    }

    fclose($out);
    exit;
}

$bulkOptions = [
    'new' => 'Označit jako nové',
    'in_progress' => 'Označit jako rozpracované',
    'resolved' => 'Označit jako vyřešené',
    'closed' => 'Označit jako uzavřené',
    'delete' => 'Smazat trvale',
];
$hasAdditionalFilters = $query !== '' || $priorityFilter !== 'all' || $assignedFilter !== 'all' || $githubFilter !== 'all';
$bulkConfirmError = trim((string)($_GET['error'] ?? '')) === 'bulk_confirm_required';
$bulkErrorFields = $bulkConfirmError ? ['confirm_form_submissions_bulk_action'] : [];
$bulkConfirmErrorMessage = 'Hromadnou akci s odpověďmi formuláře nejde provést bez potvrzení kontroly vybraných odpovědí a zvolené akce.';

$emptyStateText = match ($statusFilter) {
    'new' => 'Zatím tu nejsou žádné nové odpovědi tohoto formuláře.',
    'in_progress' => 'Zatím tu nejsou žádné rozpracované odpovědi tohoto formuláře.',
    'resolved' => 'Zatím tu nejsou žádné vyřešené odpovědi tohoto formuláře.',
    'closed' => 'Zatím tu nejsou žádné uzavřené odpovědi tohoto formuláře.',
    default => 'Tento formulář zatím nemá žádné odpovědi.',
};

adminHeader('Odpovědi formuláře – ' . mb_strimwidth((string)$form['title'], 0, 50, '…', 'UTF-8'));
?>

<?php if (isset($_GET['ok'])): ?>
  <p class="success" role="status">Workflow odpovědí formuláře byl aktualizován.</p>
<?php endif; ?>
<?php if ($bulkConfirmError): ?>
  <p id="form-submissions-bulk-form-error" class="error" role="alert" aria-atomic="true"><?= h($bulkConfirmErrorMessage) ?></p>
<?php endif; ?>

<div class="button-row">
  <a href="form_form.php?id=<?= (int)$form['id'] ?>" class="btn">Upravit formulář</a>
  <a href="forms.php" class="btn">Zpět na formuláře</a>
  <?php if ((int)($form['is_active'] ?? 0) === 1): ?>
    <a href="<?= h(formPublicPath($form)) ?>" class="btn" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
  <?php endif; ?>
  <?php if ($submissionCount > 0): ?>
    <a href="<?= h(appendUrlQuery('form_submissions.php', array_merge($currentParams, ['export' => 'csv']))) ?>" class="btn btn-primary">Přejít na kontrolu CSV exportu</a>
  <?php endif; ?>
</div>

<p>
  <strong>Formulář:</strong> <?= h((string)$form['title']) ?> ·
  <strong>Zobrazeno odpovědí:</strong> <?= $submissionCount ?>
  <?php if ($query !== '' || $statusFilter !== 'all' || $priorityFilter !== 'all'): ?>
    · <strong>Celkem odpovědí:</strong> <?= $allSubmissionCount ?>
  <?php endif; ?>
</p>

<p class="section-subtitle">Z přijatých odpovědí můžete udělat skutečný pracovní inbox: přiřadit řešitele, přidat interní poznámku, měnit stav a otevřít detail jednotlivého hlášení.</p>

<nav aria-labelledby="form-submissions-filter-heading" class="button-row admin-stack-sm">
  <h2 id="form-submissions-filter-heading" class="sr-only">Filtr odpovědí formuláře</h2>
  <a href="<?= h(appendUrlQuery('form_submissions.php', ['id' => $formId, 'status' => 'new', 'priority' => $priorityFilter !== 'all' ? $priorityFilter : null, 'assigned' => $assignedFilter !== 'all' ? $assignedFilter : null, 'github' => $githubFilter !== 'all' ? $githubFilter : null, 'q' => $query !== '' ? $query : null])) ?>"<?= $statusFilter === 'new' ? ' aria-current="page"' : '' ?>>
    Nové (<?= $statusCounts['new'] ?>)
  </a>
  <a href="<?= h(appendUrlQuery('form_submissions.php', ['id' => $formId, 'status' => 'in_progress', 'priority' => $priorityFilter !== 'all' ? $priorityFilter : null, 'assigned' => $assignedFilter !== 'all' ? $assignedFilter : null, 'github' => $githubFilter !== 'all' ? $githubFilter : null, 'q' => $query !== '' ? $query : null])) ?>"<?= $statusFilter === 'in_progress' ? ' aria-current="page"' : '' ?>>
    Rozpracované (<?= $statusCounts['in_progress'] ?>)
  </a>
  <a href="<?= h(appendUrlQuery('form_submissions.php', ['id' => $formId, 'status' => 'resolved', 'priority' => $priorityFilter !== 'all' ? $priorityFilter : null, 'assigned' => $assignedFilter !== 'all' ? $assignedFilter : null, 'github' => $githubFilter !== 'all' ? $githubFilter : null, 'q' => $query !== '' ? $query : null])) ?>"<?= $statusFilter === 'resolved' ? ' aria-current="page"' : '' ?>>
    Vyřešené (<?= $statusCounts['resolved'] ?>)
  </a>
  <a href="<?= h(appendUrlQuery('form_submissions.php', ['id' => $formId, 'status' => 'closed', 'priority' => $priorityFilter !== 'all' ? $priorityFilter : null, 'assigned' => $assignedFilter !== 'all' ? $assignedFilter : null, 'github' => $githubFilter !== 'all' ? $githubFilter : null, 'q' => $query !== '' ? $query : null])) ?>"<?= $statusFilter === 'closed' ? ' aria-current="page"' : '' ?>>
    Uzavřené (<?= $statusCounts['closed'] ?>)
  </a>
  <a href="<?= h(appendUrlQuery('form_submissions.php', ['id' => $formId, 'status' => 'all', 'priority' => $priorityFilter !== 'all' ? $priorityFilter : null, 'assigned' => $assignedFilter !== 'all' ? $assignedFilter : null, 'github' => $githubFilter !== 'all' ? $githubFilter : null, 'q' => $query !== '' ? $query : null])) ?>"<?= $statusFilter === 'all' ? ' aria-current="page"' : '' ?>>
    Všechny (<?= $allSubmissionCount ?>)
  </a>
</nav>

<form method="get" class="button-row button-row--baseline admin-stack-sm">
  <input type="hidden" name="id" value="<?= (int)$formId ?>">
  <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
  <label for="submissions-q">Hledat v odpovědích</label>
  <input type="search" id="submissions-q" name="q" value="<?= h($query) ?>" placeholder="Reference, obsah odpovědi nebo interní poznámka" class="admin-search-input">
  <label for="submissions-priority">Priorita</label>
  <select id="submissions-priority" name="priority">
    <option value="all">Všechny priority</option>
    <?php foreach ($priorityDefinitions as $priorityKey => $priorityDefinition): ?>
      <option value="<?= h($priorityKey) ?>"<?= $priorityFilter === $priorityKey ? ' selected' : '' ?>><?= h((string)$priorityDefinition['label']) ?></option>
    <?php endforeach; ?>
  </select>
  <label for="submissions-assigned">Přiřazení</label>
  <select id="submissions-assigned" name="assigned">
    <option value="all"<?= $assignedFilter === 'all' ? ' selected' : '' ?>>Všechna přiřazení</option>
    <option value="mine"<?= $assignedFilter === 'mine' ? ' selected' : '' ?>>Jen moje</option>
    <option value="unassigned"<?= $assignedFilter === 'unassigned' ? ' selected' : '' ?>>Jen nepřiřazené</option>
  </select>
  <label for="submissions-github">GitHub issue</label>
  <select id="submissions-github" name="github">
    <option value="all"<?= $githubFilter === 'all' ? ' selected' : '' ?>>Všechna hlášení</option>
    <option value="linked"<?= $githubFilter === 'linked' ? ' selected' : '' ?>>Jen s GitHub issue</option>
    <option value="unlinked"<?= $githubFilter === 'unlinked' ? ' selected' : '' ?>>Jen bez GitHub issue</option>
  </select>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($query !== '' || $priorityFilter !== 'all' || $assignedFilter !== 'all' || $githubFilter !== 'all'): ?>
    <a href="<?= h(appendUrlQuery('form_submissions.php', ['id' => $formId, 'status' => $statusFilter])) ?>" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($submissions)): ?>
  <?php if ($hasAdditionalFilters): ?>
    <p>Pro zadaný filtr se nenašla žádná odpověď. <a href="<?= h(appendUrlQuery('form_submissions.php', ['id' => $formId, 'status' => $statusFilter])) ?>">Zobrazit odpovědi bez dalších filtrů</a>.</p>
  <?php else: ?>
    <p><?= h($emptyStateText) ?></p>
  <?php endif; ?>
<?php else: ?>
  <form method="post" action="<?= BASE_URL ?>/admin/form_submission_bulk.php" id="form-submission-bulk-form"<?= $bulkConfirmError ? ' aria-describedby="form-submissions-bulk-form-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="form_id" value="<?= (int)$formId ?>">
    <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
    <fieldset class="admin-fieldset-card">
      <legend>Hromadné akce s vybranými odpověďmi</legend>
      <p data-selection-status="form-submissions" class="field-help field-help--flush" aria-live="polite">Zatím není vybraná žádná odpověď.</p>
      <div class="admin-stack-sm">
        <p id="form-submissions-bulk-review-help" class="field-help field-help--flush">
          Před hromadnou akcí zkontrolujte vybrané odpovědi a zvolenou akci. Potvrzení chrání před nechtěnou změnou workflow nebo trvalým smazáním odpovědí včetně nahraných příloh a historie.
        </p>
        <label for="confirm_form_submissions_bulk_action" class="admin-checkbox-label">
          <input type="checkbox" id="confirm_form_submissions_bulk_action" name="confirm_form_submissions_bulk_action" value="1" required aria-required="true"<?= adminFieldAttributes('confirm_form_submissions_bulk_action', $bulkErrorFields, [], ['form-submissions-bulk-review-help'], 'confirm-form-submissions-bulk-action-error') ?>>
          Potvrzuji, že jsem zkontroloval(a) vybrané odpovědi formuláře a zvolenou hromadnou akci.
        </label>
        <?php adminRenderFieldError('confirm_form_submissions_bulk_action', $bulkErrorFields, [], 'Před spuštěním hromadné akce zaškrtněte potvrzení kontroly vybraných odpovědí a zvolené akce.', 'confirm-form-submissions-bulk-action-error'); ?>
      </div>
      <div class="button-row">
        <?php foreach ($bulkOptions as $bulkAction => $bulkLabel): ?>
          <?php if ($statusFilter === $bulkAction): ?>
            <?php continue; ?>
          <?php endif; ?>
          <button type="submit"
                  form="form-submission-bulk-form"
                  name="action"
                  value="<?= h($bulkAction) ?>"
                  class="btn bulk-action-btn<?= $bulkAction === 'delete' ? ' btn-danger' : '' ?>"
                  disabled
                  <?php if ($bulkAction === 'delete'): ?>data-confirm="Smazat vybrané odpovědi formuláře trvale?"<?php endif; ?>>
            <?= h($bulkLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </fieldset>
  </form>

  <table>
    <caption>Přehled odpovědí formuláře</caption>
    <thead>
      <tr>
        <th scope="col"><label for="form-submissions-check-all" class="sr-only">Vybrat všechny odpovědi formuláře</label><input type="checkbox" id="form-submissions-check-all" form="form-submission-bulk-form"></th>
        <th scope="col">Reference</th>
        <th scope="col">Shrnutí</th>
        <th scope="col">Přijato</th>
        <th scope="col">Priorita</th>
        <th scope="col">Štítky</th>
        <th scope="col">Přiřazeno</th>
        <th scope="col">Stav</th>
        <th scope="col">GitHub</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($submissions as $submission): ?>
        <?php
        $submissionData = json_decode((string)($submission['data'] ?? ''), true) ?: [];
          $submissionSummary = formSubmissionSummary($fieldDefinitions, $submissionData, 2);
          if ($submissionSummary === '') {
              $submissionSummary = 'Bez vyplněných hodnot, které by šly zobrazit v přehledu.';
          }
          $assigneeLabel = trim((string)($submission['assigned_email'] ?? '')) !== ''
              ? formSubmissionAssigneeDisplayName([
                  'email' => (string)($submission['assigned_email'] ?? ''),
                  'first_name' => (string)($submission['assigned_first_name'] ?? ''),
                  'last_name' => (string)($submission['assigned_last_name'] ?? ''),
                  'nickname' => (string)($submission['assigned_nickname'] ?? ''),
                  'role' => (string)($submission['assigned_role'] ?? ''),
                  'is_superadmin' => (int)($submission['assigned_is_superadmin'] ?? 0),
              ])
              : 'Nepřiřazeno';
          $detailHref = 'form_submission.php?id=' . (int)$submission['id']
              . '&form_id=' . (int)$formId
              . '&redirect=' . rawurlencode($currentRedirect);
          ?>
        <tr>
          <td>
            <label for="form-submission-select-<?= (int)$submission['id'] ?>" class="sr-only">Vybrat odpověď <?= h(formSubmissionReference($form, $submission)) ?></label>
            <input type="checkbox"
                   id="form-submission-select-<?= (int)$submission['id'] ?>"
                   name="ids[]"
                   value="<?= (int)$submission['id'] ?>"
                   form="form-submission-bulk-form">
          </td>
          <td>
            <strong><?= h(formSubmissionReference($form, $submission)) ?></strong>
          </td>
          <td>
            <?= h($submissionSummary) ?>
            <?php if (trim((string)($submission['internal_note'] ?? '')) !== ''): ?>
              <br><small class="table-meta">Interní poznámka: <?= h(mb_strimwidth(trim((string)$submission['internal_note']), 0, 110, '…', 'UTF-8')) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <time datetime="<?= h(str_replace(' ', 'T', (string)$submission['created_at'])) ?>">
              <?= formatCzechDate((string)$submission['created_at']) ?>
            </time>
          </td>
          <td><strong><?= h(formSubmissionPriorityLabel((string)($submission['priority'] ?? 'medium'))) ?></strong></td>
          <td><?= h(($normalizedSubmissionLabels = formSubmissionNormalizeLabels((string)($submission['labels'] ?? ''))) !== '' ? $normalizedSubmissionLabels : '–') ?></td>
          <td><?= h($assigneeLabel) ?></td>
          <td><strong><?= h(formSubmissionStatusLabel((string)($submission['status'] ?? 'new'))) ?></strong></td>
          <td>
            <?php if (formSubmissionHasGitHubIssue($submission)): ?>
              <a href="<?= h((string)$submission['github_issue_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h(formSubmissionGitHubIssueLabel($submission)) ?><?= newWindowLinkSrOnlySuffix() ?></a>
            <?php else: ?>
              –
            <?php endif; ?>
          </td>
          <td class="actions">
            <a href="<?= h($detailHref) ?>" class="btn">Zobrazit detail</a>
            <form action="form_submission_delete.php" method="post">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
              <input type="hidden" name="form_id" value="<?= (int)$formId ?>">
              <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
              <button type="submit" class="btn btn-danger" data-confirm="Smazat tuto odpověď formuláře trvale?">Smazat</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="table-note" aria-hidden="true">Po výběru odpovědí můžete použít hromadné akce nahoře.</div>

  <script nonce="<?= h(cspNonce()) ?>">
  (() => {
      const checkAll = document.getElementById('form-submissions-check-all');
      const checkboxes = Array.from(document.querySelectorAll('input[form="form-submission-bulk-form"][name="ids[]"]'));
      const actionButtons = Array.from(document.querySelectorAll('#form-submission-bulk-form .bulk-action-btn'));
      const status = document.querySelector('[data-selection-status="form-submissions"]');

      const updateBulkUi = () => {
          const selectedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
          if (status) {
              status.textContent = selectedCount === 0
                  ? 'Zatím není vybraná žádná odpověď.'
                  : (selectedCount === 1
                      ? 'Vybraná je 1 odpověď.'
                      : 'Vybrané jsou ' + selectedCount + ' odpovědi.');
          }
          actionButtons.forEach((button) => {
              button.disabled = selectedCount === 0;
          });
          if (checkAll) {
              checkAll.checked = selectedCount > 0 && selectedCount === checkboxes.length;
              checkAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
          }
      };

      checkAll?.addEventListener('change', function () {
          checkboxes.forEach((checkbox) => {
              checkbox.checked = this.checked;
          });
          updateBulkUi();
      });

      checkboxes.forEach((checkbox) => {
          checkbox.addEventListener('change', updateBulkUi);
      });

      updateBulkUi();
  })();
  </script>
<?php endif; ?>

<?php adminFooter(); ?>
