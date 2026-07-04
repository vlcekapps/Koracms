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

/**
 * @param list<string> $issues
 * @return array<string,string>
 */
function moduleContractAuditManifestStringMapField(string $block, string $moduleKey, string $field, array &$issues): array
{
    $pattern = '/\'' . preg_quote($field, '/') . '\'\\s*=>\\s*\\[(.*?)\\]/s';
    if (preg_match($pattern, $block, $matches) !== 1) {
        $issues[] = 'core module manifest entry ' . $moduleKey . ' is missing map field ' . $field . '.';
        return [];
    }

    $itemsSource = trim((string)$matches[1]);
    if ($itemsSource === '') {
        return [];
    }

    $matchCount = preg_match_all('/\'([^\']*)\'\\s*=>\\s*\'([^\']*)\'/', $itemsSource, $itemMatches, PREG_SET_ORDER);
    if ($matchCount === false || $matchCount === 0) {
        $issues[] = 'core module manifest entry ' . $moduleKey . ' map field ' . $field . ' cannot be parsed.';
        return [];
    }

    $items = [];
    foreach ($itemMatches as $itemMatch) {
        $items[(string)$itemMatch[1]] = (string)$itemMatch[2];
    }

    return $items;
}

/**
 * @param list<string> $issues
 * @return array<string,string>
 */
