<?php

// Definice typů, profilů webu, modulů a jejich popisků – extrahováno z db.php

/**
 * @return array<string, array{
 *     label:string,
 *     settings_label:string,
 *     nav_label:string,
 *     widget_label:string,
 *     admin_label:string,
 *     content_reference_types:array<string,string>,
 *     search_result_types:array<string,string>,
 *     sitemap_sections:array<string,string>,
 *     settings_default:string,
 *     public_nav_path:string,
 *     public_paths:list<string>,
 *     public_nav_order:int,
 *     profile_managed:bool,
 *     settings_configurable:bool,
 *     public_nav:bool,
 *     admin_paths:list<string>
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
            'admin_label' => 'Blog',
            'content_reference_types' => ['blog' => 'Články blogu'],
            'search_result_types' => ['blog' => 'Článek'],
            'sitemap_sections' => [
                'blog' => 'Blogy a články',
                'blog_categories' => 'Kategorie blogu',
                'blog_tags' => 'Štítky blogu',
                'blog_series' => 'Série článků blogu',
            ],
            'settings_default' => '1',
            'public_nav_path' => '/blog/index.php',
            'public_paths' => [
                '/blog/index.php',
                '/blog/article.php',
                '/blog/page.php',
                '/blog/series.php',
            ],
            'public_nav_order' => 10,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/blog.php',
                '/admin/blogs.php',
                '/admin/blog_members.php',
                '/admin/blog_cats.php',
                '/admin/blog_tags.php',
                '/admin/blog_series.php',
                '/admin/comments.php',
            ],
        ],
        'news' => [
            'label' => 'Novinky',
            'settings_label' => 'Novinky',
            'nav_label' => 'Novinky',
            'widget_label' => 'Novinky',
            'admin_label' => 'Novinky',
            'content_reference_types' => ['news' => 'Novinky'],
            'search_result_types' => ['news' => 'Novinka'],
            'sitemap_sections' => ['news' => 'Novinky'],
            'settings_default' => '1',
            'public_nav_path' => '/news/index.php',
            'public_paths' => [
                '/news/index.php',
                '/news/article.php',
            ],
            'public_nav_order' => 20,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/news.php',
            ],
        ],
        'chat' => [
            'label' => 'Chat',
            'settings_label' => 'Chat',
            'nav_label' => 'Chat',
            'widget_label' => 'Chat',
            'admin_label' => 'Chat',
            'content_reference_types' => [],
            'search_result_types' => [],
            'sitemap_sections' => [],
            'settings_default' => '1',
            'public_nav_path' => '/chat/index.php',
            'public_paths' => [
                '/chat/index.php',
            ],
            'public_nav_order' => 90,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/chat.php',
            ],
        ],
        'contact' => [
            'label' => 'Kontakt',
            'settings_label' => 'Kontakt',
            'nav_label' => 'Kontakt',
            'widget_label' => 'Kontakt',
            'admin_label' => 'Kontakt',
            'content_reference_types' => [],
            'search_result_types' => [],
            'sitemap_sections' => [],
            'settings_default' => '1',
            'public_nav_path' => '/contact/index.php',
            'public_paths' => [
                '/contact/index.php',
            ],
            'public_nav_order' => 140,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/contact.php',
                '/admin/contact_topics.php',
            ],
        ],
        'gallery' => [
            'label' => 'Galerie',
            'settings_label' => 'Galerie',
            'nav_label' => 'Galerie',
            'widget_label' => 'Fotogalerie',
            'admin_label' => 'Galerie',
            'content_reference_types' => ['gallery' => 'Fotogalerie'],
            'search_result_types' => [
                'gallery_album' => 'Album galerie',
                'gallery_photo' => 'Fotografie',
            ],
            'sitemap_sections' => [
                'gallery_albums' => 'Alba galerie',
                'gallery_photos' => 'Fotografie galerie',
            ],
            'settings_default' => '1',
            'public_nav_path' => '/gallery/index.php',
            'public_paths' => [
                '/gallery/index.php',
                '/gallery/album.php',
                '/gallery/photo.php',
                '/gallery/image.php',
            ],
            'public_nav_order' => 50,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/gallery_albums.php',
            ],
        ],
        'events' => [
            'label' => 'Události',
            'settings_label' => 'Události',
            'nav_label' => 'Akce',
            'widget_label' => 'Události',
            'admin_label' => 'Události',
            'content_reference_types' => ['event' => 'Události'],
            'search_result_types' => ['event' => 'Akce'],
            'sitemap_sections' => [
                'events' => 'Události',
                'event_types' => 'Typy akcí',
            ],
            'settings_default' => '1',
            'public_nav_path' => '/events/index.php',
            'public_paths' => [
                '/events/index.php',
                '/events/event.php',
                '/events/ics.php',
            ],
            'public_nav_order' => 30,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/events.php',
                '/admin/event_types.php',
            ],
        ],
        'podcast' => [
            'label' => 'Podcast',
            'settings_label' => 'Podcast',
            'nav_label' => 'Podcasty',
            'widget_label' => 'Podcast',
            'admin_label' => 'Podcasty',
            'content_reference_types' => ['podcast' => 'Podcasty'],
            'search_result_types' => [
                'podcast_show' => 'Podcast',
                'podcast_episode' => 'Epizoda podcastu',
            ],
            'sitemap_sections' => [
                'podcast_shows' => 'Podcastové pořady',
                'podcast_episodes' => 'Podcastové epizody',
            ],
            'settings_default' => '1',
            'public_nav_path' => '/podcast/index.php',
            'public_paths' => [
                '/podcast/index.php',
                '/podcast/show.php',
                '/podcast/episode.php',
                '/podcast/feed.php',
                '/podcast/image.php',
                '/podcast/cover.php',
                '/podcast/audio.php',
            ],
            'public_nav_order' => 40,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/podcast_shows.php',
            ],
        ],
        'places' => [
            'label' => 'Zajímavá místa',
            'settings_label' => 'Zajímavá místa',
            'nav_label' => 'Zajímavá místa',
            'widget_label' => 'Místa',
            'admin_label' => 'Zajímavá místa',
            'content_reference_types' => ['place' => 'Zajímavá místa'],
            'search_result_types' => ['place' => 'Místo'],
            'sitemap_sections' => ['places' => 'Zajímavá místa'],
            'settings_default' => '1',
            'public_nav_path' => '/places/index.php',
            'public_paths' => [
                '/places/index.php',
                '/places/place.php',
                '/places/image.php',
            ],
            'public_nav_order' => 60,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/places.php',
            ],
        ],
        'newsletter' => [
            'label' => 'Newsletter',
            'settings_label' => 'Newsletter',
            'nav_label' => '',
            'widget_label' => 'Newsletter',
            'admin_label' => 'Newsletter',
            'content_reference_types' => [],
            'search_result_types' => [],
            'sitemap_sections' => [],
            'settings_default' => '1',
            'public_nav_path' => '',
            'public_paths' => [
                '/subscribe.php',
                '/newsletter_widget_subscribe.php',
            ],
            'public_nav_order' => 0,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => false,
            'admin_paths' => [
                '/admin/newsletter.php',
            ],
        ],
        'downloads' => [
            'label' => 'Ke stažení',
            'settings_label' => 'Ke stažení',
            'nav_label' => 'Ke stažení',
            'widget_label' => 'Ke stažení',
            'admin_label' => 'Ke stažení',
            'content_reference_types' => ['download' => 'Ke stažení'],
            'search_result_types' => ['download' => 'Ke stažení'],
            'sitemap_sections' => [
                'downloads' => 'Ke stažení',
                'download_categories' => 'Kategorie ke stažení',
                'download_series' => 'Série ke stažení',
            ],
            'settings_default' => '1',
            'public_nav_path' => '/downloads/index.php',
            'public_paths' => [
                '/downloads/index.php',
                '/downloads/item.php',
                '/downloads/file.php',
                '/downloads/series.php',
            ],
            'public_nav_order' => 70,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/downloads.php',
                '/admin/dl_cats.php',
                '/admin/download_series.php',
            ],
        ],
        'food' => [
            'label' => 'Jídelní lístek',
            'settings_label' => 'Jídelní lístek',
            'nav_label' => 'Jídelní lístek',
            'widget_label' => 'Jídelní lístek',
            'admin_label' => 'Jídelní lístek',
            'content_reference_types' => [],
            'search_result_types' => ['food_card' => 'Lístek'],
            'sitemap_sections' => ['food' => 'Jídelní lístek'],
            'settings_default' => '1',
            'public_nav_path' => '/food/index.php',
            'public_paths' => [
                '/food/index.php',
                '/food/archive.php',
                '/food/card.php',
            ],
            'public_nav_order' => 80,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/food.php',
            ],
        ],
        'polls' => [
            'label' => 'Ankety',
            'settings_label' => 'Ankety',
            'nav_label' => 'Ankety',
            'widget_label' => 'Ankety',
            'admin_label' => 'Ankety',
            'content_reference_types' => ['poll' => 'Ankety'],
            'search_result_types' => ['poll' => 'Anketa'],
            'sitemap_sections' => ['polls' => 'Ankety'],
            'settings_default' => '0',
            'public_nav_path' => '/polls/index.php',
            'public_paths' => [
                '/polls/index.php',
            ],
            'public_nav_order' => 100,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/polls.php',
            ],
        ],
        'faq' => [
            'label' => 'FAQ',
            'settings_label' => 'FAQ',
            'nav_label' => 'FAQ',
            'widget_label' => 'FAQ',
            'admin_label' => 'FAQ',
            'content_reference_types' => ['faq' => 'FAQ'],
            'search_result_types' => ['faq' => 'FAQ'],
            'sitemap_sections' => [
                'faq' => 'FAQ',
                'faq_categories' => 'Kategorie FAQ',
            ],
            'settings_default' => '0',
            'public_nav_path' => '/faq/index.php',
            'public_paths' => [
                '/faq/index.php',
                '/faq/item.php',
            ],
            'public_nav_order' => 110,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/faq.php',
                '/admin/faq_cats.php',
            ],
        ],
        'board' => [
            'label' => 'Úřední deska',
            'settings_label' => 'Úřední deska',
            'nav_label' => '',
            'widget_label' => '',
            'admin_label' => 'Vývěska',
            'content_reference_types' => ['board' => ''],
            'search_result_types' => ['board' => ''],
            'sitemap_sections' => [
                'board' => '',
                'board_categories' => 'Kategorie vývěsky',
            ],
            'settings_default' => '0',
            'public_nav_path' => '/board/index.php',
            'public_paths' => [
                '/board/index.php',
                '/board/document.php',
                '/board/file.php',
                '/board/subscribe.php',
                '/board/subscribe_confirm.php',
                '/board/unsubscribe.php',
            ],
            'public_nav_order' => 120,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/board.php',
                '/admin/board_cats.php',
            ],
        ],
        'reservations' => [
            'label' => 'Rezervace',
            'settings_label' => 'Rezervace',
            'nav_label' => 'Rezervace',
            'widget_label' => 'Rezervace',
            'admin_label' => 'Rezervace',
            'content_reference_types' => [],
            'search_result_types' => ['reservation_resource' => 'Rezervace'],
            'sitemap_sections' => ['reservations' => 'Rezervace'],
            'settings_default' => '0',
            'public_nav_path' => '/reservations/index.php',
            'public_paths' => [
                '/reservations/index.php',
                '/reservations/resource.php',
                '/reservations/book.php',
                '/reservations/my.php',
                '/reservations/cancel.php',
                '/reservations/cancel_booking.php',
            ],
            'public_nav_order' => 130,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => true,
            'admin_paths' => [
                '/admin/res_bookings.php',
                '/admin/res_resources.php',
                '/admin/res_categories.php',
                '/admin/res_locations.php',
            ],
        ],
        'forms' => [
            'label' => 'Formuláře',
            'settings_label' => 'Formuláře',
            'nav_label' => '',
            'widget_label' => 'Formuláře',
            'admin_label' => 'Formuláře',
            'content_reference_types' => ['forms' => 'Formuláře'],
            'search_result_types' => [],
            'sitemap_sections' => ['forms' => 'Formuláře'],
            'settings_default' => '0',
            'public_nav_path' => '',
            'public_paths' => [
                '/forms/index.php',
            ],
            'public_nav_order' => 0,
            'profile_managed' => true,
            'settings_configurable' => true,
            'public_nav' => false,
            'admin_paths' => [
                '/admin/forms.php',
            ],
        ],
        'statistics' => [
            'label' => 'Statistiky',
            'settings_label' => 'Statistiky (admin dashboard)',
            'nav_label' => '',
            'widget_label' => 'Statistiky',
            'admin_label' => 'Statistiky',
            'content_reference_types' => [],
            'search_result_types' => [],
            'sitemap_sections' => [],
            'settings_default' => '0',
            'public_nav_path' => '',
            'public_paths' => [],
            'public_nav_order' => 0,
            'profile_managed' => false,
            'settings_configurable' => true,
            'public_nav' => false,
            'admin_paths' => [
                '/admin/statistics.php',
            ],
        ],
    ];
}

/**
 * @return array<string,mixed>|null
 */
