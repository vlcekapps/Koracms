<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$auditPath = __DIR__ . DIRECTORY_SEPARATOR . 'accessibility_conformance_audit.php';

function accessibilityConformanceSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function accessibilityConformanceSelfTestWrite(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        accessibilityConformanceSelfTestFail('Cannot create directory: ' . $directory);
    }
    if (file_put_contents($path, $contents) === false) {
        accessibilityConformanceSelfTestFail('Cannot write file: ' . $path);
    }
}

function accessibilityConformanceSelfTestRemoveTree(string $path): void
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
            accessibilityConformanceSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }
    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int,output:string}
 */
function accessibilityConformanceSelfTestRun(array $command, string $cwd): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes, $cwd, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        accessibilityConformanceSelfTestFail('Cannot start command: ' . implode(' ', $command));
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
 * @return array<string,string>
 */
function accessibilityConformanceSelfTestFixture(): array
{
    global $projectRoot;

    $files = [];
    foreach ([
        'docs/accessibility/wcag-22-aa-conformance.md',
        'docs/accessibility/acr-vpat-wcag-draft.md',
        'docs/accessibility/a11y-remediation-backlog.md',
        'docs/accessibility/manual-test-protocol.md',
        'docs/accessibility/a11y-impact-decisions.md',
        'docs/developer-modules.md',
        'docs/module-proposal-template.md',
        'VERSION',
        'composer.json',
        'AGENTS.md',
    ] as $relativePath) {
        $source = file_get_contents(
            $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath)
        );
        if (!is_string($source)) {
            accessibilityConformanceSelfTestFail('Cannot read fixture source: ' . $relativePath);
        }
        $files[$relativePath] = $source;
    }

    return $files;
}

/**
 * @param array<string,string> $files
 * @return array{exitCode:int,output:string}
 */
function accessibilityConformanceSelfTestRunFixture(array $files): array
{
    global $projectRoot, $auditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_accessibility_conformance_'
        . bin2hex(random_bytes(6));
    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            accessibilityConformanceSelfTestFail('Cannot create temporary fixture directory.');
        }
        foreach ($files as $relativePath => $contents) {
            accessibilityConformanceSelfTestWrite(
                $tempRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                $contents
            );
        }

        return accessibilityConformanceSelfTestRun(
            [PHP_BINARY, $auditPath, $tempRoot, '--skip-change-impact'],
            $projectRoot
        );
    } finally {
        accessibilityConformanceSelfTestRemoveTree($tempRoot);
    }
}

/**
 * @param array<string,string> $files
 */
function accessibilityConformanceSelfTestAssertPasses(string $label, array $files): void
{
    $result = accessibilityConformanceSelfTestRunFixture($files);
    if ($result['exitCode'] !== 0) {
        accessibilityConformanceSelfTestFail($label . ' should pass.' . PHP_EOL . $result['output']);
    }
}

/**
 * @param array<string,string> $files
 */
function accessibilityConformanceSelfTestAssertFails(
    string $label,
    array $files,
    string $expectedOutput
): void {
    $result = accessibilityConformanceSelfTestRunFixture($files);
    if ($result['exitCode'] === 0) {
        accessibilityConformanceSelfTestFail($label . ' should fail.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        accessibilityConformanceSelfTestFail(
            $label . ' failed for an unexpected reason.' . PHP_EOL
            . 'Expected: ' . $expectedOutput . PHP_EOL
            . $result['output']
        );
    }
}

