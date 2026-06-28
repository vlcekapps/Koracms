<?php

declare(strict_types=1);

$projectRootArgument = $argv[1] ?? null;
$projectRoot = moduleContractAuditProjectRoot(is_string($projectRootArgument) ? $projectRootArgument : null);
/** @var list<string> $issues */
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
 * @param list<string> $knownModuleKeys
 * @param list<string> $issues
 */
function moduleContractAuditValidateModuleSettingReferences(string $source, string $relativePath, array $knownModuleKeys, array &$issues): void
{
    if (preg_match_all('/\b(?:getSetting|saveSetting)\(\s*[\'"]module_([a-z][a-z0-9_]*)[\'"]/', $source, $matches) === false) {
        $issues[] = $relativePath . ' module_* setting references cannot be parsed.';
        return;
    }

    foreach ($matches[1] as $moduleKey) {
        moduleContractAuditRequireKnownModule($moduleKey, $relativePath . ' module_* setting', $knownModuleKeys, $issues);
    }
}

function moduleContractAuditRelativePath(string $projectRoot, string $path): string
{
    $prefix = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (str_starts_with($path, $prefix)) {
        return str_replace('\\', '/', substr($path, strlen($prefix)));
    }

    return str_replace('\\', '/', $path);
}

/**
 * @return list<string>
 */
function moduleContractAuditApplicationPhpFiles(string $projectRoot): array
{
    $files = [];
    $rootMatches = glob($projectRoot . DIRECTORY_SEPARATOR . '*.php');
    if (is_array($rootMatches)) {
        foreach ($rootMatches as $path) {
            if (is_file($path)) {
                $files[] = $path;
            }
        }
    }

    foreach ([
        'admin',
        'authors',
        'blog',
        'board',
        'chat',
        'contact',
        'downloads',
        'events',
        'faq',
        'food',
        'forms',
        'gallery',
        'lib',
        'media',
        'news',
        'places',
        'podcast',
        'polls',
        'reservations',
        'themes',
    ] as $directoryName) {
        $directory = $projectRoot . DIRECTORY_SEPARATOR . $directoryName;
        if (!is_dir($directory)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            if (strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }

            $files[] = $fileInfo->getPathname();
        }
    }

    $files = array_values(array_unique($files));
    sort($files);

    return $files;
}

/**
 * @param list<string> $knownModuleKeys
 * @param list<string> $issues
 */
function moduleContractAuditValidateApplicationModuleGateReferences(string $projectRoot, array $knownModuleKeys, array &$issues): void
{
    foreach (moduleContractAuditApplicationPhpFiles($projectRoot) as $path) {
        $source = file_get_contents($path);
        $relativePath = moduleContractAuditRelativePath($projectRoot, $path);
        if (!is_string($source)) {
            $issues[] = $relativePath . ' cannot be read for isModuleEnabled audit.';
            continue;
        }

        moduleContractAuditValidateModuleGateReferences($source, $relativePath, $knownModuleKeys, $issues);
    }
}

/**
 * @param list<string> $knownModuleKeys
 * @param list<string> $issues
 */
function moduleContractAuditValidateApplicationModuleSettingReferences(string $projectRoot, array $knownModuleKeys, array &$issues): void
{
    foreach (moduleContractAuditApplicationPhpFiles($projectRoot) as $path) {
        $source = file_get_contents($path);
        $relativePath = moduleContractAuditRelativePath($projectRoot, $path);
        if (!is_string($source)) {
            $issues[] = $relativePath . ' cannot be read for module_* setting audit.';
            continue;
        }

        moduleContractAuditValidateModuleSettingReferences($source, $relativePath, $knownModuleKeys, $issues);
    }
}

