<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu formulářů nemáte potřebné oprávnění.');

$pdo = db_connect();
$query = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$allowedStatusFilters = ['all', 'active', 'inactive'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$forms = [];
try {
    $where = [];
    $params = [];
    if ($query !== '') {
        $where[] = '(f.title LIKE ? OR f.slug LIKE ? OR f.description LIKE ?)';
        $params[] = '%' . $query . '%';
        $params[] = '%' . $query . '%';
        $params[] = '%' . $query . '%';
    }
    if ($statusFilter === 'active') {
        $where[] = 'f.is_active = 1';
    } elseif ($statusFilter === 'inactive') {
        $where[] = 'f.is_active = 0';
    }

    $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare(
        "SELECT f.id, f.title, f.slug, f.is_active, f.created_at,
                (SELECT COUNT(*) FROM cms_form_fields WHERE form_id = f.id) AS field_count,
                (SELECT COUNT(*) FROM cms_form_submissions WHERE form_id = f.id) AS submission_count,
                (SELECT COUNT(*) FROM cms_form_submissions WHERE form_id = f.id AND status = 'new') AS new_submission_count,
                (SELECT COUNT(*) FROM cms_form_submissions WHERE form_id = f.id AND status IN ('new', 'in_progress')) AS open_submission_count
         FROM cms_forms f
         {$whereSql}
         ORDER BY f.created_at DESC"
    );
    $stmt->execute($params);
    $forms = $stmt->fetchAll();
} catch (\PDOException $e) {
    error_log('admin/forms: ' . $e->getMessage());
}

adminHeader('Formuláře');
?>
<div class="button-row">
  <a href="form_form.php" class="btn btn-primary">Vytvořit formulář</a>
  <a href="form_form.php?preset=issue_report" class="btn">Nahlášení chyby</a>
  <a href="form_form.php?preset=feature_request" class="btn">Návrh nové funkce</a>
  <a href="form_form.php?preset=support_request" class="btn">Žádost o podporu</a>
  <a href="form_form.php?preset=contact_basic" class="btn">Kontaktní formulář</a>
  <a href="form_form.php?preset=content_report" class="btn">Nahlášení problému s obsahem</a>
</div>

<p class="section-subtitle">Na jednom místě připravíte veřejné formuláře, jejich pole i přehled doručených odpovědí.</p>

<form method="get" class="filters" style="margin:1rem 0">
  <label for="forms-q">Hledat formuláře</label>
  <input type="search" id="forms-q" name="q" value="<?= h($query) ?>" placeholder="Název, adresa nebo popis">

  <label for="forms-status">Stav</label>
  <select id="forms-status" name="status">
    <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Všechny formuláře</option>
    <option value="active"<?= $statusFilter === 'active' ? ' selected' : '' ?>>Jen aktivní</option>
    <option value="inactive"<?= $statusFilter === 'inactive' ? ' selected' : '' ?>>Jen neaktivní</option>
  </select>

  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($query !== '' || $statusFilter !== 'all'): ?>
    <a href="forms.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($forms)): ?>
  <?php if ($query !== '' || $statusFilter !== 'all'): ?>
    <p>Pro zadaný filtr se nenašel žádný formulář. <a href="forms.php">Zobrazit všechny formuláře</a>.</p>
  <?php else: ?>
    <p>Zatím tu nejsou žádné formuláře. <a href="form_form.php">Vytvořit první formulář</a>, <a href="form_form.php?preset=issue_report">připravit formulář pro nahlášení chyby</a> nebo sáhnout po některé z připravených šablon.</p>
  <?php endif; ?>
<?php else: ?>
  <table>
    <caption>Přehled formulářů</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Pole</th>
        <th scope="col">Odpovědi</th>
        <th scope="col">Otevřené</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($forms as $form): ?>
      <tr>
        <td>
          <strong><?= h((string)$form['title']) ?></strong><br>
          <small style="color:#555">/forms/<?= h((string)$form['slug']) ?></small>
        </td>
        <td><?= (int)$form['field_count'] ?></td>
        <td><?= (int)$form['submission_count'] ?></td>
        <td>
          <?php if ((int)$form['open_submission_count'] > 0): ?>
            <strong><?= (int)$form['open_submission_count'] ?></strong>
            <?php if ((int)$form['new_submission_count'] > 0): ?>
              <br><small style="color:#9a3412">nové: <?= (int)$form['new_submission_count'] ?></small>
            <?php endif; ?>
          <?php else: ?>
            0
          <?php endif; ?>
        </td>
        <td><?= (int)$form['is_active'] ? 'Aktivní' : 'Neaktivní' ?></td>
        <td class="actions">
          <a href="form_form.php?id=<?= (int)$form['id'] ?>" class="btn">Upravit</a>
          <?php if ((int)$form['submission_count'] > 0): ?>
            <a href="form_submissions.php?id=<?= (int)$form['id'] ?>">Odpovědi (<?= (int)$form['submission_count'] ?>)</a>
          <?php endif; ?>
          <a href="<?= h(formPublicPath($form)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
          <form action="form_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
            <input type="hidden" name="redirect" value="<?= h((string)($_SERVER['REQUEST_URI'] ?? (BASE_URL . '/admin/forms.php'))) ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat formulář včetně všech odpovědí?">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
