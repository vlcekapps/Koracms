<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

/**
 * @param array<string, mixed> $field
 */
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
                . newWindowLinkSrOnlySuffix()
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

/**
 * @param array<string, mixed> $field
 */
function renderAdminFormSubmissionFiles(array $field, mixed $value, int $submissionId): string
{
    $fieldName = trim((string)($field['name'] ?? ''));
    if ($fieldName === '') {
        return '–';
    }

    $links = [];
    foreach (formSubmissionFileItems($value) as $index => $item) {
        $storedName = formSubmissionStoredFileName($item);
        $originalName = trim((string)($item['original_name'] ?? ''));
        if ($storedName === '' || $originalName === '') {
            continue;
        }

        $links[] = '<a href="' . h(formSubmissionFileDownloadPath($submissionId, $fieldName, (int)$index)) . '" target="_blank" rel="noopener noreferrer">'
            . h($originalName)
            . newWindowLinkSrOnlySuffix()
            . '</a>';
    }

    return $links !== [] ? implode(', ', $links) : '–';
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
            f.submitter_email_field AS form_submitter_email_field,
            f.webhook_enabled AS form_webhook_enabled,
            f.webhook_url AS form_webhook_url,
            f.webhook_events AS form_webhook_events,
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
$fieldsByName = [];
foreach ($fields as $field) {
    $fieldName = trim((string)($field['name'] ?? ''));
    if ($fieldName !== '') {
        $fieldsByName[$fieldName] = $field;
    }
}
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
$priorityDefinitions = formSubmissionPriorityDefinitions();
$normalizedLabels = formSubmissionNormalizeLabels((string)($submission['labels'] ?? ''));
$historyEntries = formSubmissionHistoryEntries($pdo, $submissionId);
$replyRecipient = formSubmissionRecipient([
    'submitter_email_field' => (string)($submission['form_submitter_email_field'] ?? ''),
], $fieldsByName, $submissionData);
$replySubject = trim((string)($submission['form_title'] ?? '')) !== ''
    ? 'Re: ' . trim((string)$submission['form_title']) . ' (' . formSubmissionReference([
        'title' => (string)($submission['form_title'] ?? ''),
        'slug' => (string)($submission['form_slug'] ?? ''),
    ], $submission) . ')'
    : 'Re: odpověď formuláře';
$replyMessage = "Dobrý den,\n\nreagujeme na vaše hlášení "
    . formSubmissionReference([
        'title' => (string)($submission['form_title'] ?? ''),
        'slug' => (string)($submission['form_slug'] ?? ''),
    ], $submission)
    . ".\n\n";
$formMeta = [
    'id' => $formId,
    'title' => (string)($submission['form_title'] ?? ''),
    'slug' => (string)($submission['form_slug'] ?? ''),
    'webhook_enabled' => (int)($submission['form_webhook_enabled'] ?? 0),
    'webhook_url' => (string)($submission['form_webhook_url'] ?? ''),
    'webhook_events' => (string)($submission['form_webhook_events'] ?? ''),
];
$replyWebhookEnabled = formWebhookWantsEvent($formMeta, 'reply_sent');
$githubIssueDraftRepository = githubIssueBridgeRepository();
$githubIssueDraftLabels = githubIssueLabelsCsv(githubIssueLabelsFromSubmission($submission));
$githubIssueDraftTitle = githubIssueDefaultTitle($formMeta, $submission, $fieldsByName, $submissionData);
$githubIssueDraftBody = githubIssueDefaultBody($formMeta, $submission, $fieldsByName, $submissionData);
$hasGitHubIssue = formSubmissionHasGitHubIssue($submission);
$githubIssueLinkLabel = formSubmissionGitHubIssueLabel($submission);
$currentAdminUserId = currentUserId();
$canManageFormIntegrations = currentUserHasCapability('settings_manage');
$replyStatus = trim((string)($_GET['reply'] ?? ''));
$replyConfirmField = 'confirm_form_submission_reply_send_' . $submissionId;
$replyConfirmId = 'confirm-form-submission-reply-send-' . $submissionId;
$replyReviewId = 'form-submission-reply-review-' . $submissionId;
$replyConfirmErrorId = 'confirm-form-submission-reply-send-' . $submissionId . '-error';
$replyFormErrorId = 'form-submission-reply-form-error';
$replyFlash = adminReplyFlashPull('form_submission', $submissionId);
$replyHasFlash = in_array($replyStatus, ['invalid', 'confirm_required', 'failed'], true) && $replyFlash !== [];
$replyFlashErrorFields = $replyHasFlash ? $replyFlash['error_fields'] : [];
$replyFieldErrors = match ($replyStatus) {
    'invalid' => $replyFlashErrorFields !== [] ? $replyFlashErrorFields : ['reply_subject', 'reply_message'],
    'confirm_required' => $replyFlashErrorFields !== [] ? $replyFlashErrorFields : [$replyConfirmField],
    default => [],
};
if ($replyHasFlash) {
    $replySubject = (string)$replyFlash['subject'];
    $replyMessage = (string)$replyFlash['message'];
}
$replyHasFormError = in_array($replyStatus, ['invalid', 'confirm_required', 'failed'], true);
$issueFieldErrors = isset($_GET['issue']) && $_GET['issue'] === 'invalid'
    ? ['github_issue_repository', 'github_issue_title', 'github_issue_body']
    : [];
$existingIssueFieldErrors = isset($_GET['issue']) && $_GET['issue'] === 'invalid_link'
    ? ['existing_issue_url']
    : [];
$replyFieldErrorMessages = [
    'reply_subject' => 'Zadejte předmět odpovědi, aby příjemce poznal, k jakému hlášení se vracíte.',
    'reply_message' => 'Doplňte text odpovědi. Nestačí prázdná zpráva.',
    $replyConfirmField => 'Před odesláním potvrďte, že jste zkontrolovali příjemce, předmět, text a uvedené externí účinky.',
];
$issueFieldErrorMessages = [
    'github_issue_repository' => 'Zadejte repozitář ve formátu owner/repo, například vlcekapps/Koracms.',
    'github_issue_title' => 'Doplňte název issue, aby šlo problém na GitHubu rychle rozpoznat.',
    'github_issue_body' => 'Doplňte text issue s popisem problému, očekávaným chováním nebo dalším krokem.',
    'existing_issue_url' => 'Zadejte úplnou adresu issue ve tvaru https://github.com/owner/repo/issues/123.',
];
$deleteError = trim((string)($_GET['delete_error'] ?? ''));
$deleteConfirmError = $deleteError === 'confirm_required';
$deleteErrorMessage = match ($deleteError) {
    'confirm_required' => 'Odpověď formuláře nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.',
    'invalid' => 'Odpověď formuláře nejde smazat, protože už není dostupná. Vraťte se na seznam odpovědí a vyberte položku znovu.',
    default => '',
};
$deleteConfirmField = 'confirm_form_submission_delete_' . (int)$submission['id'];
$deleteConfirmId = 'confirm-form-submission-delete-' . (int)$submission['id'];
$deleteReviewId = 'form-submission-delete-review-' . (int)$submission['id'];
$deleteFieldErrorId = 'confirm-form-submission-delete-' . (int)$submission['id'] . '-error';
$deleteErrorFields = $deleteConfirmError ? [$deleteConfirmField] : [];
$deleteReference = formSubmissionReference($formMeta, $submission);
$deleteUploadedFileCount = count(formCollectUploadedFilesFromSubmissionData($submissionData));
$deleteHistoryCount = count($historyEntries);

adminHeader('Detail odpovědi formuláře');
?>

<?php if (isset($_GET['ok'])): ?>
  <p class="success" role="status">Workflow odpovědi formuláře byl aktualizován.</p>
<?php endif; ?>
<?php if ($replyStatus === 'sent'): ?>
  <p class="success" role="status" aria-atomic="true">Odpověď odesílateli byla úspěšně odeslána.</p>
<?php elseif ($replyStatus === 'missing'): ?>
  <p class="error" role="alert" aria-atomic="true">U této odpovědi není dostupná žádná platná e-mailová adresa pro odpověď.</p>
<?php elseif ($replyStatus === 'invalid'): ?>
  <p id="<?= h($replyFormErrorId) ?>" class="error" role="alert" aria-atomic="true">Odpověď nejde odeslat, protože chybí povinný obsah nebo potvrzení kontroly. U zvýrazněných polí je konkrétní nápověda.</p>
<?php elseif ($replyStatus === 'confirm_required'): ?>
  <p id="<?= h($replyFormErrorId) ?>" class="error" role="alert" aria-atomic="true">Odpověď nejde odeslat bez potvrzení kontroly příjemce, obsahu a uvedených externích účinků. U pole Potvrzení odeslání je konkrétní nápověda.</p>
<?php elseif ($replyStatus === 'failed'): ?>
  <p id="<?= h($replyFormErrorId) ?>" class="error" role="alert" aria-atomic="true">Odpověď se nepodařilo odeslat. Rozepsaný obsah zůstal zachovaný; před dalším pokusem jej zkontrolujte a znovu potvrďte odeslání.</p>
<?php endif; ?>
<?php if (isset($_GET['issue']) && $_GET['issue'] === 'created'): ?>
  <p class="success" role="status">GitHub issue bylo úspěšně vytvořeno a připojeno k tomuto hlášení.</p>
<?php elseif (isset($_GET['issue']) && $_GET['issue'] === 'linked'): ?>
  <p class="success" role="status">Existující GitHub issue bylo k tomuto hlášení úspěšně připojeno.</p>
<?php elseif (isset($_GET['issue']) && $_GET['issue'] === 'exists'): ?>
  <p class="error" role="alert">Toto hlášení už má GitHub issue připojené.</p>
<?php elseif (isset($_GET['issue']) && $_GET['issue'] === 'invalid'): ?>
  <p class="error" role="alert" aria-atomic="true">Před vytvořením GitHub issue doplňte repozitář, název i text. U dotčených polí je konkrétní nápověda.</p>
<?php elseif (isset($_GET['issue']) && $_GET['issue'] === 'invalid_link'): ?>
  <p class="error" role="alert" aria-atomic="true">Zadejte platnou adresu existujícího GitHub issue. U pole je doplněná konkrétní nápověda.</p>
<?php elseif (isset($_GET['issue']) && $_GET['issue'] === 'not_ready'): ?>
  <p class="error" role="alert">Přímé vytvoření GitHub issue teď není dostupné. Zkontrolujte nastavení mostu a přístupový token.</p>
<?php elseif (isset($_GET['issue']) && $_GET['issue'] === 'failed'): ?>
  <p class="error" role="alert">
    GitHub issue se nepodařilo vytvořit.
    <?php if (trim((string)($_GET['issue_message'] ?? '')) !== ''): ?>
      Důvod: <?= h((string)$_GET['issue_message']) ?>.
    <?php endif; ?>
  </p>
<?php elseif (isset($_GET['issue']) && $_GET['issue'] === 'missing'): ?>
  <p class="error" role="alert">Požadované hlášení se nepodařilo najít.</p>
<?php endif; ?>
<?php if ($deleteErrorMessage !== ''): ?>
  <p id="form-submission-delete-error" class="error" role="alert" aria-atomic="true"><?= h($deleteErrorMessage) ?></p>
<?php endif; ?>

<div class="button-row">
  <a href="<?= h($redirect) ?>" class="btn">Zpět na odpovědi formuláře</a>
  <a href="form_form.php?id=<?= (int)$formId ?>" class="btn">Upravit formulář</a>
  <?php if ((int)($submission['form_is_active'] ?? 0) === 1): ?>
    <a href="<?= h(formPublicPath(['id' => $formId, 'slug' => (string)$submission['form_slug']])) ?>" class="btn" target="_blank" rel="noopener noreferrer">Zobrazit formulář na webu<?= newWindowLinkSrOnlySuffix() ?></a>
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
      <th scope="row">Priorita</th>
      <td><strong><?= h(formSubmissionPriorityLabel((string)($submission['priority'] ?? 'medium'))) ?></strong></td>
    </tr>
    <tr>
      <th scope="row">Štítky</th>
      <td><?= h($normalizedLabels !== '' ? $normalizedLabels : '–') ?></td>
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
    <tr>
      <th scope="row">GitHub issue</th>
      <td>
        <?php if ($hasGitHubIssue): ?>
          <a href="<?= h((string)$submission['github_issue_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($githubIssueLinkLabel) ?><?= newWindowLinkSrOnlySuffix() ?></a>
        <?php else: ?>
          –
        <?php endif; ?>
      </td>
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
        <td>
          <?php if (normalizeFormFieldType((string)($field['field_type'] ?? 'text')) === 'file'): ?>
            <?= renderAdminFormSubmissionFiles($field, $submissionData[$fieldName] ?? '', (int)$submission['id']) ?>
          <?php else: ?>
            <?= renderAdminFormSubmissionValue($field, $submissionData[$fieldName] ?? '') ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>Rychlé kroky</h2>
<div class="button-row">
  <?php if ($currentAdminUserId !== null && (int)($submission['assigned_user_id'] ?? 0) !== (int)$currentAdminUserId): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/form_submission_action.php" class="admin-inline-form">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" name="quick_action" value="take" class="btn">Převzít řešení</button>
    </form>
  <?php endif; ?>
  <?php if (normalizeFormSubmissionStatus((string)($submission['status'] ?? 'new')) !== 'in_progress'): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/form_submission_action.php" class="admin-inline-form">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" name="quick_action" value="start" class="btn">Označit jako rozpracované</button>
    </form>
  <?php endif; ?>
  <?php if (normalizeFormSubmissionStatus((string)($submission['status'] ?? 'new')) !== 'resolved'): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/form_submission_action.php" class="admin-inline-form">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" name="quick_action" value="resolve" class="btn">Označit jako vyřešené</button>
    </form>
  <?php endif; ?>
  <?php if (normalizeFormSubmissionStatus((string)($submission['status'] ?? 'new')) !== 'closed'): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/form_submission_action.php" class="admin-inline-form">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" name="quick_action" value="close" class="btn">Uzavřít hlášení</button>
    </form>
  <?php endif; ?>
</div>

<h2>Co můžete udělat</h2>
<form method="post" action="<?= BASE_URL ?>/admin/form_submission_action.php">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
  <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
  <fieldset>
    <legend>Workflow hlášení</legend>
    <div class="form-submission-grid">
      <div>
        <label for="submission-status">Stav odpovědi</label>
        <select id="submission-status" name="status" class="form-submission-control">
          <?php foreach ($statusDefinitions as $statusKey => $statusDefinition): ?>
            <option value="<?= h($statusKey) ?>"<?= normalizeFormSubmissionStatus((string)($submission['status'] ?? 'new')) === $statusKey ? ' selected' : '' ?>>
              <?= h((string)$statusDefinition['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="submission-assigned-user">Přiřadit řešiteli</label>
        <select id="submission-assigned-user" name="assigned_user_id" class="form-submission-control">
          <option value="">Nepřiřazeno</option>
          <?php foreach ($assignableUsers as $assigneeUser): ?>
            <option value="<?= (int)$assigneeUser['id'] ?>"<?= (int)($submission['assigned_user_id'] ?? 0) === (int)$assigneeUser['id'] ? ' selected' : '' ?>>
              <?= h(formSubmissionAssigneeDisplayName($assigneeUser)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="submission-priority">Priorita</label>
        <select id="submission-priority" name="priority" class="form-submission-control">
          <?php foreach ($priorityDefinitions as $priorityKey => $priorityDefinition): ?>
            <option value="<?= h($priorityKey) ?>"<?= normalizeFormSubmissionPriority((string)($submission['priority'] ?? 'medium')) === $priorityKey ? ' selected' : '' ?>>
              <?= h((string)$priorityDefinition['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="submission-labels">Štítky</label>
        <input type="text" id="submission-labels" name="labels" value="<?= h($normalizedLabels) ?>" class="form-submission-control" aria-describedby="submission-labels-help">
        <small id="submission-labels-help" class="field-help">Oddělujte je čárkou. Hodí se třeba pro modul, oblast nebo typ požadavku.</small>
      </div>
    </div>
    <div class="form-submission-field--top">
      <label for="submission-internal-note">Interní poznámka</label>
      <textarea id="submission-internal-note" name="internal_note" rows="6" class="form-submission-control form-submission-control--md" aria-describedby="submission-internal-note-help"><?= h((string)($submission['internal_note'] ?? '')) ?></textarea>
      <small id="submission-internal-note-help" class="field-help">Sem patří interní postup, doplnění pro tým nebo třeba stručné shrnutí dalšího kroku. Na veřejném webu se nikdy nezobrazí.</small>
    </div>
  </fieldset>
  <div class="button-row form-submission-actions">
    <button type="submit" class="btn btn-primary">Uložit změny workflow</button>
    <a href="<?= h($redirect) ?>" class="btn">Zrušit</a>
  </div>
</form>

<?php if ($canManageFormIntegrations): ?>
<h2>GitHub issue</h2>
<?php if ($hasGitHubIssue): ?>
  <p>Toto hlášení už má připojené issue <a href="<?= h((string)$submission['github_issue_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($githubIssueLinkLabel) ?><?= newWindowLinkSrOnlySuffix() ?></a>.</p>
<?php else: ?>
  <p class="field-help form-submission-help--spaced">
    Z tohoto hlášení si můžete připravit GitHub issue. Návrh lze otevřít ručně na GitHubu, zkopírovat do schránky
    a při zapnutém issue bridge i vytvořit přímo z administrace.
  </p>

  <?php if (!githubIssueBridgeEnabled()): ?>
    <p class="field-help">Přímé vytváření issue je zatím vypnuté v <a href="settings.php#settings-integrations">nastavení integrací</a>. Ruční otevření návrhu ale funguje i bez něj.</p>
  <?php elseif (!githubIssueBridgeHasToken()): ?>
    <p class="field-help">Přímé vytvoření issue bude dostupné po doplnění konstanty <code>GITHUB_ISSUES_TOKEN</code> do <code>config.php</code>.</p>
  <?php endif; ?>

  <form method="post" action="<?= BASE_URL ?>/admin/form_submission_issue.php" id="github-issue-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
    <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
    <fieldset>
      <legend>Připravit issue</legend>

      <div class="form-submission-field">
        <label for="github-issue-repository">Repozitář</label>
        <input type="text"
               id="github-issue-repository"
               name="repository"
               value="<?= h($githubIssueDraftRepository) ?>"
               placeholder="owner/repo"
               class="form-submission-control form-submission-control--sm"<?= adminFieldAttributes('github_issue_repository', $issueFieldErrors, [], ['github-issue-repository-help'], 'github-issue-repository-error') ?>>
        <small id="github-issue-repository-help" class="field-help">Můžete ponechat výchozí repozitář z nastavení webu, nebo sem zadat jiný ve formátu <code>owner/repo</code>.</small>
        <?php adminRenderFieldError('github_issue_repository', $issueFieldErrors, [], $issueFieldErrorMessages['github_issue_repository'], 'github-issue-repository-error'); ?>
      </div>

      <div class="form-submission-field">
        <label for="github-issue-title">Název issue</label>
        <input type="text"
               id="github-issue-title"
               name="title"
               value="<?= h($githubIssueDraftTitle) ?>"
               maxlength="180"
               class="form-submission-control form-submission-control--md"<?= adminFieldAttributes('github_issue_title', $issueFieldErrors, [], [], 'github-issue-title-error') ?>>
        <?php adminRenderFieldError('github_issue_title', $issueFieldErrors, [], $issueFieldErrorMessages['github_issue_title'], 'github-issue-title-error'); ?>
      </div>

      <div class="form-submission-field">
        <label for="github-issue-labels">GitHub štítky</label>
        <input type="text"
               id="github-issue-labels"
               name="labels"
               value="<?= h($githubIssueDraftLabels) ?>"
               class="form-submission-control form-submission-control--md"
               aria-describedby="github-issue-labels-help">
        <small id="github-issue-labels-help" class="field-help">Priorita z hlášení je do návrhu doplněná automaticky jako štítek <code>priority:…</code>. Štítky můžete před vytvořením issue upravit.</small>
      </div>

      <div class="form-submission-field">
        <label for="github-issue-body">Tělo issue</label>
        <textarea id="github-issue-body"
                  name="body"
                  rows="18"
                  class="form-submission-control form-submission-control--lg"<?= adminFieldAttributes('github_issue_body', $issueFieldErrors, [], ['github-issue-body-help'], 'github-issue-body-error') ?>><?= h($githubIssueDraftBody) ?></textarea>
        <small id="github-issue-body-help" class="field-help">Interní poznámka správce se do issue nevkládá automaticky. Pokud ji chcete zveřejnit, přidejte ji sem ručně.</small>
        <?php adminRenderFieldError('github_issue_body', $issueFieldErrors, [], $issueFieldErrorMessages['github_issue_body'], 'github-issue-body-error'); ?>
      </div>

      <div class="button-row">
        <?php if (githubIssueBridgeReady()): ?>
          <button type="submit" name="issue_action" value="create" class="btn btn-primary">Vytvořit GitHub issue</button>
        <?php endif; ?>
        <button type="button" class="btn" id="github-issue-open">Otevřít návrh na GitHubu</button>
        <button type="button" class="btn" id="github-issue-copy">Zkopírovat jako GitHub issue</button>
      </div>
      <p id="github-issue-copy-status" class="field-help form-submission-help--top" aria-live="polite"></p>
    </fieldset>
  </form>

  <form method="post" action="<?= BASE_URL ?>/admin/form_submission_issue.php" class="form-submission-actions">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
    <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
    <fieldset>
      <legend>Připojit existující issue</legend>
      <label for="existing-issue-url">Adresa existující GitHub issue</label>
      <input type="url"
             id="existing-issue-url"
             name="existing_issue_url"
             placeholder="https://github.com/owner/repo/issues/123"
             class="form-submission-control form-submission-control--md"<?= adminFieldAttributes('existing_issue_url', $existingIssueFieldErrors, [], ['existing-issue-url-help'], 'existing-issue-url-error') ?>>
      <small id="existing-issue-url-help" class="field-help">To se hodí hlavně tehdy, když si issue otevřete ručně přes tlačítko výše a potom ho chcete k hlášení uložit zpět.</small>
      <?php adminRenderFieldError('existing_issue_url', $existingIssueFieldErrors, [], $issueFieldErrorMessages['existing_issue_url'], 'existing-issue-url-error'); ?>
      <div class="button-row form-submission-actions--compact">
        <button type="submit" name="issue_action" value="link" class="btn">Připojit issue</button>
      </div>
    </fieldset>
  </form>

  <script nonce="<?= h(cspNonce()) ?>">
  (() => {
      const repositoryInput = document.getElementById('github-issue-repository');
      const titleInput = document.getElementById('github-issue-title');
      const labelsInput = document.getElementById('github-issue-labels');
      const bodyInput = document.getElementById('github-issue-body');
      const openButton = document.getElementById('github-issue-open');
      const copyButton = document.getElementById('github-issue-copy');
      const copyStatus = document.getElementById('github-issue-copy-status');

      const getDraft = () => ({
          repository: (repositoryInput?.value || '').trim(),
          title: (titleInput?.value || '').trim(),
          labels: (labelsInput?.value || '').trim(),
          body: (bodyInput?.value || '').trim(),
      });

      const repositoryPattern = /^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/;

      openButton?.addEventListener('click', () => {
          const draft = getDraft();
          if (!repositoryPattern.test(draft.repository)) {
              copyStatus.textContent = 'Nejdřív zadejte repozitář ve formátu owner/repo.';
              repositoryInput?.focus();
              return;
          }
          if (draft.title === '' || draft.body === '') {
              copyStatus.textContent = 'Pro otevření návrhu na GitHubu vyplňte název i text issue.';
              if (draft.title === '') {
                  titleInput?.focus();
              } else {
                  bodyInput?.focus();
              }
              return;
          }

          const query = new URLSearchParams({
              title: draft.title,
              body: draft.body,
          });
          if (draft.labels !== '') {
              query.set('labels', draft.labels);
          }
          window.open('https://github.com/' + draft.repository + '/issues/new?' + query.toString(), '_blank', 'noopener,noreferrer');
          copyStatus.textContent = 'Návrh issue byl otevřen na GitHubu v novém panelu.';
      });

      copyButton?.addEventListener('click', async () => {
          const draft = getDraft();
          const payload = [
              'Repozitář: ' + (draft.repository !== '' ? draft.repository : 'owner/repo'),
              'Název: ' + draft.title,
              draft.labels !== '' ? 'Štítky: ' + draft.labels : '',
              '',
              draft.body,
          ].filter((line) => line !== '').join('\n');

          try {
              await navigator.clipboard.writeText(payload);
              copyStatus.textContent = 'Návrh GitHub issue byl zkopírován do schránky.';
          } catch (error) {
              copyStatus.textContent = 'Návrh issue se nepodařilo zkopírovat. Zkuste to prosím ručně.';
          }
      });
  })();
  </script>
<?php endif; ?>
<?php endif; ?>

<?php if ($replyRecipient !== []): ?>
  <h2>Odpověď odesílateli</h2>
  <form method="post" action="<?= BASE_URL ?>/admin/form_submission_reply.php" novalidate<?= $replyHasFormError ? ' aria-describedby="' . h($replyFormErrorId) . '"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
    <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
    <fieldset>
      <legend>Obsah e-mailové odpovědi</legend>
      <p class="field-help">Odpověď odejde na <strong><?= h((string)$replyRecipient['email']) ?></strong><?php if (trim((string)$replyRecipient['field_label']) !== ''): ?> z pole „<?= h((string)$replyRecipient['field_label']) ?>“<?php endif; ?>.</p>
      <div class="form-submission-field">
        <label for="reply-subject">Předmět odpovědi</label>
        <input type="text" id="reply-subject" name="subject" value="<?= h($replySubject) ?>" maxlength="255" required aria-required="true" class="form-submission-control form-submission-control--md"<?= adminFieldAttributes('reply_subject', $replyFieldErrors, [], [], 'reply-subject-error') ?>>
        <?php adminRenderFieldError('reply_subject', $replyFieldErrors, [], $replyFieldErrorMessages['reply_subject'], 'reply-subject-error'); ?>
      </div>
      <div class="form-submission-field">
        <label for="reply-message">Text odpovědi</label>
        <textarea id="reply-message" name="message" rows="8" required aria-required="true" class="form-submission-control form-submission-control--md"<?= adminFieldAttributes('reply_message', $replyFieldErrors, [], ['reply-message-help'], 'reply-message-error') ?>><?= h($replyMessage) ?></textarea>
        <small id="reply-message-help" class="field-help">Tato odpověď se zároveň uloží do interní historie hlášení.</small>
        <?php adminRenderFieldError('reply_message', $replyFieldErrors, [], $replyFieldErrorMessages['reply_message'], 'reply-message-error'); ?>
      </div>
    </fieldset>

    <fieldset class="admin-fieldset-card admin-fieldset-spaced" aria-describedby="<?= h($replyReviewId) ?>">
      <legend>Kontrola odeslání</legend>
      <p id="<?= h($replyReviewId) ?>" class="field-help field-help--flush">E-mail se po potvrzení nevratně odešle na <strong><?= h((string)$replyRecipient['email']) ?></strong> a odpověď se uloží do interní historie.<?php if ($replyWebhookEnabled): ?> Současně se do nastavené externí služby odešle webhook události <code>reply_sent</code>.<?php endif; ?> Před odesláním zkontrolujte všechny uvedené účinky.</p>
      <label for="<?= h($replyConfirmId) ?>" class="admin-checkbox-label">
        <input type="checkbox" id="<?= h($replyConfirmId) ?>" name="<?= h($replyConfirmField) ?>" value="1" required aria-required="true"<?= adminFieldAttributes($replyConfirmField, $replyFieldErrors, [], [$replyReviewId], $replyConfirmErrorId) ?>>
        Potvrzuji, že jsem zkontroloval(a) příjemce, předmět, text a uvedené externí účinky.
      </label>
      <?php adminRenderFieldError($replyConfirmField, $replyFieldErrors, [], $replyFieldErrorMessages[$replyConfirmField], $replyConfirmErrorId); ?>
    </fieldset>

    <div class="button-row admin-action-row">
      <button type="submit" class="btn btn-primary" data-confirm="Opravdu odeslat tuto odpověď na <?= h((string)$replyRecipient['email']) ?>?">Poslat odpověď</button>
    </div>
  </form>
<?php endif; ?>

<h2>Interní historie</h2>
<?php if ($historyEntries === []): ?>
  <p>Zatím tu není žádná interní historie tohoto hlášení.</p>
<?php else: ?>
  <ul class="form-submission-history">
    <?php foreach ($historyEntries as $historyEntry): ?>
      <li class="form-submission-history__item">
        <strong><?= h(formSubmissionHistoryActorLabel($historyEntry)) ?></strong>
        <span class="field-help">· <time datetime="<?= h(str_replace(' ', 'T', (string)$historyEntry['created_at'])) ?>"><?= formatCzechDate((string)$historyEntry['created_at']) ?></time></span><br>
        <?= nl2br(h((string)($historyEntry['message'] ?? ''))) ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>/admin/form_submission_delete.php" class="form-submission-delete-form" novalidate<?= $deleteConfirmError ? ' aria-describedby="form-submission-delete-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
  <input type="hidden" name="form_id" value="<?= (int)$formId ?>">
  <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
  <input type="hidden" name="error_redirect" value="<?= h($selfRedirect) ?>">
  <fieldset class="admin-fieldset-card">
    <legend>Smazání odpovědi formuláře</legend>
    <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
      Smazání odstraní odpověď <?= h($deleteReference) ?>,
      stav <?= h(formSubmissionStatusLabel((string)($submission['status'] ?? 'new'))) ?>,
      <?= $deleteHistoryCount ?> záznamů interní historie
      a <?= $deleteUploadedFileCount ?> nahraných souborů uložených v odpovědi. Tuto akci nejde vrátit zpět.
    </p>
    <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
      <input type="checkbox" id="<?= h($deleteConfirmId) ?>" name="<?= h($deleteConfirmField) ?>" value="1" required aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
      Potvrzuji smazání této odpovědi formuláře.
    </label>
    <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před smazáním odpovědi potvrďte, že jste zkontrolovali referenci, stav, interní historii a případné přílohy.', $deleteFieldErrorId); ?>
    <div class="admin-field-row">
      <button type="submit" class="btn btn-danger" data-confirm="Smazat tuto odpověď formuláře včetně historie a příloh trvale?">Smazat odpověď</button>
    </div>
  </fieldset>
</form>

<?php adminFooter(); ?>
