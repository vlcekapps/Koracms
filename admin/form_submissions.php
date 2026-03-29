<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

$pdo = db_connect();
$formId = inputInt('get', 'id');

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
$allowedStatusFilters = array_merge(['all'], array_keys($statusDefinitions));
$statusFilter = trim((string)($_GET['status'] ?? 'new'));
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'new';
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
     ORDER BY FIELD(s.status, 'new', 'in_progress', 'resolved', 'closed'), s.created_at DESC, s.id DESC"
);
$submissionsStmt->execute($params);
$submissions = $submissionsStmt->fetchAll();
$submissionCount = count($submissions);

$currentParams = ['id' => $formId];
if ($statusFilter !== 'all') {
    $currentParams['status'] = $statusFilter;
}
if ($query !== '') {
    $currentParams['q'] = $query;
}
$currentRedirect = BASE_URL . appendUrlQuery('/admin/form_submissions.php', $currentParams);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="formular-' . rawurlencode((string)$form['slug']) . '-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");

    $headerRow = ['Reference', 'Datum', 'Stav', 'Přiřazeno', 'Interní poznámka'];
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

<div class="button-row">
  <a href="form_form.php?id=<?= (int)$form['id'] ?>" class="btn">Upravit formulář</a>
  <a href="forms.php" class="btn">Zpět na formuláře</a>
  <?php if ((int)($form['is_active'] ?? 0) === 1): ?>
    <a href="<?= h(formPublicPath($form)) ?>" class="btn" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
  <?php endif; ?>
  <?php if ($submissionCount > 0): ?>
    <a href="<?= h(appendUrlQuery('form_submissions.php', array_merge($currentParams, ['export' => 'csv']))) ?>" class="btn btn-primary">Exportovat CSV</a>
  <?php endif; ?>
</div>

<p>
  <strong>Formulář:</strong> <?= h((string)$form['title']) ?> ·
  <strong>Zobrazeno odpovědí:</strong> <?= $submissionCount ?>
  <?php if ($query !== '' || $statusFilter !== 'all'): ?>
    · <strong>Celkem odpovědí:</strong> <?= $allSubmissionCount ?>
  <?php endif; ?>
</p>

<p class="section-subtitle">Z přijatých odpovědí můžete udělat skutečný pracovní inbox: přiřadit řešitele, přidat interní poznámku, měnit stav a otevřít detail jednotlivého hlášení.</p>

<nav aria-label="Filtr odpovědí formuláře" class="button-row" style="margin-bottom:1rem">
  <a href="?id=<?= (int)$formId ?>&amp;status=new"<?= $statusFilter === 'new' ? ' aria-current="page"' : '' ?>>
    Nové (<?= $statusCounts['new'] ?>)
  </a>
  <a href="?id=<?= (int)$formId ?>&amp;status=in_progress"<?= $statusFilter === 'in_progress' ? ' aria-current="page"' : '' ?>>
    Rozpracované (<?= $statusCounts['in_progress'] ?>)
  </a>
  <a href="?id=<?= (int)$formId ?>&amp;status=resolved"<?= $statusFilter === 'resolved' ? ' aria-current="page"' : '' ?>>
    Vyřešené (<?= $statusCounts['resolved'] ?>)
  </a>
  <a href="?id=<?= (int)$formId ?>&amp;status=closed"<?= $statusFilter === 'closed' ? ' aria-current="page"' : '' ?>>
    Uzavřené (<?= $statusCounts['closed'] ?>)
  </a>
  <a href="?id=<?= (int)$formId ?>&amp;status=all"<?= $statusFilter === 'all' ? ' aria-current="page"' : '' ?>>
    Všechny (<?= $allSubmissionCount ?>)
  </a>
</nav>

<form method="get" class="filters" style="margin:1rem 0">
  <input type="hidden" name="id" value="<?= (int)$formId ?>">
  <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
  <label for="submissions-q">Hledat v odpovědích</label>
  <input type="search" id="submissions-q" name="q" value="<?= h($query) ?>" placeholder="Reference, obsah odpovědi nebo interní poznámka">
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($query !== ''): ?>
    <a href="<?= h(appendUrlQuery('form_submissions.php', ['id' => $formId, 'status' => $statusFilter])) ?>" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($submissions)): ?>
  <?php if ($query !== ''): ?>
    <p>Pro zadaný filtr se nenašla žádná odpověď. <a href="<?= h(appendUrlQuery('form_submissions.php', ['id' => $formId, 'status' => $statusFilter])) ?>">Zobrazit odpovědi bez hledání</a>.</p>
  <?php else: ?>
    <p><?= h($emptyStateText) ?></p>
  <?php endif; ?>
<?php else: ?>
  <form method="post" action="<?= BASE_URL ?>/admin/form_submission_bulk.php" id="form-submission-bulk-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="form_id" value="<?= (int)$formId ?>">
    <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
    <fieldset style="margin:0 0 .85rem;border:1px solid #d6d6d6;border-radius:10px;padding:.85rem 1rem">
      <legend>Hromadné akce s vybranými odpověďmi</legend>
      <p data-selection-status="form-submissions" class="field-help" aria-live="polite" style="margin-top:0">Zatím není vybraná žádná odpověď.</p>
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
        <th scope="col"><input type="checkbox" id="form-submissions-check-all" aria-label="Vybrat všechny odpovědi formuláře" form="form-submission-bulk-form"></th>
        <th scope="col">Reference</th>
        <th scope="col">Shrnutí</th>
        <th scope="col">Přijato</th>
        <th scope="col">Přiřazeno</th>
        <th scope="col">Stav</th>
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
            <input type="checkbox"
                   name="ids[]"
                   value="<?= (int)$submission['id'] ?>"
                   aria-label="Vybrat odpověď <?= h(formSubmissionReference($form, $submission)) ?>"
                   form="form-submission-bulk-form">
          </td>
          <td>
            <strong><?= h(formSubmissionReference($form, $submission)) ?></strong>
          </td>
          <td>
            <?= h($submissionSummary) ?>
            <?php if (trim((string)($submission['internal_note'] ?? '')) !== ''): ?>
              <br><small style="color:#555">Interní poznámka: <?= h(mb_strimwidth(trim((string)$submission['internal_note']), 0, 110, '…', 'UTF-8')) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <time datetime="<?= h(str_replace(' ', 'T', (string)$submission['created_at'])) ?>">
              <?= formatCzechDate((string)$submission['created_at']) ?>
            </time>
          </td>
          <td><?= h($assigneeLabel) ?></td>
          <td><strong><?= h(formSubmissionStatusLabel((string)($submission['status'] ?? 'new'))) ?></strong></td>
          <td class="actions">
            <a href="<?= h($detailHref) ?>" class="btn">Zobrazit detail</a>
            <form action="form_submission_delete.php" method="post" style="display:inline">
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

  <div style="margin-top:.75rem;color:#555" aria-hidden="true">Po výběru odpovědí můžete použít hromadné akce nahoře.</div>

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
