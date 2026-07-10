<?php

declare(strict_types=1);

$projectRootArgument = $argv[1] ?? null;
$projectRoot = accessibilityConformanceAuditProjectRoot(is_string($projectRootArgument) ? $projectRootArgument : null);
$skipChangeImpact = in_array('--skip-change-impact', $argv, true);
$diffFile = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--diff-file=')) {
        $diffFile = substr($argument, strlen('--diff-file='));
    }
}
$issues = [];

function accessibilityConformanceAuditProjectRoot(?string $override): string
{
    if ($override !== null && trim($override) !== '') {
        $resolved = realpath($override);
        if (is_string($resolved) && is_dir($resolved)) {
            return $resolved;
        }
    }

    $environmentOverride = getenv('KORA_ACCESSIBILITY_CONFORMANCE_AUDIT_ROOT');
    if (is_string($environmentOverride) && trim($environmentOverride) !== '') {
        $resolved = realpath($environmentOverride);
        if (is_string($resolved) && is_dir($resolved)) {
            return $resolved;
        }
    }

    return dirname(__DIR__);
}

/**
 * @return array<string,array{title:string,level:string}>
 */
function accessibilityConformanceExpectedCriteria(): array
{
    return [
        '1.1.1' => ['title' => 'Non-text Content', 'level' => 'A'],
        '1.2.1' => ['title' => 'Audio-only and Video-only', 'level' => 'A'],
        '1.2.2' => ['title' => 'Captions (Prerecorded)', 'level' => 'A'],
        '1.2.3' => ['title' => 'Audio Description or Media Alternative', 'level' => 'A'],
        '1.2.4' => ['title' => 'Captions (Live)', 'level' => 'AA'],
        '1.2.5' => ['title' => 'Audio Description (Prerecorded)', 'level' => 'AA'],
        '1.3.1' => ['title' => 'Info and Relationships', 'level' => 'A'],
        '1.3.2' => ['title' => 'Meaningful Sequence', 'level' => 'A'],
        '1.3.3' => ['title' => 'Sensory Characteristics', 'level' => 'A'],
        '1.3.4' => ['title' => 'Orientation', 'level' => 'AA'],
        '1.3.5' => ['title' => 'Identify Input Purpose', 'level' => 'AA'],
        '1.4.1' => ['title' => 'Use of Color', 'level' => 'A'],
        '1.4.2' => ['title' => 'Audio Control', 'level' => 'A'],
        '1.4.3' => ['title' => 'Contrast (Minimum)', 'level' => 'AA'],
        '1.4.4' => ['title' => 'Resize Text', 'level' => 'AA'],
        '1.4.5' => ['title' => 'Images of Text', 'level' => 'AA'],
        '1.4.10' => ['title' => 'Reflow', 'level' => 'AA'],
        '1.4.11' => ['title' => 'Non-text Contrast', 'level' => 'AA'],
        '1.4.12' => ['title' => 'Text Spacing', 'level' => 'AA'],
        '1.4.13' => ['title' => 'Content on Hover or Focus', 'level' => 'AA'],
        '2.1.1' => ['title' => 'Keyboard', 'level' => 'A'],
        '2.1.2' => ['title' => 'No Keyboard Trap', 'level' => 'A'],
        '2.1.4' => ['title' => 'Character Key Shortcuts', 'level' => 'A'],
        '2.2.1' => ['title' => 'Timing Adjustable', 'level' => 'A'],
        '2.2.2' => ['title' => 'Pause, Stop, Hide', 'level' => 'A'],
        '2.3.1' => ['title' => 'Three Flashes or Below Threshold', 'level' => 'A'],
        '2.4.1' => ['title' => 'Bypass Blocks', 'level' => 'A'],
        '2.4.2' => ['title' => 'Page Titled', 'level' => 'A'],
        '2.4.3' => ['title' => 'Focus Order', 'level' => 'A'],
        '2.4.4' => ['title' => 'Link Purpose (In Context)', 'level' => 'A'],
        '2.4.5' => ['title' => 'Multiple Ways', 'level' => 'AA'],
        '2.4.6' => ['title' => 'Headings and Labels', 'level' => 'AA'],
        '2.4.7' => ['title' => 'Focus Visible', 'level' => 'AA'],
        '2.4.11' => ['title' => 'Focus Not Obscured (Minimum)', 'level' => 'AA'],
        '2.5.1' => ['title' => 'Pointer Gestures', 'level' => 'A'],
        '2.5.2' => ['title' => 'Pointer Cancellation', 'level' => 'A'],
        '2.5.3' => ['title' => 'Label in Name', 'level' => 'A'],
        '2.5.4' => ['title' => 'Motion Actuation', 'level' => 'A'],
        '2.5.7' => ['title' => 'Dragging Movements', 'level' => 'AA'],
        '2.5.8' => ['title' => 'Target Size (Minimum)', 'level' => 'AA'],
        '3.1.1' => ['title' => 'Language of Page', 'level' => 'A'],
        '3.1.2' => ['title' => 'Language of Parts', 'level' => 'AA'],
        '3.2.1' => ['title' => 'On Focus', 'level' => 'A'],
        '3.2.2' => ['title' => 'On Input', 'level' => 'A'],
        '3.2.3' => ['title' => 'Consistent Navigation', 'level' => 'AA'],
        '3.2.4' => ['title' => 'Consistent Identification', 'level' => 'AA'],
        '3.2.6' => ['title' => 'Consistent Help', 'level' => 'A'],
        '3.3.1' => ['title' => 'Error Identification', 'level' => 'A'],
        '3.3.2' => ['title' => 'Labels or Instructions', 'level' => 'A'],
        '3.3.3' => ['title' => 'Error Suggestion', 'level' => 'AA'],
        '3.3.4' => ['title' => 'Error Prevention (Legal, Financial, Data)', 'level' => 'AA'],
        '3.3.7' => ['title' => 'Redundant Entry', 'level' => 'A'],
        '3.3.8' => ['title' => 'Accessible Authentication (Minimum)', 'level' => 'AA'],
        '4.1.2' => ['title' => 'Name, Role, Value', 'level' => 'A'],
        '4.1.3' => ['title' => 'Status Messages', 'level' => 'AA'],
    ];
}