function moduleContractAuditFindMatchingBracket(string $source, int $openPosition): ?int
{
    $depth = 0;
    $quote = null;
    $length = strlen($source);

    for ($position = $openPosition; $position < $length; $position++) {
        $character = $source[$position];
        if ($quote !== null) {
            if ($character === '\\') {
                $position++;
                continue;
            }
            if ($character === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($character === '\'' || $character === '"') {
            $quote = $character;
            continue;
        }

        if ($character === '[') {
            $depth++;
            continue;
        }

        if ($character === ']') {
            $depth--;
            if ($depth === 0) {
                return $position;
            }
        }
    }

    return null;
}

/**
 * @param list<string> $issues
 * @return array<string,string>
 */
function moduleContractAuditExtractManifestBlocks(string $definitionsSource, array &$issues): array
{
    $start = strpos($definitionsSource, 'function coreModuleDefinitions()');
    $end = $start === false ? false : strpos($definitionsSource, 'function coreModuleKeysByFlag', $start);
    if ($start === false || $end === false) {
        $issues[] = 'core module manifest function boundaries cannot be parsed.';
        return [];
    }

    $manifestSource = substr($definitionsSource, $start, $end - $start);
    $blocks = [];
    $offset = 0;
    while (preg_match('/\'([a-z][a-z0-9_]*)\'\s*=>\s*\[/', $manifestSource, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
        $moduleKey = (string)$matches[1][0];
        $matchText = (string)$matches[0][0];
        $openPosition = (int)$matches[0][1] + strlen($matchText) - 1;
        $closePosition = moduleContractAuditFindMatchingBracket($manifestSource, $openPosition);
        if ($closePosition === null) {
            $issues[] = 'core module manifest entry ' . $moduleKey . ' cannot be parsed.';
            break;
        }
        $block = substr($manifestSource, $openPosition + 1, $closePosition - $openPosition - 1);
        if (isset($blocks[$moduleKey])) {
            $issues[] = 'core module manifest contains duplicate module key ' . $moduleKey . '.';
            $offset = $closePosition + 1;
            continue;
        }

        $blocks[$moduleKey] = $block;
        $offset = $closePosition + 1;
    }

    if ($blocks === []) {
        $issues[] = 'core module manifest contains no parseable module entries.';
    }

    return $blocks;
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditManifestStringField(string $block, string $moduleKey, string $field, array &$issues): string
{
    $pattern = '/\'' . preg_quote($field, '/') . '\'\\s*=>\\s*\'([^\']*)\'/';
    if (preg_match($pattern, $block, $matches) !== 1) {
        $issues[] = 'core module manifest entry ' . $moduleKey . ' is missing string field ' . $field . '.';
        return '';
    }

    return (string)$matches[1];
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditManifestBoolField(string $block, string $moduleKey, string $field, array &$issues): ?bool
{
    $pattern = '/\'' . preg_quote($field, '/') . '\'\\s*=>\\s*(true|false)\\b/';
    if (preg_match($pattern, $block, $matches) !== 1) {
        $issues[] = 'core module manifest entry ' . $moduleKey . ' is missing boolean field ' . $field . '.';
        return null;
    }

    return $matches[1] === 'true';
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditManifestIntField(string $block, string $moduleKey, string $field, array &$issues): ?int
{
    $pattern = '/\'' . preg_quote($field, '/') . '\'\\s*=>\\s*(-?\\d+)/';
    if (preg_match($pattern, $block, $matches) !== 1) {
        $issues[] = 'core module manifest entry ' . $moduleKey . ' is missing integer field ' . $field . '.';
        return null;
    }

    return (int)$matches[1];
}

/**
 * @param list<string> $issues
 * @return list<string>
 */
function moduleContractAuditManifestStringListField(string $block, string $moduleKey, string $field, array &$issues): array
{
    $pattern = '/\'' . preg_quote($field, '/') . '\'\\s*=>\\s*\\[(.*?)\\]/s';
    if (preg_match($pattern, $block, $matches) !== 1) {
        $issues[] = 'core module manifest entry ' . $moduleKey . ' is missing list field ' . $field . '.';
        return [];
    }

    $itemsSource = (string)$matches[1];
    if (preg_match_all('/\'([^\']*)\'/', $itemsSource, $itemMatches) === false) {
        $issues[] = 'core module manifest entry ' . $moduleKey . ' list field ' . $field . ' cannot be parsed.';
        return [];
    }

    return array_map('strval', $itemMatches[1]);
}

function moduleContractAuditRootedPhpTargetExists(string $projectRoot, string $path): bool
{
    if (!str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, "\0") || !str_ends_with($path, '.php')) {
        return false;
    }

    $relativePath = str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
    return is_file($projectRoot . DIRECTORY_SEPARATOR . $relativePath);
}

function moduleContractAuditPublicNavTargetExists(string $projectRoot, string $publicNavPath): bool
{
    return moduleContractAuditRootedPhpTargetExists($projectRoot, $publicNavPath);
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidatePublicNavHttpIntegration(string $definitionsSource, string $httpIntegrationSource, array &$issues): void
{
    $hasPublicNavigationModule = false;
    foreach (moduleContractAuditExtractManifestBlocks($definitionsSource, $issues) as $moduleKey => $block) {
        $publicNav = moduleContractAuditManifestBoolField($block, $moduleKey, 'public_nav', $issues);
        if ($publicNav === true) {
            $hasPublicNavigationModule = true;
            break;
        }
    }

    if (!$hasPublicNavigationModule) {
        return;
    }

    moduleContractAuditRequire(
        str_contains($httpIntegrationSource, "httpIntegrationPrintResult('public_module_navigation_http'")
        && str_contains($httpIntegrationSource, 'moduleNavigationDefaults()')
        && str_contains($httpIntegrationSource, "saveSetting('module_' . \$moduleKey, '0')")
        && str_contains($httpIntegrationSource, "saveSetting('module_' . \$moduleKey, '1')")
        && str_contains($httpIntegrationSource, "responseHasLocationHeader(\$disabledModuleResponse['headers'], BASE_URL . '/index.php', \$baseUrl)")
        && str_contains($httpIntegrationSource, 'veřejný modul ')
        && str_contains($httpIntegrationSource, 'Tento modul není povolen'),
        'public_nav modules must be covered by dynamic public_module_navigation_http integration.',
        $issues
    );
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidateAdminHttpIntegration(string $definitionsSource, string $httpIntegrationSource, array &$issues): void
{
    $hasAdminEntryPoint = false;
    foreach (moduleContractAuditExtractManifestBlocks($definitionsSource, $issues) as $moduleKey => $block) {
        $adminPaths = moduleContractAuditManifestStringListField($block, $moduleKey, 'admin_paths', $issues);
        if ($adminPaths !== []) {
            $hasAdminEntryPoint = true;
            break;
        }
    }

    if (!$hasAdminEntryPoint) {
        return;
    }

    moduleContractAuditRequire(
        str_contains($httpIntegrationSource, "httpIntegrationPrintResult('admin_disabled_modules_http'")
        && str_contains($httpIntegrationSource, 'moduleAdminEntryPoints()')
        && str_contains($httpIntegrationSource, "saveSetting('module_' . \$moduleKey, '0')")
        && str_contains($httpIntegrationSource, 'admin stránka vypnutého modulu')
        && str_contains($httpIntegrationSource, 'není povolen'),
        'admin_paths modules must be covered by dynamic admin_disabled_modules_http integration.',
        $issues
    );
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidatePublicNavEntryPointGates(string $projectRoot, string $definitionsSource, array &$issues): void
{
    foreach (moduleContractAuditExtractManifestBlocks($definitionsSource, $issues) as $moduleKey => $block) {
        $publicNav = moduleContractAuditManifestBoolField($block, $moduleKey, 'public_nav', $issues);
        if ($publicNav !== true) {
            continue;
        }

        $publicNavPath = moduleContractAuditManifestStringField($block, $moduleKey, 'public_nav_path', $issues);
        if (!str_starts_with($publicNavPath, '/') || str_contains($publicNavPath, '..') || str_contains($publicNavPath, "\0")) {
            continue;
        }

        $relativePath = ltrim($publicNavPath, '/');
        $source = moduleContractAuditReadFile($projectRoot, $relativePath, $issues);
        if ($source === '') {
            continue;
        }

        moduleContractAuditRequire(
            preg_match('/\bisModuleEnabled\(\s*[\'"]' . preg_quote($moduleKey, '/') . '[\'"]\s*\)/', $source) === 1,
            'public_nav entrypoint ' . $relativePath . ' must guard access with isModuleEnabled(\'' . $moduleKey . '\').',
            $issues
        );
    }
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidateAdminEntryPointGates(string $projectRoot, string $definitionsSource, array &$issues): void
{
    $seenAdminPaths = [];
    foreach (moduleContractAuditExtractManifestBlocks($definitionsSource, $issues) as $moduleKey => $block) {
        $adminPaths = moduleContractAuditManifestStringListField($block, $moduleKey, 'admin_paths', $issues);
        if ($adminPaths === []) {
            $issues[] = 'core module manifest entry ' . $moduleKey . ' must define at least one admin_paths entry.';
            continue;
        }

        foreach ($adminPaths as $adminPath) {
            if (!str_starts_with($adminPath, '/admin/')) {
                $issues[] = 'admin_paths entry ' . $moduleKey . ' must start with /admin/: ' . $adminPath . '.';
                continue;
            }
            if (str_contains($adminPath, '..') || str_contains($adminPath, "\0")) {
                $issues[] = 'admin_paths entry ' . $moduleKey . ' must not contain traversal segments: ' . $adminPath . '.';
                continue;
            }
            if (isset($seenAdminPaths[$adminPath])) {
                $issues[] = 'admin_paths entry ' . $adminPath . ' is duplicated by modules ' . $seenAdminPaths[$adminPath] . ' and ' . $moduleKey . '.';
                continue;
            }
            $seenAdminPaths[$adminPath] = $moduleKey;

            if (!moduleContractAuditRootedPhpTargetExists($projectRoot, $adminPath)) {
                $issues[] = 'admin_paths entry ' . $moduleKey . ' must point to an existing PHP entrypoint: ' . $adminPath . '.';
                continue;
            }

            $source = moduleContractAuditReadFile($projectRoot, ltrim($adminPath, '/'), $issues);
            if ($source === '') {
                continue;
            }

            moduleContractAuditRequire(
                preg_match('/\brequireModuleEnabled\(\s*[\'"]' . preg_quote($moduleKey, '/') . '[\'"]/', $source) === 1,
                'admin_paths entrypoint ' . ltrim($adminPath, '/') . ' must guard access with requireModuleEnabled(\'' . $moduleKey . '\').',
                $issues
            );
        }
    }
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidateManifestValues(string $projectRoot, string $definitionsSource, array &$issues): void
{
    $knownModuleKeys = moduleContractAuditExpectedKeys();
    $blocks = moduleContractAuditExtractManifestBlocks($definitionsSource, $issues);
    $publicNavOrders = [];

    foreach (array_keys($blocks) as $moduleKey) {
        moduleContractAuditRequireKnownModule($moduleKey, 'core module manifest', $knownModuleKeys, $issues);
    }

    foreach ($knownModuleKeys as $moduleKey) {
        if (!isset($blocks[$moduleKey])) {
            continue;
        }

        $block = $blocks[$moduleKey];
        $label = trim(moduleContractAuditManifestStringField($block, $moduleKey, 'label', $issues));
        $settingsLabel = trim(moduleContractAuditManifestStringField($block, $moduleKey, 'settings_label', $issues));
        $navLabel = trim(moduleContractAuditManifestStringField($block, $moduleKey, 'nav_label', $issues));
        $settingsDefault = moduleContractAuditManifestStringField($block, $moduleKey, 'settings_default', $issues);
        $publicNavPath = moduleContractAuditManifestStringField($block, $moduleKey, 'public_nav_path', $issues);
        $publicNavOrder = moduleContractAuditManifestIntField($block, $moduleKey, 'public_nav_order', $issues);
        $adminPaths = moduleContractAuditManifestStringListField($block, $moduleKey, 'admin_paths', $issues);
        $profileManaged = moduleContractAuditManifestBoolField($block, $moduleKey, 'profile_managed', $issues);
        $settingsConfigurable = moduleContractAuditManifestBoolField($block, $moduleKey, 'settings_configurable', $issues);
        $publicNav = moduleContractAuditManifestBoolField($block, $moduleKey, 'public_nav', $issues);

        moduleContractAuditRequire($label !== '', 'core module manifest entry ' . $moduleKey . ' must define a non-empty label.', $issues);
        moduleContractAuditRequire(in_array($settingsDefault, ['0', '1'], true), 'core module manifest entry ' . $moduleKey . ' settings_default must be 0 or 1.', $issues);
        moduleContractAuditRequire($adminPaths !== [], 'core module manifest entry ' . $moduleKey . ' must define at least one admin_paths entry.', $issues);

        if ($settingsConfigurable === true) {
            moduleContractAuditRequire($settingsLabel !== '', 'settings-configurable module ' . $moduleKey . ' must define a non-empty settings_label.', $issues);
        }

        if ($profileManaged === false && $moduleKey !== 'statistics') {
            $issues[] = 'only statistics is expected to be unmanaged by site profiles; check profile_managed for ' . $moduleKey . '.';
        }

        if ($publicNav === true) {
            moduleContractAuditRequire(str_starts_with($publicNavPath, '/'), 'public_nav module ' . $moduleKey . ' must define a rooted public_nav_path.', $issues);
            moduleContractAuditRequire(!str_contains($publicNavPath, '..'), 'public_nav module ' . $moduleKey . ' public_nav_path must not contain traversal segments.', $issues);
            moduleContractAuditRequire($publicNavOrder !== null && $publicNavOrder > 0, 'public_nav module ' . $moduleKey . ' must define a positive public_nav_order.', $issues);
            moduleContractAuditRequire($navLabel !== '' || $moduleKey === 'board', 'public_nav module ' . $moduleKey . ' must define a non-empty nav_label.', $issues);
            moduleContractAuditRequire(moduleContractAuditPublicNavTargetExists($projectRoot, $publicNavPath), 'public_nav module ' . $moduleKey . ' must point to an existing PHP entrypoint.', $issues);

            if ($publicNavOrder !== null && $publicNavOrder > 0) {
                if (isset($publicNavOrders[$publicNavOrder])) {
                    $issues[] = 'public_nav_order ' . $publicNavOrder . ' is duplicated by modules ' . $publicNavOrders[$publicNavOrder] . ' and ' . $moduleKey . '.';
                } else {
                    $publicNavOrders[$publicNavOrder] = $moduleKey;
                }
            }
        } elseif ($publicNav === false) {
            moduleContractAuditRequire($publicNavPath === '', 'non-public module ' . $moduleKey . ' must keep public_nav_path empty.', $issues);
            moduleContractAuditRequire($publicNavOrder === 0, 'non-public module ' . $moduleKey . ' must keep public_nav_order at 0.', $issues);
        }
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
$httpIntegrationSource = moduleContractAuditReadFile($projectRoot, 'build/http_integration.php', $issues);

moduleContractAuditRequire(
    str_contains($definitionsSource, 'function coreModuleDefinitions()')
    && str_contains($definitionsSource, 'function coreModuleKeysByFlag(')
    && str_contains($definitionsSource, 'function moduleKeysForSettings()')
    && str_contains($definitionsSource, 'function moduleDefaultSettings()')
    && str_contains($definitionsSource, 'function moduleSettingsLabels()')
    && str_contains($definitionsSource, 'function moduleNavigationDefaults()')
    && str_contains($definitionsSource, 'function moduleAdminEntryPoints()')
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
    "'admin_paths'",
] as $manifestFragment) {
    moduleContractAuditRequire(
        str_contains($definitionsSource, $manifestFragment),
        'core module manifest is missing required field ' . $manifestFragment . '.',
        $issues
    );
}

moduleContractAuditValidateManifestValues($projectRoot, $definitionsSource, $issues);
moduleContractAuditValidatePublicNavHttpIntegration($definitionsSource, $httpIntegrationSource, $issues);
moduleContractAuditValidatePublicNavEntryPointGates($projectRoot, $definitionsSource, $issues);
moduleContractAuditValidateAdminHttpIntegration($definitionsSource, $httpIntegrationSource, $issues);
moduleContractAuditValidateAdminEntryPointGates($projectRoot, $definitionsSource, $issues);

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
moduleContractAuditValidateApplicationModuleGateReferences($projectRoot, $knownModuleKeys, $issues);
moduleContractAuditValidateApplicationModuleSettingReferences($projectRoot, $knownModuleKeys, $issues);

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
