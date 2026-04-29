<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$defaultCiWorkflowPath = $projectRoot . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'ci.yml';
$defaultFullCiWorkflowPath = $projectRoot . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'full-ci.yml';
$ciWorkflowPath = (string)($argv[1] ?? $defaultCiWorkflowPath);
$fullCiWorkflowPath = (string)($argv[2] ?? $defaultFullCiWorkflowPath);
$issues = [];

/**
 * @param list<string> $snippets
 * @param list<string> $issues
 */
function requireWorkflowSnippets(string $label, string $source, array $snippets, array &$issues): void
{
    foreach ($snippets as $snippet) {
        if (!str_contains($source, $snippet)) {
            $issues[] = $label . ' is missing workflow guard: ' . $snippet;
        }
    }
}

/**
 * @param list<string> $snippets
 * @param list<string> $issues
 */
function forbidWorkflowSnippets(string $label, string $source, array $snippets, array &$issues): void
{
    foreach ($snippets as $snippet) {
        if (str_contains($source, $snippet)) {
            $issues[] = $label . ' contains forbidden workflow pattern: ' . $snippet;
        }
    }
}

/**
 * @param list<string> $issues
 */
function auditWorkflowActionReferences(string $label, string $source, array &$issues): void
{
    $matchCount = preg_match_all('/^\s*uses:\s*([^\s#]+)/m', $source, $matches);
    if ($matchCount === false || $matchCount === 0) {
        $issues[] = $label . ' does not reference any GitHub Action.';
        return;
    }

    foreach ($matches[1] as $actionReference) {
        if (str_starts_with($actionReference, './')) {
            continue;
        }

        if (!str_contains($actionReference, '@')) {
            $issues[] = $label . ' uses an unpinned action: ' . $actionReference;
            continue;
        }

        [, $reference] = explode('@', $actionReference, 2);
        $isVersionTag = preg_match('/^v\d+(?:\.\d+){0,2}$/', $reference) === 1;
        $isCommitSha = preg_match('/^[a-f0-9]{40}$/i', $reference) === 1;
        if (!$isVersionTag && !$isCommitSha) {
            $issues[] = $label . ' uses a floating action reference: ' . $actionReference;
        }
    }
}

/**
 * @param list<string> $issues
 */
function loadWorkflow(string $path, string $label, array &$issues): string
{
    if (!is_file($path)) {
        $issues[] = $label . ' workflow is missing.';
        return '';
    }

    $source = file_get_contents($path);
    if (!is_string($source) || $source === '') {
        $issues[] = $label . ' workflow cannot be read.';
        return '';
    }

    return $source;
}

$ciWorkflowSource = loadWorkflow($ciWorkflowPath, 'Basic CI', $issues);
$fullCiWorkflowSource = loadWorkflow($fullCiWorkflowPath, 'Full CI', $issues);

if ($ciWorkflowSource !== '') {
    requireWorkflowSnippets('Basic CI', $ciWorkflowSource, [
        'name: CI',
        'pull_request:',
        'push:',
        'branches:',
        '- main',
        'permissions:',
        'contents: read',
        'concurrency:',
        'cancel-in-progress: true',
        'timeout-minutes: 30',
        'uses: actions/checkout@v6',
        'uses: shivammathur/setup-php@v2',
        "php-version: '8.0'",
        'extensions: gd, mbstring, fileinfo, pdo, pdo_mysql',
        'composer validate --strict',
        'composer install --no-interaction --no-progress --prefer-dist',
        'composer ci:basic',
    ], $issues);
    forbidWorkflowSnippets('Basic CI', $ciWorkflowSource, [
        "\t",
        'pull_request_target:',
        'contents: write',
        'id-token: write',
        'packages: write',
        '${{ secrets.',
        'github.event.pull_request.head.repo.full_name',
        'curl |',
        'curl -s |',
        'bash <(',
        'Invoke-Expression',
        'Set-ExecutionPolicy Unrestricted',
    ], $issues);
    auditWorkflowActionReferences('Basic CI', $ciWorkflowSource, $issues);

    if (str_contains($ciWorkflowSource, 'composer ci:full')) {
        $issues[] = 'Basic CI should stay on composer ci:basic; full checks belong to full-ci.yml.';
    }
}

if ($fullCiWorkflowSource !== '') {
    requireWorkflowSnippets('Full CI', $fullCiWorkflowSource, [
        'name: Full CI',
        'workflow_dispatch:',
        'schedule:',
        'permissions:',
        'contents: read',
        'concurrency:',
        'cancel-in-progress: true',
        'timeout-minutes: 45',
        'KORA_TEST_BASE_URL: http://127.0.0.1:8000',
        'services:',
        'mysql:',
        'image: mysql:8.0',
        'MYSQL_ROOT_PASSWORD: root',
        'MYSQL_DATABASE: koracms_ci',
        '3306:3306',
        'mysqladmin ping',
        'uses: actions/checkout@v6',
        'uses: shivammathur/setup-php@v2',
        "php-version: '8.0'",
        'composer validate --strict',
        'composer install --no-interaction --no-progress --prefer-dist',
        'Prepare runtime config',
        'cat > config.php',
        "define('KORA_STORAGE_DIR', __DIR__ . '/../kora_storage');",
        'Start PHP server',
        'php -S 127.0.0.1:8000 -t .',
        'Install Kora CMS',
        'csrf_token',
        'SELECT COUNT(*) FROM cms_settings',
        'composer ci:full',
    ], $issues);
    forbidWorkflowSnippets('Full CI', $fullCiWorkflowSource, [
        "\t",
        'pull_request_target:',
        'contents: write',
        'id-token: write',
        'packages: write',
        '${{ secrets.',
        'github.event.pull_request.head.repo.full_name',
        'curl |',
        'curl -s |',
        'bash <(',
        'Invoke-Expression',
        'Set-ExecutionPolicy Unrestricted',
    ], $issues);
    auditWorkflowActionReferences('Full CI', $fullCiWorkflowSource, $issues);

    if (!str_contains($fullCiWorkflowSource, 'curl -fsS "$KORA_TEST_BASE_URL/install.php"')) {
        $issues[] = 'Full CI does not verify that the local PHP server can serve install.php.';
    }
}

if ($issues !== []) {
    echo "Workflow audit failed:\n";
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
    exit(1);
}

echo "Workflow audit OK\n";
