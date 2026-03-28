<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

$pdo = db_connect();
$formId = inputInt('get', 'id');

if ($formId === null) {
    header('Location: ' . BASE_URL . '/admin/forms.php');
    exit;
}

$form = $pdo->prepare("SELECT * FROM cms_forms WHERE id = ?");
$form->execute([$formId]);
$form = $form->fetch() ?: null;

if (!$form) {
    header('Location: ' . BASE_URL . '/admin/forms.php');
    exit;
}

$fields = $pdo->prepare("SELECT * FROM cms_form_fields WHERE form_id = ? ORDER BY sort_order, id");
$fields->execute([$formId]);
$fields = $fields->fetchAll();

$fieldNames = [];
$fieldDefinitions = [];
foreach ($fields as $field) {
    $fieldNames[(string)$field['name']] = (string)$field['label'];
    $fieldDefinitions[(string)$field['name']] = $field;
}

$query = trim((string)($_GET['q'] ?? ''));
$submissionSql = "SELECT * FROM cms_form_submissions WHERE form_id = ?";
$submissionParams = [$formId];
if ($query !== '') {
    $submissionSql .= " AND data LIKE ?";
    $submissionParams[] = '%' . $query . '%';
}
$submissionSql .= " ORDER BY created_at DESC";
$submissionsStmt = $pdo->prepare($submissionSql);
$submissionsStmt->execute($submissionParams);
$submissions = $submissionsStmt->fetchAll();
$allSubmissionCountStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_form_submissions WHERE form_id = ?");
$allSubmissionCountStmt->execute([$formId]);
$allSubmissionCount = (int)$allSubmissionCountStmt->fetchColumn();
$submissionCount = count($submissions);
$currentUrl = BASE_URL . '/admin/form_submissions.php?id=' . $formId . ($query !== '' ? '&q=' . urlencode($query) : '');

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="formular-' . rawurlencode((string)$form['slug']) . '-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM

    // Hlavička
    $headerRow = ['Datum'];
    foreach ($fieldNames as $label) {
        $headerRow[] = $label;
    }
    fputcsv($out, $headerRow, ';');

    // Data
    foreach ($submissions as $sub) {
        $data = json_decode((string)$sub['data'], true) ?: [];
        $csvRow = [formatCzechDate((string)$sub['created_at'])];
        foreach ($fieldNames as $name => $label) {
            $value = $data[$name] ?? '';
            $csvRow[] = formSubmissionDisplayValueForField($fieldDefinitions[$name] ?? [], $value);
        }
        fputcsv($out, $csvRow, ';');
    }

    fclose($out);
    exit;
}

adminHeader('Odpovědi formuláře – ' . mb_strimwidth((string)$form['title'], 0, 50, '…', 'UTF-8'));
?>

<div style="display:flex;gap:.75rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap">
  <a href="form_form.php?id=<?= $formId ?>" class="btn">Upravit formulář</a>
  <a href="forms.php" class="btn">Zpět na formuláře</a>
  <?php if ((int)($form['is_active'] ?? 0) === 1): ?>
    <a href="<?= h(formPublicPath($form)) ?>" class="btn" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
  <?php endif; ?>
  <?php if (!empty($submissions)): ?>
    <a href="form_submissions.php?id=<?= $formId ?><?= $query !== '' ? '&amp;q=' . urlencode($query) : '' ?>&amp;export=csv" class="btn btn-primary">Exportovat CSV</a>
  <?php endif; ?>
</div>

<p>
  <strong>Formulář:</strong> <?= h((string)$form['title']) ?> ·
  <strong>Zobrazeno odpovědí:</strong> <?= $submissionCount ?>
  <?php if ($query !== ''): ?>
    · <strong>Celkem odpovědí:</strong> <?= $allSubmissionCount ?>
  <?php endif; ?>
</p>

<p class="section-subtitle">Tady najdete doručené odpovědi a můžete je vyhledat nebo exportovat do CSV.</p>

<form method="get" class="filters" style="margin:1rem 0">
  <input type="hidden" name="id" value="<?= $formId ?>">
  <label for="submissions-q">Hledat v odpovědích</label>
  <input type="search" id="submissions-q" name="q" value="<?= h($query) ?>" placeholder="Jméno, e-mail, telefon nebo jiná hodnota">
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($query !== ''): ?>
    <a href="form_submissions.php?id=<?= $formId ?>" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($submissions)): ?>
  <?php if ($query !== ''): ?>
    <p>Pro zadaný filtr se nenašla žádná odpověď. <a href="form_submissions.php?id=<?= $formId ?>">Zobrazit všechny odpovědi</a>.</p>
  <?php else: ?>
    <p>Tento formulář zatím nemá žádné odpovědi.</p>
  <?php endif; ?>
<?php else: ?>
  <div style="overflow-x:auto">
    <table>
      <caption>Přehled odpovědí formuláře</caption>
      <thead>
        <tr>
          <th scope="col">Datum</th>
          <?php foreach ($fieldNames as $label): ?>
            <th scope="col"><?= h($label) ?></th>
          <?php endforeach; ?>
          <th scope="col">Akce</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($submissions as $sub): ?>
        <?php $data = json_decode((string)$sub['data'], true) ?: []; ?>
        <tr>
          <td><time datetime="<?= h(str_replace(' ', 'T', (string)$sub['created_at'])) ?>"><?= h(formatCzechDate((string)$sub['created_at'])) ?></time></td>
          <?php foreach ($fieldNames as $name => $label): ?>
            <td>
              <?php
              $value = $data[$name] ?? '';
              if (is_array($value) && isset($value['url'], $value['original_name'])) {
                  ?>
                  <a href="<?= h((string)$value['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string)$value['original_name']) ?></a>
                  <?php
              } elseif (is_array($value) && array_keys($value) === range(0, count($value) - 1)) {
                  $fileLinks = [];
                  foreach ($value as $item) {
                      if (!is_array($item) || !isset($item['url'], $item['original_name'])) {
                          continue;
                      }
                      $fileLinks[] = '<a href="' . h((string)$item['url']) . '" target="_blank" rel="noopener noreferrer">' . h((string)$item['original_name']) . '</a>';
                  }
                  if ($fileLinks !== []) {
                      echo implode(', ', $fileLinks);
                  } else {
                      echo h(formSubmissionDisplayValueForField($fieldDefinitions[$name] ?? [], $value));
                  }
              } else {
                  echo h(formSubmissionDisplayValueForField($fieldDefinitions[$name] ?? [], $value));
              }
              ?>
            </td>
          <?php endforeach; ?>
          <td>
            <form action="form_submission_delete.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
              <input type="hidden" name="form_id" value="<?= $formId ?>">
              <input type="hidden" name="redirect" value="<?= h($currentUrl) ?>">
              <button type="submit" class="btn btn-danger"
                      data-confirm="Smazat tuto odpověď?">Smazat odpověď</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php adminFooter(); ?>
