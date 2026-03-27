<?php
// Definice typů, profilů webu a popisků modulů – extrahováno z db.php

function boardTypeDefinitions(): array
{
    return [
        'document' => [
            'label' => 'Dokument',
            'public_label' => 'Dokument',
        ],
        'notice' => [
            'label' => 'Oznámení',
            'public_label' => 'Oznámení',
        ],
        'lost_found' => [
            'label' => 'Ztráty a nálezy',
            'public_label' => 'Ztráty a nálezy',
        ],
        'memorial' => [
            'label' => 'Parte / vzpomínka',
            'public_label' => 'Vzpomínka',
        ],
        'invitation' => [
            'label' => 'Pozvánka',
            'public_label' => 'Pozvánka',
        ],
        'alert' => [
            'label' => 'Upozornění',
            'public_label' => 'Upozornění',
        ],
    ];
}

function placeKindDefinitions(): array
{
    return [
        'sight' => ['label' => 'Památka a zajímavost'],
        'trip' => ['label' => 'Tip na výlet'],
        'service' => ['label' => 'Služba'],
        'food' => ['label' => 'Občerstvení'],
        'accommodation' => ['label' => 'Ubytování'],
        'experience' => ['label' => 'Zážitek'],
        'info' => ['label' => 'Informační místo'],
    ];
}

function downloadTypeDefinitions(): array
{
    return [
        'document' => ['label' => 'Dokument'],
        'software' => ['label' => 'Software'],
        'release' => ['label' => 'Aktualizace / release'],
        'archive' => ['label' => 'Archiv / balíček'],
        'template' => ['label' => 'Šablona / formulář'],
        'media' => ['label' => 'Média / prezentace'],
    ];
}

function normalizeBoardType(string $type): string
{
    $definitions = boardTypeDefinitions();
    return isset($definitions[$type]) ? $type : 'document';
}

function normalizePlaceKind(string $kind): string
{
    $definitions = placeKindDefinitions();
    return isset($definitions[$kind]) ? $kind : 'sight';
}

function normalizeDownloadType(string $type): string
{
    $definitions = downloadTypeDefinitions();
    return isset($definitions[$type]) ? $type : 'document';
}