/**
 * @param list<string> $issues
 */
function accessibilityConformanceReadFile(string $projectRoot, string $relativePath, array &$issues): string
{
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        $issues[] = $relativePath . ': missing file';
        return '';
    }

    $source = file_get_contents($path);
    if (!is_string($source)) {
        $issues[] = $relativePath . ': cannot read file';
        return '';
    }

    return $source;
}

/**
 * @param list<string> $issues
 * @return array<string,array{title:string,level:string,status:string}>
 */
function accessibilityConformanceParseMatrix(string $source, array &$issues): array
{
    $rows = [];
    foreach (preg_split('/\R/', $source) ?: [] as $line) {
        if (preg_match('/^\|\s*([1-4]\.[0-9]+\.[0-9]+)\s+([^|]+?)\s*\|\s*(A|AA)\s*\|\s*([^|]+?)\s*\|/', $line, $matches) !== 1) {
            continue;
        }

        $code = (string)$matches[1];
        if (isset($rows[$code])) {
            $issues[] = 'WCAG matrix contains duplicate criterion: ' . $code;
            continue;
        }

        $rows[$code] = [
            'title' => trim((string)$matches[2]),
            'level' => (string)$matches[3],
            'status' => trim((string)$matches[4]),
        ];
    }

    return $rows;
}

/**
 * @param list<string> $issues
 * @return array<string,array{title:string,level:string,status:string}>
 */
function accessibilityConformanceParseAcr(string $source, array &$issues): array
{
    $rows = [];
    $currentLevel = '';
    foreach (preg_split('/\R/', $source) ?: [] as $line) {
        if (trim($line) === '## WCAG 2.2 Level A') {
            $currentLevel = 'A';
            continue;
        }
        if (trim($line) === '## WCAG 2.2 Level AA') {
            $currentLevel = 'AA';
            continue;
        }
        if (preg_match('/^\|\s*([1-4]\.[0-9]+\.[0-9]+)\s+([^|]+?)\s*\|\s*([^|]+?)\s*\|/', $line, $matches) !== 1) {
            continue;
        }

        $code = (string)$matches[1];
        if (isset($rows[$code])) {
            $issues[] = 'ACR draft contains duplicate criterion: ' . $code;
            continue;
        }

        $rows[$code] = [
            'title' => trim((string)$matches[2]),
            'level' => $currentLevel,
            'status' => trim((string)$matches[3]),
        ];
    }

    return $rows;
}

