<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/forms.php');
    exit;
}

verifyCsrf();

$id = inputInt('post', 'id');
$formId = inputInt('post', 'form_id');
$defaultRedirect = $formId !== null
    ? BASE_URL . '/admin/form_submissions.php?id=' . $formId
    : BASE_URL . '/admin/forms.php';
$redirect = internalRedirectTarget($_POST['redirect'] ?? '', $defaultRedirect);
$errorRedirect = internalRedirectTarget($_POST['error_redirect'] ?? '', $redirect);

$redirectWithDeleteState = static function (string $target, array $params): void {
    header('Location: ' . appendUrlQuery($target, $params));
    exit;
};

if ($id === null) {
    $redirectWithDeleteState($errorRedirect, ['delete_error' => 'invalid']);
}

$pdo = db_connect();
$submissionStmt = $pdo->prepare(
    "SELECT s.id, s.form_id, s.reference_code, s.created_at, s.status, s.data,
            f.title AS form_title,
            f.slug AS form_slug,
            (SELECT COUNT(*) FROM cms_form_submission_history WHERE submission_id = s.id) AS history_count
     FROM cms_form_submissions s
     INNER JOIN cms_forms f ON f.id = s.form_id
     WHERE s.id = ?"
);
$submissionStmt->execute([$id]);
$submission = $submissionStmt->fetch();
if (!$submission || ($formId !== null && (int)$submission['form_id'] !== $formId)) {
    $redirectWithDeleteState($errorRedirect, ['delete_error' => 'invalid', 'delete_error_id' => $id]);
}

$confirmFieldName = 'confirm_form_submission_delete_' . $id;
$confirmedSubmissionDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedSubmissionDelete) {
    $redirectWithDeleteState($errorRedirect, ['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$submissionData = json_decode((string)($submission['data'] ?? ''), true);
$uploadedFileCount = count(formCollectUploadedFilesFromSubmissionData($submissionData));
$historyCount = (int)($submission['history_count'] ?? 0);

$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM cms_form_submission_history WHERE submission_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_form_submissions WHERE id = ?")->execute([$id]);
    logAction(
        'form_submission_delete',
        'id=' . $id
        . ';form_id=' . (int)$submission['form_id']
        . ';history_count=' . $historyCount
        . ';uploaded_file_count=' . $uploadedFileCount
    );
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

formDeleteUploadedFilesFromSubmissionData($submissionData);

$redirectWithDeleteState($redirect, ['deleted' => '1']);
