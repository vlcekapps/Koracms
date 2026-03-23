<?php

function defaultThemeName(): string
{
    return 'default';
}

function themeBasePath(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'themes';
}

function isValidThemeKey(string $themeKey): bool
{
    return preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $themeKey) === 1;
}

function themeExists(string $themeKey): bool
{
    if (!isValidThemeKey($themeKey)) {
        return false;
    }

    $themePath = themeBasePath() . DIRECTORY_SEPARATOR . $themeKey;
    return is_dir($themePath) && is_file($themePath . DIRECTORY_SEPARATOR . 'theme.json');
}

function resolveThemeName(?string $themeKey = null): string
{
    $fallback = defaultThemeName();
    $candidate = trim((string)($themeKey ?? ''));

    if ($candidate === '') {
        $preview = themePreviewData();
        if ($preview !== []) {
            return $preview['theme'];
        }

        $candidate = trim(getSetting('active_theme', $fallback));
    }

    return themeExists($candidate) ? $candidate : $fallback;
}

function availableThemes(): array
{
    $themes = [];
    $basePath = themeBasePath();

    if (!is_dir($basePath)) {
        return [defaultThemeName()];
    }

    foreach (scandir($basePath) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        if (themeExists($entry)) {
            $themes[] = $entry;
        }
    }

    sort($themes, SORT_NATURAL | SORT_FLAG_CASE);

    if (empty($themes)) {
        $themes[] = defaultThemeName();
    }

    return $themes;
}

function themeManifest(?string $themeKey = null): array
{
    static $cache = [];

    $resolvedTheme = resolveThemeName($themeKey);
    if (isset($cache[$resolvedTheme])) {
        return $cache[$resolvedTheme];
    }

    $manifestPath = themeBasePath()
        . DIRECTORY_SEPARATOR
        . $resolvedTheme
        . DIRECTORY_SEPARATOR
        . 'theme.json';

    $manifest = [];
    if (is_file($manifestPath)) {
        $decoded = json_decode((string)file_get_contents($manifestPath), true);
        if (is_array($decoded)) {
            $manifest = $decoded;
        }
    }

    $normalizedSettings = themeNormalizeSettingDefinitions(
        is_array($manifest['settings'] ?? null) ? $manifest['settings'] : []
    );
    if ($resolvedTheme !== defaultThemeName()) {
        if ($normalizedSettings === []) {
            $normalizedSettings = themeSettingDefinitions(defaultThemeName());
        }

        $normalizedSettings = themeApplySettingDefaultOverrides(
            $normalizedSettings,
            is_array($manifest['settings_defaults'] ?? null) ? $manifest['settings_defaults'] : []
        );
    }

    $cache[$resolvedTheme] = [
        'key' => $resolvedTheme,
        'name' => trim((string)($manifest['name'] ?? 'Kora Default')),
        'version' => trim((string)($manifest['version'] ?? '1.0.0')),
        'author' => trim((string)($manifest['author'] ?? 'Kora CMS')),
        'description' => trim((string)($manifest['description'] ?? '')),
        'preview' => themeNormalizePreviewData(
            is_array($manifest['preview'] ?? null) ? $manifest['preview'] : []
        ),
        'settings' => $normalizedSettings,
    ];

    return $cache[$resolvedTheme];
}

function themeIsSafeCssVariable(string $value): bool
{
    return preg_match('/^[a-z][a-z0-9-]*$/i', $value) === 1;
}

function themeIsSafeSettingKey(string $value): bool
{
    return preg_match('/^[a-z][a-z0-9_]*$/i', $value) === 1;
}

function themeNormalizeRequiredModules(mixed $value): array
{
    $modules = [];
    foreach ((array)$value as $module) {
        if (!is_string($module)) {
            continue;
        }

        $moduleKey = trim($module);
        if ($moduleKey !== '' && preg_match('/^[a-z][a-z0-9_]*$/i', $moduleKey) === 1) {
            $modules[] = $moduleKey;
        }
    }

    return array_values(array_unique($modules));
}

function themeNormalizeHexColor(string $value): ?string
{
    $color = trim($value);
    if (preg_match('/^#([0-9a-f]{3})$/i', $color, $matches) === 1) {
        $chars = strtolower($matches[1]);
        return '#'
            . $chars[0] . $chars[0]
            . $chars[1] . $chars[1]
            . $chars[2] . $chars[2];
    }

    if (preg_match('/^#([0-9a-f]{6})$/i', $color) === 1) {
        return strtolower($color);
    }

    return null;
}

function themeHexToRgb(string $value): ?array
{
    $color = themeNormalizeHexColor($value);
    if ($color === null) {
        return null;
    }

    return [
        'r' => hexdec(substr($color, 1, 2)),
        'g' => hexdec(substr($color, 3, 2)),
        'b' => hexdec(substr($color, 5, 2)),
    ];
}

function themeRelativeLuminance(string $value): ?float
{
    $rgb = themeHexToRgb($value);
    if ($rgb === null) {
        return null;
    }

    $channels = [];
    foreach (['r', 'g', 'b'] as $channel) {
        $srgb = $rgb[$channel] / 255;
        $channels[] = $srgb <= 0.03928
            ? $srgb / 12.92
            : (($srgb + 0.055) / 1.055) ** 2.4;
    }

    return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
}

function themeContrastRatio(string $foreground, string $background): ?float
{
    $foregroundLum = themeRelativeLuminance($foreground);
    $backgroundLum = themeRelativeLuminance($background);
    if ($foregroundLum === null || $backgroundLum === null) {
        return null;
    }

    $lighter = max($foregroundLum, $backgroundLum);
    $darker = min($foregroundLum, $backgroundLum);
    return ($lighter + 0.05) / ($darker + 0.05);
}

function themeCssValueIsSafe(string $value): bool
{
    if (themeNormalizeHexColor($value) !== null) {
        return true;
    }

    return preg_match('/^[A-Za-z0-9\s,"\'.%(),-]+$/', $value) === 1;
}