function siteProfileDefinitions(): array
{
    return [
        'personal' => [
            'label' => 'Osobní web',
            'description' => 'Jednoduchý osobní web s důrazem na autora, články a několik základních stránek.',
            'theme' => 'editorial',
            'modules' => [
                'blog' => true,
                'news' => false,
                'chat' => false,
                'contact' => true,
                'gallery' => false,
                'events' => false,
                'podcast' => false,
                'places' => false,
                'newsletter' => false,
                'downloads' => false,
                'food' => false,
                'polls' => false,
                'faq' => false,
                'board' => false,
                'reservations' => false,
            ],
            'nav_order' => ['blog', 'contact', 'gallery', 'news', 'events', 'podcast', 'newsletter', 'downloads', 'faq', 'chat', 'places', 'food', 'polls', 'board', 'reservations'],
            'settings' => [
                'home_blog_count' => '6',
                'home_news_count' => '0',
                'home_board_count' => '0',
                'board_public_label' => 'Vývěska',
                'blog_per_page' => '10',
                'news_per_page' => '10',
                'events_per_page' => '10',
                'comments_enabled' => '1',
                'comment_moderation_mode' => 'known',
                'comment_close_days' => '0',
            ],
            'theme_settings' => [
                'header_layout' => 'centered',
                'container_width' => 'narrow',
                'home_layout' => 'editorial',
                'home_featured_module' => 'blog',
                'home_primary_order' => 'blog_news',
                'home_utility_order' => 'newsletter_cta_board_poll',
                'home_blog_visibility' => 'show',
                'home_news_visibility' => 'hide',
                'home_board_visibility' => 'hide',
                'home_poll_visibility' => 'hide',
                'home_newsletter_visibility' => 'hide',
                'home_cta_visibility' => 'hide',
            ],
        ],
        'blog' => [
            'label' => 'Blog / magazín',
            'description' => 'Obsahově orientovaný web s důrazem na články, odběr novinek a čitelnou domovskou stránku.',
            'theme' => 'editorial',
            'modules' => [
                'blog' => true,
                'news' => false,
                'chat' => false,
                'contact' => true,
                'gallery' => true,
                'events' => false,
                'podcast' => false,
                'places' => false,
                'newsletter' => true,
                'downloads' => false,
                'food' => false,
                'polls' => false,
                'faq' => false,
                'board' => false,
                'reservations' => false,
            ],
            'nav_order' => ['blog', 'gallery', 'contact', 'newsletter', 'news', 'events', 'podcast', 'downloads', 'faq', 'chat', 'places', 'food', 'polls', 'board', 'reservations'],
            'settings' => [
                'home_blog_count' => '8',
                'home_news_count' => '0',
                'home_board_count' => '0',
                'board_public_label' => 'Vývěska',
                'blog_per_page' => '12',
                'news_per_page' => '10',
                'events_per_page' => '10',
                'comments_enabled' => '1',
                'comment_moderation_mode' => 'known',
                'comment_close_days' => '0',
            ],
            'theme_settings' => [
                'header_layout' => 'centered',
                'container_width' => 'narrow',
                'home_layout' => 'editorial',
                'home_featured_module' => 'blog',
                'home_primary_order' => 'blog_news',
                'home_utility_order' => 'newsletter_cta_board_poll',
                'home_blog_visibility' => 'show',
                'home_news_visibility' => 'hide',
                'home_board_visibility' => 'hide',
                'home_poll_visibility' => 'hide',
                'home_newsletter_visibility' => 'show',
                'home_cta_visibility' => 'hide',
            ],
        ],
        'civic' => [
            'label' => 'Obec / spolek',
            'description' => 'Informační web pro obce, spolky a organizace s důrazem na aktuality, akce a důležité informace.',
            'theme' => 'civic',
            'modules' => [
                'blog' => true,
                'news' => true,
                'chat' => false,
                'contact' => true,
                'gallery' => true,
                'events' => true,
                'podcast' => false,
                'places' => true,
                'newsletter' => false,
                'downloads' => true,
                'food' => false,
                'polls' => false,
                'faq' => true,
                'board' => true,
                'reservations' => false,
            ],
            'nav_order' => ['news', 'events', 'board', 'downloads', 'faq', 'blog', 'gallery', 'places', 'contact', 'newsletter', 'chat', 'food', 'polls', 'reservations', 'podcast'],
            'settings' => [
                'home_blog_count' => '3',
                'home_news_count' => '5',
                'home_board_count' => '5',
                'board_public_label' => 'Úřední deska',
                'blog_per_page' => '10',
                'news_per_page' => '10',
                'events_per_page' => '10',
                'comments_enabled' => '0',
                'comment_moderation_mode' => 'always',
                'comment_close_days' => '0',
            ],
            'theme_settings' => [
                'header_layout' => 'split',
                'container_width' => 'standard',
                'home_layout' => 'balanced',
                'home_featured_module' => 'board',
                'home_primary_order' => 'news_blog',
                'home_utility_order' => 'board_poll_newsletter_cta',
                'home_blog_visibility' => 'show',
                'home_news_visibility' => 'show',
                'home_board_visibility' => 'show',
                'home_poll_visibility' => 'hide',
                'home_newsletter_visibility' => 'hide',
                'home_cta_visibility' => 'hide',
            ],
        ],
        'service' => [
            'label' => 'Služby / firma',
            'description' => 'Prezentační web pro služby, freelancery a menší firmy s důrazem na důvěru, obsah a kontakt.',
            'theme' => 'modern-service',
            'supports_preset' => true,
            'modules' => [
                'blog' => true,
                'news' => false,
                'chat' => false,
                'contact' => true,
                'gallery' => true,
                'events' => false,
                'podcast' => false,
                'places' => false,
                'newsletter' => true,
                'downloads' => false,
                'food' => false,
                'polls' => false,
                'faq' => true,
                'board' => false,
                'reservations' => false,
            ],
            'nav_order' => ['blog', 'gallery', 'faq', 'contact', 'newsletter', 'news', 'events', 'downloads', 'chat', 'places', 'food', 'polls', 'board', 'reservations', 'podcast'],
            'settings' => [
                'home_blog_count' => '4',
                'home_news_count' => '0',
                'home_board_count' => '0',
                'board_public_label' => 'Oznámení',
                'blog_per_page' => '10',
                'news_per_page' => '10',
                'events_per_page' => '10',
                'comments_enabled' => '0',
                'comment_moderation_mode' => 'always',
                'comment_close_days' => '0',
            ],
            'theme_settings' => [
                'header_layout' => 'split',
                'container_width' => 'wide',
                'home_layout' => 'compact',
                'home_featured_module' => 'blog',
                'home_primary_order' => 'blog_news',
                'home_utility_order' => 'newsletter_cta_board_poll',
                'home_blog_visibility' => 'show',
                'home_news_visibility' => 'hide',
                'home_board_visibility' => 'hide',
                'home_poll_visibility' => 'hide',
                'home_newsletter_visibility' => 'show',
                'home_cta_visibility' => 'show',
            ],
        ],
        'custom' => [
            'label' => 'Vlastní profil',
            'description' => 'Neutrální režim pro vlastní skladbu webu. CMS jen uloží zvolený profil a další rozhodnutí nechá na správci.',
            'supports_preset' => false,
        ],
    ];
}

