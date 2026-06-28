<?php

declare(strict_types=1);

$projectRootArgument = $argv[1] ?? null;
$projectRoot = moduleContractAuditProjectRoot(is_string($projectRootArgument) ? $projectRootArgument : null);
$issues = [];

function moduleContractAuditProjectRoot(?string $override): string
{
    $candidates = [];
    if ($override !== null && trim($override) !== '') {
        $candidates[] = $override;
    }

    $environmentOverride = getenv('KORA_MODULE_CONTRACT_AUDIT_ROOT');
    if (is_string($environmentOverride) && trim($environmentOverride) !== '') {
        $candidates[] = $environmentOverride;
    }

    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if (is_string($resolved) && is_dir($resolved)) {
            return $resolved;
        }
    }

    return dirname(__DIR__);
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditReadFile(string $projectRoot, string $relativePath, array &$issues): string
{
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        $issues[] = $relativePath . ' is missing.';
        return '';
    }

    $source = file_get_contents($path);
    if (!is_string($source) || $source === '') {
        $issues[] = $relativePath . ' cannot be read.';
        return '';
    }

    return $source;
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditRequire(bool $condition, string $message, array &$issues): void
{
    if (!$condition) {
        $issues[] = $message;
    }
}

/**
 * @param list<string> $knownModuleKeys
 * @param list<string> $issues
 */
function moduleContractAuditRequireKnownModule(string $moduleKey, string $context, array $knownModuleKeys, array &$issues): void
{
    if (!in_array($moduleKey, $knownModuleKeys, true)) {
        $issues[] = $context . ' references unknown module key ' . $moduleKey . '.';
    }
}

/**
 * @param list<string> $knownModuleKeys
 * @param list<string> $issues
 */
function moduleContractAuditValidateModuleGateReferences(string $source, string $relativePath, array $knownModuleKeys, array &$issues): void
{
    if (preg_match_all('/\bisModuleEnabled\(\s*[\'"]([a-z][a-z0-9_]*)[\'"]\s*\)/', $source, $matches) === false) {
        $issues[] = $relativePath . ' isModuleEnabled references cannot be parsed.';
        return;
    }

    foreach ($matches[1] as $moduleKey) {
        moduleContractAuditRequireKnownModule($moduleKey, $relativePath . ' isModuleEnabled', $knownModuleKeys, $issues);
    }
}

/**
 * @return list<string>
 */
function moduleContractAuditExpectedKeys(): array
{
    return [
        'blog',
        'news',
        'chat',
        'contact',
        'gallery',
        'events',
        'podcast',
        'places',
        'newsletter',
        'downloads',
        'food',
        'polls',
        'faq',
        'board',
        'reservations',
        'forms',
        'statistics',
    ];
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditCollectThemeRequiredModules(string $projectRoot, array &$issues): void
{
    $knownModuleKeys = moduleContractAuditExpectedKeys();
    $themePattern = $projectRoot . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'theme.json';
    $themePaths = glob($themePattern);
    if ($themePaths === false || $themePaths === []) {
        $issues[] = 'themes/*/theme.json manifests are missing.';
        return;
    }

    foreach ($themePaths as $themePath) {
        $relativePath = str_replace('\\', '/', str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $themePath));
        $source = file_get_contents($themePath);
        if (!is_string($source) || $source === '') {
            $issues[] = $relativePath . ' cannot be read.';
            continue;
        }

        $manifest = json_decode($source, true);
        if (!is_array($manifest)) {
            $issues[] = $relativePath . ' is not valid JSON.';
            continue;
        }

        moduleContractAuditCollectRequiredModulesFromValue($manifest, $relativePath, $knownModuleKeys, $issues);
    }
}

/**
 * @param list<string> $knownModuleKeys
 * @param list<string> $issues
 */
