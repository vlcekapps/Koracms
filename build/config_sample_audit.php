<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$configSamplePath = $projectRoot . '/config.sample.php';
$readmePath = $projectRoot . '/README.md';
$adminGuidePath = $projectRoot . '/docs/admin-guide.md';
$issues = [];

/**
 * @return array<string, string>
 */
function parseConfigSampleDefines(string $source): array
{
    $matchCount = preg_match_all(
        "/define\\(\\s*'([A-Z0-9_]+)'\\s*,\\s*([^\\n;]+)\\s*\\)\\s*;/",
        $source,
        $matches,
        PREG_SET_ORDER,
    );

    if ($matchCount === false || $matchCount === 0) {
        return [];
    }

    $defines = [];
    foreach ($matches as $match) {
        $defines[(string)$match[1]] = trim((string)$match[2]);
    }

    return $defines;
}

/**
 * @param list<string> $issues
 */
function fileContentsOrIssue(string $path, string $label, array &$issues): string
{
    if (!is_file($path)) {
        $issues[] = $label . ': missing file';
        return '';
    }

    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        $issues[] = $label . ': cannot read file';
        return '';
    }

    return $contents;
}

$configSampleSource = fileContentsOrIssue($configSamplePath, 'config.sample.php', $issues);
$readmeSource = fileContentsOrIssue($readmePath, 'README.md', $issues);
$adminGuideSource = fileContentsOrIssue($adminGuidePath, 'docs/admin-guide.md', $issues);
$sampleDefines = parseConfigSampleDefines($configSampleSource);

foreach ([
    '$server',
    '$user',
    '$pass',
    '$database',
] as $requiredVariable) {
    if (!str_contains($configSampleSource, $requiredVariable)) {
        $issues[] = 'config.sample.php is missing database variable ' . $requiredVariable;
    }
}

foreach ([
    'BASE_URL',
    'KORA_STORAGE_DIR',
    'SMTP_HOST',
    'SMTP_PORT',
    'SMTP_USER',
    'SMTP_PASS',
    'SMTP_SECURE',
    'GITHUB_ISSUES_TOKEN',
    'CRON_TOKEN',
] as $requiredConstant) {
    if (!array_key_exists($requiredConstant, $sampleDefines)) {
        $issues[] = 'config.sample.php is missing define(' . $requiredConstant . ')';
    }
}

$expectedSafeDefaults = [
    'BASE_URL' => "''",
    'KORA_STORAGE_DIR' => "''",
    'SMTP_HOST' => "'localhost'",
    'SMTP_PORT' => '25',
    'SMTP_USER' => "''",
    'SMTP_PASS' => "''",
    'SMTP_SECURE' => "''",
    'GITHUB_ISSUES_TOKEN' => "''",
    'CRON_TOKEN' => "''",
];

foreach ($expectedSafeDefaults as $constant => $expectedValue) {
    $actualValue = $sampleDefines[$constant] ?? null;
    if ($actualValue !== $expectedValue) {
        $issues[] = 'config.sample.php should use safe default for ' . $constant . ': ' . $expectedValue;
    }
}

foreach ([
    'smtp.example.com',
    'uzivatel@example.com',
    'heslo-nebo-app-password',
    'ghp_',
    'VAS_CRON_TOKEN',
] as $forbiddenPlaceholder) {
    if (str_contains($configSampleSource, $forbiddenPlaceholder)) {
        $issues[] = 'config.sample.php should not contain placeholder secret or external SMTP value: ' . $forbiddenPlaceholder;
    }
}

foreach ([
    'Přejmenujte tento soubor na config.php',
    'Privátní úložiště mimo webroot',
    'SMTP odesílání e-mailů',
    'GitHub issue bridge',
    'Token pro volitelný HTTP přístup ke cron.php',
    'Doporučený způsob spouštění je CLI cron',
] as $requiredCopy) {
    if (!str_contains($configSampleSource, $requiredCopy)) {
        $issues[] = 'config.sample.php is missing explanatory copy: ' . $requiredCopy;
    }
}

foreach ([
    'config.sample.php' => 'README.md',
    'BASE_URL' => 'README.md',
    'KORA_STORAGE_DIR' => 'README.md',
    'SMTP' => 'README.md',
    'CRON_TOKEN' => 'README.md',
] as $requiredFragment => $label) {
    if (!str_contains($readmeSource, $requiredFragment)) {
        $issues[] = $label . ' is missing configuration fragment: ' . $requiredFragment;
    }
}

foreach ([
    'config.sample.php' => 'docs/admin-guide.md',
    'composer ci:basic' => 'docs/admin-guide.md',
    'build/config_sample_audit.php' => 'docs/admin-guide.md',
] as $requiredFragment => $label) {
    if (!str_contains($adminGuideSource, $requiredFragment)) {
        $issues[] = $label . ' is missing configuration audit fragment: ' . $requiredFragment;
    }
}

if ($issues !== []) {
    echo "Config sample audit failed:\n";
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
    exit(1);
}

echo "Config sample audit OK\n";
