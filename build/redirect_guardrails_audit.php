<?php

declare(strict_types=1);

$projectRootArgument = $argv[1] ?? null;
$projectRoot = redirectGuardrailsProjectRoot(is_string($projectRootArgument) ? $projectRootArgument : null);
$issues = [];

function redirectGuardrailsProjectRoot(?string $override): string
{
    if ($override !== null && $override !== '') {
        $resolvedOverride = realpath($override);
        if ($resolvedOverride !== false && is_dir($resolvedOverride)) {
            return $resolvedOverride;
        }
    }

    $environmentOverride = getenv('KORA_REDIRECT_GUARDRAILS_ROOT');
    if (is_string($environmentOverride) && $environmentOverride !== '') {
        $resolvedEnvironmentOverride = realpath($environmentOverride);
        if ($resolvedEnvironmentOverride !== false && is_dir($resolvedEnvironmentOverride)) {
            return $resolvedEnvironmentOverride;
        }
    }

    return dirname(__DIR__);
}

/**
 * @return list<string>
 */
function trackedRedirectGuardrailFiles(string $projectRoot): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(
        ['git', 'ls-files', '-z'],
        $descriptorSpec,
        $pipes,
        $projectRoot,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        return [];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0 || !is_string($stdout) || $stdout === '') {
        return [];
    }

    return array_values(array_filter(explode("\0", rtrim($stdout, "\0"))));
}

function shouldAuditRedirectGuardrailFile(string $relativePath): bool
{
    $normalizedPath = str_replace('\\', '/', $relativePath);
    if (preg_match('#^(?:build|dist|uploads|vendor)/#', $normalizedPath) === 1) {
        return false;
    }

    return strtolower((string)pathinfo($normalizedPath, PATHINFO_EXTENSION)) === 'php';
}

function redirectGuardrailsHasSafeTargetHelper(string $source): bool
{
    return str_contains($source, 'internalRedirectTarget(')
        || str_contains($source, 'adminLoginRedirectTarget(');
}

function redirectGuardrailsReadsRequestRedirectTarget(string $source): bool
{
    $requestKeys = [
        'back',
        'next',
        'redirect',
        'redirect_target',
        'return',
        'return_url',
    ];

    foreach ($requestKeys as $requestKey) {
        $quotedKey = preg_quote($requestKey, '/');
        if (preg_match('/\$_(?:GET|POST|REQUEST)\s*\[\s*[\'"]' . $quotedKey . '[\'"]\s*\]/', $source) === 1) {
            return true;
        }

        if (preg_match('/filter_input\s*\(\s*INPUT_(?:GET|POST)\s*,\s*[\'"]' . $quotedKey . '[\'"]/', $source) === 1) {
            return true;
        }
    }

    return false;
}

function redirectGuardrailsOutputsRawRequestUriReturnField(string $source): bool
{
    return preg_match(
        '/name\s*=\s*[\'"](?:redirect|redirect_target|return_url)[\'"][^>]*REQUEST_URI/s',
        $source
    ) === 1;
}

foreach (trackedRedirectGuardrailFiles($projectRoot) as $relativePath) {
    if (!shouldAuditRedirectGuardrailFile($relativePath)) {
        continue;
    }

    $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($absolutePath)) {
        continue;
    }

    $contents = file_get_contents($absolutePath);
    if (!is_string($contents)) {
        $issues[] = $relativePath . ': cannot read file';
        continue;
    }

    $readsRedirectTarget = redirectGuardrailsReadsRequestRedirectTarget($contents);
    $outputsRawRequestUri = redirectGuardrailsOutputsRawRequestUriReturnField($contents);
    if (($readsRedirectTarget || $outputsRawRequestUri) && !redirectGuardrailsHasSafeTargetHelper($contents)) {
        $issues[] = $relativePath . ': request-derived redirect target must use internalRedirectTarget()';
    }
}

if ($issues !== []) {
    echo "Redirect guardrails audit failed:\n";
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
    exit(1);
}

echo "Redirect guardrails audit OK\n";
