<?php
// Widget systém – definice typů, rendering do zón

/**
 * Vrátí definice všech widget typů.
 * Každý typ: name, default_title, settings_fields, requires_module, requires_setting
 */
function widgetTypeDefinitions(): array
{
    return [
        'intro' => [
            'name' => 'Úvodní text',
            'default_title' => 'Úvodní text',
            'requires_module' => null,
            'requires_setting' => 'home_intro',
        ],
        'latest_articles' => [
            'name' => 'Nejnovější články',
            'default_title' => 'Nejnovější články',
            'requires_module' => 'blog',
            'requires_setting' => null,
        ],
        'latest_news' => [
            'name' => 'Nejnovější novinky',
            'default_title' => 'Nejnovější novinky',
            'requires_module' => 'news',
            'requires_setting' => null,
        ],
        'latest_downloads' => [
            'name' => 'Nejnovější položky ke stažení',
            'default_title' => 'Ke stažení',
            'requires_module' => 'downloads',
            'requires_setting' => null,
        ],
        'latest_faq' => [
            'name' => 'Nejnovější otázky',
            'default_title' => 'Nejčastější dotazy',
            'requires_module' => 'faq',
            'requires_setting' => null,
        ],
        'latest_places' => [
            'name' => 'Zajímavá místa',
            'default_title' => 'Zajímavá místa',
            'requires_module' => 'places',
            'requires_setting' => null,
        ],
        'latest_podcast_episodes' => [
            'name' => 'Nejnovější epizody podcastu',
            'default_title' => 'Nejnovější epizody',
            'requires_module' => 'podcast',
            'requires_setting' => null,
        ],
        'featured_article' => [
            'name' => 'Doporučený obsah',
            'default_title' => 'Doporučený obsah',
            'requires_module' => null,
            'requires_setting' => null,
        ],
        'board' => [
            'name' => boardModulePublicLabel(),
            'default_title' => boardModulePublicLabel(),
            'requires_module' => 'board',
            'requires_setting' => null,
        ],
        'upcoming_events' => [
            'name' => 'Nadcházející události',
            'default_title' => 'Nadcházející události',
            'requires_module' => 'events',
            'requires_setting' => null,
        ],
        'poll' => [
            'name' => 'Aktuální anketa',
            'default_title' => 'Aktuální anketa',
            'requires_module' => 'polls',
            'requires_setting' => null,
        ],
        'newsletter' => [
            'name' => 'Newsletter',
            'default_title' => 'Zůstaňte v kontaktu',
            'requires_module' => 'newsletter',
            'requires_setting' => null,
        ],
        'gallery_preview' => [
            'name' => 'Náhled galerie',
            'default_title' => 'Galerie',
            'requires_module' => 'gallery',
            'requires_setting' => null,
        ],
        'selected_form' => [
            'name' => 'Vybraný formulář',
            'default_title' => 'Vybraný formulář',
            'requires_module' => 'forms',
            'requires_setting' => null,
        ],
        'custom_html' => [
            'name' => 'Vlastní HTML',
            'default_title' => 'Vlastní blok',
            'requires_module' => null,
            'requires_setting' => null,
        ],
        'search' => [
            'name' => 'Vyhledávání',
            'default_title' => 'Vyhledávání',
            'requires_module' => null,
            'requires_setting' => null,
        ],
        'contact_info' => [
            'name' => 'Kontaktní údaje',
            'default_title' => 'Kontakt',
            'requires_module' => null,
            'requires_setting' => null,
        ],
    ];
}

/**
 * Vrátí widget typy dostupné k přidání (respektuje stav modulů a nastavení).
 */
function availableWidgetTypes(): array
{
    $all = widgetTypeDefinitions();
    $available = [];
    foreach ($all as $type => $def) {
        if ($def['requires_module'] !== null && !isModuleEnabled($def['requires_module'])) {
            continue;
        }
        if ($def['requires_setting'] !== null && trim(getSetting($def['requires_setting'], '')) === '') {
            continue;
        }
        if ($type === 'selected_form') {
            try {
                $hasActiveForms = (int)db_connect()->query("SELECT COUNT(*) FROM cms_forms WHERE is_active = 1")->fetchColumn() > 0;
            } catch (\PDOException $e) {
                $hasActiveForms = false;
            }
            if (!$hasActiveForms) {
                continue;
            }
        }
        $available[$type] = $def;
    }
    return $available;
}

/**
 * Vrátí definice zón.
 */
function widgetZoneDefinitions(): array
{
    return [
        'homepage' => 'Homepage',
        'sidebar'  => 'Sidebar',
        'footer'   => 'Footer',
    ];
}

/**
 * Vrátí aktivní widgety pro zónu.
 */
