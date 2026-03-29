<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

function renderAdminFormSubmissionValue(array $field, mixed $value): string
{
    $fieldType = normalizeFormFieldType((string)($field['field_type'] ?? 'text'));

    if ($fieldType === 'file') {
        $items = [];
        if (is_array($value) && array_keys($value) === range(0, count($value) - 1)) {
            $items = $value;
        } elseif (is_array($value)) {
            $items = [$value];
        }

        $links = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['url'], $item['original_name'])) {
                continue;
            }
            $links[] = '<a href="' . h((string)$item['url']) . '" target="_blank" rel="noopener noreferrer">'
                . h((string)$item['original_name'])
                . '</a>';
        }

        return $links !== [] ? implode(', ', $links) : '–';
    }

    $displayValue = trim(formSubmissionDisplayValueForField($field, $value));
    if ($displayValue === '') {
        return '–';
    }

    return nl2br(h($displayValue));
}

$pdo = db_connect();
$submissionId = inputInt('get', 'id');
$formId = inputInt('get', 'form_id');
$defaultRedirect = $formId !== null
    ? BASE_URL . '/admin/form_submissions.php?id=' . $formId
    : BASE_URL . '/admin/forms.php';
$redirect = internalRedirectTarget(trim((string)($_GET['redirect'] ?? '')), $defaultRedirect);

if ($submissionId === null) {
    header('Location: ' . $redirect);
    exit;
}

$submissionStmt = $pdo->prepare(
    "SELECT s.*,
            f.title AS form_title,
            f.slug AS form_slug,
            f.is_active AS form_is_active,
            u.email AS assigned_email,
            u.first_name AS assigned_first_name,
            u.last_name AS assigned_last_name,
            u.nickname AS assigned_nickname,
            u.role AS assigned_role,
            u.is_superadmin AS assigned_is_superadmin
     FROM cms_form_submissions s
     INNER JOIN cms_forms f ON f.id = s.form_id
     LEFT JOIN cms_users u ON u.id = s.assigned_user_id
     WHERE s.id = ?"
);
$submissionStmt->execute([$submissionId]);
$submission = $submissionStmt->fetch() ?: null;

if (!$submission) {
    header('Location: ' . $redirect);
    exit;
}

$formId = (int)$submission['form_id'];
$selfRedirect = BASE_URL . '/admin/form_submission.php?id=' . (int)$submission['id']
    . '&form_id=' . $formId
    . '&redirect=' . rawurlencode($redirect);

$fieldsStmt = $pdo->prepare("SELECT * FROM cms_form_fields WHERE form_id = ? ORDER BY sort_order, id");
$fieldsStmt->execute([$formId]);
$fields = $fieldsStmt->fetchAll();
$submissionData = json_decode((string)($submission['data'] ?? ''), true) ?: [];
$assignableUsers = formSubmissionAssignableUsers($pdo);
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
$statusDefinitions = formSubmissionStatusDefinitions();

adminHeader('Detail odpovědi formuláře');
?>

<?php if (isset($_GET['ok'])): ?>
  <p class="success" role="status">Workflow odpovědi formuláře byl aktualizován.</p>
<?php endif; ?>

<div class="button-row">
  <a href="<?= h($redirect) ?>" class="btn">Zpět na odpovědi formuláře</a>
  <a href="form_form.php?id=<?= (int)$formId ?>" class="btn">Upravit formulář</a>
  <?php if ((int)($submission['form_is_active'] ?? 0) === 1): ?>
    <a href="<?= h(formPublicPath(['id' => $formId, 'slug' => (string)$submission['form_slug']])) ?>" class="btn" target="_blank" rel="noopener noreferrer">Zobrazit formulář na webu</a>
  <?php endif; ?>
</div>

