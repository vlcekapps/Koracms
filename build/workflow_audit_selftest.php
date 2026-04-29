<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$workflowAuditPath = __DIR__ . DIRECTORY_SEPARATOR . 'workflow_audit.php';
$ciWorkflowPath = $projectRoot . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'ci.yml';
$fullCiWorkflowPath = $projectRoot . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'full-ci.yml';

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function writeTextFile(string $path, string $contents): void
{
    if (file_put_contents($path, $contents) === false) {
        fail('Cannot write file: ' . $path);
    }
}

function removeTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }

    $items = scandir($path);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            removeTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runCommand(array $command, string $cwd): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(
        $command,
        $descriptorSpec,
        $pipes,
        $cwd,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        fail('Cannot start command: ' . implode(' ', $command));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exitCode' => (int)$exitCode,
        'output' => trim(
            (is_string($stdout) ? $stdout : '')
            . (is_string($stderr) && $stderr !== '' ? PHP_EOL . $stderr : '')
        ),
    ];
}

/**
 * @return array{exitCode:int, output:string}
 */
function runWorkflowAudit(string $ciSource, string $fullCiSource): array
{
    global $projectRoot, $workflowAuditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_workflow_audit_'
        . bin2hex(random_bytes(6));

    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            fail('Cannot create temp directory: ' . $tempRoot);
        }

        $ciPath = $tempRoot . DIRECTORY_SEPARATOR . 'ci.yml';
        $fullCiPath = $tempRoot . DIRECTORY_SEPARATOR . 'full-ci.yml';
        writeTextFile($ciPath, $ciSource);
        writeTextFile($fullCiPath, $fullCiSource);

        return runCommand([PHP_BINARY, $workflowAuditPath, $ciPath, $fullCiPath], $projectRoot);
    } finally {
        removeTree($tempRoot);
    }
}

function replaceRequired(string $source, string $search, string $replacement, string $label): string
{
    if (!str_contains($source, $search)) {
        fail('Cannot prepare workflow audit self-test fixture: ' . $label);
    }

    return str_replace($search, $replacement, $source);
}

function assertAuditPasses(string $label, string $ciSource, string $fullCiSource): void
{
    $result = runWorkflowAudit($ciSource, $fullCiSource);
    if ($result['exitCode'] !== 0) {
        fail($label . ' should pass workflow audit.' . PHP_EOL . $result['output']);
    }
}

function assertAuditFails(string $label, string $ciSource, string $fullCiSource, string $expectedOutput): void
{
    $result = runWorkflowAudit($ciSource, $fullCiSource);
    if ($result['exitCode'] === 0) {
        fail($label . ' should fail workflow audit.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        fail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($workflowAuditPath) || !is_file($ciWorkflowPath) || !is_file($fullCiWorkflowPath)) {
    fail('Workflow audit self-test cannot find workflow audit files.');
}

$ciWorkflowSource = (string)file_get_contents($ciWorkflowPath);
$fullCiWorkflowSource = (string)file_get_contents($fullCiWorkflowPath);

assertAuditPasses('Current workflows', $ciWorkflowSource, $fullCiWorkflowSource);

assertAuditFails(
    'Basic CI pull_request_target guard',
    replaceRequired($ciWorkflowSource, 'pull_request:', 'pull_request_target:', 'basic pull_request trigger'),
    $fullCiWorkflowSource,
    'pull_request_target:'
);

assertAuditFails(
    'Basic CI floating action guard',
    replaceRequired($ciWorkflowSource, 'actions/checkout@v6', 'actions/checkout@main', 'basic checkout action'),
    $fullCiWorkflowSource,
    'uses a floating action reference: actions/checkout@main'
);

assertAuditFails(
    'Full CI secrets guard',
    $ciWorkflowSource,
    replaceRequired($fullCiWorkflowSource, 'coverage: none', "coverage: none\n          token: \${{ secrets.CI_TOKEN }}", 'full CI secrets fixture'),
    '${{ secrets.'
);

assertAuditFails(
    'Full CI runtime bootstrap guard',
    $ciWorkflowSource,
    replaceRequired($fullCiWorkflowSource, 'php -S 127.0.0.1:8000 -t .', 'php -S 127.0.0.1:9000 -t .', 'full CI PHP server command'),
    'php -S 127.0.0.1:8000 -t .'
);

echo "Workflow audit self-test OK\n";