function getWidgetsForZone(string $zone): array
{
    try {
        $stmt = db_connect()->prepare(
            "SELECT * FROM cms_widgets WHERE zone = ? AND is_active = 1 ORDER BY sort_order, id"
        );
        $stmt->execute([$zone]);
        $widgets = $stmt->fetchAll();
        $types = widgetTypeDefinitions();
        return array_filter($widgets, function (array $w) use ($types) {
            $type = $w['widget_type'];
            if (!isset($types[$type])) {
                return false;
            }
            $def = $types[$type];
            if ($def['requires_module'] !== null && !isModuleEnabled($def['requires_module'])) {
                return false;
            }
            if ($def['requires_setting'] !== null && trim(getSetting($def['requires_setting'], '')) === '') {
                return false;
            }
            return true;
        });
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * Vrátí všechny widgety seskupené dle zóny (pro admin).
 */
function getAllWidgetsByZone(): array
{
    $zones = [];
    foreach (array_keys(widgetZoneDefinitions()) as $zone) {
        $zones[$zone] = [];
    }
    try {
        $rows = db_connect()->query(
            "SELECT * FROM cms_widgets ORDER BY zone, sort_order, id"
        )->fetchAll();
        foreach ($rows as $row) {
            $zones[$row['zone']][] = $row;
        }
    } catch (\PDOException $e) {}
    return $zones;
}

/**
 * Dekóduje widget settings JSON.
 */
function widgetSettings(array $widget): array
{
    $raw = $widget['settings'] ?? '{}';
    return is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
}

/**
 * Renderuje celou zónu.
 */
function renderZone(string $zone, string $wrapperClass = ''): string
{
    $widgets = getWidgetsForZone($zone);
    if ($widgets === []) {
        return '';
    }

    $out = '';
    foreach ($widgets as $widget) {
        $out .= renderWidget($widget, $zone);
    }

    if ($wrapperClass !== '' && $out !== '') {
        $out = '<div class="' . h($wrapperClass) . '">' . $out . '</div>';
    }
    return $out;
}

/**
 * Renderuje jeden widget.
 */
function renderWidget(array $widget, string $zone = 'homepage'): string
{
    $type = $widget['widget_type'];
    $settings = widgetSettings($widget);
    $title = trim((string)($widget['title'] ?? ''));

    $fn = 'renderWidget_' . $type;
    if (function_exists($fn)) {
        return $fn($widget, $settings, $zone);
    }
    return '';
}

// ──────────────── Widget render funkce ───────────────────────────────────────

function renderWidget_intro(array $widget, array $settings, string $zone): string
{
    $text = $settings['text'] ?? getSetting('home_intro', '');
    if (trim($text) === '') {
        return '';
    }
    if ($zone === 'homepage') {
        return '<section class="surface home-section" aria-label="' . h($widget['title'] ?: 'Úvod') . '">'
             . '<div class="prose">' . renderContent($text) . '</div></section>';
    }
    return '<section class="widget-card" aria-label="' . h($widget['title'] ?: 'Úvod') . '">'
         . '<div class="prose">' . renderContent($text) . '</div></section>';
}

function renderWidget_latest_articles(array $widget, array $settings, string $zone): string
{
    $count = max(1, (int)($settings['count'] ?? 5));
    $blogId = isset($settings['blog_id']) && (int)$settings['blog_id'] > 0 ? (int)$settings['blog_id'] : null;

    $pdo = db_connect();
    $where = "WHERE a.status = 'published' AND (a.publish_at IS NULL OR a.publish_at <= NOW()) AND a.deleted_at IS NULL";
    $params = [];
    if ($blogId !== null) {
        $where .= " AND a.blog_id = ?";
        $params[] = $blogId;
    }
    $params[] = $count;

    $stmt = $pdo->prepare(
        "SELECT a.id, a.title, a.slug, a.perex, a.image_file, a.created_at, a.blog_id, a.view_count,
                b.slug AS blog_slug, c.name AS category
         FROM cms_articles a
         LEFT JOIN cms_blogs b ON b.id = a.blog_id
         LEFT JOIN cms_categories c ON c.id = a.category_id
         {$where}
         ORDER BY a.created_at DESC LIMIT ?"
    );
    $stmt->execute($params);
    $articles = $stmt->fetchAll();

    if (empty($articles)) {
        return '';
    }

    $title = h($widget['title'] ?: 'Nejnovější články');

    if ($zone === 'sidebar' || $zone === 'footer') {
        $out = '<section class="widget-card" aria-label="' . $title . '">';
        $out .= '<h3 class="widget-card__title">' . $title . '</h3><ul class="widget-list">';
        foreach ($articles as $a) {
            $out .= '<li><a href="' . h(articlePublicPath($a)) . '">' . h($a['title']) . '</a></li>';
        }
        $out .= '</ul></section>';
        return $out;
    }

    $out = '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">';
    $out .= '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>';
    $out .= '<div class="card-grid">';
    foreach ($articles as $a) {
        $out .= '<article class="card">';
        if (!empty($a['image_file'])) {
            $out .= '<a class="card__media" href="' . h(articlePublicPath($a)) . '">'
                   . '<img src="' . BASE_URL . '/uploads/articles/thumbs/' . rawurlencode($a['image_file']) . '" alt="" loading="lazy">'
                   . '</a>';
        }
        $out .= '<div class="card__body"><h3 class="card__title"><a href="' . h(articlePublicPath($a)) . '">' . h($a['title']) . '</a></h3>';
        if (!empty($a['perex'])) {
            $out .= '<p class="card__excerpt">' . h(mb_substr(strip_tags($a['perex']), 0, 150)) . '</p>';
        }
        $out .= '<p class="meta-row"><time>' . formatCzechDate($a['created_at']) . '</time></p>';
        $out .= '</div></article>';
    }
    $out .= '</div></section>';
    return $out;
}

function renderWidget_latest_news(array $widget, array $settings, string $zone): string
{
    $count = max(1, (int)($settings['count'] ?? 5));
    $pdo = db_connect();
    $stmt = $pdo->prepare(
        "SELECT id, title, slug, created_at FROM cms_news
         WHERE status = 'published' AND deleted_at IS NULL
         ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->execute([$count]);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        return '';
    }

    $title = h($widget['title'] ?: 'Novinky');

    if ($zone === 'sidebar' || $zone === 'footer') {
        $out = '<section class="widget-card" aria-label="' . $title . '">';
        $out .= '<h3 class="widget-card__title">' . $title . '</h3><ul class="widget-list">';
        foreach ($items as $n) {
            $out .= '<li><a href="' . h(newsPublicPath($n)) . '">' . h($n['title']) . '</a></li>';
        }
        $out .= '</ul></section>';
        return $out;
    }

    $out = '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">';
    $out .= '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>';
    $out .= '<ul class="link-list">';
    foreach ($items as $n) {
        $out .= '<li class="link-list__item"><a class="link-list__title" href="' . h(newsPublicPath($n)) . '">' . h($n['title']) . '</a>'
               . '<time class="meta-row">' . formatCzechDate($n['created_at']) . '</time></li>';
    }
    $out .= '</ul></section>';
    return $out;
}

function renderWidget_latest_downloads(array $widget, array $settings, string $zone): string
{
    $count = max(1, (int)($settings['count'] ?? 5));
    $pdo = db_connect();
    $stmt = $pdo->prepare(
        "SELECT d.id, d.title, d.slug, d.download_type, d.excerpt, d.description, d.image_file,
                d.version_label, d.platform_label, d.created_at, d.filename, d.original_name, d.file_size, d.external_url
         FROM cms_downloads d
         WHERE d.status = 'published' AND d.is_published = 1
         ORDER BY d.created_at DESC, d.id DESC
         LIMIT ?"
    );
    $stmt->execute([$count]);
    $items = array_map(
        static fn(array $download): array => hydrateDownloadPresentation($download),
        $stmt->fetchAll()
    );

    if ($items === []) {
        return '';
    }

    $title = h($widget['title'] ?: 'Ke stažení');

    if ($zone === 'sidebar' || $zone === 'footer') {
        $out = '<section class="widget-card" aria-label="' . $title . '">';
        $out .= '<h3 class="widget-card__title">' . $title . '</h3><ul class="widget-list">';
        foreach ($items as $download) {
            $meta = [];
            if ($download['version_label'] !== '') {
                $meta[] = $download['version_label'];
            }
            if ($download['platform_label'] !== '') {
                $meta[] = $download['platform_label'];
            }
            $out .= '<li><a href="' . h(downloadPublicPath($download)) . '">' . h($download['title']) . '</a>';
            if ($meta !== []) {
                $out .= '<br><small>' . h(implode(' · ', $meta)) . '</small>';
            }
            $out .= '</li>';
        }
        $out .= '</ul></section>';
        return $out;
    }

    $out = '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">';
    $out .= '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>';
    $out .= '<div class="card-grid card-grid--compact">';
    foreach ($items as $download) {
        $out .= '<article class="card">';
        if (!empty($download['image_url'])) {
            $out .= '<a class="card__media" href="' . h(downloadPublicPath($download)) . '">'
                  . '<img src="' . h((string)$download['image_url']) . '" alt="" loading="lazy">'
                  . '</a>';
        }
        $out .= '<div class="card__body">';
        $out .= '<h3 class="card__title"><a href="' . h(downloadPublicPath($download)) . '">' . h($download['title']) . '</a></h3>';
        $out .= '<p class="meta-row meta-row--tight"><span>' . h((string)$download['download_type_label']) . '</span>';
        if ($download['version_label'] !== '') {
            $out .= '<span>' . h($download['version_label']) . '</span>';
        }
        if ($download['platform_label'] !== '') {
            $out .= '<span>' . h($download['platform_label']) . '</span>';
        }
        $out .= '</p>';
        if ($download['excerpt_plain'] !== '') {
            $out .= '<p>' . h(mb_substr((string)$download['excerpt_plain'], 0, 150)) . '</p>';
        }
        $out .= '<p><a class="section-link" href="' . h(downloadPublicPath($download)) . '">Zobrazit položku <span aria-hidden="true">→</span></a></p>';
        $out .= '</div></article>';
    }
    $out .= '</div></section>';
    return $out;
}

function renderWidget_latest_faq(array $widget, array $settings, string $zone): string
{
    $count = max(1, (int)($settings['count'] ?? 5));
    $pdo = db_connect();
    $stmt = $pdo->prepare(
        "SELECT f.id, f.question, f.slug, f.excerpt, f.answer, f.updated_at,
                COALESCE(c.name, '') AS category_name
         FROM cms_faqs f
         LEFT JOIN cms_faq_categories c ON c.id = f.category_id
         WHERE COALESCE(f.status,'published') = 'published' AND f.is_published = 1
         ORDER BY f.updated_at DESC, f.created_at DESC, f.id DESC
         LIMIT ?"
    );
    $stmt->execute([$count]);
    $items = array_map(
        static fn(array $faq): array => hydrateFaqPresentation($faq),
        $stmt->fetchAll()
    );

    if ($items === []) {
        return '';
    }

    $title = h($widget['title'] ?: 'Nejčastější dotazy');

    if ($zone === 'sidebar' || $zone === 'footer') {
        $out = '<section class="widget-card" aria-label="' . $title . '">';
        $out .= '<h3 class="widget-card__title">' . $title . '</h3><ul class="widget-list">';
        foreach ($items as $faq) {
            $out .= '<li><a href="' . h(faqPublicPath($faq)) . '">' . h($faq['question']) . '</a></li>';
        }
        $out .= '</ul></section>';
        return $out;
    }

    $out = '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">';
    $out .= '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>';
    $out .= '<ul class="link-list">';
    foreach ($items as $faq) {
        $out .= '<li class="link-list__item">';
        $out .= '<a class="link-list__title" href="' . h(faqPublicPath($faq)) . '">' . h($faq['question']) . '</a>';
        if (!empty($faq['category_name'])) {
            $out .= '<p class="meta-row meta-row--tight"><span>' . h((string)$faq['category_name']) . '</span></p>';
        }
        if (!empty($faq['excerpt'])) {
            $out .= '<p>' . h(mb_substr((string)$faq['excerpt'], 0, 170)) . '</p>';
        }
        $out .= '</li>';
    }
    $out .= '</ul></section>';
    return $out;
}

function renderWidget_latest_places(array $widget, array $settings, string $zone): string
{
    $count = max(1, (int)($settings['count'] ?? 5));
    $pdo = db_connect();
    $stmt = $pdo->prepare(
        "SELECT *
         FROM cms_places
         WHERE status = 'published' AND is_published = 1
         ORDER BY name ASC
         LIMIT ?"
    );
    $stmt->execute([$count]);
    $places = array_map(
        static fn(array $place): array => hydratePlacePresentation($place),
        $stmt->fetchAll()
    );

    if ($places === []) {
        return '';
    }

    $title = h($widget['title'] ?: 'Zajímavá místa');

    if ($zone === 'sidebar' || $zone === 'footer') {
        $out = '<section class="widget-card" aria-label="' . $title . '">';
        $out .= '<h3 class="widget-card__title">' . $title . '</h3><ul class="widget-list">';
        foreach ($places as $place) {
            $out .= '<li><a href="' . h(placePublicPath($place)) . '">' . h($place['name']) . '</a>';
            if ($place['locality'] !== '') {
                $out .= '<br><small>' . h($place['locality']) . '</small>';
            }
            $out .= '</li>';
        }
        $out .= '</ul></section>';
        return $out;
    }

    $out = '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">';
    $out .= '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>';
    $out .= '<div class="card-grid card-grid--compact">';
    foreach ($places as $place) {
        $out .= '<article class="card">';
        if (!empty($place['image_url'])) {
            $out .= '<a class="card__media" href="' . h(placePublicPath($place)) . '">'
                  . '<img src="' . h((string)$place['image_url']) . '" alt="" loading="lazy">'
                  . '</a>';
        }
        $out .= '<div class="card__body">';
        $out .= '<h3 class="card__title"><a href="' . h(placePublicPath($place)) . '">' . h($place['name']) . '</a></h3>';
        $out .= '<p class="meta-row meta-row--tight"><span>' . h((string)$place['place_kind_label']) . '</span>';
        if ($place['locality'] !== '') {
            $out .= '<span>' . h($place['locality']) . '</span>';
        }
        $out .= '</p>';
        if ($place['excerpt_plain'] !== '') {
            $out .= '<p>' . h(mb_substr((string)$place['excerpt_plain'], 0, 150)) . '</p>';
        }
        $out .= '<p><a class="section-link" href="' . h(placePublicPath($place)) . '">Zobrazit místo <span aria-hidden="true">→</span></a></p>';
        $out .= '</div></article>';
    }
    $out .= '</div></section>';
    return $out;
}

function renderWidget_latest_podcast_episodes(array $widget, array $settings, string $zone): string
{
    $count = max(1, (int)($settings['count'] ?? 5));
    $showId = (int)($settings['show_id'] ?? 0);
    $pdo = db_connect();

    $where = "WHERE p.status = 'published' AND (p.publish_at IS NULL OR p.publish_at <= NOW())";
    $params = [];
    if ($showId > 0) {
        $where .= " AND p.show_id = ?";
        $params[] = $showId;
    }
    $params[] = $count;

    $stmt = $pdo->prepare(
        "SELECT p.*, s.slug AS show_slug, s.title AS show_title
         FROM cms_podcasts p
         INNER JOIN cms_podcast_shows s ON s.id = p.show_id
         {$where}
         ORDER BY COALESCE(p.publish_at, p.created_at) DESC, COALESCE(p.episode_num, 0) DESC, p.id DESC
         LIMIT ?"
    );
    $stmt->execute($params);
    $episodes = array_map(
        static fn(array $episode): array => hydratePodcastEpisodePresentation($episode),
        $stmt->fetchAll()
    );

    if ($episodes === []) {
        return '';
    }

    $title = h($widget['title'] ?: 'Nejnovější epizody');

    if ($zone === 'sidebar' || $zone === 'footer') {
        $out = '<section class="widget-card" aria-label="' . $title . '">';
        $out .= '<h3 class="widget-card__title">' . $title . '</h3><ul class="widget-list">';
        foreach ($episodes as $episode) {
            $out .= '<li><a href="' . h(podcastEpisodePublicPath($episode)) . '">' . h($episode['title']) . '</a>';
            $out .= '<br><small>' . h((string)$episode['show_title']) . ' · ' . h(formatCzechDate((string)$episode['display_date'])) . '</small></li>';
        }
        $out .= '</ul></section>';
        return $out;
    }

    $out = '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">';
    $out .= '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>';
    $out .= '<ul class="link-list">';
    foreach ($episodes as $episode) {
        $out .= '<li class="link-list__item">';
        $out .= '<a class="link-list__title" href="' . h(podcastEpisodePublicPath($episode)) . '">' . h($episode['title']) . '</a>';
        $out .= '<p class="meta-row meta-row--tight"><span>' . h((string)$episode['show_title']) . '</span><time datetime="' . h(str_replace(' ', 'T', (string)$episode['display_date'])) . '">' . formatCzechDate((string)$episode['display_date']) . '</time></p>';
        if ($episode['excerpt'] !== '') {
            $out .= '<p>' . h(mb_substr((string)$episode['excerpt'], 0, 170)) . '</p>';
        }
        $out .= '</li>';
    }
    $out .= '</ul></section>';
    return $out;
}

function renderWidget_poll(array $widget, array $settings, string $zone): string
{
    $pdo = db_connect();
    $now = date('Y-m-d H:i:s');
    try {
        $stmt = $pdo->prepare(
            "SELECT id, question, slug
             FROM cms_polls
             WHERE status = 'active'
               AND (start_date IS NULL OR start_date <= ?)
               AND (end_date IS NULL OR end_date > ?)
             ORDER BY COALESCE(start_date, created_at) DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$now, $now]);
        $poll = $stmt->fetch();
    } catch (\PDOException $e) {
        return '';
    }
    if (!$poll) {
        return '';
    }

    $title = h($widget['title'] ?: 'Aktuální anketa');
    $path = pollPublicPath($poll);

    if ($zone === 'sidebar' || $zone === 'footer') {
        return '<section class="widget-card" aria-label="' . $title . '">'
             . '<h3 class="widget-card__title">' . $title . '</h3>'
             . '<p><strong>' . h($poll['question']) . '</strong></p>'
             . '<p><a href="' . h($path) . '">Hlasovat</a></p></section>';
    }

    return '<section class="surface surface--accent home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">'
         . '<h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2>'
         . '<p><strong>' . h($poll['question']) . '</strong></p>'
         . '<div class="button-row button-row--start"><a class="button-primary" href="' . h($path) . '">Hlasovat</a></div>'
         . '</section>';
}

function renderWidget_newsletter(array $widget, array $settings, string $zone): string
{
    $title = h($widget['title'] ?: 'Zůstaňte v kontaktu');
    $ctaText = h($settings['cta_text'] ?? 'Přihlaste se k odběru a dostávejte nové články přímo e-mailem.');

    if ($zone === 'sidebar' || $zone === 'footer') {
        return '<section class="widget-card" aria-label="' . $title . '">'
             . '<h3 class="widget-card__title">' . $title . '</h3>'
             . '<p>' . $ctaText . '</p>'
             . '<p><a href="' . BASE_URL . '/subscribe.php">Přihlásit odběr</a></p></section>';
    }

    return '<section class="surface surface--accent home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">'
         . '<h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2>'
         . '<p class="section-subtitle">' . $ctaText . '</p>'
         . '<div class="button-row button-row--start"><a class="button-primary" href="' . BASE_URL . '/subscribe.php">Přihlásit odběr</a></div>'
         . '</section>';
}

function renderWidget_board(array $widget, array $settings, string $zone): string
{
    $count = max(1, (int)($settings['count'] ?? 5));
    $pdo = db_connect();
    $stmt = $pdo->prepare(
        "SELECT id, title, slug, created_at FROM cms_board
         WHERE is_published = 1 AND COALESCE(status,'published') = 'published'
         ORDER BY is_pinned DESC, created_at DESC LIMIT ?"
    );
    $stmt->execute([$count]);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        return '';
    }

    $title = h($widget['title'] ?: boardModulePublicLabel());

    if ($zone === 'sidebar' || $zone === 'footer') {
        $out = '<section class="widget-card" aria-label="' . $title . '">';
        $out .= '<h3 class="widget-card__title">' . $title . '</h3><ul class="widget-list">';
        foreach ($items as $b) {
            $out .= '<li><a href="' . h(boardPublicPath($b)) . '">' . h($b['title']) . '</a></li>';
        }
        $out .= '</ul></section>';
        return $out;
    }

    $out = '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">';
    $out .= '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>';
    $out .= '<ul class="link-list">';
    foreach ($items as $b) {
        $out .= '<li class="link-list__item"><a class="link-list__title" href="' . h(boardPublicPath($b)) . '">' . h($b['title']) . '</a></li>';
    }
    $out .= '</ul></section>';
    return $out;
}