function moduleContractAuditExtractAdminRouteRequirementBlocks(string $authSource, array &$issues): array
{
    $functionStart = strpos($authSource, 'function adminRouteModuleRequirements(');
    $requirementsStart = $functionStart === false ? false : strpos($authSource, 'return [', $functionStart);
    $openPosition = $requirementsStart === false ? false : strpos($authSource, '[', $requirementsStart);
    $closePosition = is_int($openPosition) ? moduleContractAuditFindMatchingBracket($authSource, $openPosition) : null;

    if ($functionStart === false || $requirementsStart === false || !is_int($openPosition) || $closePosition === null) {
        $issues[] = 'auth.php adminRouteModuleRequirements return map cannot be parsed.';
        return [];
    }

    $requirementsSource = substr($authSource, $openPosition + 1, $closePosition - $openPosition - 1);
    $blocks = [];
    $offset = 0;
    while (preg_match('/\'([a-z][a-z0-9_]*)\'\s*=>\s*\[/', $requirementsSource, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
        $moduleKey = (string)$matches[1][0];
        $matchText = (string)$matches[0][0];
        $entryOpenPosition = (int)$matches[0][1] + strlen($matchText) - 1;
        $entryClosePosition = moduleContractAuditFindMatchingBracket($requirementsSource, $entryOpenPosition);
        if ($entryClosePosition === null) {
            $issues[] = 'adminRouteModuleRequirement entry ' . $moduleKey . ' cannot be parsed.';
            break;
        }

        if (isset($blocks[$moduleKey])) {
            $issues[] = 'adminRouteModuleRequirement contains duplicate module key ' . $moduleKey . '.';
            $offset = $entryClosePosition + 1;
            continue;
        }

        $blocks[$moduleKey] = substr($requirementsSource, $entryOpenPosition + 1, $entryClosePosition - $entryOpenPosition - 1);
        $offset = $entryClosePosition + 1;
    }

    if ($blocks === []) {
        $issues[] = 'adminRouteModuleRequirement contains no parseable module entries.';
    }

    return $blocks;
}

/**
 * @param list<string> $issues
 * @return list<string>
 */
function moduleContractAuditAdminRouteFilesField(string $block, string $moduleKey, array &$issues): array
{
    if (preg_match('/\'files\'\\s*=>\\s*\\[(.*?)\\]/s', $block, $matches) !== 1) {
        $issues[] = 'adminRouteModuleRequirement entry ' . $moduleKey . ' is missing files list.';
        return [];
    }

    $itemsSource = (string)$matches[1];
    if (preg_match_all('/\'([^\']*)\'/', $itemsSource, $itemMatches) === false) {
        $issues[] = 'adminRouteModuleRequirement entry ' . $moduleKey . ' files list cannot be parsed.';
        return [];
    }

    $files = array_map('strval', $itemMatches[1]);
    if ($files === []) {
        $issues[] = 'adminRouteModuleRequirement entry ' . $moduleKey . ' must define at least one file.';
    }

    return $files;
}

/**
 * @param list<string> $issues
 * @return array<string,mixed>
 */
function moduleContractAuditComposerScripts(string $composerSource, array &$issues): array
{
    $composer = json_decode($composerSource, true);
    if (!is_array($composer)) {
        $issues[] = 'composer.json cannot be parsed as JSON.';
        return [];
    }

    $scripts = $composer['scripts'] ?? null;
    if (!is_array($scripts)) {
        $issues[] = 'composer.json is missing scripts object.';
        return [];
    }

    /** @var array<string,mixed> $normalizedScripts */
    $normalizedScripts = [];
    foreach ($scripts as $scriptName => $script) {
        if (is_string($scriptName)) {
            $normalizedScripts[$scriptName] = $script;
        }
    }

    return $normalizedScripts;
}

function moduleContractAuditComposerScriptText(mixed $script): string
{
    if (is_string($script)) {
        return $script;
    }

    if (!is_array($script)) {
        return '';
    }

    $parts = [];
    foreach ($script as $scriptLine) {
        if (is_string($scriptLine)) {
            $parts[] = $scriptLine;
        }
    }

    return implode(' ', $parts);
}

/**
 * @param array<string,mixed> $scripts
 */
function moduleContractAuditComposerScriptGroupText(array $scripts, string $scriptPrefix): string
{
    $parts = [];
    foreach ($scripts as $scriptName => $script) {
        if ($scriptName !== $scriptPrefix && !str_starts_with($scriptName, $scriptPrefix . ':')) {
            continue;
        }

        $scriptText = moduleContractAuditComposerScriptText($script);
        if ($scriptText !== '') {
            $parts[] = $scriptText;
        }
    }

    return implode(' ', $parts);
}

function moduleContractAuditCommandTextContainsPath(string $commandText, string $relativePath): bool
{
    return preg_match('/(?:^|\s)' . preg_quote($relativePath, '/') . '(?:\s|$)/', $commandText) === 1;
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

function moduleContractAuditPublicEntryPointExists(string $projectRoot, string $publicPath): bool
{
    return moduleContractAuditRootedPhpTargetExists($projectRoot, $publicPath);
}

/**
 * @param list<string> $issues
 * @return list<string>
 */
function moduleContractAuditKnownModuleKeys(string $definitionsSource, array &$issues): array
{
    $moduleKeys = array_keys(moduleContractAuditExtractManifestBlocks($definitionsSource, $issues));
    sort($moduleKeys);

    return $moduleKeys;
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
        && str_contains($httpIntegrationSource, "saveSetting(moduleSettingKey(\$moduleKey), '0')")
        && str_contains($httpIntegrationSource, "saveSetting(moduleSettingKey(\$moduleKey), '1')")
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
        && str_contains($httpIntegrationSource, "saveSetting(moduleSettingKey(\$moduleKey), '0')")
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
            moduleContractAuditRequire(
                preg_match('/\brequireModuleEnabled\(\s*[\'"]' . preg_quote($moduleKey, '/') . '[\'"]\s*,/', $source) !== 1,
                'admin_paths entrypoint ' . ltrim($adminPath, '/') . ' must rely on the manifest-derived requireModuleEnabled() disabled message.',
                $issues
            );
        }
    }
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidateAdminRouteModuleRequirements(
    string $projectRoot,
    string $definitionsSource,
    string $authSource,
    array &$issues
): void {
    $knownModuleKeys = moduleContractAuditKnownModuleKeys($definitionsSource, $issues);
    $routeBlocks = moduleContractAuditExtractAdminRouteRequirementBlocks($authSource, $issues);
    $routeFileModules = [];

    moduleContractAuditRequire(
        str_contains($authSource, 'function adminRouteModuleRequirements(')
        && str_contains($authSource, 'foreach (adminRouteModuleRequirements() as $moduleKey => $requirement)'),
        'adminRouteModuleRequirement() must use the shared adminRouteModuleRequirements() map.',
        $issues
    );

    foreach ($routeBlocks as $moduleKey => $block) {
        moduleContractAuditRequireKnownModule($moduleKey, 'adminRouteModuleRequirement', $knownModuleKeys, $issues);

        foreach (moduleContractAuditAdminRouteFilesField($block, $moduleKey, $issues) as $file) {
            if ($file === '' || str_contains($file, '/') || str_contains($file, '\\') || str_contains($file, '..') || str_contains($file, "\0") || !str_ends_with($file, '.php')) {
                $issues[] = 'adminRouteModuleRequirement entry ' . $moduleKey . ' contains invalid admin file name: ' . $file . '.';
                continue;
            }

            if (isset($routeFileModules[$file]) && $routeFileModules[$file] !== $moduleKey) {
                $issues[] = 'adminRouteModuleRequirement file ' . $file . ' is duplicated by modules ' . $routeFileModules[$file] . ' and ' . $moduleKey . '.';
                continue;
            }
            $routeFileModules[$file] = $moduleKey;

            $adminPath = '/admin/' . $file;
            if (!moduleContractAuditRootedPhpTargetExists($projectRoot, $adminPath)) {
                $issues[] = 'adminRouteModuleRequirement entry ' . $moduleKey . ' references missing admin PHP file: ' . $adminPath . '.';
                continue;
            }

            $source = moduleContractAuditReadFile($projectRoot, ltrim($adminPath, '/'), $issues);
            if ($source === '') {
                continue;
            }

            moduleContractAuditRequire(
                preg_match('/\b(?:requireLogin|requireSuperAdmin|requireModuleEnabled|requireCapability)\s*\(/', $source) === 1,
                'adminRouteModuleRequirement file ' . $adminPath . ' must call requireLogin(), requireSuperAdmin(), requireModuleEnabled() or requireCapability().',
                $issues
            );
            moduleContractAuditRequire(
                preg_match('/\brequireModuleEnabled\(\s*[\'"]' . preg_quote($moduleKey, '/') . '[\'"]\s*,/', $source) !== 1,
                'adminRouteModuleRequirement file ' . $adminPath . ' must rely on the manifest-derived requireModuleEnabled() disabled message.',
                $issues
            );
        }
    }

    foreach (moduleContractAuditExtractManifestBlocks($definitionsSource, $issues) as $moduleKey => $block) {
        foreach (moduleContractAuditManifestStringListField($block, $moduleKey, 'admin_paths', $issues) as $adminPath) {
            $file = basename($adminPath);
            if (($routeFileModules[$file] ?? null) !== $moduleKey) {
                $issues[] = 'admin_paths entry ' . $adminPath . ' must be listed in adminRouteModuleRequirement() for ' . $moduleKey . '.';
            }
        }
    }
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidateAdminRouteStaticCoverage(string $authSource, string $composerSource, array &$issues): void
{
    $scripts = moduleContractAuditComposerScripts($composerSource, $issues);
    if ($scripts === []) {
        return;
    }

    $strictAnalysisText = moduleContractAuditComposerScriptGroupText($scripts, 'analyse:strict');
    $formatCheckText = moduleContractAuditComposerScriptGroupText($scripts, 'format:check');

    moduleContractAuditRequire(
        $strictAnalysisText !== '',
        'composer.json must define analyse:strict scripts for module admin route coverage.',
        $issues
    );
    moduleContractAuditRequire(
        $formatCheckText !== '',
        'composer.json must define format:check scripts for module admin route coverage.',
        $issues
    );

    if ($strictAnalysisText === '' || $formatCheckText === '') {
        return;
    }

    foreach (moduleContractAuditExtractAdminRouteRequirementBlocks($authSource, $issues) as $moduleKey => $block) {
        foreach (moduleContractAuditAdminRouteFilesField($block, $moduleKey, $issues) as $file) {
            if ($file === '' || str_contains($file, '/') || str_contains($file, '\\') || str_contains($file, '..') || str_contains($file, "\0") || !str_ends_with($file, '.php')) {
                continue;
            }

            $relativePath = 'admin/' . $file;
            moduleContractAuditRequire(
                moduleContractAuditCommandTextContainsPath($strictAnalysisText, $relativePath),
                'adminRouteModuleRequirement file ' . $relativePath . ' must be covered by an analyse:strict composer script.',
                $issues
            );
            moduleContractAuditRequire(
                moduleContractAuditCommandTextContainsPath($formatCheckText, $relativePath),
                'adminRouteModuleRequirement file ' . $relativePath . ' must be covered by a format:check composer script.',
                $issues
            );
        }
    }
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidatePublicNavStaticCoverage(string $definitionsSource, string $composerSource, array &$issues): void
{
    $scripts = moduleContractAuditComposerScripts($composerSource, $issues);
    if ($scripts === []) {
        return;
    }

    $strictAnalysisText = moduleContractAuditComposerScriptGroupText($scripts, 'analyse:strict');
    $formatCheckText = moduleContractAuditComposerScriptGroupText($scripts, 'format:check');

    moduleContractAuditRequire(
        $strictAnalysisText !== '',
        'composer.json must define analyse:strict scripts for module public navigation coverage.',
        $issues
    );
    moduleContractAuditRequire(
        $formatCheckText !== '',
        'composer.json must define format:check scripts for module public navigation coverage.',
        $issues
    );

    if ($strictAnalysisText === '' || $formatCheckText === '') {
        return;
    }

    foreach (moduleContractAuditExtractManifestBlocks($definitionsSource, $issues) as $moduleKey => $block) {
        $publicNav = moduleContractAuditManifestBoolField($block, $moduleKey, 'public_nav', $issues);
        if ($publicNav !== true) {
            continue;
        }

        $publicNavPath = moduleContractAuditManifestStringField($block, $moduleKey, 'public_nav_path', $issues);
        if (!str_starts_with($publicNavPath, '/') || str_contains($publicNavPath, '..') || str_contains($publicNavPath, "\0") || !str_ends_with($publicNavPath, '.php')) {
            continue;
        }

        $relativePath = ltrim($publicNavPath, '/');
        moduleContractAuditRequire(
            moduleContractAuditCommandTextContainsPath($strictAnalysisText, $relativePath),
            'public_nav entrypoint ' . $relativePath . ' must be covered by an analyse:strict composer script.',
            $issues
        );
        moduleContractAuditRequire(
            moduleContractAuditCommandTextContainsPath($formatCheckText, $relativePath),
            'public_nav entrypoint ' . $relativePath . ' must be covered by a format:check composer script.',
            $issues
        );
    }
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidatePublicEntryPointStaticCoverage(string $definitionsSource, string $composerSource, array &$issues): void
{
    $scripts = moduleContractAuditComposerScripts($composerSource, $issues);
    if ($scripts === []) {
        return;
    }

    $strictAnalysisText = moduleContractAuditComposerScriptGroupText($scripts, 'analyse:strict');
    $formatCheckText = moduleContractAuditComposerScriptGroupText($scripts, 'format:check');

    moduleContractAuditRequire(
        $strictAnalysisText !== '',
        'composer.json must define analyse:strict scripts for module public entrypoint coverage.',
        $issues
    );
    moduleContractAuditRequire(
        $formatCheckText !== '',
        'composer.json must define format:check scripts for module public entrypoint coverage.',
        $issues
    );

    if ($strictAnalysisText === '' || $formatCheckText === '') {
        return;
    }

    foreach (moduleContractAuditExtractManifestBlocks($definitionsSource, $issues) as $moduleKey => $block) {
        foreach (moduleContractAuditManifestStringListField($block, $moduleKey, 'public_paths', $issues) as $publicPath) {
            if (!str_starts_with($publicPath, '/') || str_contains($publicPath, '..') || str_contains($publicPath, "\0") || !str_ends_with($publicPath, '.php')) {
                continue;
            }

            $relativePath = ltrim($publicPath, '/');
            moduleContractAuditRequire(
                moduleContractAuditCommandTextContainsPath($strictAnalysisText, $relativePath),
                'public module entrypoint ' . $relativePath . ' must be covered by an analyse:strict composer script.',
                $issues
            );
            moduleContractAuditRequire(
                moduleContractAuditCommandTextContainsPath($formatCheckText, $relativePath),
                'public module entrypoint ' . $relativePath . ' must be covered by a format:check composer script.',
                $issues
            );
        }
    }
}

/**
 * @param list<string> $issues
 * @return array<string,string>
 */
function moduleContractAuditContentReferenceTypeModuleMap(string $definitionsSource, array &$issues): array
{
    $map = [];
    foreach (moduleContractAuditExtractManifestBlocks($definitionsSource, $issues) as $moduleKey => $block) {
        foreach (moduleContractAuditManifestStringMapField($block, $moduleKey, 'content_reference_types', $issues) as $type => $label) {
            if ($type === '') {
                $issues[] = 'core module manifest entry ' . $moduleKey . ' content_reference_types contains empty type.';
                continue;
            }
            if (isset($map[$type])) {
                $issues[] = 'content_reference_types entry ' . $type . ' is duplicated by modules ' . $map[$type] . ' and ' . $moduleKey . '.';
                continue;
            }
            if ($label === '' && !($moduleKey === 'board' && $type === 'board')) {
                $issues[] = 'core module manifest entry ' . $moduleKey . ' content_reference_types label for ' . $type . ' must not be empty.';
            }

            $map[$type] = $moduleKey;
        }
    }

    return $map;
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidateContentReferenceTypeCoverage(
    string $definitionsSource,
    string $pickerSource,
    string $searchSource,
    array &$issues
): void {
    $typeModuleMap = moduleContractAuditContentReferenceTypeModuleMap($definitionsSource, $issues);
    $modulesWithContentReferences = array_values(array_unique(array_values($typeModuleMap)));

    moduleContractAuditRequire(
        str_contains($definitionsSource, 'function moduleContentReferenceTypeLabels(')
        && str_contains($definitionsSource, 'function contentReferenceTypeModuleMap('),
        'lib/definitions.php must expose manifest-derived content reference type helpers.',
        $issues
    );
    moduleContractAuditRequire(
        str_contains($pickerSource, 'moduleContentReferenceTypeLabels()'),
        'admin/content_reference_picker.php must derive module picker types from the central manifest.',
        $issues
    );
    moduleContractAuditRequire(
        str_contains($searchSource, 'contentReferenceTypeModuleMap()'),
        'admin/content_reference_search.php must derive allowed module picker types from the central manifest.',
        $issues
    );

    if (preg_match_all('/\bisModuleEnabled\(\s*[\'"]([a-z][a-z0-9_]*)[\'"]\s*\)/', $searchSource, $gateMatches) === false) {
        $issues[] = 'admin/content_reference_search.php isModuleEnabled references cannot be parsed for content reference coverage.';
    } else {
        foreach (array_unique($gateMatches[1]) as $moduleKey) {
            moduleContractAuditRequire(
                in_array($moduleKey, $modulesWithContentReferences, true),
                'content reference search gates module ' . $moduleKey . ' but the module manifest has no content_reference_types entry for it.',
                $issues
            );
        }
    }

    if (preg_match_all('/\$requestedType\s*===\s*[\'"]([a-z][a-z0-9_]*)[\'"]/', $searchSource, $typeMatches) === false) {
        $issues[] = 'admin/content_reference_search.php requestedType checks cannot be parsed.';
    } else {
        foreach (array_unique($typeMatches[1]) as $type) {
            if (in_array($type, ['all', 'page', 'media'], true)) {
                continue;
            }
            moduleContractAuditRequire(
                isset($typeModuleMap[$type]),
                'content reference search checks request type ' . $type . ' but no module manifest content_reference_types entry defines it.',
                $issues
            );
        }
    }
}

/**
 * @param list<string> $issues
 * @return array<string,string>
 */
function moduleContractAuditSearchResultTypeModuleMap(string $definitionsSource, array &$issues): array
{
    $map = [];
    foreach (moduleContractAuditExtractManifestBlocks($definitionsSource, $issues) as $moduleKey => $block) {
        foreach (moduleContractAuditManifestStringMapField($block, $moduleKey, 'search_result_types', $issues) as $type => $label) {
            if ($type === '') {
                $issues[] = 'core module manifest entry ' . $moduleKey . ' search_result_types contains empty type.';
                continue;
            }
            if (preg_match('/^[a-z][a-z0-9_]*$/', $type) !== 1) {
                $issues[] = 'core module manifest entry ' . $moduleKey . ' contains invalid search_result_types key ' . $type . '.';
                continue;
            }
            if (isset($map[$type])) {
                $issues[] = 'search_result_types entry ' . $type . ' is duplicated by modules ' . $map[$type] . ' and ' . $moduleKey . '.';
                continue;
            }
            if ($label === '' && !($moduleKey === 'board' && $type === 'board')) {
                $issues[] = 'core module manifest entry ' . $moduleKey . ' search_result_types label for ' . $type . ' must not be empty.';
            }

            $map[$type] = $moduleKey;
        }
    }

    return $map;
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidateSearchResultTypeCoverage(
    string $definitionsSource,
    string $searchSource,
    array &$issues
): void {
    $typeModuleMap = moduleContractAuditSearchResultTypeModuleMap($definitionsSource, $issues);
    $modulesWithSearchResults = array_values(array_unique(array_values($typeModuleMap)));

    moduleContractAuditRequire(
        str_contains($definitionsSource, 'function moduleSearchResultTypeLabels(')
        && str_contains($definitionsSource, 'function searchResultTypeModuleMap('),
        'lib/definitions.php must expose manifest-derived search result type helpers.',
        $issues
    );
    moduleContractAuditRequire(
        str_contains($searchSource, 'moduleSearchResultTypeLabels()'),
        'search.php must derive search result labels from the central module manifest.',
        $issues
    );

    if (preg_match_all('/\bisModuleEnabled\(\s*[\'"]([a-z][a-z0-9_]*)[\'"]\s*\)/', $searchSource, $gateMatches) === false) {
        $issues[] = 'search.php isModuleEnabled references cannot be parsed for search result coverage.';
    } else {
        foreach (array_unique($gateMatches[1]) as $moduleKey) {
            moduleContractAuditRequire(
                in_array($moduleKey, $modulesWithSearchResults, true),
                'search.php gates module ' . $moduleKey . ' but the module manifest has no search_result_types entry for it.',
                $issues
            );
        }
    }

    if (preg_match_all('/[\'"]([a-z][a-z0-9_]*)[\'"]\s+AS\s+type\b/i', $searchSource, $typeMatches) === false) {
        $issues[] = 'search.php result type literals cannot be parsed.';
    } else {
        $searchTypeLiterals = array_values(array_unique($typeMatches[1]));
        foreach ($searchTypeLiterals as $type) {
            if ($type === 'page') {
                continue;
            }
            moduleContractAuditRequire(
                isset($typeModuleMap[$type]),
                'search.php returns result type ' . $type . ' but no module manifest search_result_types entry defines it.',
                $issues
            );
        }
        foreach (array_keys($typeModuleMap) as $type) {
            moduleContractAuditRequire(
                in_array($type, $searchTypeLiterals, true),
                'module manifest search_result_types entry ' . $type . ' is not returned by search.php.',
                $issues
            );
        }
    }

    $resultUrlPosition = strpos($searchSource, 'function resultUrl(');
    $typeLabelPosition = strpos($searchSource, 'function typeLabel(');
    if (!is_int($resultUrlPosition) || !is_int($typeLabelPosition) || $typeLabelPosition <= $resultUrlPosition) {
        $issues[] = 'search.php resultUrl() block cannot be parsed for search result URL coverage.';
        return;
    }

    $resultUrlBlock = substr($searchSource, $resultUrlPosition, $typeLabelPosition - $resultUrlPosition);
    if (preg_match_all('/[\'"]([a-z][a-z0-9_]*)[\'"]\s*=>/', $resultUrlBlock, $urlMatches) === false) {
        $issues[] = 'search.php resultUrl() result types cannot be parsed.';
        return;
    }

    $urlTypes = array_values(array_unique($urlMatches[1]));
    foreach (array_keys($typeModuleMap) as $type) {
        moduleContractAuditRequire(
            in_array($type, $urlTypes, true),
            'search result type ' . $type . ' must be handled by search.php resultUrl().',
            $issues
        );
    }
    moduleContractAuditRequire(
        in_array('page', $urlTypes, true),
        'search.php resultUrl() must keep the non-module page result type.',
        $issues
    );
}

/**
 * @param list<string> $issues
 * @return array<string,string>
 */
function moduleContractAuditSitemapSectionModuleMap(string $definitionsSource, array &$issues): array
{
    $map = [];
    foreach (moduleContractAuditExtractManifestBlocks($definitionsSource, $issues) as $moduleKey => $block) {
        foreach (moduleContractAuditManifestStringMapField($block, $moduleKey, 'sitemap_sections', $issues) as $section => $label) {
            if ($section === '') {
                $issues[] = 'core module manifest entry ' . $moduleKey . ' sitemap_sections contains empty section.';
                continue;
            }
            if (preg_match('/^[a-z][a-z0-9_]*$/', $section) !== 1) {
                $issues[] = 'core module manifest entry ' . $moduleKey . ' contains invalid sitemap_sections key ' . $section . '.';
                continue;
            }
            if (isset($map[$section])) {
                $issues[] = 'sitemap_sections entry ' . $section . ' is duplicated by modules ' . $map[$section] . ' and ' . $moduleKey . '.';
                continue;
            }
            if ($label === '' && !($moduleKey === 'board' && $section === 'board')) {
                $issues[] = 'core module manifest entry ' . $moduleKey . ' sitemap_sections label for ' . $section . ' must not be empty.';
            }

            $map[$section] = $moduleKey;
        }
    }

    return $map;
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidateSitemapSectionCoverage(
    string $definitionsSource,
    string $sitemapSource,
    array &$issues
): void {
    $sectionModuleMap = moduleContractAuditSitemapSectionModuleMap($definitionsSource, $issues);
    $modulesWithSitemapSections = array_values(array_unique(array_values($sectionModuleMap)));

    moduleContractAuditRequire(
        str_contains($definitionsSource, 'function moduleSitemapSections(')
        && str_contains($definitionsSource, 'function sitemapSectionModuleMap('),
        'lib/definitions.php must expose manifest-derived sitemap section helpers.',
        $issues
    );

    if (preg_match_all('/\bisModuleEnabled\(\s*[\'"]([a-z][a-z0-9_]*)[\'"]\s*\)/', $sitemapSource, $gateMatches) === false) {
        $issues[] = 'sitemap.php isModuleEnabled references cannot be parsed for sitemap section coverage.';
    } else {
        foreach (array_unique($gateMatches[1]) as $moduleKey) {
            moduleContractAuditRequire(
                in_array($moduleKey, $modulesWithSitemapSections, true),
                'sitemap.php gates module ' . $moduleKey . ' but the module manifest has no sitemap_sections entry for it.',
                $issues
            );
        }
    }

    if (preg_match_all('/\bsitemapLogSectionError\(\s*[\'"]([a-z][a-z0-9_]*)[\'"]/', $sitemapSource, $sectionMatches) === false) {
        $issues[] = 'sitemap.php section labels cannot be parsed.';
        return;
    }

    $loggedSections = array_values(array_unique($sectionMatches[1]));
    foreach ($loggedSections as $section) {
        if (in_array($section, ['pages', 'authors'], true)) {
            continue;
        }
        moduleContractAuditRequire(
            isset($sectionModuleMap[$section]),
            'sitemap.php logs section ' . $section . ' but no module manifest sitemap_sections entry defines it.',
            $issues
        );
    }
    foreach (array_keys($sectionModuleMap) as $section) {
        moduleContractAuditRequire(
            in_array($section, $loggedSections, true),
            'module manifest sitemap_sections entry ' . $section . ' is not logged by sitemap.php.',
            $issues
        );
    }
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidateManifestValues(string $projectRoot, string $definitionsSource, array &$issues): void
{
    $requiredCoreModuleKeys = moduleContractAuditRequiredCoreModuleKeys();
    $blocks = moduleContractAuditExtractManifestBlocks($definitionsSource, $issues);
    $publicNavOrders = [];
    $publicPathModules = [];
    $contentReferenceTypeModules = [];
    $searchResultTypeModules = [];
    $sitemapSectionModules = [];
    $statsPageTypeModules = [];

    foreach ($requiredCoreModuleKeys as $moduleKey) {
        if (!isset($blocks[$moduleKey])) {
            $issues[] = 'core module manifest is missing required built-in module key ' . $moduleKey . '.';
            continue;
        }
    }

    foreach ($blocks as $moduleKey => $block) {
        $label = trim(moduleContractAuditManifestStringField($block, $moduleKey, 'label', $issues));
        $settingsLabel = trim(moduleContractAuditManifestStringField($block, $moduleKey, 'settings_label', $issues));
        $navLabel = trim(moduleContractAuditManifestStringField($block, $moduleKey, 'nav_label', $issues));
        $adminLabel = trim(moduleContractAuditManifestStringField($block, $moduleKey, 'admin_label', $issues));
        $adminCapability = trim(moduleContractAuditManifestStringField($block, $moduleKey, 'admin_capability', $issues));
        $contentReferenceTypes = moduleContractAuditManifestStringMapField($block, $moduleKey, 'content_reference_types', $issues);
        $searchResultTypes = moduleContractAuditManifestStringMapField($block, $moduleKey, 'search_result_types', $issues);
        $sitemapSections = moduleContractAuditManifestStringMapField($block, $moduleKey, 'sitemap_sections', $issues);
        $statsPageTypes = moduleContractAuditManifestStringListField($block, $moduleKey, 'stats_page_types', $issues);
        $settingsDefault = moduleContractAuditManifestStringField($block, $moduleKey, 'settings_default', $issues);
        $publicNavPath = moduleContractAuditManifestStringField($block, $moduleKey, 'public_nav_path', $issues);
        $publicPaths = moduleContractAuditManifestStringListField($block, $moduleKey, 'public_paths', $issues);
        $publicNavOrder = moduleContractAuditManifestIntField($block, $moduleKey, 'public_nav_order', $issues);
        $adminPaths = moduleContractAuditManifestStringListField($block, $moduleKey, 'admin_paths', $issues);
        $profileManaged = moduleContractAuditManifestBoolField($block, $moduleKey, 'profile_managed', $issues);
        $settingsConfigurable = moduleContractAuditManifestBoolField($block, $moduleKey, 'settings_configurable', $issues);
        $publicNav = moduleContractAuditManifestBoolField($block, $moduleKey, 'public_nav', $issues);

        moduleContractAuditRequire($label !== '', 'core module manifest entry ' . $moduleKey . ' must define a non-empty label.', $issues);
        moduleContractAuditRequire($adminLabel !== '', 'core module manifest entry ' . $moduleKey . ' must define a non-empty admin_label.', $issues);
        moduleContractAuditRequire(
            preg_match('/^[a-z][a-z0-9_]*$/', $adminCapability) === 1,
            'core module manifest entry ' . $moduleKey . ' must define a valid admin_capability.',
            $issues
        );
        foreach ($contentReferenceTypes as $contentReferenceType => $contentReferenceLabel) {
            moduleContractAuditRequire(
                preg_match('/^[a-z][a-z0-9_]*$/', $contentReferenceType) === 1,
                'core module manifest entry ' . $moduleKey . ' contains invalid content_reference_types key ' . $contentReferenceType . '.',
                $issues
            );
            moduleContractAuditRequire(
                trim($contentReferenceLabel) !== '' || $moduleKey === 'board',
                'core module manifest entry ' . $moduleKey . ' must define a non-empty content_reference_types label for ' . $contentReferenceType . '.',
                $issues
            );
            if (isset($contentReferenceTypeModules[$contentReferenceType]) && $contentReferenceTypeModules[$contentReferenceType] !== $moduleKey) {
                $issues[] = 'content_reference_types key ' . $contentReferenceType . ' is duplicated by modules ' . $contentReferenceTypeModules[$contentReferenceType] . ' and ' . $moduleKey . '.';
            } else {
                $contentReferenceTypeModules[$contentReferenceType] = $moduleKey;
            }
        }
        foreach ($searchResultTypes as $searchResultType => $searchResultLabel) {
            moduleContractAuditRequire(
                preg_match('/^[a-z][a-z0-9_]*$/', $searchResultType) === 1,
                'core module manifest entry ' . $moduleKey . ' contains invalid search_result_types key ' . $searchResultType . '.',
                $issues
            );
            moduleContractAuditRequire(
                trim($searchResultLabel) !== '' || $moduleKey === 'board',
                'core module manifest entry ' . $moduleKey . ' must define a non-empty search_result_types label for ' . $searchResultType . '.',
                $issues
            );
            if (isset($searchResultTypeModules[$searchResultType]) && $searchResultTypeModules[$searchResultType] !== $moduleKey) {
                $issues[] = 'search_result_types key ' . $searchResultType . ' is duplicated by modules ' . $searchResultTypeModules[$searchResultType] . ' and ' . $moduleKey . '.';
            } else {
                $searchResultTypeModules[$searchResultType] = $moduleKey;
            }
        }
        foreach ($sitemapSections as $sitemapSection => $sitemapSectionLabel) {
            moduleContractAuditRequire(
                preg_match('/^[a-z][a-z0-9_]*$/', $sitemapSection) === 1,
                'core module manifest entry ' . $moduleKey . ' contains invalid sitemap_sections key ' . $sitemapSection . '.',
                $issues
            );
            moduleContractAuditRequire(
                trim($sitemapSectionLabel) !== '' || $moduleKey === 'board',
                'core module manifest entry ' . $moduleKey . ' must define a non-empty sitemap_sections label for ' . $sitemapSection . '.',
                $issues
            );
            if (isset($sitemapSectionModules[$sitemapSection]) && $sitemapSectionModules[$sitemapSection] !== $moduleKey) {
                $issues[] = 'sitemap_sections key ' . $sitemapSection . ' is duplicated by modules ' . $sitemapSectionModules[$sitemapSection] . ' and ' . $moduleKey . '.';
            } else {
                $sitemapSectionModules[$sitemapSection] = $moduleKey;
            }
        }
        foreach ($statsPageTypes as $statsPageType) {
            moduleContractAuditRequire(
                preg_match('/^[a-z][a-z0-9_]*$/', $statsPageType) === 1,
                'core module manifest entry ' . $moduleKey . ' contains invalid stats_page_types key ' . $statsPageType . '.',
                $issues
            );
            if (isset($statsPageTypeModules[$statsPageType]) && $statsPageTypeModules[$statsPageType] !== $moduleKey) {
                $issues[] = 'stats_page_types key ' . $statsPageType . ' is duplicated by modules ' . $statsPageTypeModules[$statsPageType] . ' and ' . $moduleKey . '.';
            } else {
                $statsPageTypeModules[$statsPageType] = $moduleKey;
            }
        }
        moduleContractAuditRequire(in_array($settingsDefault, ['0', '1'], true), 'core module manifest entry ' . $moduleKey . ' settings_default must be 0 or 1.', $issues);
        moduleContractAuditRequire($adminPaths !== [], 'core module manifest entry ' . $moduleKey . ' must define at least one admin_paths entry.', $issues);

        foreach ($publicPaths as $publicPath) {
            if (!str_starts_with($publicPath, '/')) {
                $issues[] = 'public_paths entry ' . $moduleKey . ' must start with /: ' . $publicPath . '.';
                continue;
            }
            if (str_contains($publicPath, '..') || str_contains($publicPath, "\0")) {
                $issues[] = 'public_paths entry ' . $moduleKey . ' must not contain traversal segments: ' . $publicPath . '.';
                continue;
            }
            if (isset($publicPathModules[$publicPath])) {
                $issues[] = 'public_paths entry ' . $publicPath . ' is duplicated by modules ' . $publicPathModules[$publicPath] . ' and ' . $moduleKey . '.';
                continue;
            }
            $publicPathModules[$publicPath] = $moduleKey;

            if (!moduleContractAuditPublicEntryPointExists($projectRoot, $publicPath)) {
                $issues[] = 'public_paths entry ' . $moduleKey . ' must point to an existing PHP entrypoint: ' . $publicPath . '.';
                continue;
            }

            $source = moduleContractAuditReadFile($projectRoot, ltrim($publicPath, '/'), $issues);
            if ($source === '') {
                continue;
            }

            moduleContractAuditRequire(
                preg_match('/\bisModuleEnabled\(\s*[\'"]' . preg_quote($moduleKey, '/') . '[\'"]\s*\)/', $source) === 1,
                'public module entrypoint ' . ltrim($publicPath, '/') . ' must guard access with isModuleEnabled(\'' . $moduleKey . '\').',
                $issues
            );
        }

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
            moduleContractAuditRequire(in_array($publicNavPath, $publicPaths, true), 'public_nav module ' . $moduleKey . ' public_nav_path must also be listed in public_paths.', $issues);

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
function moduleContractAuditRequiredCoreModuleKeys(): array
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
function moduleContractAuditCollectThemeRequiredModules(string $projectRoot, string $definitionsSource, array &$issues): void
{
    $knownModuleKeys = moduleContractAuditKnownModuleKeys($definitionsSource, $issues);
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

/**
 * @param list<string> $fragments
 * @param list<string> $issues
 */
function moduleContractAuditRequireDocumentationFragments(string $source, string $relativePath, array $fragments, array &$issues): void
{
    foreach ($fragments as $fragment) {
        moduleContractAuditRequire(
            str_contains($source, $fragment),
            $relativePath . ' must document module development fragment: ' . $fragment . '.',
            $issues
        );
    }
}

/**
 * @param list<string> $issues
 */
function moduleContractAuditValidateDeveloperDocumentation(
    string $developerModulesDocSource,
    string $readmeSource,
    string $adminGuideSource,
    array &$issues
): void {
    moduleContractAuditRequireDocumentationFragments(
        $developerModulesDocSource,
        'docs/developer-modules.md',
        [
            '# Vývoj nového modulu v Kora CMS',
            'Povinné integrační body',
            'Bezpečnostní pravidla',
            'WCAG 2.2 checklist',
            'wcag-22-aa-conformance.md',
            'a11y-remediation-backlog.md',
            'manual-test-protocol.md',
            'author-content-checklist.md',
            'Testy a guardrails',
            'Definition of done',
            'install.php',
            'migrate.php',
            'schema parity',
            'coreModuleDefinitions()',
            'modulePublicPathModuleMap()',
            'moduleAdminPathModuleMap()',
            'modulePrimaryAdminPath()',
            'settings_default',
            'public_nav_path',
            'public_paths',
            'admin_paths',
            'admin_capability',
            'moduleSettingKey()',
            'Další moduly',
            'adminRouteModuleRequirements()',
            'requireModuleEnabled()',
            'isModuleEnabled()',
            'content_reference_types',
            'search_result_types',
            'sitemap_sections',
            'stats_page_types',
            'requires_module',
            'requires_modules',
            'internalRedirectTarget()',
            'lib/uploads.php',
            'aria-labelledby',
            'build/theme_view_audit.php',
            'build/module_contract_audit.php',
            'composer ci:module-ready',
            'README.md',
            'docs/admin-guide.md',
            'CHANGELOG.md',
        ],
        $issues
    );

    moduleContractAuditRequireDocumentationFragments(
        $readmeSource,
        'README.md',
        [
            'docs/developer-modules.md',
            'coreModuleDefinitions()',
            'modulePublicPathModuleMap()',
            'moduleAdminPathModuleMap()',
            'modulePrimaryAdminPath()',
            'install.php',
            'migrate.php',
            'public_paths',
            'adminRouteModuleRequirements()',
            'admin_capability',
            'moduleSettingKey()',
            'Další moduly',
            'content_reference_types',
            'search_result_types',
            'sitemap_sections',
            'stats_page_types',
            'public_module_navigation_http',
            'admin_disabled_modules_http',
            'content_reference_disabled_modules_http',
            'composer ci:module-ready',
            'wcag-22-aa-conformance.md',
            'a11y-remediation-backlog.md',
            'manual-test-protocol.md',
            'author-content-checklist.md',
        ],
        $issues
    );

    moduleContractAuditRequireDocumentationFragments(
        $adminGuideSource,
        'docs/admin-guide.md',
        [
            'developer-modules.md',
            'coreModuleDefinitions()',
            'modulePublicPathModuleMap()',
            'moduleAdminPathModuleMap()',
            'modulePrimaryAdminPath()',
            'adminRouteModuleRequirements()',
            'admin_capability',
            'moduleSettingKey()',
            'Další moduly',
            'content_reference_types',
            'search_result_types',
            'sitemap_sections',
            'stats_page_types',
            'composer ci:module-ready',
            'wcag-22-aa-conformance.md',
            'a11y-remediation-backlog.md',
            'manual-test-protocol.md',
            'author-content-checklist.md',
        ],
        $issues
    );
}

$dbSource = moduleContractAuditReadFile($projectRoot, 'db.php', $issues);
$definitionsSource = moduleContractAuditReadFile($projectRoot, 'lib/definitions.php', $issues);
$statsSource = moduleContractAuditReadFile($projectRoot, 'lib/stats.php', $issues);
$settingsModulesSource = moduleContractAuditReadFile($projectRoot, 'admin/settings_modules.php', $issues);
$adminLayoutSource = moduleContractAuditReadFile($projectRoot, 'admin/layout.php', $issues);
$installSource = moduleContractAuditReadFile($projectRoot, 'install.php', $issues);
$migrateSource = moduleContractAuditReadFile($projectRoot, 'migrate.php', $issues);
$composerSource = moduleContractAuditReadFile($projectRoot, 'composer.json', $issues);
$runtimeAuditSource = moduleContractAuditReadFile($projectRoot, 'build/runtime_audit.php', $issues);
$developerModulesDocSource = moduleContractAuditReadFile($projectRoot, 'docs/developer-modules.md', $issues);
$adminGuideSource = moduleContractAuditReadFile($projectRoot, 'docs/admin-guide.md', $issues);
$readmeSource = moduleContractAuditReadFile($projectRoot, 'README.md', $issues);
$authSource = moduleContractAuditReadFile($projectRoot, 'auth.php', $issues);
$contentReferencePickerSource = moduleContractAuditReadFile($projectRoot, 'admin/content_reference_picker.php', $issues);
$contentReferenceSearchSource = moduleContractAuditReadFile($projectRoot, 'admin/content_reference_search.php', $issues);
$publicSearchSource = moduleContractAuditReadFile($projectRoot, 'search.php', $issues);
$sitemapSource = moduleContractAuditReadFile($projectRoot, 'sitemap.php', $issues);
$httpIntegrationSource = moduleContractAuditReadFile($projectRoot, 'build/http_integration.php', $issues);
$adminCommandSource = moduleContractAuditReadFile($projectRoot, 'lib/admin_command.php', $issues);

moduleContractAuditRequire(
    str_contains($definitionsSource, 'function coreModuleDefinitions()')
    && str_contains($definitionsSource, 'function coreModuleKeysByFlag(')
    && str_contains($definitionsSource, 'function moduleKeysForSettings()')
    && str_contains($definitionsSource, 'function moduleDefaultSettings()')
    && str_contains($definitionsSource, 'function moduleSettingsLabels()')
    && str_contains($definitionsSource, 'function moduleNavigationDefaults()')
    && str_contains($definitionsSource, 'function modulePublicEntryPoints()')
    && str_contains($definitionsSource, 'function moduleAdminEntryPoints()')
    && str_contains($definitionsSource, 'function modulePrimaryAdminPath(')
    && str_contains($definitionsSource, 'function moduleWidgetLabel(')
    && str_contains($definitionsSource, 'function moduleContentReferenceTypeLabels(')
    && str_contains($definitionsSource, 'function contentReferenceTypeModuleMap(')
    && str_contains($definitionsSource, 'function moduleSearchResultTypeLabels(')
    && str_contains($definitionsSource, 'function searchResultTypeModuleMap(')
    && str_contains($definitionsSource, 'function moduleSitemapSections(')
    && str_contains($definitionsSource, 'function sitemapSectionModuleMap(')
    && str_contains($definitionsSource, 'function moduleStatsPageTypes(')
    && str_contains($definitionsSource, 'function moduleStatsPageTypeMap(')
    && str_contains($definitionsSource, 'function moduleAdminLabel(')
    && str_contains($definitionsSource, 'function moduleAdminCapability('),
    'lib/definitions.php must keep the central module manifest helper set.',
    $issues
);

moduleContractAuditRequire(
    str_contains($dbSource, 'function moduleSettingKey(')
    && str_contains($dbSource, "return 'module_' . trim(\$module);")
    && str_contains($dbSource, 'getSetting(moduleSettingKey($module),'),
    'db.php must centralize module_* setting key construction through moduleSettingKey().',
    $issues
);

moduleContractAuditRequire(
    str_contains($authSource, 'function adminRouteModuleDisabledMessage(')
    && str_contains($authSource, 'adminRouteModuleDisabledMessage($moduleKey)')
    && str_contains($authSource, 'moduleAdminLabel($moduleKey)')
    && str_contains($authSource, "'message' => adminRouteModuleDisabledMessage('"),
    'auth.php must derive admin disabled module messages from moduleAdminLabel().',
    $issues
);

moduleContractAuditRequire(
    str_contains($adminCommandSource, 'coreModuleDefinitions()')
    && str_contains($adminCommandSource, 'moduleAdminCapability(')
    && str_contains($adminCommandSource, 'modulePrimaryAdminPath(')
    && !str_contains($adminCommandSource, "['admin_paths'][0]")
    && str_contains($adminCommandSource, "'module.' ."),
    'lib/admin_command.php must derive fallback module shortcuts through modulePrimaryAdminPath() and admin_capability.',
    $issues
);

moduleContractAuditRequire(
    str_contains($adminLayoutSource, 'coreModuleDefinitions()')
    && str_contains($adminLayoutSource, 'moduleAdminCapability(')
    && str_contains($adminLayoutSource, 'modulePrimaryAdminPath(')
    && !str_contains($adminLayoutSource, "['admin_paths'][0]")
    && str_contains($adminLayoutSource, 'moduleAdminLabel(')
    && str_contains($adminLayoutSource, "'nav-modules'"),
    'admin/layout.php must derive fallback module navigation through modulePrimaryAdminPath() and admin_capability.',
    $issues
);

foreach (moduleContractAuditRequiredCoreModuleKeys() as $moduleKey) {
    moduleContractAuditRequire(
        preg_match('/\'' . preg_quote($moduleKey, '/') . '\'\\s*=>\\s*\\[/', $definitionsSource) === 1,
        'core module manifest is missing required built-in module key ' . $moduleKey . '.',
        $issues
    );
}

foreach ([
    "'profile_managed'",
    "'settings_configurable'",
    "'settings_default'",
    "'public_nav'",
    "'public_nav_path'",
    "'public_paths'",
    "'public_nav_order'",
    "'settings_label'",
    "'widget_label'",
    "'admin_label'",
    "'admin_capability'",
    "'content_reference_types'",
    "'search_result_types'",
    "'sitemap_sections'",
    "'stats_page_types'",
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
moduleContractAuditValidateAdminRouteModuleRequirements($projectRoot, $definitionsSource, $authSource, $issues);
moduleContractAuditValidateAdminRouteStaticCoverage($authSource, $composerSource, $issues);
moduleContractAuditValidatePublicNavStaticCoverage($definitionsSource, $composerSource, $issues);
moduleContractAuditValidatePublicEntryPointStaticCoverage($definitionsSource, $composerSource, $issues);
moduleContractAuditValidateContentReferenceTypeCoverage($definitionsSource, $contentReferencePickerSource, $contentReferenceSearchSource, $issues);
moduleContractAuditValidateSearchResultTypeCoverage($definitionsSource, $publicSearchSource, $issues);
moduleContractAuditValidateSitemapSectionCoverage($definitionsSource, $sitemapSource, $issues);

moduleContractAuditRequire(
    str_contains($definitionsSource, "return coreModuleKeysByFlag('profile_managed');"),
    'siteProfileModuleKeys() must be derived from the central module manifest.',
    $issues
);

moduleContractAuditRequire(
    str_contains($settingsModulesSource, '$moduleKeys = moduleKeysForSettings();')
    && str_contains($settingsModulesSource, '$moduleLabels = moduleSettingsLabels();')
    && str_contains($settingsModulesSource, 'moduleSettingKey(')
    && !str_contains($settingsModulesSource, '$moduleKeys = ['),
    'admin/settings_modules.php must derive configurable modules, labels and setting keys from the central manifest.',
    $issues
);

moduleContractAuditRequire(
    str_contains($installSource, 'moduleDefaultSettings()')
    && str_contains($migrateSource, 'moduleDefaultSettings()'),
    'install.php and migrate.php must derive module_* defaults from the central manifest.',
    $issues
);

$legacyModuleDefaultPattern = '/[\'"]module_(?:'
    . implode('|', array_map(static fn (string $moduleKey): string => preg_quote($moduleKey, '/'), moduleContractAuditKnownModuleKeys($definitionsSource, $issues)))
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

moduleContractAuditRequire(
    str_contains($statsSource, 'moduleStatsPageTypeMap()[$pageType] ??')
    && str_contains($statsSource, 'array_keys(moduleStatsPageTypes())')
    && !str_contains($statsSource, "'food_card' => 'food'"),
    'lib/stats.php content trend page_type mapping must derive from module stats_page_types manifest entries.',
    $issues
);

$widgetsSource = moduleContractAuditReadFile($projectRoot, 'lib/widgets.php', $issues);
moduleContractAuditRequire(
    str_contains($widgetsSource, 'return moduleWidgetLabel($moduleKey);'),
    'lib/widgets.php widgetModuleDisplayName() must derive module labels from the central manifest.',
    $issues
);

$knownModuleKeys = moduleContractAuditKnownModuleKeys($definitionsSource, $issues);
if (preg_match_all('/[\'"]requires_module[\'"]\\s*=>\\s*[\'"]([a-z][a-z0-9_]*)[\'"]/', $widgetsSource, $widgetRequiresMatches) === false) {
    $issues[] = 'lib/widgets.php requires_module references cannot be parsed.';
} else {
    foreach ($widgetRequiresMatches[1] as $moduleKey) {
        moduleContractAuditRequireKnownModule($moduleKey, 'lib/widgets.php requires_module', $knownModuleKeys, $issues);
    }
}
moduleContractAuditCollectThemeRequiredModules($projectRoot, $definitionsSource, $issues);
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

moduleContractAuditValidateDeveloperDocumentation($developerModulesDocSource, $readmeSource, $adminGuideSource, $issues);

if ($issues !== []) {
    fwrite(STDERR, "Module contract audit failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

echo "Module contract audit OK\n";
