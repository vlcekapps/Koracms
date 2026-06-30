<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$moduleContractAuditPath = __DIR__ . DIRECTORY_SEPARATOR . 'module_contract_audit.php';

function moduleContractAuditSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function moduleContractAuditSelfTestWriteFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        moduleContractAuditSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        moduleContractAuditSelfTestFail('Cannot write file: ' . $path);
    }
}

function moduleContractAuditSelfTestRemoveTree(string $path): void
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

            moduleContractAuditSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runModuleContractAuditSelfTestCommand(array $command, string $cwd): array
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
        moduleContractAuditSelfTestFail('Cannot start command: ' . implode(' ', $command));
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
 * @return list<string>
 */
function moduleContractAuditSelfTestModuleKeys(): array
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

function moduleContractAuditSelfTestAdminScriptTargets(): string
{
    return implode(
        ' ',
        array_map(
            static fn (string $moduleKey): string => 'admin/' . $moduleKey . '.php',
            moduleContractAuditSelfTestModuleKeys()
        )
    );
}

function moduleContractAuditSelfTestStaticScriptTargets(): string
{
    return moduleContractAuditSelfTestAdminScriptTargets() . ' blog/index.php';
}

function moduleContractAuditSelfTestDefinitionsFixture(): string
{
    $entries = [];
    foreach (moduleContractAuditSelfTestModuleKeys() as $moduleKey) {
        $publicNav = $moduleKey === 'blog';
        $publicNavPath = $publicNav ? '/blog/index.php' : '';
        $publicNavOrder = $publicNav ? 10 : 0;
        $publicNavValue = $publicNav ? 'true' : 'false';
        $adminPath = '/admin/' . $moduleKey . '.php';
        $entries[] = "        '{$moduleKey}' => ['label' => 'Label', 'settings_label' => 'Label', 'nav_label' => 'Label', 'widget_label' => 'Label', 'admin_label' => 'Label', 'settings_default' => '0', 'public_nav_path' => '{$publicNavPath}', 'public_nav_order' => {$publicNavOrder}, 'profile_managed' => true, 'settings_configurable' => true, 'public_nav' => {$publicNavValue}, 'admin_paths' => ['{$adminPath}']],\n";
    }

    return "<?php\n"
        . "function coreModuleDefinitions(): array\n{\n    return [\n"
        . implode('', $entries)
        . "    ];\n}\n"
        . "function coreModuleKeysByFlag(string \$flag): array { return []; }\n"
        . "function moduleKeysForSettings(): array { return coreModuleKeysByFlag('settings_configurable'); }\n"
        . "function moduleDefaultSettings(): array { return []; }\n"
        . "function moduleSettingsLabels(): array { return []; }\n"
        . "function moduleNavigationDefaults(): array { return []; }\n"
        . "function moduleAdminEntryPoints(): array { return []; }\n"
        . "function moduleWidgetLabel(string \$moduleKey): string { return \$moduleKey; }\n"
        . "function moduleAdminLabel(string \$moduleKey): string { return \$moduleKey; }\n"
        . "function siteProfileModuleKeys(): array { return coreModuleKeysByFlag('profile_managed'); }\n";
}

function moduleContractAuditSelfTestAuthFixture(): string
{
    $entries = [];
    foreach (moduleContractAuditSelfTestModuleKeys() as $moduleKey) {
        $entries[] = "        '{$moduleKey}' => ['message' => adminRouteModuleDisabledMessage('{$moduleKey}'), 'files' => ['{$moduleKey}.php']],\n";
    }

    return "<?php\n"
        . "function requireModuleEnabled(string \$moduleKey): void { adminRouteModuleDisabledMessage(\$moduleKey); }\n"
        . "function adminRouteModuleDisabledMessage(string \$moduleKey): string { return 'Přístup odepřen. Modul ' . moduleAdminLabel(\$moduleKey) . ' není povolen.'; }\n"
        . "function adminRouteModuleRequirements(): array\n{\n"
        . "    return [\n"
        . implode('', $entries)
        . "    ];\n"
        . "}\n"
        . "function adminRouteModuleRequirement(?string \$scriptPath = null): ?array\n{\n"
        . "    foreach (adminRouteModuleRequirements() as \$moduleKey => \$requirement) { return null; }\n"
        . "    return null;\n"
        . "}\n";
}

