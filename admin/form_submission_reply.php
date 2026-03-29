<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/mail.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');
verifyCsrf();

$submissionId = inputInt('post', 'id');
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/forms.php');

if ($submissionId === null) {
    header('Location: ' . appendUrlQuery($redirect, ['reply' => 'missing']));
    exit;
}

$pdo = db_connect();
$submissionStmt = $pdo->prepare(
    "SELECT s.*, f.title AS form_title, f.slug AS form_slug, f.submitter_email_field
     FROM cms_form_submissions s
     INNER JOIN cms_forms f ON f.id = s.form_id
     WHERE s.id = ?"
);
$submissionStmt->execute([$submissionId]);
$submission = $submissionStmt->fetch() ?: null;

if (!$submission) {
    header('Location: ' . appendUrlQuery($redirect, ['reply' => 'missing']));
    exit;
}

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
$recipient = formSubmissionRecipient([
    'submitter_email_field' => (string)($submission['submitter_email_field'] ?? ''),
], $fieldsByName, $submissionData);

if ($recipient === []) {
    header('Location: ' . appendUrlQuery($redirect, ['reply' => 'missing']));
    exit;
}

$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
if ($subject === '' || $message === '') {
    header('Location: ' . appendUrlQuery($redirect, ['reply' => 'invalid']));
    exit;
}

if (!sendFormSubmissionReply((string)$recipient['email'], $subject, $message)) {
    header('Location: ' . appendUrlQuery($redirect, ['reply' => 'failed']));
    exit;
}

formSubmissionHistoryCreate(
    $pdo,
    $submissionId,
    currentUserId(),
    'reply',
    'Odeslána odpověď odesílateli „' . (string)$recipient['email'] . '“ s předmětem „' . $subject . '“.'
);

logAction('form_submission_reply', 'id=' . $submissionId . ' recipient=' . (string)$recipient['email']);

header('Location: ' . appendUrlQuery($redirect, ['reply' => 'sent']));
exit;