/**
 * @param array<string,array{title:string,level:string,status:string}> $matrixRows
 * @param array<string,array{title:string,level:string,status:string}> $acrRows
 * @param list<string> $issues
 */
function accessibilityConformanceValidateCriteria(array $matrixRows, array $acrRows, array &$issues): void
{
    $expected = accessibilityConformanceExpectedCriteria();
    $allowedStatuses = ['Supports', 'Partially Supports', 'Does Not Support', 'Not Applicable'];

    foreach ($expected as $code => $spec) {
        foreach (['WCAG matrix' => $matrixRows, 'ACR draft' => $acrRows] as $label => $rows) {
            if (!isset($rows[$code])) {
                $issues[] = $label . ' is missing WCAG 2.2 A/AA criterion: ' . $code . ' ' . $spec['title'];
                continue;
            }
            if ($rows[$code]['title'] !== $spec['title']) {
                $issues[] = $label . ' has unexpected title for ' . $code . ': ' . $rows[$code]['title'];
            }
            if ($rows[$code]['level'] !== $spec['level']) {
                $issues[] = $label . ' has unexpected level for ' . $code . ': ' . $rows[$code]['level'];
            }
            if (!in_array($rows[$code]['status'], $allowedStatuses, true)) {
                $issues[] = $label . ' has unsupported conformance status for ' . $code . ': ' . $rows[$code]['status'];
            }
        }

        if (isset($matrixRows[$code], $acrRows[$code]) && $matrixRows[$code]['status'] !== $acrRows[$code]['status']) {
            $issues[] = 'WCAG matrix and ACR status mismatch for ' . $code . ': '
                . $matrixRows[$code]['status'] . ' vs ' . $acrRows[$code]['status'];
        }
    }

    foreach (['WCAG matrix' => $matrixRows, 'ACR draft' => $acrRows] as $label => $rows) {
        foreach (array_keys($rows) as $code) {
            if (!isset($expected[$code])) {
                $issues[] = $label . ' contains unexpected A/AA criterion: ' . $code;
            }
        }
        if (count($rows) !== count($expected)) {
            $issues[] = $label . ' must contain exactly 55 WCAG 2.2 A/AA criteria; found ' . count($rows);
        }
    }
}

function accessibilityConformanceContainsCode(string $source, string $code): bool
{
    return preg_match('/(?<![0-9.])' . preg_quote($code, '/') . '(?![0-9.])/', $source) === 1;
}

/**
 * @param array<string,array{title:string,level:string,status:string}> $matrixRows
 * @param list<string> $issues
 */
function accessibilityConformanceValidateTraceability(
    array $matrixRows,
    string $backlogSource,
    string $manualProtocolSource,
    array &$issues
): void {
    foreach ($matrixRows as $code => $row) {
        if ($row['status'] !== 'Partially Supports') {
            continue;
        }
        if (!accessibilityConformanceContainsCode($backlogSource, $code)) {
            $issues[] = 'Partially Supports criterion is missing explicit backlog traceability: ' . $code;
        }
        if (!accessibilityConformanceContainsCode($manualProtocolSource, $code)) {
            $issues[] = 'Partially Supports criterion is missing explicit manual-test traceability: ' . $code;
        }
    }
}

/**
 * @param array<string,array{title:string,level:string,status:string}> $matrixRows
 * @param list<string> $issues
 */
