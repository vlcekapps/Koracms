<?php

function formWebhookEventDefinitions(): array
{
    return [
        'submission_created' => [
            'label' => 'Nové veřejné odeslání formuláře',
        ],
        'workflow_updated' => [
            'label' => 'Změna workflow hlášení',
        ],
        'reply_sent' => [
            'label' => 'Odpověď odesílateli',
        ],
        'github_issue_created' => [
            'label' => 'Vytvoření GitHub issue',
        ],
        'github_issue_linked' => [
            'label' => 'Připojení existujícího GitHub issue',
        ],
    ];
}

function formWebhookEventLabel(string $event): string
{
    $definitions = formWebhookEventDefinitions();
    return $definitions[$event]['label'] ?? $event;
}

function normalizeFormWebhookUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $validated = filter_var($url, FILTER_VALIDATE_URL);
    if (!is_string($validated) || !preg_match('#^https?://#i', $validated)) {
        return '';
    }

    return $validated;
}

function formWebhookEventList(mixed $value): array
{
    $rawItems = is_array($value)
        ? $value
        : (preg_split('/[,;\s]+/u', trim((string)$value)) ?: []);

    $allowedEvents = array_keys(formWebhookEventDefinitions());
    $normalized = [];
    foreach ($rawItems as $rawItem) {
        $event = trim((string)$rawItem);
        if ($event === '' || !in_array($event, $allowedEvents, true)) {
            continue;
        }
        $normalized[$event] = $event;
    }

    return array_values($normalized);
}

function formWebhookEventStorage(mixed $value): string
{
    return implode(',', formWebhookEventList($value));
}

function formWebhookEnabled(array $form): bool
{
    return (int)($form['webhook_enabled'] ?? 0) === 1
        && normalizeFormWebhookUrl((string)($form['webhook_url'] ?? '')) !== '';
}

function formWebhookWantsEvent(array $form, string $event): bool
{
    if (!formWebhookEnabled($form)) {
        return false;
    }

    return in_array($event, formWebhookEventList((string)($form['webhook_events'] ?? '')), true);
}

function formWebhookAssigneePayload(array $submission): ?array
{
    $assignedUserId = (int)($submission['assigned_user_id'] ?? 0);
    if ($assignedUserId <= 0) {
        return null;
    }

    $label = null;
    if (
        isset($submission['assigned_email'])
        || isset($submission['assigned_first_name'])
        || isset($submission['assigned_last_name'])
        || isset($submission['assigned_nickname'])
    ) {
        $label = formSubmissionAssigneeDisplayName([
            'email' => (string)($submission['assigned_email'] ?? ''),
            'first_name' => (string)($submission['assigned_first_name'] ?? ''),
            'last_name' => (string)($submission['assigned_last_name'] ?? ''),
            'nickname' => (string)($submission['assigned_nickname'] ?? ''),
            'role' => (string)($submission['assigned_role'] ?? ''),
            'is_superadmin' => (int)($submission['assigned_is_superadmin'] ?? 0),
        ]);
    }

    return [
        'id' => $assignedUserId,
        'label' => $label,
    ];
}