function defaultSiteProfileKey(): string
{
    return 'personal';
}

function normalizeSiteProfileKey(string $profileKey): string
{
    $definitions = siteProfileDefinitions();
    return isset($definitions[$profileKey]) ? $profileKey : defaultSiteProfileKey();
}

function guessSiteProfileKey(): string
{
    $activeTheme = resolveThemeName(getSetting('active_theme', defaultThemeName()));
    if ($activeTheme === 'civic') {
        return 'civic';
    }
    if ($activeTheme === 'modern-service') {
        return 'service';
    }
    if ($activeTheme === 'editorial') {
        return isModuleEnabled('newsletter') ? 'blog' : 'personal';
    }

    if (isModuleEnabled('board') || isModuleEnabled('news') || isModuleEnabled('downloads') || isModuleEnabled('events')) {
        return 'civic';
    }

    if (isModuleEnabled('newsletter') && isModuleEnabled('contact') && !isModuleEnabled('board') && !isModuleEnabled('news')) {
        return 'service';
    }

    if (isModuleEnabled('blog')) {
        return isModuleEnabled('newsletter') ? 'blog' : 'personal';
    }

    return defaultSiteProfileKey();
}

function currentSiteProfileKey(): string
{
    $stored = trim(getSetting('site_profile', ''));
    if ($stored !== '' && isset(siteProfileDefinitions()[$stored])) {
        return $stored;
    }

    return guessSiteProfileKey();
}

function defaultBoardPublicLabelForProfile(string $profileKey): string
{
    return match (normalizeSiteProfileKey($profileKey)) {
        'civic' => 'Úřední deska',
        'service' => 'Oznámení',
        default => 'Vývěska',
    };
}

function boardModulePublicLabel(): string
{
    $label = trim(getSetting('board_public_label', ''));
    if ($label === '') {
        return defaultBoardPublicLabelForProfile(currentSiteProfileKey());
    }

    return mb_substr($label, 0, 60);
}

function boardModuleSectionKicker(): string
{
    return boardModulePublicLabel() === 'Úřední deska'
        ? 'Veřejné dokumenty'
        : 'Veřejná oznámení';
}

function boardModuleArchiveTitle(): string
{
    return match (boardModulePublicLabel()) {
        'Úřední deska' => 'Archiv dokumentů',
        'Oznámení' => 'Archiv oznámení',
        'Vývěska' => 'Archiv vývěsky',
        default => 'Archiv položek',
    };
}