function accessibilityConformanceValidateAcrMetadata(
    string $acrSource,
    string $versionSource,
    array $matrixRows,
    array &$issues
): void {
    $version = trim($versionSource);
    if ($version === '' || !str_contains($acrSource, '- Version evaluated: ' . $version)) {
        $issues[] = 'ACR draft must identify the evaluated VERSION value: ' . ($version !== '' ? $version : '(missing)');
    }

    if (preg_match('/^- Report date:\s*(\d{4}-\d{2}-\d{2})\s*$/m', $acrSource, $reportDateMatch) !== 1) {
        $issues[] = 'ACR draft is missing a valid report date';
    } else {
        preg_match_all('/\b20\d{2}-\d{2}-\d{2}\b/', $acrSource, $dateMatches);
        $evidenceDates = $dateMatches[0];
        rsort($evidenceDates);
        $latestEvidenceDate = $evidenceDates[0] ?? '';
        if ($latestEvidenceDate !== '' && $reportDateMatch[1] < $latestEvidenceDate) {
            $issues[] = 'ACR report date predates its latest evidence: ' . $reportDateMatch[1] . ' < ' . $latestEvidenceDate;
        }
    }

    $counts = ['Supports' => 0, 'Partially Supports' => 0, 'Does Not Support' => 0, 'Not Applicable' => 0];
    foreach ($matrixRows as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']]++;
        }
    }
    foreach ($counts as $status => $count) {
        if (!str_contains($acrSource, '- ' . $status . ': ' . $count . ' criteria')) {
            $issues[] = 'ACR current summary is missing exact status count: ' . $status . ' = ' . $count;
        }
    }
}

/**
 * @param list<string> $command
 * @return array{exitCode:int,stdout:string,stderr:string}
 */
function accessibilityConformanceRunCommand(array $command, string $cwd): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes, $cwd, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        return ['exitCode' => 1, 'stdout' => '', 'stderr' => 'cannot start command'];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exitCode' => (int)$exitCode,
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

/**
 * @param list<string> $issues
 */
function accessibilityConformanceGitDiff(string $projectRoot, array &$issues): string
{
    $status = accessibilityConformanceRunCommand(
        ['git', 'status', '--porcelain', '--untracked-files=all'],
        $projectRoot
    );
    if ($status['exitCode'] !== 0) {
        $issues[] = 'cannot inspect git status for accessibility change impact';
        return '';
    }

    $dirty = trim($status['stdout']) !== '';
    $command = $dirty
        ? ['git', 'diff', '--no-ext-diff', '--unified=0', 'HEAD', '--']
        : ['git', 'diff', '--no-ext-diff', '--unified=0', 'HEAD^', 'HEAD', '--'];
    $diffResult = accessibilityConformanceRunCommand($command, $projectRoot);
    if (!$dirty && $diffResult['exitCode'] !== 0) {
        // GitHub Actions commonly uses a depth-1 checkout where HEAD^ is unavailable.
        $diffResult = accessibilityConformanceRunCommand(
            ['git', 'show', '--format=', '--no-ext-diff', '--unified=0', 'HEAD', '--'],
            $projectRoot
        );
    }
    if ($diffResult['exitCode'] !== 0) {
        $issues[] = 'cannot inspect git diff for accessibility change impact';
        return '';
    }
    $diff = $diffResult['stdout'];

    if (!$dirty) {
        return $diff;
    }

    $untrackedResult = accessibilityConformanceRunCommand(
        ['git', 'ls-files', '--others', '--exclude-standard', '-z'],
        $projectRoot
    );
    if ($untrackedResult['exitCode'] !== 0) {
        $issues[] = 'cannot inspect untracked files for accessibility change impact';
        return $diff;
    }
    if ($untrackedResult['stdout'] === '') {
        return $diff;
    }

    foreach (array_filter(explode("\0", rtrim($untrackedResult['stdout'], "\0"))) as $relativePath) {
        $normalizedPath = str_replace('\\', '/', $relativePath);
        $fullPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
        $source = is_file($fullPath) ? file_get_contents($fullPath) : false;
        if (!is_string($source)) {
            continue;
        }
        $diff .= "\ndiff --git a/{$normalizedPath} b/{$normalizedPath}\n--- /dev/null\n+++ b/{$normalizedPath}\n";
        foreach (preg_split('/\R/', $source) ?: [] as $line) {
            $diff .= '+' . $line . "\n";
        }
    }

    return $diff;
}

/**
 * @return array<string,list<string>>
 */