function themeNormalizeSettingDefinitions(array $settings): array
{
    $normalized = [];

    foreach ($settings as $settingKey => $definition) {
        if (!is_string($settingKey) || !themeIsSafeSettingKey($settingKey) || !is_array($definition)) {
            continue;
        }

        $type = trim((string)($definition['type'] ?? ''));
        $label = trim((string)($definition['label'] ?? $settingKey));
        $description = trim((string)($definition['description'] ?? ''));
        $requiresModules = themeNormalizeRequiredModules($definition['requires_modules'] ?? []);

        if ($type === 'color') {
            $cssVar = trim((string)($definition['css_var'] ?? ''));
            $default = themeNormalizeHexColor((string)($definition['default'] ?? ''));
            if ($default === null || !themeIsSafeCssVariable($cssVar)) {
                continue;
            }

            $contrastWith = [];
            foreach ((array)($definition['contrast_with'] ?? []) as $contrastColor) {
                if (!is_string($contrastColor)) {
                    continue;
                }

                $normalizedColor = themeNormalizeHexColor($contrastColor);
                if ($normalizedColor !== null) {
                    $contrastWith[] = $normalizedColor;
                }
            }

            $normalized[$settingKey] = [
                'type' => 'color',
                'label' => $label,
                'description' => $description,
                'default' => $default,
                'css_var' => $cssVar,
                'requires_modules' => $requiresModules,
                'contrast_with' => $contrastWith,
                'min_contrast' => max(1.0, min(21.0, (float)($definition['min_contrast'] ?? 4.5))),
            ];
            continue;
        }

        if ($type !== 'select' || !is_array($definition['options'] ?? null)) {
            continue;
        }

        $options = [];
        foreach ($definition['options'] as $optionKey => $option) {
            if (!is_string($optionKey) || !themeIsSafeSettingKey($optionKey) || !is_array($option)) {
                continue;
            }

            $optionRequiresModules = themeNormalizeRequiredModules($option['requires_modules'] ?? []);

            $cssVars = [];
            if (is_array($option['css_vars'] ?? null)) {
                foreach ($option['css_vars'] as $cssVarName => $cssVarValue) {
                    if (!is_string($cssVarName) || !is_string($cssVarValue)) {
                        continue;
                    }

                    $normalizedColor = themeNormalizeHexColor($cssVarValue);
                    $normalizedValue = $normalizedColor ?? trim($cssVarValue);
                    if (!themeIsSafeCssVariable($cssVarName) || $normalizedValue === '' || !themeCssValueIsSafe($normalizedValue)) {
                        continue;
                    }

                    $cssVars[$cssVarName] = $normalizedValue;
                }
            } else {
                $cssVarName = trim((string)($option['css_var'] ?? ''));
                $cssVarValue = trim((string)($option['value'] ?? ''));
                $normalizedColor = themeNormalizeHexColor($cssVarValue);
                $normalizedValue = $normalizedColor ?? $cssVarValue;
                if ($cssVarName !== '' && $normalizedValue !== '' && themeIsSafeCssVariable($cssVarName) && themeCssValueIsSafe($normalizedValue)) {
                    $cssVars[$cssVarName] = $normalizedValue;
                }
            }

            $options[$optionKey] = [
                'label' => trim((string)($option['label'] ?? $optionKey)),
                'description' => trim((string)($option['description'] ?? '')),
                'requires_modules' => $optionRequiresModules,
                'css_vars' => $cssVars,
            ];
        }

        if ($options === []) {
            continue;
        }

        $default = trim((string)($definition['default'] ?? ''));
        if ($default === '' || !isset($options[$default])) {
            $default = (string)array_key_first($options);
        }

        $normalized[$settingKey] = [
            'type' => 'select',
            'label' => $label,
            'description' => $description,
            'default' => $default,
            'requires_modules' => $requiresModules,
            'options' => $options,
        ];
    }

    return $normalized;
}

function themeNormalizePreviewData(array $preview): array
{
    $colors = [];
    foreach ((array)($preview['colors'] ?? []) as $colorValue) {
        if (!is_string($colorValue)) {
            continue;
        }

        $normalizedColor = themeNormalizeHexColor($colorValue);
        if ($normalizedColor !== null) {
            $colors[] = $normalizedColor;
        }
    }

    return [
        'summary' => trim((string)($preview['summary'] ?? '')),
        'colors' => array_values(array_unique($colors)),
    ];
}

function themeModuleLabelMap(): array
{
    static $labels = null;

    if (is_array($labels)) {
        return $labels;
    }

    $labels = [
        'newsletter' => 'Newsletter',
    ];

    foreach (navModuleDefaults() as $moduleKey => $moduleConfig) {
        $labels[$moduleKey] = (string)($moduleConfig[1] ?? $moduleKey);
    }

    return $labels;
}

function themeRequiredModulesDescription(array $requiredModules): string
{
    if ($requiredModules === []) {
        return '';
    }

    $moduleLabels = themeModuleLabelMap();
    $labels = [];
    foreach ($requiredModules as $moduleKey) {
        $labels[] = $moduleLabels[$moduleKey] ?? $moduleKey;
    }

    return implode(', ', array_values(array_unique($labels)));
}

function themeModulesAvailable(array $requiredModules): bool
{
    foreach ($requiredModules as $moduleKey) {
        if (!is_string($moduleKey) || $moduleKey === '' || !isModuleEnabled($moduleKey)) {
            return false;
        }
    }

    return true;
}

function themeSettingIsAvailable(array $definition): bool
{
    return themeModulesAvailable((array)($definition['requires_modules'] ?? []));
}

function themeSelectOptionIsAvailable(array $option): bool
{
    return themeModulesAvailable((array)($option['requires_modules'] ?? []));
}

function themeAvailableSelectOptions(array $definition): array
{
    if (($definition['type'] ?? '') !== 'select') {
        return [];
    }

    $availableOptions = [];
    foreach ((array)($definition['options'] ?? []) as $optionKey => $option) {
        if (is_string($optionKey) && is_array($option) && themeSelectOptionIsAvailable($option)) {
            $availableOptions[$optionKey] = $option;
        }
    }

    return $availableOptions;
}

function themeAvailableSelectValue(array $definition, string $currentValue): string
{
    $availableOptions = themeAvailableSelectOptions($definition);
    if (isset($availableOptions[$currentValue])) {
        return $currentValue;
    }

    $defaultValue = (string)($definition['default'] ?? '');
    if (isset($availableOptions[$defaultValue])) {
        return $defaultValue;
    }

    $firstKey = array_key_first($availableOptions);
    return is_string($firstKey) ? $firstKey : $defaultValue;
}

function themeApplySettingDefaultOverrides(array $definitions, array $overrides): array
{
    foreach ($overrides as $settingKey => $overrideValue) {
        if (!is_string($settingKey) || !isset($definitions[$settingKey])) {
            continue;
        }

        $validated = themeValidateSettingValue($definitions[$settingKey], $overrideValue);
        if ($validated['valid']) {
            $definitions[$settingKey]['default'] = (string)$validated['value'];
        }
    }

    return $definitions;
}

function themeSettingDefinitions(?string $themeKey = null): array
{
    $manifest = themeManifest($themeKey);
    return is_array($manifest['settings'] ?? null) ? $manifest['settings'] : [];
}

function themeSettingStorageKey(?string $themeKey = null): string
{
    return 'theme_settings_' . resolveThemeName($themeKey);
}

