<?php
require_once __DIR__ . '/../db.php';
requireCapability('settings_manage', 'Přístup odepřen. GitHub bridge mohou spravovat jen správci webu.');
verifyCsrf();

$submissionId = inputInt('post', 'id');
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/forms.php');
$issueAction = trim((string)($_POST['issue_action'] ?? 'create'));

if ($submissionId === null) {
    header('Location: ' . appendUrlQuery($redirect, ['issue' => 'missing']));
    exit;
}

$pdo = db_connect();
$submissionStmt = $pdo->prepare(
    "SELECT s.*, f.title AS form_title, f.slug AS form_slug
     FROM cms_form_submissions s
     INNER JOIN cms_forms f ON f.id = s.form_id
     WHERE s.id = ?"
);
$submissionStmt->execute([$submissionId]);
$submission = $submissionStmt->fetch() ?: null;

if (!$submission) {
    header('Location: ' . appendUrlQuery($redirect, ['issue' => 'missing']));
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

if ($issueAction === 'link') {
    $existingIssueUrl = trim((string)($_POST['existing_issue_url'] ?? ''));
    $parsedIssue = githubIssueParseUrl($existingIssueUrl);
    if ($parsedIssue === null) {
        header('Location: ' . appendUrlQuery($redirect, ['issue' => 'invalid_link']));
        exit;
    }

    $pdo->prepare(
        "UPDATE cms_form_submissions
         SET github_issue_repository = ?, github_issue_number = ?, github_issue_url = ?, updated_at = NOW()
         WHERE id = ?"
    )->execute([
        (string)$parsedIssue['repository'],
        (int)$parsedIssue['number'],
        (string)$parsedIssue['url'],
        $submissionId,
    ]);

    formSubmissionHistoryCreate(
        $pdo,
        $submissionId,
        currentUserId(),
        'github_issue_link',
        'K hlášení bylo připojeno existující GitHub issue ' . (string)$parsedIssue['repository'] . '#' . (int)$parsedIssue['number'] . '.'
    );

    logAction(
        'form_submission_github_issue_link',
        'id=' . $submissionId . ' repo=' . (string)$parsedIssue['repository'] . ' number=' . (int)$parsedIssue['number']
    );

    dispatchFormWebhook(
        $form,
        'github_issue_linked',
        array_merge($submission, [
            'github_issue_repository' => (string)$parsedIssue['repository'],
            'github_issue_number' => (int)$parsedIssue['number'],
            'github_issue_url' => (string)$parsedIssue['url'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]),
        $fieldsByName,
        $submissionData,
        [
            'repository' => (string)$parsedIssue['repository'],
            'number' => (int)$parsedIssue['number'],
            'url' => (string)$parsedIssue['url'],
        ]
    );

    header('Location: ' . appendUrlQuery($redirect, ['issue' => 'linked']));
    exit;
}

if (formSubmissionHasGitHubIssue($submission)) {
    header('Location: ' . appendUrlQuery($redirect, ['issue' => 'exists']));
    exit;
}

if (!githubIssueBridgeEnabled() || !githubIssueBridgeHasToken()) {
    header('Location: ' . appendUrlQuery($redirect, ['issue' => 'not_ready']));
    exit;
}

$repository = normalizeGitHubRepository((string)($_POST['repository'] ?? ''));
$title = trim((string)($_POST['title'] ?? ''));
$body = trim((string)($_POST['body'] ?? ''));
$labels = array_values(array_filter(
    array_map('trim', explode(',', (string)($_POST['labels'] ?? ''))),
    static fn(string $label): bool => $label !== ''
));

if ($repository === '' || $title === '' || $body === '') {
    header('Location: ' . appendUrlQuery($redirect, ['issue' => 'invalid']));
    exit;
}

$result = githubIssueCreate($repository, $title, $body, $labels);
if (!($result['ok'] ?? false)) {
    $errorMessage = trim((string)($result['error'] ?? ''));
    if ($errorMessage === '') {
        $errorMessage = 'GitHub issue se nepodařilo vytvořit.';
    }
    header('Location: ' . appendUrlQuery($redirect, [
        'issue' => 'failed',
        'issue_message' => mb_strimwidth($errorMessage, 0, 180, '…', 'UTF-8'),
    ]));
    exit;
}

$pdo->prepare(
    "UPDATE cms_form_submissions
     SET github_issue_repository = ?, github_issue_number = ?, github_issue_url = ?, updated_at = NOW()
     WHERE id = ?"
)->execute([
    (string)$result['repository'],
    (int)$result['number'],
    (string)$result['url'],
    $submissionId,
]);

formSubmissionHistoryCreate(
    $pdo,
    $submissionId,
    currentUserId(),
    'github_issue_create',
    'Bylo vytvořeno GitHub issue ' . (string)$result['repository'] . '#' . (int)$result['number'] . '.'
);

logAction(
    'form_submission_github_issue_create',
    'id=' . $submissionId . ' repo=' . (string)$result['repository'] . ' number=' . (int)$result['number']
);

dispatchFormWebhook(
    $form,
    'github_issue_created',
    array_merge($submission, [
        'github_issue_repository' => (string)$result['repository'],
        'github_issue_number' => (int)$result['number'],
        'github_issue_url' => (string)$result['url'],
        'updated_at' => date('Y-m-d H:i:s'),
    ]),
    $fieldsByName,
    $submissionData,
    [
        'repository' => (string)$result['repository'],
        'number' => (int)$result['number'],
        'url' => (string)$result['url'],
        'labels' => $labels,
    ]
);

header('Location: ' . appendUrlQuery($redirect, ['issue' => 'created']));
exit;
