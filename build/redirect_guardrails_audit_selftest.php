<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$redirectGuardrailsAuditPath = __DIR__ . DIRECTORY_SEPARATOR . 'redirect_guardrails_audit.php';

function redirectGuardrailsSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function redirectGuardrailsSelfTestWriteTextFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        redirectGuardrailsSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        redirectGuardrailsSelfTestFail('Cannot write file: ' . $path);
    }
}

function redirectGuardrailsSelfTestRemoveTree(string $path): void
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

            redirectGuardrailsSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runRedirectGuardrailsSelfTestCommand(array $command, string $cwd): array
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
        redirectGuardrailsSelfTestFail('Cannot start command: ' . implode(' ', $command));
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
 * @param array<string,string> $files
 * @return array{exitCode:int, output:string}
 */
function runRedirectGuardrailsAuditWithFixture(array $files): array
{
    global $projectRoot, $redirectGuardrailsAuditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_redirect_guardrails_'
        . bin2hex(random_bytes(6));

    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            redirectGuardrailsSelfTestFail('Cannot create temp directory: ' . $tempRoot);
        }

        $gitInitResult = runRedirectGuardrailsSelfTestCommand(['git', 'init', '--quiet'], $tempRoot);
        if ($gitInitResult['exitCode'] !== 0) {
            redirectGuardrailsSelfTestFail('Cannot initialize fixture git repository.' . PHP_EOL . $gitInitResult['output']);
        }

        foreach ($files as $relativePath => $contents) {
            redirectGuardrailsSelfTestWriteTextFile(
                $tempRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                $contents
            );
        }

        $gitAddResult = runRedirectGuardrailsSelfTestCommand(['git', 'add', '-f', '--', '.'], $tempRoot);
        if ($gitAddResult['exitCode'] !== 0) {
            redirectGuardrailsSelfTestFail('Cannot stage fixture files.' . PHP_EOL . $gitAddResult['output']);
        }

        return runRedirectGuardrailsSelfTestCommand(
            [PHP_BINARY, $redirectGuardrailsAuditPath, $tempRoot],
            $projectRoot
        );
    } finally {
        redirectGuardrailsSelfTestRemoveTree($tempRoot);
    }
}

/**
 * @param array<string,string> $files
 */
function assertRedirectGuardrailsPasses(string $label, array $files): void
{
    $result = runRedirectGuardrailsAuditWithFixture($files);
    if ($result['exitCode'] !== 0) {
        redirectGuardrailsSelfTestFail($label . ' should pass redirect guardrails audit.' . PHP_EOL . $result['output']);
    }
}

/**
 * @param array<string,string> $files
 */
function assertRedirectGuardrailsFails(string $label, array $files, string $expectedOutput): void
{
    $result = runRedirectGuardrailsAuditWithFixture($files);
    if ($result['exitCode'] === 0) {
        redirectGuardrailsSelfTestFail($label . ' should fail redirect guardrails audit.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        redirectGuardrailsSelfTestFail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($redirectGuardrailsAuditPath)) {
    redirectGuardrailsSelfTestFail('Redirect guardrails audit self-test cannot find redirect_guardrails_audit.php.');
}

assertRedirectGuardrailsPasses(
    'Clean redirect fixtures',
    [
        'admin/safe_redirect.php' => <<<'PHP'
<?php
$target = internalRedirectTarget((string)($_GET['redirect'] ?? ''), '/admin/index.php');
header('Location: ' . $target);
PHP,
        'admin/safe_login_redirect.php' => <<<'PHP'
<?php
$target = adminLoginRedirectTarget((string)($_GET['redirect'] ?? ''), '/admin/index.php');
header('Location: ' . $target);
PHP,
        'public/safe_public_return.php' => <<<'PHP'
<?php
$target = safePublicReturnTarget((string)($_POST['return_url'] ?? ''), '/subscribe.php');
header('Location: ' . $target);
PHP,
        'build/ignored_fixture.php' => <<<'PHP'
<?php
$target = (string)($_GET['redirect'] ?? '/admin/index.php');
header('Location: ' . $target);
PHP,
    ]
);

assertRedirectGuardrailsFails(
    'Raw GET redirect target guard',
    [
        'admin/raw_get_redirect.php' => <<<'PHP'
<?php
$target = (string)($_GET['redirect'] ?? '/admin/index.php');
header('Location: ' . $target);
PHP,
    ],
    'admin/raw_get_redirect.php: request-derived redirect target must use internalRedirectTarget()'
);

assertRedirectGuardrailsFails(
    'Raw POST return URL guard',
    [
        'admin/raw_post_return.php' => <<<'PHP'
<?php
$target = (string)($_POST['return_url'] ?? '/admin/index.php');
header('Location: ' . $target);
PHP,
    ],
    'admin/raw_post_return.php: request-derived redirect target must use internalRedirectTarget()'
);

assertRedirectGuardrailsFails(
    'filter_input redirect target guard',
    [
        'admin/filter_input_redirect.php' => <<<'PHP'
<?php
$target = (string)(filter_input(INPUT_GET, 'next') ?: '/admin/index.php');
header('Location: ' . $target);
PHP,
    ],
    'admin/filter_input_redirect.php: request-derived redirect target must use internalRedirectTarget()'
);

assertRedirectGuardrailsFails(
    'Raw REQUEST_URI return field guard',
    [
        'admin/raw_request_uri_field.php' => <<<'PHP'
<?php
?>
<form method="post">
  <input type="hidden" name="return_url" value="<?= h((string)($_SERVER['REQUEST_URI'] ?? '')) ?>">
</form>
PHP,
    ],
    'admin/raw_request_uri_field.php: request-derived redirect target must use internalRedirectTarget()'
);

echo "Redirect guardrails audit self-test OK\n";
