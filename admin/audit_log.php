<?php
require_once __DIR__ . '/layout.php';
requireCapability('settings_manage', 'Přístup odepřen.');

$isCsvExport = (string)($_GET['export'] ?? '') === 'csv';
$requestMethod = requireHttpMethods($isCsvExport ? ['GET', 'HEAD', 'POST'] : ['GET', 'HEAD']);

$pdo = db_connect();

$filterAction = trim((string)($_GET['action'] ?? ''));
$filterUser   = trim((string)($_GET['user'] ?? ''));
$filterDate   = trim((string)($_GET['date'] ?? ''));
$perPage = 50;

$where = [];
$params = [];

if ($filterAction !== '') {
    $where[] = 'l.action LIKE ?';
    $params[] = '%' . $filterAction . '%';
}
if ($filterUser !== '' && ctype_digit($filterUser)) {
    $where[] = 'l.user_id = ?';
    $params[] = (int)$filterUser;
}
if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $where[] = 'DATE(l.created_at) = ?';
    $params[] = $filterDate;
}

$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
$selectSql = "SELECT l.id, l.action, l.detail, l.user_id, l.created_at,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email, '–') AS user_name
     FROM cms_log l
     LEFT JOIN cms_users u ON u.id = l.user_id
     {$whereSql}
     ORDER BY l.created_at DESC, l.id DESC";

$pag = paginate($pdo, "SELECT COUNT(*) FROM cms_log l {$whereSql}", $params, $perPage);
['total' => $totalEntries, 'totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pag;

$stmt = $pdo->prepare($selectSql . ' LIMIT ? OFFSET ?');
$stmt->execute(array_merge($params, [$perPage, $offset]));
$entries = $stmt->fetchAll();

$users = $pdo->query(
    "SELECT DISTINCT l.user_id, COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS name
     FROM cms_log l LEFT JOIN cms_users u ON u.id = l.user_id WHERE l.user_id IS NOT NULL ORDER BY name"
)->fetchAll();

$filterParams = [];
if ($filterAction !== '') {
    $filterParams['action'] = $filterAction;
}
if ($filterUser !== '') {
    $filterParams['user'] = $filterUser;
}
if ($filterDate !== '') {
    $filterParams['date'] = $filterDate;
}
$baseUrl = BASE_URL . '/admin/audit_log.php';
$paginBase = $baseUrl . ($filterParams !== [] ? '?' . http_build_query($filterParams) . '&' : '?');
$exportUrl = appendUrlQuery('audit_log.php', array_merge($filterParams, ['export' => 'csv']));
$returnUrl = appendUrlQuery('audit_log.php', $filterParams);
$hasFilter = $filterParams !== [];

$renderAuditLogCsvExportForm = static function (bool $confirmExportError = false) use (
    $exportUrl,
    $returnUrl,
    $filterAction,
    $filterUser,
    $filterDate,
    $totalEntries
): void {
    $exportErrorFields = $confirmExportError ? ['confirm_audit_log_csv_export'] : [];
    $exportConfirmErrorMessage = 'CSV export audit logu nejde stáhnout bez potvrzení kontroly citlivosti provozních záznamů. U pole Potvrzení stažení je konkrétní nápověda.';
    $filterSummaryParts = [];
    if ($filterAction !== '') {
        $filterSummaryParts[] = 'akce obsahuje "' . $filterAction . '"';
    }
    if ($filterUser !== '') {
        $filterSummaryParts[] = 'uživatel ID ' . $filterUser;
    }
    if ($filterDate !== '') {
        $filterSummaryParts[] = 'datum ' . $filterDate;
    }
    $filterSummary = $filterSummaryParts !== []
        ? 'Aktuální filtr: ' . implode(', ', $filterSummaryParts) . '.'
        : 'Export zahrne všechny záznamy audit logu.';

    adminHeader('Export audit logu');
    ?>
    <p class="admin-description">
      Připraví CSV export audit logu podle aktuálního filtru.
    </p>

    <?php if ($confirmExportError): ?>
      <p id="audit-log-csv-export-form-error" class="error" role="alert" aria-atomic="true"><?= h($exportConfirmErrorMessage) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= h($exportUrl) ?>" novalidate<?= $confirmExportError ? ' aria-describedby="audit-log-csv-export-form-error"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <fieldset class="admin-fieldset-card">
        <legend>Export audit logu</legend>
        <p id="audit-log-csv-export-review-help" class="field-help field-help--flush">
          CSV export audit logu obsahuje administrační akce, uživatele, časy a provozní podrobnosti, které mohou prozrazovat bezpečnostní i redakční chování CMS.
          Export zahrne <?= (int)$totalEntries ?> záznamů. <?= h($filterSummary) ?> Soubor ukládejte jen do oprávněného a bezpečného úložiště.
        </p>
        <label for="confirm_audit_log_csv_export" class="admin-checkbox-label">
          <input type="checkbox" id="confirm_audit_log_csv_export" name="confirm_audit_log_csv_export" value="1" required aria-required="true"<?= adminFieldAttributes('confirm_audit_log_csv_export', $exportErrorFields, [], ['audit-log-csv-export-review-help'], 'confirm-audit-log-csv-export-error') ?>>
          Potvrzuji, že jsem zkontroloval(a) rozsah audit logu a mám oprávnění CSV stáhnout.
        </label>
        <?php adminRenderFieldError('confirm_audit_log_csv_export', $exportErrorFields, [], 'Před stažením CSV exportu potvrďte, že rozumíte citlivosti audit logu a máte oprávnění soubor stáhnout.', 'confirm-audit-log-csv-export-error'); ?>
        <div class="admin-field-row">
          <button type="submit" class="btn" data-confirm="Stáhnout CSV export audit logu? Soubor obsahuje provozní a bezpečnostní záznamy administrace.">Stáhnout CSV export</button>
        </div>
      </fieldset>
    </form>

    <p><a href="<?= h($returnUrl) ?>"><span aria-hidden="true">←</span> Zpět na audit log</a></p>
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
        $renderAuditLogCsvExportForm();
        exit;
    }

    verifyCsrf();
    $confirmAuditLogCsvExport = isset($_POST['confirm_audit_log_csv_export'])
        && (string)$_POST['confirm_audit_log_csv_export'] === '1';
    if (!$confirmAuditLogCsvExport) {
        $renderAuditLogCsvExportForm(true);
        exit;
    }

    $exportStmt = $pdo->prepare($selectSql);
    $exportStmt->execute($params);
    $exportRows = $exportStmt->fetchAll();

    sendAdminAttachmentHeaders(
        'text/csv; charset=UTF-8',
        'audit-log-' . date('Y-m-d_His') . '.csv'
    );
    logAction(
        'audit_log_export_csv',
        'action_filter=' . $filterAction . ';user_filter=' . $filterUser . ';date=' . $filterDate . ';count=' . count($exportRows)
    );

    $outputHandle = fopen('php://output', 'wb');
    if ($outputHandle !== false) {
        fwrite($outputHandle, "\xEF\xBB\xBF");
        fputcsv($outputHandle, ['ID', 'Datum a čas', 'Uživatel', 'ID uživatele', 'Akce', 'Podrobnosti'], ';');
        foreach ($exportRows as $exportRow) {
            fputcsv($outputHandle, [
                (int)$exportRow['id'],
                (string)$exportRow['created_at'],
                (string)$exportRow['user_name'],
                $exportRow['user_id'] !== null ? (int)$exportRow['user_id'] : '',
                (string)$exportRow['action'],
                (string)$exportRow['detail'],
            ], ';');
        }
    }
    exit;
}

