<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');
verifyCsrf();

$id = inputInt('post', 'id');
$formId = inputInt('post', 'form_id');
$redirect = internalRedirectTarget(
    $_POST['redirect'] ?? '',
    $formId !== null
        ? BASE_URL . '/admin/form_submissions.php?id=' . $formId
        : BASE_URL . '/admin/forms.php'
);

if ($id !== null) {
    $pdo = db_connect();
    $submissionStmt = $pdo->prepare("SELECT data FROM cms_form_submissions WHERE id = ?");
    $submissionStmt->execute([$id]);
    $submission = $submissionStmt->fetch();
    if ($submission) {
        $submissionData = json_decode((string)($submission['data'] ?? ''), true);
        formDeleteUploadedFilesFromSubmissionData($submissionData);
    }
    $pdo->prepare("DELETE FROM cms_form_submissions WHERE id = ?")->execute([$id]);
    logAction('form_submission_delete', "id={$id}");
}

header('Location: ' . $redirect);
exit;