function renderWidget_upcoming_events(array $widget, array $settings, string $zone): string
{
    $count = max(1, (int)($settings['count'] ?? 5));
    $pdo = db_connect();
    $stmt = $pdo->prepare(
        "SELECT id, title, slug, event_date FROM cms_events
         WHERE is_published = 1 AND status = 'published' AND event_date >= CURDATE()
         ORDER BY event_date ASC LIMIT ?"
    );
    $stmt->execute([$count]);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        return '';
    }

    $title = h($widget['title'] ?: 'Nadcházející události');

    if ($zone === 'sidebar' || $zone === 'footer') {
        $out = '<section class="widget-card" aria-label="' . $title . '">';
        $out .= '<h3 class="widget-card__title">' . $title . '</h3><ul class="widget-list">';
        foreach ($items as $e) {
            $out .= '<li><a href="' . h(eventPublicPath($e)) . '">' . h($e['title']) . '</a>'
                   . '<br><small>' . formatCzechDate($e['event_date']) . '</small></li>';
        }
        $out .= '</ul></section>';
        return $out;
    }

    $out = '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">';
    $out .= '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>';
    $out .= '<ul class="link-list">';
    foreach ($items as $e) {
        $out .= '<li class="link-list__item"><a class="link-list__title" href="' . h(eventPublicPath($e)) . '">' . h($e['title']) . '</a>'
               . '<time class="meta-row">' . formatCzechDate($e['event_date']) . '</time></li>';
    }
    $out .= '</ul></section>';
    return $out;
}

