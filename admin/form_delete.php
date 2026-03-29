<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');
verifyCsrf();

$id = inputInt('post', 'id');
$redirect = internalRedirectTarget($_POST['redirect'] ?? '', BASE_URL . '/admin/forms.php');
if ($id !== null) {
    $pdo = db_connect();
    $submissionIdsStmt = $pdo->prepare("SELECT id, data FROM cms_form_submissions WHERE form_id = ?");
    $submissionIdsStmt->execute([$id]);
    $submissionRows = $submissionIdsStmt->fetchAll();
    $submissionIds = [];
    foreach ($submissionRows as $submission) {
        $submissionIds[] = (int)($submission['id'] ?? 0);
        $submissionData = json_decode((string)($submission['data'] ?? ''), true);
        formDeleteUploadedFilesFromSubmissionData($submissionData);
    }
    if ($submissionIds !== []) {
        $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
        $pdo->prepare("DELETE FROM cms_form_submission_history WHERE submission_id IN ({$placeholders})")->execute($submissionIds);
    }
    $pdo->prepare("DELETE FROM cms_form_submissions WHERE form_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_form_fields WHERE form_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_forms WHERE id = ?")->execute([$id]);
    logAction('form_delete', "id={$id}");
}

header('Location: ' . $redirect);
exit;