function accessibilityConformanceSelfTestDiff(
    string $label,
    string $diff,
    bool $shouldPass,
    string $expectedOutput = ''
): void {
    global $projectRoot, $auditPath;

    $diffPath = tempnam(sys_get_temp_dir(), 'koracms_a11y_diff_');
    if (!is_string($diffPath)) {
        accessibilityConformanceSelfTestFail('Cannot create temporary diff fixture.');
    }
    try {
        accessibilityConformanceSelfTestWrite($diffPath, $diff);
        $result = accessibilityConformanceSelfTestRun(
            [PHP_BINARY, $auditPath, $projectRoot, '--diff-file=' . $diffPath],
            $projectRoot
        );
    } finally {
        @unlink($diffPath);
    }

    if ($shouldPass && $result['exitCode'] !== 0) {
        accessibilityConformanceSelfTestFail($label . ' should pass.' . PHP_EOL . $result['output']);
    }
    if (!$shouldPass && $result['exitCode'] === 0) {
        accessibilityConformanceSelfTestFail($label . ' should fail.');
    }
    if (!$shouldPass && !str_contains($result['output'], $expectedOutput)) {
        accessibilityConformanceSelfTestFail(
            $label . ' failed for an unexpected reason.' . PHP_EOL
            . 'Expected: ' . $expectedOutput . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($auditPath)) {
    accessibilityConformanceSelfTestFail('Cannot find accessibility_conformance_audit.php.');
}

$validFiles = accessibilityConformanceSelfTestFixture();
accessibilityConformanceSelfTestAssertPasses('Clean conformance fixture', $validFiles);

$missingCriterionFiles = $validFiles;
$missingCriterionFiles['docs/accessibility/wcag-22-aa-conformance.md'] = (string)preg_replace(
    '/^\| 3\.3\.4 [^\r\n]*\R/m',
    '',
    $missingCriterionFiles['docs/accessibility/wcag-22-aa-conformance.md'],
    1
);
accessibilityConformanceSelfTestAssertFails(
    'Missing criterion guard',
    $missingCriterionFiles,
    'WCAG matrix is missing WCAG 2.2 A/AA criterion: 3.3.4'
);

$statusMismatchFiles = $validFiles;
$statusMismatchFiles['docs/accessibility/acr-vpat-wcag-draft.md'] = (string)preg_replace(
    '/^(\| 3\.3\.4 [^|]+\| )Partially Supports( \|)/m',
    '$1Supports$2',
    $statusMismatchFiles['docs/accessibility/acr-vpat-wcag-draft.md'],
    1
);
accessibilityConformanceSelfTestAssertFails(
    'Status parity guard',
    $statusMismatchFiles,
    'WCAG matrix and ACR status mismatch for 3.3.4'
);

$missingTraceabilityFiles = $validFiles;
$missingTraceabilityFiles['docs/accessibility/a11y-remediation-backlog.md'] = str_replace(
    '2.5.3',
    '2.5.x',
    $missingTraceabilityFiles['docs/accessibility/a11y-remediation-backlog.md']
);
accessibilityConformanceSelfTestAssertFails(
    'Partial-status traceability guard',
    $missingTraceabilityFiles,
    'Partially Supports criterion is missing explicit backlog traceability: 2.5.3'
);

$staleDateFiles = $validFiles;
$staleDateFiles['docs/accessibility/acr-vpat-wcag-draft.md'] = (string)preg_replace(
    '/^- Report date: \d{4}-\d{2}-\d{2}$/m',
    '- Report date: 2000-01-01',
    $staleDateFiles['docs/accessibility/acr-vpat-wcag-draft.md'],
    1
);
accessibilityConformanceSelfTestAssertFails(
    'Stale report-date guard',
    $staleDateFiles,
    'ACR report date predates its latest evidence'
);

$podcastRiskDiff = <<<'DIFF'
diff --git a/admin/podcast_people.php b/admin/podcast_people.php
--- a/admin/podcast_people.php
+++ b/admin/podcast_people.php
+<form method="post"><input name="person_name"><button type="submit">Uložit</button></form>
diff --git a/build/http_integration.php b/build/http_integration.php
--- a/build/http_integration.php
+++ b/build/http_integration.php
+echo "podcast_people_http OK\n";
DIFF;
accessibilityConformanceSelfTestDiff(
    'Historical podcast documentation-drift guard',
    $podcastRiskDiff,
    false,
    'accessibility-sensitive change is missing conformance impact review'
);

$reviewedPodcastDiff = $podcastRiskDiff . <<<'DIFF'

diff --git a/docs/accessibility/a11y-impact-decisions.md b/docs/accessibility/a11y-impact-decisions.md
--- a/docs/accessibility/a11y-impact-decisions.md
+++ b/docs/accessibility/a11y-impact-decisions.md
+- Dotčená kritéria: 3.3.2, 3.3.4
DIFF;
accessibilityConformanceSelfTestDiff('Reviewed podcast change guard', $reviewedPodcastDiff, true);

$missingEvidenceDiff = <<<'DIFF'
diff --git a/admin/podcast_platforms.php b/admin/podcast_platforms.php
--- a/admin/podcast_platforms.php
+++ b/admin/podcast_platforms.php
+<form method="post"><button type="submit">Smazat</button></form>
diff --git a/docs/accessibility/a11y-impact-decisions.md b/docs/accessibility/a11y-impact-decisions.md
--- a/docs/accessibility/a11y-impact-decisions.md
+++ b/docs/accessibility/a11y-impact-decisions.md
+- Dotčená kritéria: 3.3.4
DIFF;
accessibilityConformanceSelfTestDiff(
    'Automated evidence guard',
    $missingEvidenceDiff,
    false,
    'accessibility-sensitive change is missing updated automated evidence'
);

$removedLiveRegionDiff = <<<'DIFF'
diff --git a/admin/layout.php b/admin/layout.php
--- a/admin/layout.php
+++ b/admin/layout.php
-<div role="status" aria-live="polite"></div>
DIFF;
accessibilityConformanceSelfTestDiff(
    'Removed accessibility markup guard',
    $removedLiveRegionDiff,
    false,
    'accessibility-sensitive change is missing conformance impact review'
);

$stylesheetDiff = <<<'DIFF'
diff --git a/admin/assets/layout.css b/admin/assets/layout.css
--- a/admin/assets/layout.css
+++ b/admin/assets/layout.css
+.podcast-row { display: none; }
DIFF;
accessibilityConformanceSelfTestDiff(
    'Stylesheet impact guard',
    $stylesheetDiff,
    false,
    'accessibility-sensitive change is missing conformance impact review'
);

echo "Accessibility conformance audit self-test OK\n";