function themePersistedSettings(?string $themeKey = null): array
{
    $raw = trim(getSetting(themeSettingStorageKey($themeKey), ''));
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function themePreviewAllowed(): bool
{
    return isLoggedIn() && !isPublicUser();
}

function themePreviewData(): array
{
    if (!themePreviewAllowed()) {
        return [];
    }

    $preview = $_SESSION['cms_theme_preview'] ?? null;
    if (!is_array($preview)) {
        return [];
    }

    $themeKey = trim((string)($preview['theme'] ?? ''));
    if ($themeKey === '' || !themeExists($themeKey)) {
        unset($_SESSION['cms_theme_preview']);
        return [];
    }

    $validation = themeSettingsValidation(
        is_array($preview['settings'] ?? null) ? $preview['settings'] : [],
        $themeKey
    );

    return [
        'theme' => $themeKey,
        'settings' => $validation['values'],
    ];
}

function themePreviewIsActive(): bool
{
    return themePreviewData() !== [];
}

function setThemePreview(string $themeKey, array $settings = []): void
{
    if (!themePreviewAllowed() || !themeExists($themeKey)) {
        return;
    }

    $validation = themeSettingsValidation($settings, $themeKey);
    $_SESSION['cms_theme_preview'] = [
        'theme' => $themeKey,
        'settings' => $validation['values'],
    ];
}

function clearThemePreview(): void
{
    unset($_SESSION['cms_theme_preview']);
}

function themeDefaultSettings(?string $themeKey = null): array
{
    $defaults = [];
    foreach (themeSettingDefinitions($themeKey) as $settingKey => $definition) {
        $defaults[$settingKey] = (string)$definition['default'];
    }
    return $defaults;
}

function themeStoredSettings(?string $themeKey = null): array
{
    $resolvedTheme = resolveThemeName($themeKey);
    $preview = themePreviewData();
    if ($preview !== [] && $preview['theme'] === $resolvedTheme) {
        return is_array($preview['settings']) ? $preview['settings'] : [];
    }

    return themePersistedSettings($resolvedTheme);
}

function themeValidateSettingValue(array $definition, mixed $rawValue, bool $enforceAvailability = false): array
{
    if ($definition['type'] === 'color') {
        $normalizedColor = themeNormalizeHexColor((string)$rawValue);
        if ($normalizedColor === null) {
            return [
                'valid' => false,
                'value' => (string)$definition['default'],
                'error' => 'Musí být zadaná platná hex barva.',
            ];
        }

        foreach ((array)$definition['contrast_with'] as $contrastColor) {
            $ratio = themeContrastRatio($normalizedColor, $contrastColor);
            if ($ratio !== null && $ratio < (float)$definition['min_contrast']) {
                return [
                    'valid' => false,
                    'value' => (string)$definition['default'],
                    'error' => 'Nesplňuje minimální kontrast ' . number_format((float)$definition['min_contrast'], 1, ',', '') . ':1.',
                ];
            }
        }

        return [
            'valid' => true,
            'value' => $normalizedColor,
            'error' => '',
        ];
    }

    $value = trim((string)$rawValue);
    if (!isset($definition['options'][$value])) {
        return [
            'valid' => false,
            'value' => (string)$definition['default'],
            'error' => 'Vybraná možnost není dostupná.',
        ];
    }

    if ($enforceAvailability && !themeSelectOptionIsAvailable($definition['options'][$value])) {
        return [
            'valid' => false,
            'value' => (string)$definition['default'],
            'error' => 'Vybraná možnost není dostupná, protože potřebný modul není zapnutý.',
        ];
    }

    return [
        'valid' => true,
        'value' => $value,
        'error' => '',
    ];
}

function themeSettingsValues(?string $themeKey = null): array
{
    $definitions = themeSettingDefinitions($themeKey);
    $stored = themeStoredSettings($themeKey);
    $values = themeDefaultSettings($themeKey);

    foreach ($definitions as $settingKey => $definition) {
        if (!array_key_exists($settingKey, $stored)) {
            continue;
        }

        $validated = themeValidateSettingValue($definition, $stored[$settingKey]);
        $values[$settingKey] = (string)$validated['value'];
    }

    return $values;
}

function themePersistedSettingsValues(?string $themeKey = null): array
{
    $definitions = themeSettingDefinitions($themeKey);
    $stored = themePersistedSettings($themeKey);
    $values = themeDefaultSettings($themeKey);

    foreach ($definitions as $settingKey => $definition) {
        if (!array_key_exists($settingKey, $stored)) {
            continue;
        }

        $validated = themeValidateSettingValue($definition, $stored[$settingKey]);
        $values[$settingKey] = (string)$validated['value'];
    }

    return $values;
}

function themeSettingValue(string $settingKey, ?string $themeKey = null): string
{
    $values = themeSettingsValues($themeKey);
    return (string)($values[$settingKey] ?? '');
}

function themeSettingsValidation(array $submittedValues, ?string $themeKey = null): array
{
    $definitions = themeSettingDefinitions($themeKey);
    $stored = themePersistedSettings($themeKey);
    $values = [];
    $errors = [];

    foreach ($definitions as $settingKey => $definition) {
        $storedCandidate = array_key_exists($settingKey, $stored)
            ? $stored[$settingKey]
            : $definition['default'];
        $storedValidated = themeValidateSettingValue($definition, $storedCandidate);
        $preservedValue = (string)$storedValidated['value'];

        if (!themeSettingIsAvailable($definition)) {
            $values[$settingKey] = $preservedValue;
            continue;
        }

        if (!array_key_exists($settingKey, $submittedValues)) {
            $values[$settingKey] = $preservedValue;
            continue;
        }

        $validated = themeValidateSettingValue($definition, $submittedValues[$settingKey], true);
        if (!$validated['valid']) {
            $values[$settingKey] = $preservedValue;
            $errors[$settingKey] = (string)$validated['error'];
            continue;
        }

        $values[$settingKey] = (string)$validated['value'];
    }

    return [
        'values' => $values,
        'errors' => $errors,
    ];
}

function saveThemeSettings(array $values, ?string $themeKey = null): void
{
    saveSetting(
        themeSettingStorageKey($themeKey),
        json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function resetThemeSettings(?string $themeKey = null): void
{
    saveSetting(themeSettingStorageKey($themeKey), '');
}

function themeCssVariables(?string $themeKey = null): array
{
    $definitions = themeSettingDefinitions($themeKey);
    $values = themeSettingsValues($themeKey);
    $cssVariables = [];

    foreach ($definitions as $settingKey => $definition) {
        $selectedValue = $values[$settingKey] ?? (string)$definition['default'];

        if ($definition['type'] === 'color') {
            $cssVariables[$definition['css_var']] = $selectedValue;
            continue;
        }

        $option = $definition['options'][$selectedValue] ?? $definition['options'][$definition['default']] ?? null;
        if ($option === null) {
            continue;
        }

        foreach ($option['css_vars'] as $cssVarName => $cssVarValue) {
            $cssVariables[$cssVarName] = $cssVarValue;
        }
    }

    foreach ($cssVariables as $cssVarName => $cssVarValue) {
        $rgb = themeHexToRgb($cssVarValue);
        if ($rgb === null) {
            continue;
        }

        $cssVariables[$cssVarName . '-rgb'] = $rgb['r'] . ', ' . $rgb['g'] . ', ' . $rgb['b'];
    }

    return $cssVariables;
}

function themeCssVariablesStyleTag(?string $themeKey = null): string
{
    $cssVariables = themeCssVariables($themeKey);
    if ($cssVariables === []) {
        return '';
    }

    $declarations = [];
    foreach ($cssVariables as $cssVarName => $cssVarValue) {
        if (!themeIsSafeCssVariable($cssVarName) || !themeCssValueIsSafe($cssVarValue)) {
            continue;
        }

        $declarations[] = '--' . $cssVarName . ':' . $cssVarValue;
    }

    if ($declarations === []) {
        return '';
    }

    return "  <style>:root{" . implode(';', $declarations) . ";}</style>\n";
}

function themeDirectoryPath(string $themeKey): string
{
    if (!isValidThemeKey($themeKey)) {
        throw new InvalidArgumentException('Invalid theme key.');
    }

    return themeBasePath() . DIRECTORY_SEPARATOR . $themeKey;
}

function themeRawManifest(?string $themeKey = null): array
{
    $resolvedTheme = resolveThemeName($themeKey);
    $manifestPath = themeDirectoryPath($resolvedTheme) . DIRECTORY_SEPARATOR . 'theme.json';
    if (!is_file($manifestPath)) {
        return [];
    }

    $decoded = json_decode((string)file_get_contents($manifestPath), true);
    return is_array($decoded) ? $decoded : [];
}

function themePortablePackageType(): string
{
    return 'kora-theme';
}

function themePortablePackageSchema(): int
{
    return 1;
}

function themePortablePackageMode(): string
{
    return 'portable-static';
}

function themePortablePackageAllowedExtensions(): array
{
    return [
        'avif',
        'css',
        'gif',
        'ico',
        'jpeg',
        'jpg',
        'otf',
        'png',
        'svg',
        'ttf',
        'webp',
        'woff',
        'woff2',
    ];
}

function themePortablePackageMaxFileCount(): int
{
    return 48;
}

function themePortablePackageMaxFileBytes(): int
{
    return 2 * 1024 * 1024;
}

function themePortablePackageMaxTotalBytes(): int
{
    return 5 * 1024 * 1024;
}

function themePortableStringLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function themePortablePackageManifest(?string $themeKey = null): array
{
    $resolvedTheme = resolveThemeName($themeKey);
    $rawManifest = themeRawManifest($resolvedTheme);
    $runtimeManifest = themeManifest($resolvedTheme);

    $version = trim((string)($rawManifest['version'] ?? $runtimeManifest['version'] ?? '1.0.0'));
    if ($version === '') {
        $version = '1.0.0';
    }

    $author = trim((string)($rawManifest['author'] ?? $runtimeManifest['author'] ?? 'Kora CMS'));
    if ($author === '') {
        $author = 'Kora CMS';
    }

    return [
        'key' => $resolvedTheme,
        'package' => [
            'type' => themePortablePackageType(),
            'schema' => themePortablePackageSchema(),
            'mode' => themePortablePackageMode(),
            'base_theme' => defaultThemeName(),
        ],
        'name' => trim((string)($rawManifest['name'] ?? $runtimeManifest['name'] ?? $resolvedTheme)),
        'version' => $version,
        'author' => $author,
        'description' => trim((string)($rawManifest['description'] ?? $runtimeManifest['description'] ?? '')),
        'preview' => themeNormalizePreviewData(
            is_array($rawManifest['preview'] ?? null) ? $rawManifest['preview'] : []
        ),
        'settings_defaults' => themePersistedSettingsValues($resolvedTheme),
    ];
}

function themeValidatePortablePackageManifest(array $manifest, string $themeKey): array
{
    $errors = [];
    $allowedKeys = ['key', 'package', 'name', 'version', 'author', 'description', 'preview', 'settings_defaults'];
    foreach (array_keys($manifest) as $manifestKey) {
        if (is_string($manifestKey) && !in_array($manifestKey, $allowedKeys, true)) {
            $errors[] = 'Manifest obsahuje nepodporovanou položku `' . $manifestKey . '`.';
        }
    }

    $manifestThemeKey = trim((string)($manifest['key'] ?? ''));
    if ($manifestThemeKey === '' || !isValidThemeKey($manifestThemeKey)) {
        $errors[] = 'Manifest musí obsahovat platný klíč šablony.';
    } elseif ($manifestThemeKey !== $themeKey) {
        $errors[] = 'Klíč v manifestu neodpovídá adresáři ZIP balíčku.';
    }

    $package = is_array($manifest['package'] ?? null) ? $manifest['package'] : [];
    if (($package['type'] ?? '') !== themePortablePackageType()) {
        $errors[] = 'Balíček nepoužívá podporovaný formát Kora theme package.';
    }
    if ((int)($package['schema'] ?? 0) !== themePortablePackageSchema()) {
        $errors[] = 'Balíček používá nepodporovanou verzi schema.';
    }
    if (($package['mode'] ?? '') !== themePortablePackageMode()) {
        $errors[] = 'Balíček musí být typu portable-static.';
    }

    $name = trim((string)($manifest['name'] ?? ''));
    if ($name === '') {
        $errors[] = 'Manifest musí obsahovat název šablony.';
    } elseif (themePortableStringLength($name) > 80) {
        $errors[] = 'Název šablony je příliš dlouhý.';
    }

    $version = trim((string)($manifest['version'] ?? ''));
    if ($version === '') {
        $errors[] = 'Manifest musí obsahovat verzi šablony.';
    } elseif (preg_match('/^[0-9A-Za-z][0-9A-Za-z._+-]{0,31}$/', $version) !== 1) {
        $errors[] = 'Verze šablony obsahuje nepodporované znaky.';
    }

    $author = trim((string)($manifest['author'] ?? ''));
    if ($author === '') {
        $errors[] = 'Manifest musí obsahovat autora šablony.';
    } elseif (themePortableStringLength($author) > 80) {
        $errors[] = 'Autor šablony je příliš dlouhý.';
    }

    $description = trim((string)($manifest['description'] ?? ''));
    if (themePortableStringLength($description) > 280) {
        $errors[] = 'Popis šablony je příliš dlouhý.';
    }

    $preview = is_array($manifest['preview'] ?? null) ? $manifest['preview'] : [];
    $normalizedPreview = themeNormalizePreviewData($preview);
    if ($preview !== []) {
        $rawColors = is_array($preview['colors'] ?? null) ? $preview['colors'] : [];
        if ($rawColors !== [] && count($rawColors) !== count($normalizedPreview['colors'])) {
            $errors[] = 'Náhled šablony obsahuje neplatné barvy.';
        }
    }
    if (themePortableStringLength($normalizedPreview['summary']) > 180) {
        $errors[] = 'Souhrn náhledu šablony je příliš dlouhý.';
    }
    if (count($normalizedPreview['colors']) > 6) {
        $errors[] = 'Náhled šablony může obsahovat nejvýše 6 barev.';
    }

    $baseDefinitions = themeSettingDefinitions(defaultThemeName());
    $rawDefaults = is_array($manifest['settings_defaults'] ?? null) ? $manifest['settings_defaults'] : [];
    $sanitizedDefaults = [];
    foreach ($rawDefaults as $settingKey => $settingValue) {
        if (!is_string($settingKey) || !isset($baseDefinitions[$settingKey])) {
            $errors[] = 'Balíček obsahuje nepodporované výchozí theme setting `' . (string)$settingKey . '`.';
            continue;
        }

        $validated = themeValidateSettingValue($baseDefinitions[$settingKey], $settingValue);
        if (!$validated['valid']) {
            $errors[] = 'Výchozí hodnota `' . $settingKey . '` není platná: ' . $validated['error'];
            continue;
        }

        $sanitizedDefaults[$settingKey] = (string)$validated['value'];
    }

    return [
        'valid' => $errors === [],
        'errors' => $errors,
        'manifest' => [
            'key' => $themeKey,
            'package' => [
                'type' => themePortablePackageType(),
                'schema' => themePortablePackageSchema(),
                'mode' => themePortablePackageMode(),
                'base_theme' => defaultThemeName(),
            ],
            'name' => $name !== '' ? $name : $themeKey,
            'version' => $version !== '' ? $version : '1.0.0',
            'author' => $author !== '' ? $author : 'Kora CMS',
            'description' => $description,
            'preview' => $normalizedPreview,
            'settings_defaults' => $sanitizedDefaults,
        ],
    ];
}

function themePortableCssIsSafe(string $css): bool
{
    $blockedPatterns = [
        '/@import\s+(?:url\()?\s*[\'"]?(?:https?:)?\/\//i',
        '/url\(\s*[\'"]?(?:https?:|data:|javascript:|\/\/)/i',
        '/expression\s*\(/i',
        '/behavior\s*:/i',
    ];

    foreach ($blockedPatterns as $pattern) {
        if (preg_match($pattern, $css) === 1) {
            return false;
        }
    }

    return true;
}

function themePortableSvgIsSafe(string $svg): bool
{
    return preg_match('/<script|javascript:|onload\s*=|onerror\s*=|<foreignObject/i', $svg) !== 1;
}

function themePortablePackageFileValidation(string $relativePath, string $contents): array
{
    $normalizedPath = trim(str_replace('\\', '/', $relativePath), '/');
    if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
        return ['valid' => false, 'error' => 'Balíček obsahuje neplatnou cestu souboru.'];
    }

    if (preg_match('/(^|\/)\.[^\/]/', $normalizedPath) === 1) {
        return ['valid' => false, 'error' => 'Balíček obsahuje skrytý soubor, který není podporovaný.'];
    }

    if ($normalizedPath === 'theme.json') {
        return ['valid' => true, 'error' => ''];
    }

    if (!str_starts_with($normalizedPath, 'assets/')) {
        return ['valid' => false, 'error' => 'Balíček smí obsahovat jen `theme.json` a soubory v `assets/`.'];
    }

    if (preg_match('/^[A-Za-z0-9._\/-]+$/', $normalizedPath) !== 1) {
        return ['valid' => false, 'error' => 'Balíček obsahuje nepodporované znaky v cestě souboru.'];
    }

    $extension = strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, themePortablePackageAllowedExtensions(), true)) {
        return ['valid' => false, 'error' => 'Balíček obsahuje nepovolený typ assetu `' . $normalizedPath . '`.'];
    }

    if ($extension === 'css' && !themePortableCssIsSafe($contents)) {
        return ['valid' => false, 'error' => 'CSS asset obsahuje nepovolené externí nebo nebezpečné odkazy.'];
    }

    if ($extension === 'svg' && !themePortableSvgIsSafe($contents)) {
        return ['valid' => false, 'error' => 'SVG asset obsahuje nepovolený aktivní obsah.'];
    }

    return ['valid' => true, 'error' => ''];
}

