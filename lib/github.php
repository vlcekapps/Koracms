<?php
// GitHub issue bridge helpery pro formuláře

function githubIssueBridgeEnabled(): bool
{
    return getSetting('github_issues_enabled', '0') === '1';
}

function githubIssueBridgeToken(): string
{
    return defined('GITHUB_ISSUES_TOKEN') ? trim((string)GITHUB_ISSUES_TOKEN) : '';
}

function githubIssueBridgeHasToken(): bool
{
    return githubIssueBridgeToken() !== '';
}

function normalizeGitHubRepository(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('#^https?://github\.com/#i', '', $value);
    $value = trim((string)$value, " \t\n\r\0\x0B/");

    if (!preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $value)) {
        return '';
    }

    return $value;
}

function githubIssueBridgeRepository(): string
{
    return normalizeGitHubRepository(getSetting('github_issues_repository', ''));
}

function githubIssueBridgeReady(): bool
{
    return githubIssueBridgeEnabled()
        && githubIssueBridgeHasToken();
}

function githubIssueAbsoluteUrl(string $path): string
{
    $normalizedPath = trim($path);
    if ($normalizedPath === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $normalizedPath)) {
        return $normalizedPath;
    }

    $base = BASE_URL;
    if ($base === '' || !preg_match('#^https?://#i', $base)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host . $base;
    }

    return rtrim($base, '/') . '/' . ltrim($normalizedPath, '/');
}

function githubIssueLabelsFromSubmission(array $submission): array
{
    $labels = [];
    $normalized = formSubmissionNormalizeLabels((string)($submission['labels'] ?? ''));
    if ($normalized !== '') {
        foreach (explode(',', $normalized) as $label) {
            $label = trim($label);
            if ($label !== '') {
                $labels[] = $label;
            }
        }
    }

    $priority = normalizeFormSubmissionPriority((string)($submission['priority'] ?? 'medium'));
    $labels[] = 'priority:' . $priority;

    return array_values(array_unique($labels));
}

function githubIssueLabelsCsv(array $labels): string
{
    return implode(', ', array_values(array_filter(array_map('trim', $labels), static fn(string $item): bool => $item !== '')));
}

function githubIssueTitleCandidate(array $fieldsByName, array $submissionData): string
{
    $preferredNames = [
        'strucne_shrnuti',
        'nazev_navrhu',
        'nazev_problemu',
        'tema',
        'summary',
        'title',
        'subject',
    ];

    foreach ($preferredNames as $fieldName) {
        if (!isset($fieldsByName[$fieldName])) {
            continue;
        }

        $displayValue = trim(formSubmissionDisplayValueForField($fieldsByName[$fieldName], $submissionData[$fieldName] ?? ''));
        if ($displayValue !== '') {
            return $displayValue;
        }
    }

    foreach ($fieldsByName as $fieldName => $field) {
        $fieldType = normalizeFormFieldType((string)($field['field_type'] ?? 'text'));
        if (!formFieldStoresSubmissionValue($field) || in_array($fieldType, ['hidden', 'consent', 'file', 'checkbox', 'checkbox_group'], true)) {
            continue;
        }

        $displayValue = trim(formSubmissionDisplayValueForField($field, $submissionData[$fieldName] ?? ''));
        if ($displayValue !== '') {
            return $displayValue;
        }
    }

    return '';
}

function githubIssueDefaultTitle(array $form, array $submission, array $fieldsByName, array $submissionData): string
{
    $reference = formSubmissionReference($form, $submission);
    $candidate = githubIssueTitleCandidate($fieldsByName, $submissionData);
    if ($candidate === '') {
        $candidate = trim((string)($form['title'] ?? 'Hlášení z formuláře'));
    }

    $title = '[' . $reference . '] ' . $candidate;
    return trim(mb_strimwidth($title, 0, 180, '…', 'UTF-8'));
}

function githubIssueMarkdownFileList(int $submissionId, string $fieldName, mixed $value): array
{
    $items = formSubmissionFileItems($value);
    $fieldName = trim($fieldName);
    if ($submissionId <= 0 || $fieldName === '') {
        return [];
    }

    $links = [];
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $originalName = trim((string)($item['original_name'] ?? ''));
        $storedName = formSubmissionStoredFileName($item);
        $url = $storedName !== ''
            ? githubIssueAbsoluteUrl(formSubmissionFileDownloadPath($submissionId, $fieldName, (int)$index))
            : '';
        if ($originalName === '' || $url === '') {
            continue;
        }

        $links[] = '- [' . $originalName . '](' . $url . ')';
    }

    return $links;
}

