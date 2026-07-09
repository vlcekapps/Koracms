<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');
requireModuleEnabled('forms');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/forms.php');
    exit;
}

verifyCsrf();

$id = inputInt('post', 'id');
$redirect = internalRedirectTarget($_POST['redirect'] ?? '', BASE_URL . '/admin/forms.php');

$redirectWithDeleteState = static function (array $params) use ($redirect): void {
    header('Location: ' . appendUrlQuery($redirect, $params));
    exit;
};

if ($id === null) {
    $redirectWithDeleteState(['delete_error' => 'invalid']);
}

$pdo = db_connect();
$formStmt = $pdo->prepare(
    "SELECT f.id, f.title, f.slug,
            (SELECT COUNT(*) FROM cms_form_fields WHERE form_id = f.id) AS field_count,
            (SELECT COUNT(*) FROM cms_form_submissions WHERE form_id = f.id) AS submission_count,
            (SELECT COUNT(*)
               FROM cms_form_submission_history h
               INNER JOIN cms_form_submissions s ON s.id = h.submission_id
              WHERE s.form_id = f.id) AS history_count
     FROM cms_forms f
     WHERE f.id = ?"
);
$formStmt->execute([$id]);
$form = $formStmt->fetch();
if (!$form) {
    $redirectWithDeleteState(['delete_error' => 'invalid', 'delete_error_id' => $id]);
}

$confirmFieldName = 'confirm_form_delete_' . $id;
$confirmedFormDelete = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$confirmedFormDelete) {
    $redirectWithDeleteState(['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

$submissionIdsStmt = $pdo->prepare("SELECT id, data FROM cms_form_submissions WHERE form_id = ?");
$submissionIdsStmt->execute([$id]);
$submissionRows = $submissionIdsStmt->fetchAll();
$submissionIds = [];
foreach ($submissionRows as $submission) {
    $submissionIds[] = (int)($submission['id'] ?? 0);
}

$pdo->beginTransaction();
try {
    if ($submissionIds !== []) {
        $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
        $pdo->prepare("DELETE FROM cms_form_submission_history WHERE submission_id IN ({$placeholders})")->execute($submissionIds);
    }
    $pdo->prepare("DELETE FROM cms_form_submissions WHERE form_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_form_fields WHERE form_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_forms WHERE id = ?")->execute([$id]);
    logAction(
        'form_delete',
        'id=' . $id
        . ';field_count=' . (int)($form['field_count'] ?? 0)
        . ';submission_count=' . (int)($form['submission_count'] ?? 0)
        . ';history_count=' . (int)($form['history_count'] ?? 0)
    );
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

foreach ($submissionRows as $submission) {
    $submissionData = json_decode((string)($submission['data'] ?? ''), true);
    formDeleteUploadedFilesFromSubmissionData($submissionData);
}

$redirectWithDeleteState(['deleted' => '1']);