function themeDeleteDirectory(string $path): void
{
    if ($path === '' || !file_exists($path)) {
        return;
    }

    if (is_file($path)) {
        @unlink($path);
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($path);
}

function themeZipDosDateTime(?int $timestamp = null): array
{
    $parts = getdate($timestamp ?? time());
    $year = max(1980, min(2107, (int)$parts['year']));
    $month = max(1, min(12, (int)$parts['mon']));
    $day = max(1, min(31, (int)$parts['mday']));
    $hour = max(0, min(23, (int)$parts['hours']));
    $minute = max(0, min(59, (int)$parts['minutes']));
    $second = max(0, min(59, (int)$parts['seconds']));

    return [
        'time' => ($hour << 11) | ($minute << 5) | intdiv($second, 2),
        'date' => (($year - 1980) << 9) | ($month << 5) | $day,
    ];
}

function themeZipUnsignedIntString(int $value): string
{
    return sprintf('%u', $value);
}

function themeCreateZipArchive(string $path, array $files): bool
{
    $archive = '';
    $centralDirectory = '';
    $offset = 0;
    $dosStamp = themeZipDosDateTime();

    foreach ($files as $entryName => $contents) {
        $entryName = str_replace('\\', '/', (string)$entryName);
        $contents = (string)$contents;
        $crc = (int)sprintf('%u', crc32($contents));
        $size = strlen($contents);
        $nameLength = strlen($entryName);

        $localHeader = "PK\x03\x04"
            . pack('v', 20)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', $dosStamp['time'])
            . pack('v', $dosStamp['date'])
            . pack('V', $crc)
            . pack('V', $size)
            . pack('V', $size)
            . pack('v', $nameLength)
            . pack('v', 0)
            . $entryName;

        $archive .= $localHeader . $contents;

        $centralDirectory .= "PK\x01\x02"
            . pack('v', 20)
            . pack('v', 20)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', $dosStamp['time'])
            . pack('v', $dosStamp['date'])
            . pack('V', $crc)
            . pack('V', $size)
            . pack('V', $size)
            . pack('v', $nameLength)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', 0)
            . pack('V', $offset)
            . $entryName;

        $offset += strlen($localHeader) + $size;
    }

    $entriesCount = count($files);
    $endOfCentralDirectory = "PK\x05\x06"
        . pack('v', 0)
        . pack('v', 0)
        . pack('v', $entriesCount)
        . pack('v', $entriesCount)
        . pack('V', strlen($centralDirectory))
        . pack('V', strlen($archive))
        . pack('v', 0);

    return file_put_contents($path, $archive . $centralDirectory . $endOfCentralDirectory) !== false;
}

function themeReadZipArchive(string $path): array
{
    $binary = @file_get_contents($path);
    if (!is_string($binary) || $binary === '') {
        return [
            'ok' => false,
            'errors' => ['ZIP balíček se nepodařilo přečíst.'],
            'files' => [],
        ];
    }

    $eocdPosition = strrpos($binary, "PK\x05\x06");
    if ($eocdPosition === false || strlen($binary) - $eocdPosition < 22) {
        return [
            'ok' => false,
            'errors' => ['ZIP balíček nemá platnou koncovou hlavičku.'],
            'files' => [],
        ];
    }

    $eocd = unpack(
        'vdisk/vdisk_start/ventries_disk/ventries_total/Vcentral_size/Vcentral_offset/vcomment_length',
        substr($binary, $eocdPosition + 4, 18)
    );
    if (!is_array($eocd)) {
        return [
            'ok' => false,
            'errors' => ['ZIP balíček má poškozenou koncovou hlavičku.'],
            'files' => [],
        ];
    }

    $offset = (int)$eocd['central_offset'];
    $entriesTotal = (int)$eocd['entries_total'];
    $files = [];
    $errors = [];

    for ($i = 0; $i < $entriesTotal; $i++) {
        if (substr($binary, $offset, 4) !== "PK\x01\x02") {
            $errors[] = 'ZIP balíček má poškozený centrální adresář.';
            break;
        }

        $central = unpack(
            'vmade_by/vneeded/vflags/vcompression/vtime/vdate/Vcrc/Vcompressed_size/Vuncompressed_size/'
            . 'vname_length/vextra_length/vcomment_length/vdisk_number/vinternal_attr/Vexternal_attr/Vlocal_offset',
            substr($binary, $offset + 4, 42)
        );
        if (!is_array($central)) {
            $errors[] = 'ZIP balíček má nečitelný záznam v centrálním adresáři.';
            break;
        }

        $offset += 46;
        $entryName = substr($binary, $offset, (int)$central['name_length']);
        $offset += (int)$central['name_length'] + (int)$central['extra_length'] + (int)$central['comment_length'];

        if (($central['flags'] & 0x0001) === 0x0001) {
            $errors[] = 'ZIP balíček používá šifrované soubory, které nejsou podporované.';
            continue;
        }

        $localOffset = (int)$central['local_offset'];
        if (substr($binary, $localOffset, 4) !== "PK\x03\x04") {
            $errors[] = 'ZIP balíček má neplatnou lokální hlavičku souboru.';
            continue;
        }

        $local = unpack(
            'vneeded/vflags/vcompression/vtime/vdate/Vcrc/Vcompressed_size/Vuncompressed_size/vname_length/vextra_length',
            substr($binary, $localOffset + 4, 26)
        );
        if (!is_array($local)) {
            $errors[] = 'ZIP balíček má nečitelnou lokální hlavičku souboru.';
            continue;
        }

        $dataOffset = $localOffset + 30 + (int)$local['name_length'] + (int)$local['extra_length'];
        $compressedData = substr($binary, $dataOffset, (int)$central['compressed_size']);

        if ((int)$central['compression'] === 0) {
            $contents = $compressedData;
        } elseif ((int)$central['compression'] === 8) {
            $inflated = @gzinflate($compressedData);
            if (!is_string($inflated)) {
                $errors[] = 'ZIP balíček obsahuje poškozený deflate stream.';
                continue;
            }
            $contents = $inflated;
        } else {
            $errors[] = 'ZIP balíček používá nepodporovanou kompresní metodu.';
            continue;
        }

        if (strlen($contents) !== (int)$central['uncompressed_size']) {
            $errors[] = 'ZIP balíček obsahuje soubor s neplatnou rozbalenou velikostí.';
            continue;
        }

        if (themeZipUnsignedIntString(crc32($contents)) !== themeZipUnsignedIntString((int)$central['crc'])) {
            $errors[] = 'ZIP balíček obsahuje soubor s neplatným CRC.';
            continue;
        }

        $files[$entryName] = $contents;
    }

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'files' => $files,
    ];
}