function moduleContractAuditCollectRequiredModulesFromValue(mixed $value, string $context, array $knownModuleKeys, array &$issues): void
{
    if (!is_array($value)) {
        return;
    }

    foreach ($value as $key => $nestedValue) {
        if ($key === 'requires_modules') {
            if (!is_array($nestedValue)) {
                $issues[] = $context . ' contains non-list requires_modules.';
                continue;
            }

            foreach ($nestedValue as $moduleKey) {
                if (!is_string($moduleKey) || trim($moduleKey) === '') {
                    $issues[] = $context . ' contains invalid requires_modules item.';
                    continue;
                }

                moduleContractAuditRequireKnownModule(trim($moduleKey), $context . ' requires_modules', $knownModuleKeys, $issues);
            }
            continue;
        }

        moduleContractAuditCollectRequiredModulesFromValue($nestedValue, $context, $knownModuleKeys, $issues);
    }
}

$definitionsSource = moduleContractAuditReadFile($projectRoot, 'lib/definitions.php', $issues);
$statsSource = moduleContractAuditReadFile($projectRoot, 'lib/stats.php', $issues);
$settingsModulesSource = moduleContractAuditReadFile($projectRoot, 'admin/settings_modules.php', $issues);
$installSource = moduleContractAuditReadFile($projectRoot, 'install.php', $issues);
$migrateSource = moduleContractAuditReadFile($projectRoot, 'migrate.php', $issues);
$composerSource = moduleContractAuditReadFile($projectRoot, 'composer.json', $issues);
$runtimeAuditSource = moduleContractAuditReadFile($projectRoot, 'build/runtime_audit.php', $issues);
$developerModulesDocSource = moduleContractAuditReadFile($projectRoot, 'docs/developer-modules.md', $issues);
$readmeSource = moduleContractAuditReadFile($projectRoot, 'README.md', $issues);
$contentReferencePickerSource = moduleContractAuditReadFile($projectRoot, 'admin/content_reference_picker.php', $issues);
$contentReferenceSearchSource = moduleContractAuditReadFile($projectRoot, 'admin/content_reference_search.php', $issues);

moduleContractAuditRequire(
    str_contains($definitionsSource, 'function coreModuleDefinitions()')
    && str_contains($definitionsSource, 'function coreModuleKeysByFlag(')
    && str_contains($definitionsSource, 'function moduleKeysForSettings()')
    && str_contains($definitionsSource, 'function moduleDefaultSettings()')
    && str_contains($definitionsSource, 'function moduleSettingsLabels()')
    && str_contains($definitionsSource, 'function moduleNavigationDefaults()')
    && str_contains($definitionsSource, 'function moduleWidgetLabel('),
    'lib/definitions.php must keep the central module manifest helper set.',
    $issues
);

foreach (moduleContractAuditExpectedKeys() as $moduleKey) {
    moduleContractAuditRequire(
        preg_match('/\'' . preg_quote($moduleKey, '/') . '\'\\s*=>\\s*\\[/', $definitionsSource) === 1,
        'core module manifest is missing module key ' . $moduleKey . '.',
        $issues
    );
}

foreach ([
    "'profile_managed'",
    "'settings_configurable'",
    "'settings_default'",
    "'public_nav'",
    "'public_nav_path'",
    "'public_nav_order'",
    "'settings_label'",
    "'widget_label'",
] as $manifestFragment) {
    moduleContractAuditRequire(
        str_contains($definitionsSource, $manifestFragment),
        'core module manifest is missing required field ' . $manifestFragment . '.',
        $issues
    );
}

moduleContractAuditRequire(
    str_contains($definitionsSource, "return coreModuleKeysByFlag('profile_managed');"),
    'siteProfileModuleKeys() must be derived from the central module manifest.',
    $issues
);

moduleContractAuditRequire(
    str_contains($settingsModulesSource, '$moduleKeys = moduleKeysForSettings();')
    && str_contains($settingsModulesSource, '$moduleLabels = moduleSettingsLabels();')
    && !str_contains($settingsModulesSource, '$moduleKeys = ['),
    'admin/settings_modules.php must derive configurable modules and labels from the central manifest.',
    $issues
);

