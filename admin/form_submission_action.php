<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');
verifyCsrf();

$submissionId = inputInt('post', 'id');
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/forms.php');

if ($submissionId === null) {
    header('Location: ' . $redirect);
    exit;
}

$pdo = db_connect();
$submissionStmt = $pdo->prepare(
    "SELECT s.id, s.form_id, s.reference_code, s.created_at, s.updated_at, s.status, s.priority, s.labels, s.assigned_user_id, s.internal_note,
            s.data, s.ip_hash, s.github_issue_repository, s.github_issue_number, s.github_issue_url,
            f.title AS form_title, f.slug AS form_slug
     FROM cms_form_submissions s
     INNER JOIN cms_forms f ON f.id = s.form_id
     WHERE s.id = ?"
);
$submissionStmt->execute([$submissionId]);
$submission = $submissionStmt->fetch() ?: null;

if (!$submission) {
    header('Location: ' . $redirect);
    exit;
}

$formStmt = $pdo->prepare("SELECT * FROM cms_forms WHERE id = ?");
$formStmt->execute([(int)$submission['form_id']]);
$form = $formStmt->fetch() ?: [
    'id' => (int)$submission['form_id'],
    'title' => (string)($submission['form_title'] ?? ''),
    'slug' => (string)($submission['form_slug'] ?? ''),
];

$fieldsStmt = $pdo->prepare("SELECT * FROM cms_form_fields WHERE form_id = ? ORDER BY sort_order, id");
$fieldsStmt->execute([(int)$submission['form_id']]);
$fields = $fieldsStmt->fetchAll();
$fieldsByName = [];
foreach ($fields as $field) {
    $fieldName = trim((string)($field['name'] ?? ''));
    if ($fieldName !== '') {
        $fieldsByName[$fieldName] = $field;
    }
}
$submissionData = json_decode((string)($submission['data'] ?? ''), true) ?: [];

$currentStatus = normalizeFormSubmissionStatus((string)($submission['status'] ?? 'new'));
$currentPriority = normalizeFormSubmissionPriority((string)($submission['priority'] ?? 'medium'));
$currentLabels = formSubmissionNormalizeLabels((string)($submission['labels'] ?? ''));
$currentAssignedUserId = (int)($submission['assigned_user_id'] ?? 0) > 0 ? (int)$submission['assigned_user_id'] : null;
$currentInternalNote = trim((string)($submission['internal_note'] ?? ''));
$quickAction = trim((string)($_POST['quick_action'] ?? ''));

$status = array_key_exists('status', $_POST)
    ? normalizeFormSubmissionStatus((string)($_POST['status'] ?? 'new'))
    : $currentStatus;
$priority = array_key_exists('priority', $_POST)
    ? normalizeFormSubmissionPriority((string)($_POST['priority'] ?? 'medium'))
    : $currentPriority;
$labels = array_key_exists('labels', $_POST)
    ? formSubmissionNormalizeLabels((string)($_POST['labels'] ?? ''))
    : $currentLabels;
$assignedUserPosted = array_key_exists('assigned_user_id', $_POST);
$assignedUserId = $assignedUserPosted ? inputInt('post', 'assigned_user_id') : $currentAssignedUserId;
$internalNote = array_key_exists('internal_note', $_POST)
    ? trim((string)($_POST['internal_note'] ?? ''))
    : $currentInternalNote;

if ($quickAction === 'take' && currentUserId() !== null) {
    $assignedUserId = currentUserId();
    $status = 'in_progress';
} elseif ($quickAction === 'start') {
    $status = 'in_progress';
} elseif ($quickAction === 'resolve') {
    $status = 'resolved';
} elseif ($quickAction === 'close') {
    $status = 'closed';
}

if ($assignedUserId !== null) {
    $assigneeCheckStmt = $pdo->prepare(
        "SELECT id
         FROM cms_users
         WHERE id = ? AND is_confirmed = 1 AND role <> 'public'
         LIMIT 1"
    );
    $assigneeCheckStmt->execute([$assignedUserId]);
    if (!$assigneeCheckStmt->fetch()) {
        $assignedUserId = null;
    }
}

$resolvedReference = formSubmissionReference([
    'title' => (string)($submission['form_title'] ?? ''),
    'slug' => (string)($submission['form_slug'] ?? ''),
], $submission);

$pdo->prepare(
    "UPDATE cms_form_submissions
     SET reference_code = ?, status = ?, priority = ?, labels = ?, assigned_user_id = ?, internal_note = ?, updated_at = NOW()
     WHERE id = ?"
)->execute([
    $resolvedReference,
    $status,
    $priority,
    $labels,
    $assignedUserId,
    $internalNote,
    $submissionId,
]);

$historyParts = [];
if ($quickAction === 'take' && $currentAssignedUserId !== $assignedUserId) {
    $historyParts[] = 'Hlášení bylo převzato k řešení.';
}
if ($status !== $currentStatus) {
    $historyParts[] = 'Stav změněn na „' . formSubmissionStatusLabel($status) . '“.';
}
if ($priority !== $currentPriority) {
    $historyParts[] = 'Priorita změněna na „' . formSubmissionPriorityLabel($priority) . '“.';
}
if ($labels !== $currentLabels) {
    $historyParts[] = $labels !== ''
        ? 'Štítky upraveny na „' . $labels . '“.'
        : 'Štítky byly vyčištěny.';
}
if (($assignedUserId ?? 0) !== ($currentAssignedUserId ?? 0)) {
    if ($assignedUserId === null) {
        $historyParts[] = 'Přiřazení řešiteli bylo zrušeno.';
    } elseif ($quickAction !== 'take') {
        $historyParts[] = 'Změněn přiřazený řešitel.';
    }
}
if ($internalNote !== $currentInternalNote) {
    $historyParts[] = $internalNote !== ''
        ? 'Interní poznámka byla upravena.'
        : 'Interní poznámka byla smazána.';
}

if ($historyParts !== []) {
    formSubmissionHistoryCreate(
        $pdo,
        $submissionId,
        currentUserId(),
        'workflow',
        implode(' ', $historyParts)
    );

    dispatchFormWebhook(
        $form,
        'workflow_updated',
        array_merge($submission, [
            'reference_code' => $resolvedReference,
            'status' => $status,
            'priority' => $priority,
            'labels' => $labels,
            'assigned_user_id' => $assignedUserId,
            'internal_note' => $internalNote,
            'updated_at' => date('Y-m-d H:i:s'),
        ]),
        $fieldsByName,
        $submissionData,
        [
            'quick_action' => $quickAction,
            'changes' => $historyParts,
        ]
    );
}

logAction(
    'form_submission_update',
    'id=' . $submissionId
    . ' status=' . $status
    . ' priority=' . $priority
    . ' labels=' . ($labels !== '' ? $labels : '-')
    . ' assigned_user_id=' . ($assignedUserId !== null ? (string)$assignedUserId : 'null')
);

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