function accessibilityConformanceChangedLinesByFile(string $diff): array
{
    $changedLines = [];
    $currentPath = null;
    foreach (preg_split('/\R/', $diff) ?: [] as $line) {
        if (preg_match('#^diff --git a/(.+) b/(.+)$#', $line, $matches) === 1) {
            $currentPath = (string)$matches[2];
            $changedLines[$currentPath] ??= [];
            continue;
        }
        if (str_starts_with($line, '+++ b/')) {
            $currentPath = substr($line, 6);
            $changedLines[$currentPath] ??= [];
            continue;
        }
        if (str_starts_with($line, '--- a/')) {
            $currentPath = substr($line, 6);
            $changedLines[$currentPath] ??= [];
            continue;
        }
        if (
            $currentPath !== null
            && (str_starts_with($line, '+') || str_starts_with($line, '-'))
            && !str_starts_with($line, '+++')
            && !str_starts_with($line, '---')
        ) {
            $changedLines[$currentPath][] = substr($line, 1);
        }
    }

    return $changedLines;
}

function accessibilityConformanceSensitiveSourcePath(string $path): bool
{
    $normalizedPath = str_replace('\\', '/', $path);
    if (preg_match('#^(?:build|docs|vendor|node_modules|dist)/#', $normalizedPath) === 1) {
        return false;
    }

    return in_array(strtolower((string)pathinfo($normalizedPath, PATHINFO_EXTENSION)), ['php', 'js', 'mjs', 'css'], true);
}

/**
 * @param list<string> $lines
 */