function githubIssueDefaultBody(array $form, array $submission, array $fieldsByName, array $submissionData): string
{
    $reference = formSubmissionReference($form, $submission);
    $lines = [
        '## Kontext hlášení',
        '- Formulář: ' . trim((string)($form['title'] ?? '')),
        '- Reference: ' . $reference,
        '- Přijato: ' . formatCzechDate((string)($submission['created_at'] ?? '')),
        '- Priorita: ' . formSubmissionPriorityLabel((string)($submission['priority'] ?? 'medium')),
    ];

    $labels = formSubmissionNormalizeLabels((string)($submission['labels'] ?? ''));
    if ($labels !== '') {
        $lines[] = '- Štítky formuláře: ' . $labels;
    }

    $recipient = formSubmissionRecipient($form, $fieldsByName, $submissionData);
    if ($recipient !== []) {
        $lines[] = '- Kontakt na odesílatele: ' . (string)$recipient['email'];
    }

    $summary = formSubmissionSummary($fieldsByName, $submissionData, 3);
    if ($summary !== '') {
        $lines[] = '- Rychlé shrnutí: ' . str_replace(' · ', '; ', $summary);
    }

    $lines[] = '';
    $lines[] = '## Odeslaná data';

    foreach ($fieldsByName as $fieldName => $field) {
        $fieldType = normalizeFormFieldType((string)($field['field_type'] ?? 'text'));
        if (!formFieldStoresSubmissionValue($field) || in_array($fieldType, ['hidden', 'consent'], true)) {
            continue;
        }

        $label = trim((string)($field['label'] ?? $fieldName));
        if ($fieldType === 'file') {
            $fileLinks = githubIssueMarkdownFileList((int)($submission['id'] ?? 0), $fieldName, $submissionData[$fieldName] ?? []);
            if ($fileLinks === []) {
                continue;
            }

            $lines[] = '### ' . $label;
            foreach ($fileLinks as $fileLink) {
                $lines[] = $fileLink;
            }
            $lines[] = '';
            continue;
        }

        $displayValue = trim(formSubmissionDisplayValueForField($field, $submissionData[$fieldName] ?? ''));
        if ($displayValue === '') {
            continue;
        }

        $lines[] = '### ' . $label;
        $lines[] = $displayValue;
        $lines[] = '';
    }

    return trim(implode("\n", $lines));
}

function githubIssueComposeUrl(string $repository, string $title, string $body, array $labels = []): string
{
    $normalizedRepository = normalizeGitHubRepository($repository);
    if ($normalizedRepository === '') {
        return '';
    }

    return 'https://github.com/' . $normalizedRepository . '/issues/new?' . http_build_query([
        'title' => trim($title),
        'body' => trim($body),
        'labels' => githubIssueLabelsCsv($labels),
    ]);
}

function githubIssueParseUrl(string $url): ?array
{
    $normalizedUrl = trim($url);
    if ($normalizedUrl === '') {
        return null;
    }

    if (!preg_match('#^https://github\.com/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)/issues/([0-9]+)(?:[/?#].*)?$#i', $normalizedUrl, $matches)) {
        return null;
    }

    return [
        'repository' => $matches[1] . '/' . $matches[2],
        'number' => (int)$matches[3],
        'url' => 'https://github.com/' . $matches[1] . '/' . $matches[2] . '/issues/' . $matches[3],
    ];
}

function formSubmissionHasGitHubIssue(array $submission): bool
{
    return trim((string)($submission['github_issue_url'] ?? '')) !== ''
        && (int)($submission['github_issue_number'] ?? 0) > 0
        && normalizeGitHubRepository((string)($submission['github_issue_repository'] ?? '')) !== '';
}

function formSubmissionGitHubIssueLabel(array $submission): string
{
    if (!formSubmissionHasGitHubIssue($submission)) {
        return 'Nepřipojeno';
    }

    return normalizeGitHubRepository((string)$submission['github_issue_repository']) . '#' . (int)$submission['github_issue_number'];
}

function githubApiResponseStatus(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+([0-9]{3})#', $header, $matches)) {
            return (int)$matches[1];
        }
    }

    return 0;
}

function githubIssueCreate(string $repository, string $title, string $body, array $labels = []): array
{
    $normalizedRepository = normalizeGitHubRepository($repository);
    $normalizedTitle = trim($title);
    $normalizedBody = trim($body);
    if ($normalizedRepository === '' || $normalizedTitle === '' || $normalizedBody === '') {
        return [
            'ok' => false,
            'status' => 0,
            'error' => 'Repository, název i text issue jsou povinné.',
        ];
    }

    $token = githubIssueBridgeToken();
    if ($token === '') {
        return [
            'ok' => false,
            'status' => 0,
            'error' => 'Chybí přístupový token pro GitHub issue bridge.',
        ];
    }

    $payload = json_encode([
        'title' => $normalizedTitle,
        'body' => $normalizedBody,
        'labels' => array_values(array_filter(array_map('trim', $labels), static fn(string $label): bool => $label !== '')),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'User-Agent: KoraCMS/' . KORA_VERSION,
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $token,
                'X-GitHub-Api-Version: 2022-11-28',
                'Content-Type: application/json',
            ]),
            'content' => $payload,
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);

    $response = @file_get_contents('https://api.github.com/repos/' . rawurlencode(explode('/', $normalizedRepository)[0]) . '/' . rawurlencode(explode('/', $normalizedRepository)[1]) . '/issues', false, $context);
    $headers = $http_response_header ?? [];
    $status = githubApiResponseStatus($headers);
    $data = is_string($response) && $response !== '' ? json_decode($response, true) : [];

    if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['html_url']) || empty($data['number'])) {
        $error = '';
        if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
            $error = trim($data['message']);
        }
        if ($error === '') {
            $error = 'GitHub API nevrátila platnou odpověď.';
        }

        return [
            'ok' => false,
            'status' => $status,
            'error' => $error,
        ];
    }

    return [
        'ok' => true,
        'status' => $status,
        'repository' => $normalizedRepository,
        'number' => (int)$data['number'],
        'url' => (string)$data['html_url'],
    ];
}