/**
 * @return array<string,string>
 */
function moduleContractAuditSelfTestValidFiles(): array
{
    $staticScriptTargets = moduleContractAuditSelfTestStaticScriptTargets();
    $files = [
        'lib/definitions.php' => moduleContractAuditSelfTestDefinitionsFixture(),
        'auth.php' => moduleContractAuditSelfTestAuthFixture(),
        'lib/stats.php' => "<?php\nfunction navModuleDefaults(): array { return moduleNavigationDefaults(); }\n",
        'lib/widgets.php' => "<?php\nfunction widgetModuleDisplayName(string \$moduleKey): string { return moduleWidgetLabel(\$moduleKey); }\n",
        'blog/index.php' => "<?php\nif (!isModuleEnabled('blog')) { exit; }\n",
        'admin/content_reference_picker.php' => "<?php\nif (isModuleEnabled('blog')) {}\n",
        'admin/content_reference_search.php' => "<?php\nif (isModuleEnabled('news')) {}\n",
        'forms/show.php' => "<?php\ngetSetting('module_blog', '0');\n",
        'admin/settings_modules.php' => "<?php\n\$moduleKeys = moduleKeysForSettings();\n\$moduleLabels = moduleSettingsLabels();\n",
        'install.php' => "<?php\n\$defaults = array_merge(['site_name' => 'Demo'], moduleDefaultSettings(), ['nav_module_order' => '']);\n",
        'migrate.php' => "<?php\n\$newSettings = array_merge(moduleDefaultSettings(), ['nav_module_order' => '']);\n",
        'themes/default/theme.json' => '{"name":"Fixture theme","settings":{"accent":{"type":"color","requires_modules":["blog"],"default":"#000000"}}}',
        'composer.json' => '{"scripts":{"test:module-contract":"php build/module_contract_audit.php","test:module-contract-selftest":"php build/module_contract_audit_selftest.php","ci:basic":["@test:module-contract","@test:module-contract-selftest"],"analyse:strict":"' . $staticScriptTargets . '","analyse:strict:build-tests":"build/module_contract_audit.php build/module_contract_audit_selftest.php","format:check":"' . $staticScriptTargets . '","format:check:build-tests":"build/module_contract_audit.php build/module_contract_audit_selftest.php"}}',
        'build/runtime_audit.php' => "<?php\n'build/module_contract_audit.php'; 'build/module_contract_audit_selftest.php'; 'coreModuleDefinitions';\n",
        'build/http_integration.php' => "<?php\nforeach (moduleNavigationDefaults() as \$moduleKey => \$moduleNavigation) { saveSetting('module_' . \$moduleKey, '0'); responseHasLocationHeader(\$disabledModuleResponse['headers'], BASE_URL . '/index.php', \$baseUrl); saveSetting('module_' . \$moduleKey, '1'); } httpIntegrationPrintResult('public_module_navigation_http', ['veřejný modul ', 'Tento modul není povolen'], \$failures); foreach (moduleAdminEntryPoints() as \$moduleKey => \$adminPaths) { saveSetting('module_' . \$moduleKey, '0'); } httpIntegrationPrintResult('admin_disabled_modules_http', ['admin stránka vypnutého modulu ', 'není povolen'], \$failures);\n",
        'docs/developer-modules.md' => "Použijte coreModuleDefinitions() a build/module_contract_audit.php.\n",
        'README.md' => "Spusťte composer ci:module-ready.\n",
    ];

    foreach (moduleContractAuditSelfTestModuleKeys() as $moduleKey) {
        $files['admin/' . $moduleKey . '.php'] = "<?php\nrequireModuleEnabled('{$moduleKey}');\n";
    }

    return $files;
}

/**
 * @param array<string,string> $files
 * @return array{exitCode:int, output:string}
 */