function themeBuildPortablePackage(?string $themeKey = null): array
{
    $resolvedTheme = resolveThemeName($themeKey);
    $manifestValidation = themeValidatePortablePackageManifest(
        themePortablePackageManifest($resolvedTheme),
        $resolvedTheme
    );
    if (!$manifestValidation['valid']) {
        return [
            'ok' => false,
            'errors' => $manifestValidation['errors'],
        ];
    }

    $files = [
        'theme.json' => json_encode(
            $manifestValidation['manifest'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . "\n",
    ];
    $errors = [];
    $totalBytes = strlen($files['theme.json']);
    $assetsPath = themeDirectoryPath($resolvedTheme) . DIRECTORY_SEPARATOR . 'assets';

    if (is_dir($assetsPath)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($assetsPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $fullPath = $item->getPathname();
            $relativePath = 'assets/' . str_replace('\\', '/', substr($fullPath, strlen($assetsPath) + 1));
            $contents = (string)file_get_contents($fullPath);
            $validation = themePortablePackageFileValidation($relativePath, $contents);
            if (!$validation['valid']) {
                $errors[] = $validation['error'];
                continue;
            }

            $size = strlen($contents);
            if ($size > themePortablePackageMaxFileBytes()) {
                $errors[] = 'Soubor `' . $relativePath . '` je příliš velký pro portable theme package.';
                continue;
            }

            $totalBytes += $size;
            if ($totalBytes > themePortablePackageMaxTotalBytes()) {
                $errors[] = 'Portable theme package překračuje maximální povolenou velikost.';
                break;
            }

            $files[$relativePath] = $contents;
        }
    }

    if (!isset($files['assets/public.css'])) {
        $errors[] = 'Šablona musí obsahovat `assets/public.css`.';
    }

    if ($errors !== []) {
        return [
            'ok' => false,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    $tempZipPath = tempnam(sys_get_temp_dir(), 'kora-theme-');
    if ($tempZipPath === false) {
        return [
            'ok' => false,
            'errors' => ['Nepodařilo se vytvořit dočasný ZIP balíček šablony.'],
        ];
    }

    if (!themeCreateZipArchive($tempZipPath, array_combine(
        array_map(static fn($relativePath) => $resolvedTheme . '/' . $relativePath, array_keys($files)),
        array_values($files)
    ) ?: [])) {
        return [
            'ok' => false,
            'errors' => ['Nepodařilo se otevřít ZIP balíček pro export šablony.'],
        ];
    }

    $versionSlug = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$manifestValidation['manifest']['version']);
    $versionSlug = $versionSlug !== '' ? $versionSlug : '1.0.0';

    return [
        'ok' => true,
        'errors' => [],
        'path' => $tempZipPath,
        'filename' => 'kora-theme-' . $resolvedTheme . '-' . $versionSlug . '.zip',
        'manifest' => $manifestValidation['manifest'],
    ];
}

function themeUploadErrorMessage(int $uploadError): string
{
    return match ($uploadError) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Nahraný ZIP balíček je příliš velký.',
        UPLOAD_ERR_PARTIAL => 'ZIP balíček se nepodařilo nahrát celý.',
        UPLOAD_ERR_NO_TMP_DIR => 'Na serveru chybí dočasný adresář pro upload.',
        UPLOAD_ERR_CANT_WRITE => 'Server nedokázal uložit nahraný ZIP balíček na disk.',
        UPLOAD_ERR_EXTENSION => 'Upload ZIP balíčku byl zastaven rozšířením PHP.',
        UPLOAD_ERR_NO_FILE => 'Nebyl vybrán žádný ZIP balíček šablony.',
        default => 'Nahrání ZIP balíčku šablony selhalo.',
    };
}

function themeImportPortablePackageUpload(?array $upload): array
{
    if (!is_array($upload)) {
        return [
            'ok' => false,
            'errors' => ['Nebyl předán žádný ZIP balíček šablony.'],
        ];
    }

    $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'ok' => false,
            'errors' => [themeUploadErrorMessage($uploadError)],
        ];
    }

    $tmpName = (string)($upload['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return [
            'ok' => false,
            'errors' => ['Server nedostal platný upload ZIP balíčku šablony.'],
        ];
    }

    $zipResult = themeReadZipArchive($tmpName);
    if (!$zipResult['ok']) {
        return [
            'ok' => false,
            'errors' => $zipResult['errors'],
        ];
    }

    $archiveFiles = $zipResult['files'];
    $files = [];
    $errors = [];
    $packageThemeKey = '';
    $totalBytes = 0;

    if ($archiveFiles === []) {
        $errors[] = 'ZIP balíček šablony je prázdný.';
    }
    if (count($archiveFiles) > themePortablePackageMaxFileCount()) {
        $errors[] = 'ZIP balíček obsahuje příliš mnoho souborů.';
    }

    foreach ($archiveFiles as $entryName => $contents) {
        $entryName = str_replace('\\', '/', trim((string)$entryName, '/'));
        if ($entryName === '') {
            continue;
        }

        $segments = array_values(array_filter(explode('/', $entryName), static fn($segment) => $segment !== ''));
        if ($segments === []) {
            continue;
        }

        $baseName = end($segments);
        if ($segments[0] === '__MACOSX' || $baseName === '.DS_Store' || str_starts_with($baseName, '._')) {
            continue;
        }

        if (count($segments) < 2) {
            $errors[] = 'ZIP balíček musí mít jediný kořenový adresář s obsahem šablony.';
            break;
        }

        $rootThemeKey = array_shift($segments);
        if (!is_string($rootThemeKey) || !isValidThemeKey($rootThemeKey)) {
            $errors[] = 'ZIP balíček používá neplatný kořenový adresář šablony.';
            break;
        }

        if ($packageThemeKey === '') {
            $packageThemeKey = $rootThemeKey;
        } elseif ($packageThemeKey !== $rootThemeKey) {
            $errors[] = 'ZIP balíček obsahuje více kořenových adresářů šablon.';
            break;
        }

        $relativePath = implode('/', $segments);
        $validation = themePortablePackageFileValidation($relativePath, $contents);
        if (!$validation['valid']) {
            $errors[] = $validation['error'];
            continue;
        }

        if (isset($files[$relativePath])) {
            $errors[] = 'ZIP balíček obsahuje duplicitní soubor `' . $relativePath . '`.';
            continue;
        }

        $size = strlen($contents);
        if ($size > themePortablePackageMaxFileBytes()) {
            $errors[] = 'Soubor `' . $relativePath . '` je příliš velký.';
            continue;
        }

        $totalBytes += $size;
        if ($totalBytes > themePortablePackageMaxTotalBytes()) {
            $errors[] = 'ZIP balíček překračuje maximální povolenou velikost po rozbalení.';
            break;
        }

        $files[$relativePath] = $contents;
    }

    if ($packageThemeKey === '') {
        $errors[] = 'ZIP balíček neobsahuje platný kořenový adresář šablony.';
    }
    if (!isset($files['theme.json'])) {
        $errors[] = 'ZIP balíček musí obsahovat soubor `theme.json`.';
    }
    if (!isset($files['assets/public.css'])) {
        $errors[] = 'ZIP balíček musí obsahovat soubor `assets/public.css`.';
    }
    if ($packageThemeKey !== '' && themeExists($packageThemeKey)) {
        $errors[] = 'Šablona s klíčem `' . $packageThemeKey . '` už na serveru existuje.';
    }

    $decodedManifest = isset($files['theme.json']) ? json_decode($files['theme.json'], true) : null;
    if (!is_array($decodedManifest)) {
        $errors[] = 'Soubor `theme.json` neobsahuje platný JSON manifest.';
    }

    $manifestValidation = ['valid' => false, 'errors' => [], 'manifest' => []];
    if ($packageThemeKey !== '' && is_array($decodedManifest)) {
        $manifestValidation = themeValidatePortablePackageManifest($decodedManifest, $packageThemeKey);
        if (!$manifestValidation['valid']) {
            $errors = array_merge($errors, $manifestValidation['errors']);
        }
    }

    if ($errors !== []) {
        return [
            'ok' => false,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    $tempImportDir = themeBasePath() . DIRECTORY_SEPARATOR . '__import_' . bin2hex(random_bytes(8));
    $tempThemeDir = $tempImportDir . DIRECTORY_SEPARATOR . $packageThemeKey;
    $targetThemeDir = themeDirectoryPath($packageThemeKey);

    try {
        if (!is_dir($tempThemeDir) && !mkdir($tempThemeDir, 0775, true) && !is_dir($tempThemeDir)) {
            throw new RuntimeException('Nepodařilo se vytvořit dočasný adresář pro import šablony.');
        }

        $files['theme.json'] = json_encode(
            $manifestValidation['manifest'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . "\n";

        foreach ($files as $relativePath => $contents) {
            $destination = $tempThemeDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $destinationDir = dirname($destination);
            if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
                throw new RuntimeException('Nepodařilo se připravit strukturu souborů pro import šablony.');
            }

            if (file_put_contents($destination, $contents) === false) {
                throw new RuntimeException('Nepodařilo se uložit soubor `' . $relativePath . '` do importované šablony.');
            }
        }

        if (!@rename($tempThemeDir, $targetThemeDir)) {
            throw new RuntimeException('Nepodařilo se přesunout importovanou šablonu do adresáře `themes/`.');
        }
        @rmdir($tempImportDir);
    } catch (Throwable $exception) {
        themeDeleteDirectory($tempImportDir);
        return [
            'ok' => false,
            'errors' => [$exception->getMessage()],
        ];
    }

    return [
        'ok' => true,
        'errors' => [],
        'theme_key' => $packageThemeKey,
        'manifest' => $manifestValidation['manifest'],
    ];
}

function themeSafeRelativePath(string $path): string
{
    $normalized = trim(str_replace('\\', '/', $path), '/');
    if ($normalized === ''
        || str_contains($normalized, '..')
        || preg_match('/[^A-Za-z0-9._\\/-]/', $normalized) === 1
    ) {
        throw new InvalidArgumentException('Invalid theme path.');
    }

    return $normalized;
}

function themeTemplatePath(string $bucket, string $templateName, ?string $themeKey = null): string
{
    $resolvedTheme = resolveThemeName($themeKey);
    $relativePath = themeSafeRelativePath($templateName);
    if (!str_ends_with($relativePath, '.php')) {
        $relativePath .= '.php';
    }

    $relativeFsPath = str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $candidates = [
        themeBasePath() . DIRECTORY_SEPARATOR . $resolvedTheme . DIRECTORY_SEPARATOR . $bucket . DIRECTORY_SEPARATOR . $relativeFsPath,
    ];

    if ($resolvedTheme !== defaultThemeName()) {
        $candidates[] = themeBasePath() . DIRECTORY_SEPARATOR . defaultThemeName() . DIRECTORY_SEPARATOR . $bucket . DIRECTORY_SEPARATOR . $relativeFsPath;
    }

    foreach ($candidates as $candidatePath) {
        if (is_file($candidatePath)) {
            return $candidatePath;
        }
    }

    throw new RuntimeException("Theme template not found: {$bucket}/{$relativePath}");
}

function themeViewPath(string $viewName, ?string $themeKey = null): string
{
    return themeTemplatePath('views', $viewName, $themeKey);
}

function themePartialPath(string $partialName, ?string $themeKey = null): string
{
    return themeTemplatePath('partials', $partialName, $themeKey);
}

function themeLayoutPath(string $layoutName = 'base', ?string $themeKey = null): string
{
    return themeTemplatePath('layouts', $layoutName, $themeKey);
}

function themeAssetUrl(string $path, ?string $themeKey = null): string
{
    $resolvedTheme = resolveThemeName($themeKey);
    $relativePath = themeSafeRelativePath($path);
    $encodedSegments = array_map('rawurlencode', explode('/', $relativePath));

    return BASE_URL
        . '/themes/'
        . rawurlencode($resolvedTheme)
        . '/'
        . implode('/', $encodedSegments);
}

function themePreviewAssetPath(?string $themeKey = null): string
{
    $resolvedTheme = resolveThemeName($themeKey);
    $themePath = themeDirectoryPath($resolvedTheme);

    foreach (['preview.svg', 'preview.webp', 'preview.png', 'preview.jpg', 'preview.jpeg'] as $filename) {
        $candidate = $themePath . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $filename;
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return '';
}

function themePreviewAssetUrl(?string $themeKey = null): string
{
    $previewPath = themePreviewAssetPath($themeKey);
    if ($previewPath === '') {
        return '';
    }

    return themeAssetUrl('assets/' . basename($previewPath), $themeKey);
}

function renderThemeTemplate(string $bucket, string $templateName, array $data = [], ?string $themeKey = null): string
{
    $resolvedTheme = resolveThemeName($themeKey);
    $templatePath = themeTemplatePath($bucket, $templateName, $resolvedTheme);
    $themeManifest = themeManifest($resolvedTheme);

    ob_start();
    extract($data, EXTR_SKIP);
    include $templatePath;
    return (string)ob_get_clean();
}

function renderThemeView(string $viewName, array $data = [], ?string $themeKey = null): string
{
    return renderThemeTemplate('views', $viewName, $data, $themeKey);
}

function renderThemePartial(string $partialName, array $data = [], ?string $themeKey = null): string
{
    return renderThemeTemplate('partials', $partialName, $data, $themeKey);
}

function renderPublicPage(array $pageData): void
{
    $themeName = resolveThemeName(isset($pageData['theme']) ? (string)$pageData['theme'] : null);
    $siteName = getSetting('site_name', 'Kora CMS');
    $pageTitle = trim((string)($pageData['title'] ?? $siteName));
    if ($pageTitle === '') {
        $pageTitle = $siteName;
    }

    $viewName = trim((string)($pageData['view'] ?? ''));
    if ($viewName === '') {
        throw new InvalidArgumentException('Missing public theme view.');
    }

    $meta = is_array($pageData['meta'] ?? null) ? $pageData['meta'] : [];
    if (!isset($meta['title'])) {
        $meta['title'] = $pageTitle;
    }

    $bodyClasses = ['theme-public', 'theme-' . $themeName];
    $bodyClass = trim((string)($pageData['body_class'] ?? ''));
    if ($bodyClass !== '') {
        $bodyClasses[] = $bodyClass;
    }
    $extraBodyClasses = is_array($pageData['body_classes'] ?? null) ? $pageData['body_classes'] : [];
    foreach ($extraBodyClasses as $extraBodyClass) {
        $extraBodyClass = trim((string)$extraBodyClass);
        if ($extraBodyClass !== '') {
            $bodyClasses[] = $extraBodyClass;
        }
    }
    if (isLoggedIn()) {
        $bodyClasses[] = 'has-admin-bar';
    }

    $contentHtml = renderThemeView(
        $viewName,
        is_array($pageData['view_data'] ?? null) ? $pageData['view_data'] : [],
        $themeName
    );

    $layoutPath = themeLayoutPath((string)($pageData['layout'] ?? 'base'), $themeName);
    $currentNav = (string)($pageData['current_nav'] ?? '');
    $adminEditUrl = (string)($pageData['admin_edit_url'] ?? '');
    $pageKind = (string)($pageData['page_kind'] ?? 'page');
    $showSiteDescription = (bool)($pageData['show_site_description'] ?? false);
    $siteDescription = (string)($pageData['site_description'] ?? getSetting('site_description', ''));
    $mainClass = trim('site-main ' . (string)($pageData['main_class'] ?? ''));
    $bodyClassAttr = implode(' ', array_unique(array_filter($bodyClasses)));
    $themeManifest = themeManifest($themeName);
    $extraHeadHtml = (string)($pageData['extra_head_html'] ?? '');

    $headerData = [
        'siteName' => $siteName,
        'siteDescription' => $siteDescription,
        'showSiteDescription' => $showSiteDescription,
        'currentNav' => $currentNav,
        'pageKind' => $pageKind,
    ];

    include $layoutPath;
}