moduleContractAuditRequire(
    str_contains($installSource, 'moduleDefaultSettings()')
    && str_contains($migrateSource, 'moduleDefaultSettings()'),
    'install.php and migrate.php must derive module_* defaults from the central manifest.',
    $issues
);

$legacyModuleDefaultPattern = '/[\'"]module_(?:'
    . implode('|', array_map(static fn (string $moduleKey): string => preg_quote($moduleKey, '/'), moduleContractAuditExpectedKeys()))
    . ')[\'"]\\s*=>/';
moduleContractAuditRequire(
    preg_match($legacyModuleDefaultPattern, $installSource) !== 1
    && preg_match($legacyModuleDefaultPattern, $migrateSource) !== 1,
    'install.php and migrate.php must not keep hard-coded module_* default lists.',
    $issues
);

moduleContractAuditRequire(
    str_contains($statsSource, 'return moduleNavigationDefaults();'),
    'lib/stats.php navModuleDefaults() must derive public module navigation from the central manifest.',
    $issues
);

$widgetsSource = moduleContractAuditReadFile($projectRoot, 'lib/widgets.php', $issues);
moduleContractAuditRequire(
    str_contains($widgetsSource, 'return moduleWidgetLabel($moduleKey);'),
    'lib/widgets.php widgetModuleDisplayName() must derive module labels from the central manifest.',
    $issues
);

$knownModuleKeys = moduleContractAuditExpectedKeys();
if (preg_match_all('/[\'"]requires_module[\'"]\\s*=>\\s*[\'"]([a-z][a-z0-9_]*)[\'"]/', $widgetsSource, $widgetRequiresMatches) === false) {
    $issues[] = 'lib/widgets.php requires_module references cannot be parsed.';
} else {
    foreach ($widgetRequiresMatches[1] as $moduleKey) {
        moduleContractAuditRequireKnownModule($moduleKey, 'lib/widgets.php requires_module', $knownModuleKeys, $issues);
    }
}
moduleContractAuditCollectThemeRequiredModules($projectRoot, $issues);
moduleContractAuditValidateModuleGateReferences($contentReferencePickerSource, 'admin/content_reference_picker.php', $knownModuleKeys, $issues);
moduleContractAuditValidateModuleGateReferences($contentReferenceSearchSource, 'admin/content_reference_search.php', $knownModuleKeys, $issues);

foreach ([
    '"test:module-contract"',
    '"test:module-contract-selftest"',
    '@test:module-contract',
    '@test:module-contract-selftest',
] as $composerFragment) {
    moduleContractAuditRequire(
        str_contains($composerSource, $composerFragment),
        'composer.json is missing module contract script wiring: ' . $composerFragment . '.',
        $issues
    );
}

foreach ([
    'build/module_contract_audit.php',
    'build/module_contract_audit_selftest.php',
] as $buildFileFragment) {
    moduleContractAuditRequire(
        str_contains($composerSource, $buildFileFragment),
        'composer.json must keep module contract audit files under static checks: ' . $buildFileFragment . '.',
        $issues
    );
}

moduleContractAuditRequire(
    str_contains($runtimeAuditSource, 'module_contract_audit.php')
    && str_contains($runtimeAuditSource, 'module_contract_audit_selftest.php')
    && str_contains($runtimeAuditSource, 'coreModuleDefinitions'),
    'build/runtime_audit.php must keep guardrails for module contract audit wiring.',
    $issues
);

moduleContractAuditRequire(
    str_contains($developerModulesDocSource, 'coreModuleDefinitions()')
    && str_contains($developerModulesDocSource, 'build/module_contract_audit.php')
    && str_contains($readmeSource, 'composer ci:module-ready'),
    'module development documentation must describe the manifest and module contract audit.',
    $issues
);

if ($issues !== []) {
    fwrite(STDERR, "Module contract audit failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

echo "Module contract audit OK\n";
