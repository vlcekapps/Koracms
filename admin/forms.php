<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu formulářů nemáte potřebné oprávnění.');
requireModuleEnabled('forms');

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
        "SELECT f.id, f.title, f.slug, f.is_active, f.show_in_nav, f.created_at,
                (SELECT COUNT(*) FROM cms_form_fields WHERE form_id = f.id) AS field_count,
                (SELECT COUNT(*) FROM cms_form_submissions WHERE form_id = f.id) AS submission_count,
                (SELECT COUNT(*) FROM cms_form_submissions WHERE form_id = f.id AND status = 'new') AS new_submission_count,
                (SELECT COUNT(*) FROM cms_form_submissions WHERE form_id = f.id AND status IN ('new', 'in_progress')) AS open_submission_count,
                (SELECT COUNT(*)
                   FROM cms_form_submission_history h
                   INNER JOIN cms_form_submissions s ON s.id = h.submission_id
                  WHERE s.form_id = f.id) AS history_count
         FROM cms_forms f
         {$whereSql}
         ORDER BY f.created_at DESC"
    );
    $stmt->execute($params);
    $forms = $stmt->fetchAll();
} catch (\PDOException $e) {
    koraLog('warning', 'admin forms overview query failed', [
        'status_filter' => $statusFilter,
        'has_query' => $query !== '',
        'exception' => $e,
    ]);
}

$currentRedirect = appendUrlQuery(BASE_URL . '/admin/forms.php', [
    'q' => $query !== '' ? $query : null,
    'status' => $statusFilter !== 'all' ? $statusFilter : null,
]);
$deleteError = trim((string)($_GET['delete_error'] ?? ''));
$deleteErrorFormId = inputInt('get', 'delete_error_id');
$deleteErrorMessage = match ($deleteError) {
    'confirm_required' => 'Formulář nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.',
    'invalid' => 'Formulář nejde smazat, protože už není dostupný. Vyberte formulář ze seznamu znovu.',
    default => '',
};
$deleteSuccessMessage = trim((string)($_GET['deleted'] ?? '')) === '1'
    ? 'Formulář byl smazán.'
    : '';

adminHeader('Formuláře');
?>
<?php if ($deleteSuccessMessage !== ''): ?><p class="success" role="status"><?= h($deleteSuccessMessage) ?></p><?php endif; ?>
<?php if ($deleteErrorMessage !== ''): ?><p id="form-delete-error" class="error" role="alert" aria-atomic="true"><?= h($deleteErrorMessage) ?></p><?php endif; ?>

<div class="button-row">
  <a href="form_form.php" class="btn btn-primary">Vytvořit formulář</a>
  <a href="form_form.php?preset=issue_report" class="btn">Nahlášení chyby</a>
  <a href="form_form.php?preset=feature_request" class="btn">Návrh nové funkce</a>
  <a href="form_form.php?preset=support_request" class="btn">Žádost o podporu</a>
  <a href="form_form.php?preset=contact_basic" class="btn">Kontaktní formulář</a>
  <a href="form_form.php?preset=content_report" class="btn">Nahlášení problému s obsahem</a>
</div>

<p class="section-subtitle">Na jednom místě připravíte veřejné formuláře, jejich pole i přehled doručených odpovědí.</p>

<form method="get" class="button-row button-row--baseline admin-stack-sm">
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
  <div class="table-responsive">
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
        <?php
          $formId = (int)$form['id'];
          $deleteConfirmField = 'confirm_form_delete_' . $formId;
          $deleteConfirmId = 'confirm-form-delete-' . $formId;
          $deleteReviewId = 'form-delete-review-' . $formId;
          $deleteFieldErrorId = 'confirm-form-delete-' . $formId . '-error';
          $deleteHasError = $deleteError === 'confirm_required' && $deleteErrorFormId === $formId;
          $deleteErrorFields = $deleteHasError ? [$deleteConfirmField] : [];
          ?>
        <tr>
          <td>
            <strong><?= h((string)$form['title']) ?></strong><br>
            <small class="table-meta">/forms/<?= h((string)$form['slug']) ?></small>
          </td>
          <td><?= (int)$form['field_count'] ?></td>
          <td><?= (int)$form['submission_count'] ?></td>
          <td>
            <?php if ((int)$form['open_submission_count'] > 0): ?>
              <strong><?= (int)$form['open_submission_count'] ?></strong>
              <?php if ((int)$form['new_submission_count'] > 0): ?>
                <br><small class="text-pending">nové: <?= (int)$form['new_submission_count'] ?></small>
              <?php endif; ?>
            <?php else: ?>
              0
            <?php endif; ?>
          </td>
          <td>
            <?= (int)$form['is_active'] ? 'Aktivní' : 'Neaktivní' ?>
            <br><small class="table-meta"><?= (int)($form['show_in_nav'] ?? 0) === 1 ? 'v navigaci webu' : 'mimo navigaci' ?></small>
          </td>
          <td class="actions">
            <a href="form_form.php?id=<?= $formId ?>" class="btn">Upravit</a>
            <?php if ((int)$form['submission_count'] > 0): ?>
              <a href="form_submissions.php?id=<?= $formId ?>">Odpovědi (<?= (int)$form['submission_count'] ?>)</a>
            <?php endif; ?>
            <a href="<?= h(formPublicPath($form)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
            <form action="form_delete.php" method="post" class="admin-inline-form" novalidate<?= $deleteHasError ? ' aria-describedby="form-delete-error"' : '' ?>>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= $formId ?>">
              <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
              <fieldset class="admin-inline-fieldset">
                <legend class="sr-only">Smazání formuláře <?= h((string)$form['title']) ?></legend>
                <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
                  Smazání odstraní veřejný formulář <?= h(formPublicPath($form)) ?>,
                  <?= (int)$form['field_count'] ?> polí,
                  <?= (int)$form['submission_count'] ?> odpovědí,
                  <?= (int)($form['history_count'] ?? 0) ?> záznamů historie odpovědí
                  a nahrané soubory uložené v odpovědích. Tuto akci nejde vrátit zpět.
                </p>
                <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
                  <input
                    type="checkbox"
                    id="<?= h($deleteConfirmId) ?>"
                    name="<?= h($deleteConfirmField) ?>"
                    value="1"
                    required
                    aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
                  Potvrzuji smazání tohoto formuláře a jeho odpovědí.
                </label>
                <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před smazáním formuláře potvrďte, že jste zkontrolovali počet polí, odpovědí, historii odpovědí a případné přílohy.', $deleteFieldErrorId); ?>
                <button type="submit" class="btn btn-danger"
                        data-confirm="Smazat formulář včetně všech odpovědí, historie a příloh?">Smazat</button>
              </fieldset>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php adminFooter(); ?>