function renderWidget_custom_html(array $widget, array $settings, string $zone): string
{
    $content = $settings['content'] ?? '';
    if (trim($content) === '') {
        return '';
    }
    $title = h($widget['title'] ?: 'Vlastní blok');

    if ($zone === 'sidebar' || $zone === 'footer') {
        return '<section class="widget-card" aria-label="' . $title . '">'
             . '<h3 class="widget-card__title">' . $title . '</h3>'
             . '<div class="prose">' . renderContent($content) . '</div></section>';
    }

    return '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">'
         . '<h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2>'
         . '<div class="prose">' . renderContent($content) . '</div></section>';
}

function renderWidget_search(array $widget, array $settings, string $zone): string
{
    $title = h($widget['title'] ?: 'Vyhledávání');
    $inputId = 'widget-search-q-' . (int)($widget['id'] ?? 0);

    if ($zone === 'homepage') {
        return '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">'
             . '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>'
             . '<form action="' . BASE_URL . '/search.php" method="get">'
             . '<label for="' . h($inputId) . '" class="visually-hidden">Hledat</label>'
             . '<input type="search" id="' . h($inputId) . '" name="q" class="form-control" placeholder="Hledat na webu…">'
             . '<button type="submit" class="button-primary" style="margin-top:.5rem">Hledat</button>'
             . '</form></section>';
    }

    return '<section class="widget-card" aria-label="' . $title . '">'
         . '<h3 class="widget-card__title">' . $title . '</h3>'
         . '<form action="' . BASE_URL . '/search.php" method="get">'
         . '<label for="' . h($inputId) . '" class="visually-hidden">Hledat</label>'
         . '<input type="search" id="' . h($inputId) . '" name="q" class="form-control" placeholder="Hledat na webu…">'
         . '<button type="submit" class="button-primary" style="margin-top:.5rem">Hledat</button>'
         . '</form></section>';
}