adminHeader('Audit log');
?>

<p class="admin-description">Záznam akcí provedených v administraci – přihlášení, úpravy obsahu, změny nastavení a další.</p>

<form method="get" class="button-row admin-stack-sm">
  <label for="action" class="visually-hidden">Akce</label>
  <input type="text" id="action" name="action" placeholder="Hledat akci…"
         value="<?= h($filterAction) ?>" class="admin-input-sm">

  <label for="user" class="visually-hidden">Uživatel</label>
  <select id="user" name="user" class="admin-select-sm">
    <option value="">Všichni uživatelé</option>
    <?php foreach ($users as $u): ?>
      <option value="<?= (int)$u['user_id'] ?>"<?= $filterUser === (string)$u['user_id'] ? ' selected' : '' ?>><?= h((string)$u['name']) ?></option>
    <?php endforeach; ?>
  </select>

  <label for="date" class="visually-hidden">Datum</label>
  <input type="date" id="date" name="date" value="<?= h($filterDate) ?>" class="admin-input-auto">

  <button type="submit" class="btn">Filtrovat</button>
  <?php if ($hasFilter): ?>
    <a href="audit_log.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
  <a href="<?= h($exportUrl) ?>" class="btn">Zkontrolovat CSV export</a>
</form>

<?php if (empty($entries)): ?>
  <p>Žádné záznamy pro zadaný filtr.</p>
<?php else: ?>
  <table>
    <caption>Audit log – záznamy akcí</caption>
    <thead>
      <tr>
        <th scope="col">Datum a čas</th>
        <th scope="col">Uživatel</th>
        <th scope="col">Akce</th>
        <th scope="col">Podrobnosti akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($entries as $entry): ?>
      <tr>
        <td><time datetime="<?= h(str_replace(' ', 'T', (string)$entry['created_at'])) ?>"><?= h((string)$entry['created_at']) ?></time></td>
        <td><?= h((string)$entry['user_name']) ?></td>
        <td><code><?= h((string)$entry['action']) ?></code></td>
        <td class="table-cell--detail"><?= h(mb_substr((string)$entry['detail'], 0, 200)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?= renderPager($page, $pages, $paginBase, 'Stránkování audit logu', 'Předchozí', 'Další') ?>
<?php endif; ?>

<?php adminFooter(); ?>