function boardModuleListingEmptyState(): string
{
    return match (boardModulePublicLabel()) {
        'Úřední deska' => 'Na úřední desce zatím nejsou zveřejněné žádné dokumenty.',
        'Oznámení' => 'V oznámeních zatím nejsou zveřejněné žádné položky.',
        'Vývěska' => 'Ve vývěsce zatím nejsou zveřejněné žádné položky.',
        default => 'V této části zatím nejsou zveřejněné žádné položky.',
    };
}

function boardModuleAllItemsLabel(): string
{
    return match (boardModulePublicLabel()) {
        'Úřední deska' => 'Všechny dokumenty',
        'Oznámení' => 'Všechna oznámení',
        default => 'Všechny položky',
    };
}

function boardModuleBackLabel(): string
{
    return match (boardModulePublicLabel()) {
        'Úřední deska' => 'Zpět na úřední desku',
        'Oznámení' => 'Zpět na oznámení',
        'Vývěska' => 'Zpět na vývěsku',
        default => 'Zpět na přehled položek',
    };
}

function siteProfileConfig(string $profileKey): array
{
    $definitions = siteProfileDefinitions();
    return $definitions[normalizeSiteProfileKey($profileKey)];
}

function siteProfileSupportsPreset(string $profileKey): bool
{
    $config = siteProfileConfig($profileKey);
    return ($config['supports_preset'] ?? true) !== false;
}

function siteProfileRecommendedTheme(string $profileKey): string
{
    $config = siteProfileConfig($profileKey);
    $themeKey = trim((string)($config['theme'] ?? ''));
    if ($themeKey === '') {
        $themeKey = defaultThemeName();
    }

    return resolveThemeName($themeKey);
}

function siteProfileShouldDetachForTheme(string $profileKey, string $themeKey): bool
{
    $normalizedProfileKey = normalizeSiteProfileKey($profileKey);
    if (!siteProfileSupportsPreset($normalizedProfileKey)) {
        return false;
    }

    return siteProfileRecommendedTheme($normalizedProfileKey) !== resolveThemeName($themeKey);
}

function siteProfileModuleKeys(): array
{
    return ['blog', 'news', 'chat', 'contact', 'gallery', 'events', 'podcast', 'places', 'newsletter', 'downloads', 'food', 'polls', 'faq', 'board', 'reservations'];
}

function applySiteProfilePreset(string $profileKey): void
{
    $normalizedProfileKey = normalizeSiteProfileKey($profileKey);
    $profileConfig = siteProfileConfig($normalizedProfileKey);
    saveSetting('site_profile', $normalizedProfileKey);

    if (!siteProfileSupportsPreset($normalizedProfileKey)) {
        clearThemePreview();
        return;
    }

    $themeKey = siteProfileRecommendedTheme($normalizedProfileKey);

    saveSetting('active_theme', $themeKey);

    foreach (siteProfileModuleKeys() as $moduleKey) {
        $enabled = !empty($profileConfig['modules'][$moduleKey]) ? '1' : '0';
        saveSetting('module_' . $moduleKey, $enabled);
    }

    foreach ((array)($profileConfig['settings'] ?? []) as $settingKey => $settingValue) {
        if (is_string($settingKey)) {
            saveSetting($settingKey, (string)$settingValue);
        }
    }

    $navOrder = array_values(array_filter((array)($profileConfig['nav_order'] ?? []), 'is_string'));
    if ($navOrder !== []) {
        saveSetting('nav_module_order', implode(',', $navOrder));
    }

    $themeSettings = themeDefaultSettings($themeKey);
    foreach ((array)($profileConfig['theme_settings'] ?? []) as $settingKey => $settingValue) {
        if (is_string($settingKey) && array_key_exists($settingKey, $themeSettings)) {
            $themeSettings[$settingKey] = (string)$settingValue;
        }
    }
    saveThemeSettings($themeSettings, $themeKey);
    clearThemePreview();
}