function accessibilityConformanceLinesContainRisk(array $lines): bool
{
    $source = implode("\n", $lines);
    foreach ([
        '/<(?:form|input|select|textarea|button|dialog|table|img|iframe|audio|video|track)\b/i',
        '/\b(?:aria-[a-z-]+|role\s*=|tabindex\s*=|autocomplete\s*=|data-confirm\s*=)/i',
        '/\$_POST\b|REQUEST_METHOD[^\n]*POST|verifyCsrf\s*\(/i',
        '/\b(?:DELETE\s+FROM|INSERT\s+INTO|UPDATE\s+[a-z0-9_]+\s+SET)\b/i',
        '/\b(?:sendMail|dispatch[A-Za-z0-9_]*Webhook|ZipArchive|Content-Disposition)\b/i',
        '/\b(?:focus|activeElement|keydown|keyup|pointerdown|click|setAttribute|removeAttribute|classList|innerHTML|textContent)\b/i',
    ] as $pattern) {
        if (preg_match($pattern, $source) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string,list<string>> $changedLinesByFile
 * @param list<string> $issues
 */
function accessibilityConformanceValidateChangeImpact(array $changedLinesByFile, array &$issues): void
{
    $riskFiles = [];
    foreach ($changedLinesByFile as $path => $lines) {
        $isStylesheet = strtolower((string)pathinfo($path, PATHINFO_EXTENSION)) === 'css';
        if (
            accessibilityConformanceSensitiveSourcePath($path)
            && ($isStylesheet || accessibilityConformanceLinesContainRisk($lines))
        ) {
            $riskFiles[] = $path;
        }
    }
    if ($riskFiles === []) {
        return;
    }

    $changedPaths = array_keys($changedLinesByFile);
    $impactPaths = [
        'docs/accessibility/wcag-22-aa-conformance.md',
        'docs/accessibility/acr-vpat-wcag-draft.md',
        'docs/accessibility/a11y-remediation-backlog.md',
        'docs/accessibility/manual-test-protocol.md',
        'docs/accessibility/a11y-impact-decisions.md',
    ];
    $hasImpactReview = false;
    foreach ($changedPaths as $path) {
        if (in_array($path, $impactPaths, true) || str_starts_with($path, 'docs/accessibility/modules/')) {
            $hasImpactReview = true;
            break;
        }
    }
    if (!$hasImpactReview) {
        $issues[] = 'accessibility-sensitive change is missing conformance impact review; update the WCAG/ACR/backlog/manual docs or a11y-impact-decisions.md. Risk files: '
            . implode(', ', $riskFiles);
    }

    $evidencePaths = [
        'build/runtime_audit.php',
        'build/http_integration.php',
        'build/unit_tests.php',
        'build/theme_view_audit.php',
        'build/module_contract_audit.php',
        'build/accessibility_conformance_audit.php',
    ];
    $hasAutomatedEvidence = array_intersect($changedPaths, $evidencePaths) !== [];
    if (!$hasAutomatedEvidence) {
        $issues[] = 'accessibility-sensitive change is missing updated automated evidence. Risk files: '
            . implode(', ', $riskFiles);
    }
}

$wcagSource = accessibilityConformanceReadFile(
    $projectRoot,
    'docs/accessibility/wcag-22-aa-conformance.md',
    $issues
);
$acrSource = accessibilityConformanceReadFile(
    $projectRoot,
    'docs/accessibility/acr-vpat-wcag-draft.md',
    $issues
);
$backlogSource = accessibilityConformanceReadFile(
    $projectRoot,
    'docs/accessibility/a11y-remediation-backlog.md',
    $issues
);
$manualProtocolSource = accessibilityConformanceReadFile(
    $projectRoot,
    'docs/accessibility/manual-test-protocol.md',
    $issues
);
$impactDecisionsSource = accessibilityConformanceReadFile(
    $projectRoot,
    'docs/accessibility/a11y-impact-decisions.md',
    $issues
);
$versionSource = accessibilityConformanceReadFile($projectRoot, 'VERSION', $issues);
$composerSource = accessibilityConformanceReadFile($projectRoot, 'composer.json', $issues);
$agentsSource = accessibilityConformanceReadFile($projectRoot, 'AGENTS.md', $issues);
$developerModulesSource = accessibilityConformanceReadFile($projectRoot, 'docs/developer-modules.md', $issues);
$moduleProposalSource = accessibilityConformanceReadFile($projectRoot, 'docs/module-proposal-template.md', $issues);

$matrixRows = accessibilityConformanceParseMatrix($wcagSource, $issues);
$acrRows = accessibilityConformanceParseAcr($acrSource, $issues);
accessibilityConformanceValidateCriteria($matrixRows, $acrRows, $issues);
accessibilityConformanceValidateTraceability($matrixRows, $backlogSource, $manualProtocolSource, $issues);
accessibilityConformanceValidateAcrMetadata($acrSource, $versionSource, $matrixRows, $issues);

foreach ([
    '# Accessibility Impact Decisions',
    'Dotčená kritéria',
    'Automatizovaný důkaz',
    'Rozhodnutí',
] as $impactDecisionFragment) {
    if (!str_contains($impactDecisionsSource, $impactDecisionFragment)) {
        $issues[] = 'a11y impact decision log is missing fragment: ' . $impactDecisionFragment;
    }
}

foreach ([
    'composer.json' => [$composerSource, 'test:accessibility-conformance'],
    'composer.json self-test' => [$composerSource, 'test:accessibility-conformance-selftest'],
    'AGENTS.md' => [$agentsSource, 'build/accessibility_conformance_audit.php'],
    'docs/developer-modules.md' => [$developerModulesSource, 'a11y-impact-decisions.md'],
    'docs/module-proposal-template.md' => [$moduleProposalSource, 'a11y-impact-decisions.md'],
] as $label => [$source, $fragment]) {
    if (!str_contains((string)$source, (string)$fragment)) {
        $issues[] = $label . ' is missing accessibility conformance gate fragment: ' . $fragment;
    }
}

if (!$skipChangeImpact) {
    if ($diffFile !== null) {
        $diffSource = is_file($diffFile) ? file_get_contents($diffFile) : false;
        if (!is_string($diffSource)) {
            $issues[] = 'cannot read accessibility change diff fixture: ' . $diffFile;
            $diffSource = '';
        }
    } else {
        $diffSource = accessibilityConformanceGitDiff($projectRoot, $issues);
    }
    accessibilityConformanceValidateChangeImpact(
        accessibilityConformanceChangedLinesByFile($diffSource),
        $issues
    );
}

if ($issues !== []) {
    echo "Accessibility conformance audit failed:\n";
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
    exit(1);
}

echo "Accessibility conformance audit OK\n";
echo "- 55 WCAG 2.2 A/AA criteria are complete and status-aligned.\n";
echo "- Partially Supports criteria are traceable to backlog and manual testing.\n";
if (!$skipChangeImpact) {
    echo "- Accessibility-sensitive changes include impact review and automated evidence.\n";
}
