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
    "SELECT s.id, s.form_id, s.reference_code, s.created_at, f.title AS form_title, f.slug AS form_slug
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

$status = normalizeFormSubmissionStatus((string)($_POST['status'] ?? 'new'));
$assignedUserId = inputInt('post', 'assigned_user_id');
$internalNote = trim((string)($_POST['internal_note'] ?? ''));

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
     SET reference_code = ?, status = ?, assigned_user_id = ?, internal_note = ?, updated_at = NOW()
     WHERE id = ?"
)->execute([
    $resolvedReference,
    $status,
    $assignedUserId,
    $internalNote,
    $submissionId,
]);

logAction(
    'form_submission_update',
    'id=' . $submissionId
    . ' status=' . $status
    . ' assigned_user_id=' . ($assignedUserId !== null ? (string)$assignedUserId : 'null')
);

header('Location: ' . appendUrlQuery($redirect, ['ok' => 1]));
exit;