function moduleDefinition(string $moduleKey): ?array
{
    $definition = coreModuleDefinitions()[$moduleKey] ?? null;
    return is_array($definition) ? $definition : null;
}

function knownModuleKey(string $moduleKey): bool
{
    return moduleDefinition($moduleKey) !== null;
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
function moduleDefaultSettings(): array
{
    $settings = [];
    foreach (moduleKeysForSettings() as $moduleKey) {
        $definition = coreModuleDefinitions()[$moduleKey];
        $settings['module_' . $moduleKey] = $definition['settings_default'];
    }

    return $settings;
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

/**
 * @return array<string, list<string>>
 */
function modulePublicEntryPoints(): array
{
    $entryPoints = [];
    foreach (coreModuleDefinitions() as $moduleKey => $definition) {
        $entryPoints[$moduleKey] = $definition['public_paths'];
    }

    return $entryPoints;
}

/**
 * @return array<string,string>
 */
function modulePublicPathModuleMap(): array
{
    $map = [];
    foreach (modulePublicEntryPoints() as $moduleKey => $publicPaths) {
        foreach ($publicPaths as $publicPath) {
            $map[$publicPath] = $moduleKey;
        }
    }

    return $map;
}

/**
 * @return array<string, list<string>>
 */
function moduleAdminEntryPoints(): array
{
    $entryPoints = [];
    foreach (coreModuleDefinitions() as $moduleKey => $definition) {
        $entryPoints[$moduleKey] = $definition['admin_paths'];
    }

    return $entryPoints;
}

/**
 * @return array<string,string>
 */
function moduleAdminPathModuleMap(): array
{
    $map = [];
    foreach (moduleAdminEntryPoints() as $moduleKey => $adminPaths) {
        foreach ($adminPaths as $adminPath) {
            $map[$adminPath] = $moduleKey;
        }
    }

    return $map;
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
 * @return array<string, array<string,string>>
 */
function moduleContentReferenceTypeLabels(): array
{
    $labels = [];
    foreach (coreModuleDefinitions() as $moduleKey => $definition) {
        $typeLabels = $definition['content_reference_types'];
        if ($typeLabels === []) {
            continue;
        }

        $normalizedTypeLabels = [];
        foreach ($typeLabels as $type => $label) {
            $type = trim((string)$type);
            if ($type === '') {
                continue;
            }

            $label = trim($label);
            if ($label === '') {
                $label = $moduleKey === 'board'
                    ? (function_exists('resolveThemeName') ? boardModulePublicLabel() : 'Vývěska')
                    : moduleWidgetLabel($moduleKey);
            }

            $normalizedTypeLabels[$type] = $label;
        }

        if ($normalizedTypeLabels !== []) {
            $labels[$moduleKey] = $normalizedTypeLabels;
        }
    }

    return $labels;
}

/**
 * @return array<string,string>
 */
function contentReferenceTypeModuleMap(): array
{
    $map = [];
    foreach (moduleContentReferenceTypeLabels() as $moduleKey => $typeLabels) {
        foreach (array_keys($typeLabels) as $type) {
            $map[$type] = $moduleKey;
        }
    }

    return $map;
}

/**
 * @return array<string, array<string,string>>
 */
function moduleSearchResultTypeLabels(): array
{
    $labels = [];
    foreach (coreModuleDefinitions() as $moduleKey => $definition) {
        $typeLabels = $definition['search_result_types'];
        if ($typeLabels === []) {
            continue;
        }

        $normalizedTypeLabels = [];
        foreach ($typeLabels as $type => $label) {
            $type = trim((string)$type);
            if ($type === '') {
                continue;
            }

            $label = trim($label);
            if ($label === '') {
                $label = $moduleKey === 'board'
                    ? (function_exists('resolveThemeName') ? boardModulePublicLabel() : 'Vývěska')
                    : moduleWidgetLabel($moduleKey);
            }

            $normalizedTypeLabels[$type] = $label;
        }

        if ($normalizedTypeLabels !== []) {
            $labels[$moduleKey] = $normalizedTypeLabels;
        }
    }

    return $labels;
}

/**
 * @return array<string,string>
 */
function searchResultTypeModuleMap(): array
{
    $map = [];
    foreach (moduleSearchResultTypeLabels() as $moduleKey => $typeLabels) {
        foreach (array_keys($typeLabels) as $type) {
            $map[$type] = $moduleKey;
        }
    }

    return $map;
}

/**
 * @return array<string, array<string,string>>
 */
function moduleSitemapSections(): array
{
    $sections = [];
    foreach (coreModuleDefinitions() as $moduleKey => $definition) {
        $sectionLabels = $definition['sitemap_sections'];
        if ($sectionLabels === []) {
            continue;
        }

        $normalizedSectionLabels = [];
        foreach ($sectionLabels as $section => $label) {
            $section = trim((string)$section);
            if ($section === '') {
                continue;
            }

            $label = trim($label);
            if ($label === '') {
                $label = $moduleKey === 'board'
                    ? (function_exists('resolveThemeName') ? boardModulePublicLabel() : 'Vývěska')
                    : moduleWidgetLabel($moduleKey);
            }

            $normalizedSectionLabels[$section] = $label;
        }

        if ($normalizedSectionLabels !== []) {
            $sections[$moduleKey] = $normalizedSectionLabels;
        }
    }

    return $sections;
}

/**
 * @return array<string,string>
 */
function sitemapSectionModuleMap(): array
{
    $map = [];
    foreach (moduleSitemapSections() as $moduleKey => $sectionLabels) {
        foreach (array_keys($sectionLabels) as $section) {
            $map[$section] = $moduleKey;
        }
    }

    return $map;
}

function moduleAdminLabel(string $moduleKey): string
{
    $definition = coreModuleDefinitions()[$moduleKey] ?? null;
    if ($definition === null) {
        return $moduleKey;
    }

    $label = trim($definition['admin_label']);
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

/**
 * @return array<int, array{legacy_key:string,title:string,slug:string,description:string,sort_order:int}>
 */
function defaultEventTypeRows(): array
{
    $rows = [];
    $sortOrder = 10;
    foreach (eventKindDefinitions() as $legacyKey => $definition) {
        $rows[] = [
            'legacy_key' => $legacyKey,
            'title' => (string)$definition['label'],
            'slug' => $legacyKey,
            'description' => (string)$definition['help'],
            'sort_order' => $sortOrder,
        ];
        $sortOrder += 10;
    }

    return $rows;
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