function runModuleContractAuditWithFixture(array $files): array
{
    global $projectRoot, $moduleContractAuditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_module_contract_'
        . bin2hex(random_bytes(6));

    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            moduleContractAuditSelfTestFail('Cannot create temp directory: ' . $tempRoot);
        }

        foreach ($files as $relativePath => $contents) {
            moduleContractAuditSelfTestWriteFile(
                $tempRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                $contents
            );
        }

        return runModuleContractAuditSelfTestCommand(
            [PHP_BINARY, $moduleContractAuditPath, $tempRoot],
            $projectRoot
        );
    } finally {
        moduleContractAuditSelfTestRemoveTree($tempRoot);
    }
}

/**
 * @param array<string,string> $files
 */
function assertModuleContractAuditPasses(string $label, array $files): void
{
    $result = runModuleContractAuditWithFixture($files);
    if ($result['exitCode'] !== 0) {
        moduleContractAuditSelfTestFail($label . ' should pass module contract audit.' . PHP_EOL . $result['output']);
    }
}

/**
 * @param array<string,string> $files
 */
function assertModuleContractAuditFails(string $label, array $files, string $expectedOutput): void
{
    $result = runModuleContractAuditWithFixture($files);
    if ($result['exitCode'] === 0) {
        moduleContractAuditSelfTestFail($label . ' should fail module contract audit.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        moduleContractAuditSelfTestFail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($moduleContractAuditPath)) {
    moduleContractAuditSelfTestFail('Module contract audit self-test cannot find module_contract_audit.php.');
}

$validFiles = moduleContractAuditSelfTestValidFiles();
assertModuleContractAuditPasses('Clean module contract fixture', $validFiles);

$additionalModuleFiles = $validFiles;
$additionalModuleFiles['lib/definitions.php'] = str_replace(
    "    ];\n}\nfunction coreModuleKeysByFlag",
    "        'jobs' => ['label' => 'Práce', 'settings_label' => 'Práce', 'nav_label' => '', 'widget_label' => 'Práce', 'admin_label' => 'Práce', 'settings_default' => '0', 'public_nav_path' => '', 'public_nav_order' => 0, 'profile_managed' => true, 'settings_configurable' => true, 'public_nav' => false, 'admin_paths' => ['/admin/jobs.php']],\n    ];\n}\nfunction coreModuleKeysByFlag",
    $additionalModuleFiles['lib/definitions.php']
);
$additionalModuleFiles['auth.php'] = str_replace(
    "    ];\n}\nfunction adminRouteModuleRequirement",
    "        'jobs' => ['message' => adminRouteModuleDisabledMessage('jobs'), 'files' => ['jobs.php']],\n    ];\n}\nfunction adminRouteModuleRequirement",
    $additionalModuleFiles['auth.php']
);
$additionalModuleFiles['composer.json'] = str_replace(' admin/statistics.php blog/index.php', ' admin/statistics.php admin/jobs.php blog/index.php', $additionalModuleFiles['composer.json']);
$additionalModuleFiles['admin/jobs.php'] = "<?php\nrequireModuleEnabled('jobs');\n";
assertModuleContractAuditPasses('Additional manifest module fixture', $additionalModuleFiles);

$missingAdminLabelFiles = $validFiles;
$missingAdminLabelFiles['lib/definitions.php'] = str_replace(
    "'admin_label' => 'Label', ",
    '',
    $missingAdminLabelFiles['lib/definitions.php']
);
assertModuleContractAuditFails(
    'Missing module admin label',
    $missingAdminLabelFiles,
    'core module manifest entry blog is missing string field admin_label.'
);

$hardCodedAdminMessageFiles = $validFiles;
foreach (moduleContractAuditSelfTestModuleKeys() as $moduleKey) {
    $hardCodedAdminMessageFiles['auth.php'] = str_replace(
        "adminRouteModuleDisabledMessage('{$moduleKey}')",
        "'Disabled'",
        $hardCodedAdminMessageFiles['auth.php']
    );
}
assertModuleContractAuditFails(
    'Hard-coded admin disabled module message',
    $hardCodedAdminMessageFiles,
    'auth.php must derive admin disabled module messages from moduleAdminLabel().'
);

$duplicateRequireModuleMessageFiles = $validFiles;
$duplicateRequireModuleMessageFiles['admin/blog.php'] = "<?php\nrequireModuleEnabled('blog', 'Disabled');\n";
assertModuleContractAuditFails(
    'Duplicate requireModuleEnabled disabled message',
    $duplicateRequireModuleMessageFiles,
    'admin_paths entrypoint admin/blog.php must rely on the manifest-derived requireModuleEnabled() disabled message.'
);

$missingSharedAdminRouteMapFiles = $validFiles;
$missingSharedAdminRouteMapFiles['auth.php'] = "<?php\nfunction adminRouteModuleRequirement(?string \$scriptPath = null): ?array\n{\n    \$requirements = ['blog' => ['message' => 'Disabled', 'files' => ['blog.php']]];\n    return null;\n}\n";
assertModuleContractAuditFails(
    'Missing shared admin route requirement map',
    $missingSharedAdminRouteMapFiles,
    'auth.php adminRouteModuleRequirements return map cannot be parsed.'
);

$missingModuleFiles = $validFiles;
$missingModuleFiles['lib/definitions.php'] = str_replace("        'statistics' => ", "        'stats_missing' => ", $missingModuleFiles['lib/definitions.php']);
assertModuleContractAuditFails(
    'Missing manifest module key',
    $missingModuleFiles,
    'core module manifest is missing required built-in module key statistics.'
);

$legacySettingsFiles = $validFiles;
$legacySettingsFiles['admin/settings_modules.php'] = "<?php\n\$moduleKeys = ['blog'];\n";
assertModuleContractAuditFails(
    'Legacy settings module list',
    $legacySettingsFiles,
    'admin/settings_modules.php must derive configurable modules and labels from the central manifest.'
);

$legacyInstallFiles = $validFiles;
$legacyInstallFiles['install.php'] = "<?php\n\$defaults = ['module_blog' => '1'];\n";
assertModuleContractAuditFails(
    'Legacy install module defaults',
    $legacyInstallFiles,
    'install.php and migrate.php must derive module_* defaults from the central manifest.'
);

$unknownWidgetModuleFiles = $validFiles;
$unknownWidgetModuleFiles['lib/widgets.php'] = "<?php\nfunction widgetModuleDisplayName(string \$moduleKey): string { return moduleWidgetLabel(\$moduleKey); }\n\$definition = ['requires_module' => 'unknown_widget_module'];\n";
assertModuleContractAuditFails(
    'Unknown widget required module',
    $unknownWidgetModuleFiles,
    'lib/widgets.php requires_module references unknown module key unknown_widget_module.'
);

$unknownThemeModuleFiles = $validFiles;
$unknownThemeModuleFiles['themes/default/theme.json'] = '{"name":"Fixture theme","settings":{"accent":{"type":"color","requires_modules":["unknown_theme_module"],"default":"#000000"}}}';
assertModuleContractAuditFails(
    'Unknown theme required module',
    $unknownThemeModuleFiles,
    'themes/default/theme.json requires_modules references unknown module key unknown_theme_module.'
);

$unknownPickerModuleFiles = $validFiles;
$unknownPickerModuleFiles['admin/content_reference_picker.php'] = "<?php\nif (isModuleEnabled('unknown_picker_module')) {}\n";
assertModuleContractAuditFails(
    'Unknown content picker module gate',
    $unknownPickerModuleFiles,
    'admin/content_reference_picker.php isModuleEnabled references unknown module key unknown_picker_module.'
);

$unknownApplicationModuleFiles = $validFiles;
$unknownApplicationModuleFiles['forms/show.php'] = "<?php\nif (isModuleEnabled('unknown_application_module')) {}\n";
assertModuleContractAuditFails(
    'Unknown application module gate',
    $unknownApplicationModuleFiles,
    'forms/show.php isModuleEnabled references unknown module key unknown_application_module.'
);

$unknownApplicationModuleSettingFiles = $validFiles;
$unknownApplicationModuleSettingFiles['forms/show.php'] = "<?php\ngetSetting('module_unknown_setting_module', '0');\n";
assertModuleContractAuditFails(
    'Unknown application module setting',
    $unknownApplicationModuleSettingFiles,
    'forms/show.php module_* setting references unknown module key unknown_setting_module.'
);

$invalidManifestDefaultFiles = $validFiles;
$invalidManifestDefaultFiles['lib/definitions.php'] = str_replace("'settings_default' => '0'", "'settings_default' => 'maybe'", $invalidManifestDefaultFiles['lib/definitions.php']);
assertModuleContractAuditFails(
    'Invalid module manifest default',
    $invalidManifestDefaultFiles,
    'core module manifest entry blog settings_default must be 0 or 1.'
);

$invalidManifestPublicPathFiles = $validFiles;
$invalidManifestPublicPathFiles['lib/definitions.php'] = str_replace("'public_nav_path' => '/blog/index.php'", "'public_nav_path' => 'blog/index.php'", $invalidManifestPublicPathFiles['lib/definitions.php']);
assertModuleContractAuditFails(
    'Invalid public navigation module path',
    $invalidManifestPublicPathFiles,
    'public_nav module blog must define a rooted public_nav_path.'
);

$missingPublicNavTargetFiles = $validFiles;
unset($missingPublicNavTargetFiles['blog/index.php']);
assertModuleContractAuditFails(
    'Missing public navigation target',
    $missingPublicNavTargetFiles,
    'public_nav module blog must point to an existing PHP entrypoint.'
);

$missingPublicNavGateFiles = $validFiles;
$missingPublicNavGateFiles['blog/index.php'] = "<?php\n";
assertModuleContractAuditFails(
    'Missing public navigation module gate',
    $missingPublicNavGateFiles,
    "public_nav entrypoint blog/index.php must guard access with isModuleEnabled('blog')."
);

$missingPublicNavHttpFiles = $validFiles;
$missingPublicNavHttpFiles['build/http_integration.php'] = "<?php\n";
assertModuleContractAuditFails(
    'Missing public navigation HTTP scenario',
    $missingPublicNavHttpFiles,
    'public_nav modules must be covered by dynamic public_module_navigation_http integration.'
);

$missingAdminTargetFiles = $validFiles;
unset($missingAdminTargetFiles['admin/blog.php']);
assertModuleContractAuditFails(
    'Missing admin entrypoint target',
    $missingAdminTargetFiles,
    'admin_paths entry blog must point to an existing PHP entrypoint: /admin/blog.php.'
);

$missingAdminGateFiles = $validFiles;
$missingAdminGateFiles['admin/blog.php'] = "<?php\n";
assertModuleContractAuditFails(
    'Missing admin entrypoint module gate',
    $missingAdminGateFiles,
    "admin_paths entrypoint admin/blog.php must guard access with requireModuleEnabled('blog')."
);

$missingAdminRouteTargetFiles = $validFiles;
$missingAdminRouteTargetFiles['auth.php'] = str_replace(
    "['blog.php']",
    "['blog.php', 'blog_missing.php']",
    $missingAdminRouteTargetFiles['auth.php']
);
assertModuleContractAuditFails(
    'Missing admin route module requirement target',
    $missingAdminRouteTargetFiles,
    'adminRouteModuleRequirement entry blog references missing admin PHP file: /admin/blog_missing.php.'
);

$unknownAdminRouteModuleFiles = $validFiles;
$unknownAdminRouteModuleFiles['auth.php'] = str_replace(
    "        'statistics' => ",
    "        'stats_missing' => ",
    $unknownAdminRouteModuleFiles['auth.php']
);
assertModuleContractAuditFails(
    'Unknown admin route module requirement key',
    $unknownAdminRouteModuleFiles,
    'adminRouteModuleRequirement references unknown module key stats_missing.'
);

$missingManifestAdminRouteFiles = $validFiles;
$missingManifestAdminRouteFiles['auth.php'] = str_replace(
    "['blog.php']",
    "['blog_save.php']",
    $missingManifestAdminRouteFiles['auth.php']
);
$missingManifestAdminRouteFiles['admin/blog_save.php'] = "<?php\n";
assertModuleContractAuditFails(
    'Missing manifest admin path in route requirement map',
    $missingManifestAdminRouteFiles,
    'admin_paths entry /admin/blog.php must be listed in adminRouteModuleRequirement() for blog.'
);

$missingAdminRouteLoginGuardFiles = $validFiles;
$missingAdminRouteLoginGuardFiles['auth.php'] = str_replace(
    "['blog.php']",
    "['blog.php', 'blog_save.php']",
    $missingAdminRouteLoginGuardFiles['auth.php']
);
$missingAdminRouteLoginGuardFiles['admin/blog_save.php'] = "<?php\n";
assertModuleContractAuditFails(
    'Missing admin route login guard',
    $missingAdminRouteLoginGuardFiles,
    'adminRouteModuleRequirement file /admin/blog_save.php must call requireLogin(), requireSuperAdmin(), requireModuleEnabled() or requireCapability().'
);

$staticScriptTargets = moduleContractAuditSelfTestStaticScriptTargets();
$staticScriptTargetsWithoutAdminBlog = str_replace('admin/blog.php ', '', $staticScriptTargets);

$missingAdminRouteAnalysisFiles = $validFiles;
$missingAdminRouteAnalysisFiles['composer.json'] = str_replace(
    '"analyse:strict":"' . $staticScriptTargets . '"',
    '"analyse:strict":"' . $staticScriptTargetsWithoutAdminBlog . '"',
    $missingAdminRouteAnalysisFiles['composer.json']
);
assertModuleContractAuditFails(
    'Missing admin route PHPStan coverage',
    $missingAdminRouteAnalysisFiles,
    'adminRouteModuleRequirement file admin/blog.php must be covered by an analyse:strict composer script.'
);

$missingAdminRouteFormatFiles = $validFiles;
$missingAdminRouteFormatFiles['composer.json'] = str_replace(
    '"format:check":"' . $staticScriptTargets . '"',
    '"format:check":"' . $staticScriptTargetsWithoutAdminBlog . '"',
    $missingAdminRouteFormatFiles['composer.json']
);
assertModuleContractAuditFails(
    'Missing admin route format coverage',
    $missingAdminRouteFormatFiles,
    'adminRouteModuleRequirement file admin/blog.php must be covered by a format:check composer script.'
);

$staticScriptTargetsWithoutPublicBlog = str_replace(' blog/index.php', '', $staticScriptTargets);

$missingPublicNavAnalysisFiles = $validFiles;
$missingPublicNavAnalysisFiles['composer.json'] = str_replace(
    '"analyse:strict":"' . $staticScriptTargets . '"',
    '"analyse:strict":"' . $staticScriptTargetsWithoutPublicBlog . '"',
    $missingPublicNavAnalysisFiles['composer.json']
);
assertModuleContractAuditFails(
    'Missing public navigation PHPStan coverage',
    $missingPublicNavAnalysisFiles,
    'public_nav entrypoint blog/index.php must be covered by an analyse:strict composer script.'
);

$missingPublicNavFormatFiles = $validFiles;
$missingPublicNavFormatFiles['composer.json'] = str_replace(
    '"format:check":"' . $staticScriptTargets . '"',
    '"format:check":"' . $staticScriptTargetsWithoutPublicBlog . '"',
    $missingPublicNavFormatFiles['composer.json']
);
assertModuleContractAuditFails(
    'Missing public navigation format coverage',
    $missingPublicNavFormatFiles,
    'public_nav entrypoint blog/index.php must be covered by a format:check composer script.'
);

$missingAdminHttpFiles = $validFiles;
$missingAdminHttpFiles['build/http_integration.php'] = "<?php\nforeach (moduleNavigationDefaults() as \$moduleKey => \$moduleNavigation) { saveSetting('module_' . \$moduleKey, '0'); responseHasLocationHeader(\$disabledModuleResponse['headers'], BASE_URL . '/index.php', \$baseUrl); saveSetting('module_' . \$moduleKey, '1'); } httpIntegrationPrintResult('public_module_navigation_http', ['veřejný modul ', 'Tento modul není povolen'], \$failures);\n";
assertModuleContractAuditFails(
    'Missing admin module HTTP scenario',
    $missingAdminHttpFiles,
    'admin_paths modules must be covered by dynamic admin_disabled_modules_http integration.'
);

$missingComposerFiles = $validFiles;
$missingComposerFiles['composer.json'] = str_replace('"test:module-contract-selftest"', '"test:module-contract-old"', $missingComposerFiles['composer.json']);
assertModuleContractAuditFails(
    'Missing composer wiring',
    $missingComposerFiles,
    'composer.json is missing module contract script wiring: "test:module-contract-selftest".'
);

echo "Module contract audit self-test OK\n";
