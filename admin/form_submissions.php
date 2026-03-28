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
foreach ($fields as $field) {
    $fieldNames[(string)$field['name']] = (string)$field['label'];
}

$submissions = $pdo->prepare(
    "SELECT * FROM cms_form_submissions WHERE form_id = ? ORDER BY created_at DESC"
);
$submissions->execute([$formId]);
$submissions = $submissions->fetchAll();

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
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $csvRow[] = (string)$value;
        }
        fputcsv($out, $csvRow, ';');
    }

    fclose($out);
    exit;
}

adminHeader('Odpovědi – ' . mb_substr((string)$form['title'], 0, 50));
?>

<div style="display:flex;gap:.75rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap">
  <a href="form_form.php?id=<?= $formId ?>" class="btn">&larr; Zpět na formulář</a>
  <a href="forms.php" class="btn">Všechny formuláře</a>
  <?php if (!empty($submissions)): ?>
    <a href="form_submissions.php?id=<?= $formId ?>&amp;export=csv" class="btn btn-primary">Exportovat CSV</a>
  <?php endif; ?>
</div>

<p>
  <strong>Formulář:</strong> <?= h((string)$form['title']) ?> ·
  <strong>Odpovědí:</strong> <?= count($submissions) ?>
</p>

<?php if (empty($submissions)): ?>
  <p>Tento formulář zatím nemá žádné odpovědi.</p>
<?php else: ?>
  <div style="overflow-x:auto">
    <table>
      <caption class="sr-only">Odpovědi formuláře <?= h((string)$form['title']) ?></caption>
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
              if (is_array($value)) {
                  $value = implode(', ', $value);
              }
              echo h((string)$value);
              ?>
            </td>
          <?php endforeach; ?>
          <td>
            <form action="form_submission_delete.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
              <input type="hidden" name="form_id" value="<?= $formId ?>">
              <button type="submit" class="btn btn-danger"
                      onclick="return confirm('Smazat tuto odpověď?')">Smazat</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php adminFooter(); ?>