function formWebhookPayload(
    array $form,
    string $event,
    array $submission,
    array $fieldsByName = [],
    array $submissionData = [],
    array $context = []
): array {
    $reference = formSubmissionReference($form, $submission);
    $displayValues = [];
    foreach ($fieldsByName as $fieldName => $fieldDefinition) {
        if (!formFieldStoresSubmissionValue($fieldDefinition)) {
            continue;
        }

        $displayValues[$fieldName] = [
            'label' => trim((string)($fieldDefinition['label'] ?? $fieldName)),
            'type' => normalizeFormFieldType((string)($fieldDefinition['field_type'] ?? 'text')),
            'value' => $submissionData[$fieldName] ?? '',
            'display_value' => formSubmissionDisplayValueForField($fieldDefinition, $submissionData[$fieldName] ?? ''),
        ];
    }

    $githubIssue = null;
    if (formSubmissionHasGitHubIssue($submission)) {
        $githubIssue = [
            'repository' => trim((string)($submission['github_issue_repository'] ?? '')),
            'number' => (int)($submission['github_issue_number'] ?? 0),
            'url' => trim((string)($submission['github_issue_url'] ?? '')),
            'label' => formSubmissionGitHubIssueLabel($submission),
        ];
    }

    return [
        'event' => $event,
        'event_label' => formWebhookEventLabel($event),
        'triggered_at' => gmdate('c'),
        'site' => [
            'name' => getSetting('site_name', 'Kora CMS'),
            'base_url' => siteUrl(),
            'version' => KORA_VERSION,
        ],
        'form' => [
            'id' => (int)($form['id'] ?? 0),
            'title' => trim((string)($form['title'] ?? 'Formulář')),
            'slug' => trim((string)($form['slug'] ?? '')),
            'public_url' => siteUrl(formPublicPath($form)),
        ],
        'submission' => [
            'id' => (int)($submission['id'] ?? 0),
            'reference' => $reference,
            'status' => normalizeFormSubmissionStatus((string)($submission['status'] ?? 'new')),
            'status_label' => formSubmissionStatusLabel((string)($submission['status'] ?? 'new')),
            'priority' => normalizeFormSubmissionPriority((string)($submission['priority'] ?? 'medium')),
            'priority_label' => formSubmissionPriorityLabel((string)($submission['priority'] ?? 'medium')),
            'labels' => formSubmissionLabelsFromString((string)($submission['labels'] ?? '')),
            'internal_note' => trim((string)($submission['internal_note'] ?? '')),
            'summary' => formSubmissionSummary($fieldsByName, $submissionData, 3),
            'created_at' => (string)($submission['created_at'] ?? ''),
            'updated_at' => (string)($submission['updated_at'] ?? ($submission['created_at'] ?? '')),
            'ip_hash' => trim((string)($submission['ip_hash'] ?? '')),
            'assignee' => formWebhookAssigneePayload($submission),
            'github_issue' => $githubIssue,
            'admin_url' => siteUrl('/admin/form_submission.php?id=' . (int)($submission['id'] ?? 0) . '&form_id=' . (int)($form['id'] ?? 0)),
        ],
        'fields' => $displayValues,
        'context' => $context,
    ];
}

function formWebhookSignature(string $secret, string $payload): string
{
    return 'sha256=' . hash_hmac('sha256', $payload, $secret);
}

function dispatchFormWebhook(
    array $form,
    string $event,
    array $submission,
    array $fieldsByName = [],
    array $submissionData = [],
    array $context = []
): bool {
    if (!formWebhookWantsEvent($form, $event)) {
        return false;
    }

    $webhookUrl = normalizeFormWebhookUrl((string)($form['webhook_url'] ?? ''));
    if ($webhookUrl === '') {
        return false;
    }

    $payload = json_encode(
        formWebhookPayload($form, $event, $submission, $fieldsByName, $submissionData, $context),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($payload) || $payload === '') {
        error_log('form webhook failed: payload encoding error');
        return false;
    }

    $headers = [
        'Content-Type: application/json; charset=UTF-8',
        'Accept: application/json',
        'User-Agent: KoraCMS/' . KORA_VERSION,
        'X-Kora-Event: ' . $event,
        'X-Kora-Form: ' . (string)($form['slug'] ?? ''),
        'X-Kora-Reference: ' . formSubmissionReference($form, $submission),
    ];

    $secret = trim((string)($form['webhook_secret'] ?? ''));
    if ($secret !== '') {
        $headers[] = 'X-Kora-Signature: ' . formWebhookSignature($secret, $payload);
    }

    $contextOptions = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $payload,
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($webhookUrl, false, $contextOptions);
    $statusLine = $http_response_header[0] ?? '';
    if (!preg_match('#\s(\d{3})\s#', $statusLine, $matches)) {
        error_log('form webhook failed: invalid HTTP response for ' . $event . ' to ' . $webhookUrl);
        return false;
    }

    $statusCode = (int)$matches[1];
    if ($statusCode < 200 || $statusCode >= 300) {
        $responsePreview = is_string($response) ? mb_strimwidth(trim($response), 0, 200, '…', 'UTF-8') : '';
        error_log('form webhook failed: ' . $event . ' returned HTTP ' . $statusCode . ' for ' . $webhookUrl . ($responsePreview !== '' ? ' body=' . $responsePreview : ''));
        return false;
    }

    return true;
}