function renderWidget_contact_info(array $widget, array $settings, string $zone): string
{
    $email = getSetting('contact_email', '');
    $siteName = getSetting('site_name', '');
    if ($email === '' && $siteName === '') {
        return '';
    }
    $title = h($widget['title'] ?: 'Kontakt');
    $tag = $zone === 'homepage' ? 'h2' : 'h3';
    $wrapperStart = $zone === 'homepage'
        ? '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">'
        : '<section class="widget-card" aria-label="' . $title . '">';
    $out = $wrapperStart;
    $out .= '<' . $tag . ($zone === 'homepage' ? ' id="w-' . (int)$widget['id'] . '-title" class="section-title"' : ' class="widget-card__title"') . '>' . $title . '</' . $tag . '>';
    if ($siteName !== '') {
        $out .= '<p><strong>' . h($siteName) . '</strong></p>';
    }
    if ($email !== '') {
        $out .= '<p><a href="mailto:' . h($email) . '">' . h($email) . '</a></p>';
    }
    if (isModuleEnabled('contact')) {
        $out .= '<p><a href="' . BASE_URL . '/contact/index.php">Kontaktní formulář</a></p>';
    }
    $out .= '</section>';
    return $out;
}

function renderWidget_featured_article(array $widget, array $settings, string $zone): string
{
    $source = $settings['source'] ?? 'blog';
    $pdo = db_connect();
    $rawWidgetTitle = trim((string)($widget['title'] ?? ''));
    $useSourceSpecificTitle = $rawWidgetTitle === '' || $rawWidgetTitle === 'Doporučený obsah';

    if ($source === 'poll' && isModuleEnabled('polls')) {
        $pollWidget = $widget;
        if ($useSourceSpecificTitle) {
            $pollWidget['title'] = 'Aktuální anketa';
        }
        return renderWidget_poll($pollWidget, $settings, $zone);
    }

    if ($source === 'newsletter' && isModuleEnabled('newsletter')) {
        $newsletterWidget = $widget;
        if ($useSourceSpecificTitle) {
            $newsletterWidget['title'] = 'Zůstaňte v kontaktu';
        }
        return renderWidget_newsletter($newsletterWidget, $settings, $zone);
    }

    if ($source === 'board' && isModuleEnabled('board')) {
        $stmt = $pdo->prepare(
            "SELECT b.id, b.title, b.slug, b.board_type, b.posted_date, b.created_at, b.excerpt, b.description,
                    b.image_file, b.filename, b.original_name, b.file_size, b.contact_name, b.contact_phone, b.contact_email,
                    b.is_pinned, COALESCE(c.name, '') AS category_name
             FROM cms_board b
             LEFT JOIN cms_board_categories c ON c.id = b.category_id
             WHERE b.is_published = 1 AND COALESCE(b.status, 'published') = 'published'
             ORDER BY b.is_pinned DESC, b.posted_date DESC, b.created_at DESC
             LIMIT 1"
        );
        $stmt->execute();
        $document = $stmt->fetch();
        if (!$document) {
            return '';
        }
        $document = hydrateBoardPresentation($document);
        $title = h($useSourceSpecificTitle ? 'Zvýrazněná položka' : $rawWidgetTitle);

        if ($zone === 'sidebar' || $zone === 'footer') {
            $out = '<section class="widget-card" aria-label="' . $title . '">';
            $out .= '<h3 class="widget-card__title">' . $title . '</h3>';
            $out .= '<p><a href="' . h(boardPublicPath($document)) . '"><strong>' . h($document['title']) . '</strong></a></p>';
            if ($document['excerpt_plain'] !== '') {
                $out .= '<p>' . h(mb_substr((string)$document['excerpt_plain'], 0, 160)) . '</p>';
            }
            $out .= '<p><a href="' . h(boardPublicPath($document)) . '">Zobrazit detail</a></p>';
            $out .= '</section>';
            return $out;
        }

        return '<section class="surface surface--accent home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">'
             . '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>'
             . '<p class="meta-row meta-row--tight"><span class="pill">' . h((string)$document['board_type_label']) . '</span>'
             . ($document['category_name'] !== '' ? '<span>' . h((string)$document['category_name']) . '</span>' : '')
             . '<time datetime="' . h((string)$document['posted_date']) . '">' . formatCzechDate((string)$document['posted_date']) . '</time></p>'
             . '<h3 class="card__title card__title--feature"><a href="' . h(boardPublicPath($document)) . '">' . h($document['title']) . '</a></h3>'
             . ($document['excerpt_plain'] !== '' ? '<p>' . h(mb_substr((string)$document['excerpt_plain'], 0, 200)) . '</p>' : '')
             . '<div class="button-row button-row--start"><a class="button-primary" href="' . h(boardPublicPath($document)) . '">Zobrazit detail</a></div>'
             . '</section>';
    }

    if ($source === 'blog' && isModuleEnabled('blog')) {
        $article = $pdo->query(
            "SELECT a.id, a.title, a.slug, a.perex, a.image_file, a.blog_id, a.view_count, b.slug AS blog_slug
             FROM cms_articles a LEFT JOIN cms_blogs b ON b.id = a.blog_id
             WHERE a.status = 'published' AND a.deleted_at IS NULL AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             ORDER BY a.view_count DESC, a.created_at DESC LIMIT 1"
        )->fetch();
        if (!$article) {
            return '';
        }
        $title = h($useSourceSpecificTitle ? 'Doporučený článek' : $rawWidgetTitle);
        if ($zone === 'sidebar' || $zone === 'footer') {
            return '<section class="widget-card" aria-label="' . $title . '">'
                 . '<h3 class="widget-card__title">' . $title . '</h3>'
                 . '<p><a href="' . h(articlePublicPath($article)) . '"><strong>' . h($article['title']) . '</strong></a></p>'
                 . '</section>';
        }
        return '<section class="surface surface--accent home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">'
             . '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>'
             . '<p><a href="' . h(articlePublicPath($article)) . '"><strong>' . h($article['title']) . '</strong></a></p>'
             . (!empty($article['perex']) ? '<p>' . h(mb_substr(strip_tags($article['perex']), 0, 200)) . '</p>' : '')
             . '<div class="button-row button-row--start"><a class="button-primary" href="' . h(articlePublicPath($article)) . '">Číst článek</a></div>'
             . '</section>';
    }

    return '';
}

