<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');
verifyCsrf();

$formId = inputInt('post', 'form_id');
$redirect = internalRedirectTarget(
    trim((string)($_POST['redirect'] ?? '')),
    $formId !== null ? BASE_URL . '/admin/form_submissions.php?id=' . $formId : BASE_URL . '/admin/forms.php'
);
$action = trim((string)($_POST['action'] ?? ''));
$ids = array_values(array_filter(
    array_map(static fn($value): int => (int)$value, (array)($_POST['ids'] ?? [])),
    static fn(int $value): bool => $value > 0
));

if ($ids === []) {
    header('Location: ' . $redirect);
    exit;
}

$pdo = db_connect();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$form = null;
$fieldsByName = [];
if ($formId !== null) {
    $formStmt = $pdo->prepare("SELECT * FROM cms_forms WHERE id = ?");
    $formStmt->execute([$formId]);
    $form = $formStmt->fetch() ?: null;
    if ($form) {
        $fieldsStmt = $pdo->prepare("SELECT * FROM cms_form_fields WHERE form_id = ? ORDER BY sort_order, id");
        $fieldsStmt->execute([$formId]);
        foreach ($fieldsStmt->fetchAll() as $field) {
            $fieldName = trim((string)($field['name'] ?? ''));
            if ($fieldName !== '') {
                $fieldsByName[$fieldName] = $field;
            }
        }
    }
}

if ($action === 'delete') {
    $submissionStmt = $pdo->prepare("SELECT id, data FROM cms_form_submissions WHERE id IN ({$placeholders})");
    $submissionStmt->execute($ids);
    foreach ($submissionStmt->fetchAll() as $submission) {
        $submissionData = json_decode((string)($submission['data'] ?? ''), true);
        formDeleteUploadedFilesFromSubmissionData($submissionData);
    }

    $pdo->prepare("DELETE FROM cms_form_submission_history WHERE submission_id IN ({$placeholders})")->execute($ids);
    $deleteStmt = $pdo->prepare("DELETE FROM cms_form_submissions WHERE id IN ({$placeholders})");
    $deleteStmt->execute($ids);
    logAction('form_submission_bulk_delete', 'ids=' . implode(',', $ids));
    header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
    exit;
}

$statusDefinitions = formSubmissionStatusDefinitions();
if (!isset($statusDefinitions[$action])) {
    header('Location: ' . $redirect);
    exit;
}

$statusUpdateParams = array_merge([$action], $ids);
$statusUpdateStmt = $pdo->prepare(
    "UPDATE cms_form_submissions
     SET status = ?, updated_at = NOW()
     WHERE id IN ({$placeholders})"
);
$statusUpdateStmt->execute($statusUpdateParams);

foreach ($ids as $submissionId) {
    formSubmissionHistoryCreate(
        $pdo,
        $submissionId,
        currentUserId(),
        'bulk_workflow',
        'Stav odpovědi byl hromadně změněn na „' . formSubmissionStatusLabel($action) . '“.'
    );

    if ($form) {
        $submissionWebhookStmt = $pdo->prepare("SELECT * FROM cms_form_submissions WHERE id = ?");
        $submissionWebhookStmt->execute([$submissionId]);
        $submissionRow = $submissionWebhookStmt->fetch() ?: null;
        if ($submissionRow) {
            $submissionData = json_decode((string)($submissionRow['data'] ?? ''), true) ?: [];
            dispatchFormWebhook(
                $form,
                'workflow_updated',
                $submissionRow,
                $fieldsByName,
                $submissionData,
                [
                    'bulk' => true,
                    'status' => $action,
                ]
            );
        }
    }
}

logAction('form_submission_bulk_update', 'status=' . $action . ' ids=' . implode(',', $ids));

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
