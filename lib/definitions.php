<?php

// Definice typů, profilů webu, modulů a jejich popisků – extrahováno z db.php

/**
 * @return array<string, array{
 *     label:string,
 *     settings_label:string,
 *     nav_label:string,
 *     widget_label:string,
 *     public_nav_path:string,
 *     public_nav_order:int,
 *     profile_managed:bool,
 *     settings_configurable:bool,
 *     public_nav:bool
 * }>
 */
function coreModuleDefinitions(): array
{
    return [
        'blog' => [
            'label' => 'Blog',
            'settings_label' => 'Blog',
            'nav_label' => 'Blog',
            'widget_label' => 'Blog',
            'public_nav_path' => '/blog/index.php',
            'public_nav_order' => 10,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'news' => [
            'label' => 'Novinky',
            'settings_label' => 'Novinky',
            'nav_label' => 'Novinky',
            'widget_label' => 'Novinky',
            'public_nav_path' => '/news/index.php',
            'public_nav_order' => 20,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'chat' => [
            'label' => 'Chat',
            'settings_label' => 'Chat',
            'nav_label' => 'Chat',
            'widget_label' => 'Chat',
            'public_nav_path' => '/chat/index.php',
            'public_nav_order' => 90,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'contact' => [
            'label' => 'Kontakt',
            'settings_label' => 'Kontakt',
            'nav_label' => 'Kontakt',
            'widget_label' => 'Kontakt',
            'public_nav_path' => '/contact/index.php',
            'public_nav_order' => 140,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'gallery' => [
            'label' => 'Galerie',
            'settings_label' => 'Galerie',
            'nav_label' => 'Galerie',
            'widget_label' => 'Fotogalerie',
            'public_nav_path' => '/gallery/index.php',
            'public_nav_order' => 50,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'events' => [
            'label' => 'Události',
            'settings_label' => 'Události',
            'nav_label' => 'Akce',
            'widget_label' => 'Události',
            'public_nav_path' => '/events/index.php',
            'public_nav_order' => 30,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'podcast' => [
            'label' => 'Podcast',
            'settings_label' => 'Podcast',
            'nav_label' => 'Podcasty',
            'widget_label' => 'Podcast',
            'public_nav_path' => '/podcast/index.php',
            'public_nav_order' => 40,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'places' => [
            'label' => 'Zajímavá místa',
            'settings_label' => 'Zajímavá místa',
            'nav_label' => 'Zajímavá místa',
            'widget_label' => 'Místa',
            'public_nav_path' => '/places/index.php',
            'public_nav_order' => 60,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'newsletter' => [
            'label' => 'Newsletter',
            'settings_label' => 'Newsletter',
            'nav_label' => '',
            'widget_label' => 'Newsletter',
            'public_nav_path' => '',
            'public_nav_order' => 0,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => false,
        ],
        'downloads' => [
            'label' => 'Ke stažení',
            'settings_label' => 'Ke stažení',
            'nav_label' => 'Ke stažení',
            'widget_label' => 'Ke stažení',
            'public_nav_path' => '/downloads/index.php',
            'public_nav_order' => 70,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'food' => [
            'label' => 'Jídelní lístek',
            'settings_label' => 'Jídelní lístek',
            'nav_label' => 'Jídelní lístek',
            'widget_label' => 'Jídelní lístek',
            'public_nav_path' => '/food/index.php',
            'public_nav_order' => 80,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'polls' => [
            'label' => 'Ankety',
            'settings_label' => 'Ankety',
            'nav_label' => 'Ankety',
            'widget_label' => 'Ankety',
            'public_nav_path' => '/polls/index.php',
            'public_nav_order' => 100,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'faq' => [
            'label' => 'FAQ',
            'settings_label' => 'FAQ',
            'nav_label' => 'FAQ',
            'widget_label' => 'FAQ',
            'public_nav_path' => '/faq/index.php',
            'public_nav_order' => 110,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'board' => [
            'label' => 'Úřední deska',
            'settings_label' => 'Úřední deska',
            'nav_label' => '',
            'widget_label' => '',
            'public_nav_path' => '/board/index.php',
            'public_nav_order' => 120,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'reservations' => [
            'label' => 'Rezervace',
            'settings_label' => 'Rezervace',
            'nav_label' => 'Rezervace',
            'widget_label' => 'Rezervace',
            'public_nav_path' => '/reservations/index.php',
            'public_nav_order' => 130,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
        ],
        'forms' => [
            'label' => 'Formuláře',
            'settings_label' => 'Formuláře',
            'nav_label' => '',
            'widget_label' => 'Formuláře',
            'public_nav_path' => '',
            'public_nav_order' => 0,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => false,
        ],
        'statistics' => [
            'label' => 'Statistiky',
            'settings_label' => 'Statistiky (admin dashboard)',
            'nav_label' => '',
            'widget_label' => 'Statistiky',
            'public_nav_path' => '',
            'public_nav_order' => 0,
            'profile_managed' => false,
            'settings_configurable' => true,
            'public_nav' => false,
        ],
    ];
}

/**
 * @return list<string>
 */
function coreModuleKeysByFlag(string $flag): array
{
    $keys = [];
    foreach (coreModuleDefinitions() as $moduleKey => $definition) {
        if (array_key_exists($flag, $definition) && $definition[$flag] === true) {
            $keys[] = $moduleKey;
        }
    }

    return $keys;
}

/**
 * @return list<string>
 */
function moduleKeysForSettings(): array
{
    return coreModuleKeysByFlag('settings_configurable');
}

/**
 * @return array<string,string>
 */
function moduleSettingsLabels(): array
{
    $labels = [];
    foreach (moduleKeysForSettings() as $moduleKey) {
        $definition = coreModuleDefinitions()[$moduleKey];
        $labels[$moduleKey] = $definition['settings_label'];
    }

    return $labels;
}

/**
 * @return array<string, array{0:string, 1:string}>
 */
function moduleNavigationDefaults(): array
{
    $defaults = [];
    $definitions = coreModuleDefinitions();
    uasort($definitions, static function (array $left, array $right): int {
        return $left['public_nav_order'] <=> $right['public_nav_order'];
    });

    foreach ($definitions as $moduleKey => $definition) {
        if ($definition['public_nav'] !== true || $definition['public_nav_path'] === '') {
            continue;
        }

        $label = $moduleKey === 'board' ? boardModulePublicLabel() : $definition['nav_label'];
        $defaults[$moduleKey] = [$definition['public_nav_path'], $label];
    }

    return $defaults;
}

function moduleWidgetLabel(string $moduleKey): string
{
    if ($moduleKey === 'board') {
        return boardModulePublicLabel();
    }

    $definition = coreModuleDefinitions()[$moduleKey] ?? null;
    if ($definition === null) {
        return $moduleKey;
    }

    $label = trim($definition['widget_label']);
    return $label !== '' ? $label : $definition['label'];
}

/**
 * @return array<string, array{label:string, public_label:string, help:string}>
 */
function boardTypeDefinitions(): array
{
    return [
        'document' => [
            'label' => 'Dokument',
            'public_label' => 'Dokument',
            'help' => 'Vhodné pro úřední dokument, oznámení ke stažení nebo text, který má mít hlavně přílohu a jasné datum vyvěšení.',
        ],
        'notice' => [
            'label' => 'Oznámení',
            'public_label' => 'Oznámení',
            'help' => 'Krátká veřejná informace bez složité struktury. Hodí se pro běžná oznámení, změny provozu nebo stručné sdělení.',
        ],
        'lost_found' => [
            'label' => 'Ztráty a nálezy',
            'public_label' => 'Ztráty a nálezy',
            'help' => 'Počítá s krátkým shrnutím, kontaktem a ideálně i obrázkem nalezené nebo ztracené věci.',
        ],
        'memorial' => [
            'label' => 'Parte / vzpomínka',
            'public_label' => 'Vzpomínka',
            'help' => 'Vhodné pro parte nebo vzpomínkovou položku. Nejlépe funguje s obrázkem, datem vyvěšení a kontaktní osobou.',
        ],
        'invitation' => [
            'label' => 'Pozvánka',
            'public_label' => 'Pozvánka',
            'help' => 'Použijte pro pozvánky na akce, schůze nebo veřejná setkání. Hodí se datum sejmutí, obrázek i delší popis.',
        ],
        'alert' => [
            'label' => 'Upozornění',
            'public_label' => 'Upozornění',
            'help' => 'Pro důležité nebo urgentní sdělení. Zvažte připnutí mezi důležité položky a krátký, jasný perex.',
        ],
    ];
}

/**
 * @return array<string, array{label:string}>
 */
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

/**
 * @return array<string, array{label:string}>
 */
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

/**
 * @return array<string, array{label:string, help:string}>
 */
function eventKindDefinitions(): array
{
    return [
        'general' => [
            'label' => 'Obecná akce',
            'help' => 'Univerzální typ pro běžnou událost, která nepotřebuje specifičtější zařazení.',
        ],
        'community' => [
            'label' => 'Komunitní setkání',
            'help' => 'Hodí se pro klubová, sousedská nebo komunitní setkání, kde je důležitý kontakt, místo a stručný program.',
        ],
        'workshop' => [
            'label' => 'Workshop / dílna',
            'help' => 'Vhodné pro praktickou akci, kurz nebo workshop. Často se hodí registrační odkaz, kapacita a bližší program.',
        ],
        'lecture' => [
            'label' => 'Přednáška / beseda',
            'help' => 'Počítá s jasným začátkem, místem a volitelně i představením pořadatele nebo hosta.',
        ],
        'webinar' => [
            'label' => 'Online akce / webinář',
            'help' => 'Použijte pro online stream, videohovor nebo webinář. Uveďte registrační odkaz a případně platformu v popisu.',
        ],
        'performance' => [
            'label' => 'Koncert / vystoupení',
            'help' => 'Vhodné pro kulturní program, koncert, divadelní nebo jiné veřejné vystoupení.',
        ],
        'training' => [
            'label' => 'Školení / kurz',
            'help' => 'Hodí se pro vzdělávací akci, kde bývají důležité požadavky na účastníky, cena a přihlášení.',
        ],
        'other' => [
            'label' => 'Jiný typ akce',
            'help' => 'Použijte, když žádný z nabízených typů přesně nesedí, ale chcete typ akce přesto odlišit.',
        ],
    ];
}

function normalizeBoardType(string $type): string
{
    $definitions = boardTypeDefinitions();
    return isset($definitions[$type]) ? $type : 'document';
}

function boardTypeHelp(string $type): string
{
    $definitions = boardTypeDefinitions();
    $normalized = normalizeBoardType($type);

    return (string)($definitions[$normalized]['help'] ?? '');
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

function normalizeEventKind(string $kind): string
{
    $definitions = eventKindDefinitions();
    return isset($definitions[$kind]) ? $kind : 'general';
}

function eventKindHelp(string $kind): string
{
    $definitions = eventKindDefinitions();
    $normalized = normalizeEventKind($kind);

    return (string)($definitions[$normalized]['help'] ?? '');
}

/**
 * @return array<string, array{
 *     label:string,
 *     description:string,
 *     theme?:string,
 *     supports_preset?:bool,
 *     modules?:array<string, bool>,
 *     nav_order?:list<string>,
 *     settings?:array<string, string>,
 *     theme_settings?:array<string, string>
 * }>
 */
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

    if (isModuleEnabled('newsletter') && isModuleEnabled('contact')) {
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

/**
 * @return array{
 *     label:string,
 *     description:string,
 *     theme?:string,
 *     supports_preset?:bool,
 *     modules?:array<string, bool>,
 *     nav_order?:list<string>,
 *     settings?:array<string, string>,
 *     theme_settings?:array<string, string>
 * }
 */
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

/**
 * @return list<string>
 */
function siteProfileModuleKeys(): array
{
    return coreModuleKeysByFlag('profile_managed');
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
        saveSetting($settingKey, (string)$settingValue);
    }

    $navOrder = array_values(array_filter((array)($profileConfig['nav_order'] ?? []), 'is_string'));
    if ($navOrder !== []) {
        saveSetting('nav_module_order', implode(',', $navOrder));
    }

    $themeSettings = themeDefaultSettings($themeKey);
    foreach ((array)($profileConfig['theme_settings'] ?? []) as $settingKey => $settingValue) {
        if (array_key_exists($settingKey, $themeSettings)) {
            $themeSettings[$settingKey] = (string)$settingValue;
        }
    }
    saveThemeSettings($themeSettings, $themeKey);
    clearThemePreview();
}