function renderWidget_gallery_preview(array $widget, array $settings, string $zone): string
{
    $albumId = (int)($settings['album_id'] ?? 0);
    $pdo = db_connect();
    $where = "WHERE COALESCE(p.status,'published') = 'published' AND COALESCE(p.is_published, 1) = 1";
    $params = [];
    if ($albumId > 0) {
        $where .= " AND p.album_id = ?";
        $params[] = $albumId;
    }
    $params[] = 6;

    $stmt = $pdo->prepare(
        "SELECT p.id, p.filename, p.title, p.slug FROM cms_gallery_photos p {$where} ORDER BY p.id DESC LIMIT ?"
    );
    $stmt->execute($params);
    $photos = $stmt->fetchAll();

    if (empty($photos)) {
        return '';
    }

    $title = h($widget['title'] ?: 'Galerie');

    if ($zone === 'sidebar' || $zone === 'footer') {
        $out = '<section class="widget-card" aria-label="' . $title . '">';
        $out .= '<h3 class="widget-card__title">' . $title . '</h3>';
        $out .= '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.3rem">';
        foreach ($photos as $p) {
            $out .= '<img src="' . BASE_URL . '/uploads/gallery/thumbs/' . rawurlencode($p['filename']) . '" alt="' . h($p['title'] ?? '') . '" loading="lazy" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:.3rem">';
        }
        $out .= '</div>';
        $out .= '<p style="margin-top:.5rem"><a href="' . BASE_URL . '/gallery/index.php">Celá galerie</a></p>';
        $out .= '</section>';
        return $out;
    }

    $out = '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">';
    $out .= '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>';
    $out .= '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.5rem">';
    foreach ($photos as $p) {
        $out .= '<img src="' . BASE_URL . '/uploads/gallery/thumbs/' . rawurlencode($p['filename']) . '" alt="' . h($p['title'] ?? '') . '" loading="lazy" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:.5rem">';
    }
    $out .= '</div>';
    $out .= '<div class="button-row button-row--start" style="margin-top:.75rem"><a class="button-secondary" href="' . BASE_URL . '/gallery/index.php">Celá galerie</a></div>';
    $out .= '</section>';
    return $out;
}