<table>
  <caption class="sr-only">Detail odpovědi formuláře</caption>
  <tbody>
    <tr>
      <th scope="row">Referenční kód</th>
      <td><strong><?= h(formSubmissionReference([
          'title' => (string)($submission['form_title'] ?? ''),
          'slug' => (string)($submission['form_slug'] ?? ''),
      ], $submission)) ?></strong></td>
    </tr>
    <tr>
      <th scope="row">Formulář</th>
      <td><?= h((string)$submission['form_title']) ?></td>
    </tr>
    <tr>
      <th scope="row">Stav</th>
      <td><strong><?= h(formSubmissionStatusLabel((string)($submission['status'] ?? 'new'))) ?></strong></td>
    </tr>
    <tr>
      <th scope="row">Přiřazeno</th>
      <td><?= h($assigneeLabel) ?></td>
    </tr>
    <tr>
      <th scope="row">Přijato</th>
      <td><time datetime="<?= h(str_replace(' ', 'T', (string)$submission['created_at'])) ?>"><?= formatCzechDate((string)$submission['created_at']) ?></time></td>
    </tr>
    <tr>
      <th scope="row">Aktualizováno</th>
      <td><time datetime="<?= h(str_replace(' ', 'T', (string)$submission['updated_at'])) ?>"><?= formatCzechDate((string)$submission['updated_at']) ?></time></td>
    </tr>
    <tr>
      <th scope="row">Otisk IP</th>
      <td><code><?= h((string)$submission['ip_hash']) ?></code></td>
    </tr>
    <tr>
      <th scope="row">Interní poznámka</th>
      <td><?= nl2br(h(trim((string)($submission['internal_note'] ?? '')) !== '' ? (string)$submission['internal_note'] : '–')) ?></td>
    </tr>
  </tbody>
</table>

<h2>Odeslané údaje</h2>
<table>
  <caption class="sr-only">Vyplněná pole odpovědi formuláře</caption>
  <tbody>
    <?php foreach ($fields as $field): ?>
      <?php if (!formFieldStoresSubmissionValue($field)): ?>
        <?php continue; ?>
      <?php endif; ?>
      <?php $fieldName = (string)($field['name'] ?? ''); ?>
      <tr>
        <th scope="row"><?= h((string)($field['label'] ?? $fieldName)) ?></th>
        <td><?= renderAdminFormSubmissionValue($field, $submissionData[$fieldName] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>Co můžete udělat</h2>
<form method="post" action="<?= BASE_URL ?>/admin/form_submission_action.php">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
  <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
  <fieldset>
    <legend>Workflow hlášení</legend>
    <div style="display:grid;grid-template-columns:minmax(14rem,1fr) minmax(18rem,1.1fr);gap:1rem;align-items:start">
      <div>
        <label for="submission-status">Stav odpovědi</label>
        <select id="submission-status" name="status" style="width:100%">
          <?php foreach ($statusDefinitions as $statusKey => $statusDefinition): ?>
            <option value="<?= h($statusKey) ?>"<?= normalizeFormSubmissionStatus((string)($submission['status'] ?? 'new')) === $statusKey ? ' selected' : '' ?>>
              <?= h((string)$statusDefinition['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="submission-assigned-user">Přiřadit řešiteli</label>
        <select id="submission-assigned-user" name="assigned_user_id" style="width:100%">
          <option value="">Nepřiřazeno</option>
          <?php foreach ($assignableUsers as $assigneeUser): ?>
            <option value="<?= (int)$assigneeUser['id'] ?>"<?= (int)($submission['assigned_user_id'] ?? 0) === (int)$assigneeUser['id'] ? ' selected' : '' ?>>
              <?= h(formSubmissionAssigneeDisplayName($assigneeUser)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div style="margin-top:1rem">
      <label for="submission-internal-note">Interní poznámka</label>
      <textarea id="submission-internal-note" name="internal_note" rows="6" style="width:100%;max-width:52rem" aria-describedby="submission-internal-note-help"><?= h((string)($submission['internal_note'] ?? '')) ?></textarea>
      <small id="submission-internal-note-help" class="field-help">Sem patří interní postup, doplnění pro tým nebo třeba stručné shrnutí dalšího kroku. Na veřejném webu se nikdy nezobrazí.</small>
    </div>
  </fieldset>
  <div class="button-row" style="margin-top:1rem">
    <button type="submit" class="btn btn-primary">Uložit změny workflow</button>
    <a href="<?= h($redirect) ?>" class="btn">Zrušit</a>
  </div>
</form>

<form method="post" action="<?= BASE_URL ?>/admin/form_submission_delete.php" style="margin-top:1rem">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
  <input type="hidden" name="form_id" value="<?= (int)$formId ?>">
  <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
  <button type="submit" class="btn btn-danger" data-confirm="Smazat tuto odpověď formuláře trvale?">Smazat odpověď</button>
</form>

<?php adminFooter(); ?>