function renderWidget_selected_form(array $widget, array $settings, string $zone): string
{
    $formId = (int)($settings['form_id'] ?? 0);
    if ($formId <= 0) {
        return '';
    }

    $stmt = db_connect()->prepare(
        "SELECT id, title, slug, description
         FROM cms_forms
         WHERE id = ? AND is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$formId]);
    $form = $stmt->fetch();
    if (!$form) {
        return '';
    }

    $rawWidgetTitle = trim((string)($widget['title'] ?? ''));
    $title = h(($rawWidgetTitle !== '' && $rawWidgetTitle !== 'Vybraný formulář') ? $rawWidgetTitle : (string)$form['title']);
    $description = trim((string)($form['description'] ?? ''));

    if ($zone === 'sidebar' || $zone === 'footer') {
        $out = '<section class="widget-card" aria-label="' . $title . '">';
        $out .= '<h3 class="widget-card__title">' . $title . '</h3>';
        if ($description !== '') {
            $out .= '<p>' . h(mb_substr(strip_tags($description), 0, 180)) . '</p>';
        }
        $out .= '<p><a href="' . h(formPublicPath($form)) . '">Vyplnit formulář</a></p>';
        $out .= '</section>';
        return $out;
    }

    $out = '<section class="surface home-section" aria-labelledby="w-' . (int)$widget['id'] . '-title">';
    $out .= '<div class="section-heading"><div><h2 id="w-' . (int)$widget['id'] . '-title" class="section-title">' . $title . '</h2></div></div>';
    if ($description !== '') {
        $out .= '<div class="prose"><p>' . h(mb_substr(strip_tags($description), 0, 260)) . '</p></div>';
    }
    $out .= '<div class="button-row button-row--start"><a class="button-primary" href="' . h(formPublicPath($form)) . '">Vyplnit formulář</a></div>';
    $out .= '</section>';
    return $out;
}
