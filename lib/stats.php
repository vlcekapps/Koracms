<?php
// Statistiky návštěvnosti, auto-complete rezervací, navigace – extrahováno z db.php

// ─────────────────────────────── Statistiky ──────────────────────────────────

/**
 * Zaznamená zobrazení stránky (jedno volání za request).
 * Přeskočí adminy a známé boty. Kontroluje visitor_tracking_enabled.
 */
function trackPageView(string $pageType = 'other', ?int $refId = null): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    if (getSetting('visitor_tracking_enabled', '0') !== '1') return;
    if (isset($_SESSION['cms_user_id'])) return; // admin/spolupracovník

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|wget|curl/i', $ua)) return;

    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ipHash  = hash('sha256', $ip . '|' . date('Y-m-d'));
    $pageUrl = mb_substr(($_SERVER['REQUEST_URI'] ?? '/'), 0, 500);
    $ref     = mb_substr(($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);

    try {
        db_connect()->prepare(
            "INSERT INTO cms_page_views (page_url, page_type, page_ref_id, ip_hash, user_agent, referrer)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $pageUrl,
            $pageType,
            $refId,
            $ipHash,
            mb_substr($ua, 0, 500),
            $ref,
        ]);
    } catch (\PDOException $e) {
        // Tabulka nemusí existovat
    }
}

/**
 * Počet unikátních návštěvníků online (za posledních 5 minut).
 */
function getOnlineCount(): int
{
    try {
        return (int)db_connect()->query(
            "SELECT COUNT(DISTINCT ip_hash) FROM cms_page_views
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        )->fetchColumn();
    } catch (\PDOException $e) {
        return 0;
    }
}

/**
 * Vrátí statistiky návštěvnosti: online, today, month, total.
 */
function getVisitorStats(): array
{
    $stats = ['online' => 0, 'today' => 0, 'month' => 0, 'total' => 0];
    try {
        $pdo = db_connect();

        // Online (posledních 5 min)
        $stats['online'] = (int)$pdo->query(
            "SELECT COUNT(DISTINCT ip_hash) FROM cms_page_views
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        )->fetchColumn();

        // Dnes (unikátní IP)
        $stats['today'] = (int)$pdo->query(
            "SELECT COUNT(DISTINCT ip_hash) FROM cms_page_views
             WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();

        // Měsíc = agregáty z cms_stats_daily + dnešní live data
        $monthAgg = (int)$pdo->query(
            "SELECT COALESCE(SUM(unique_visitors), 0) FROM cms_stats_daily
             WHERE stat_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
               AND stat_date < CURDATE()"
        )->fetchColumn();
        $stats['month'] = $monthAgg + $stats['today'];

        // Celkem = agregáty + dnešní live data
        $totalAgg = (int)$pdo->query(
            "SELECT COALESCE(SUM(unique_visitors), 0) FROM cms_stats_daily
             WHERE stat_date < CURDATE()"
        )->fetchColumn();
        $stats['total'] = $totalAgg + $stats['today'];

    } catch (\PDOException $e) {
        // Tabulky nemusí existovat
    }
    return $stats;
}

/**
 * Líná agregace denních statistik + mazání starých raw dat (GDPR).
 * Volá se při návštěvě admin statistik.
 */
function statsCleanup(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $pdo = db_connect();

        // Agregace: dny starší než včerejšek, které ještě nejsou v cms_stats_daily
        $pdo->exec(
            "INSERT IGNORE INTO cms_stats_daily (stat_date, total_views, unique_visitors)
             SELECT DATE(created_at),
                    COUNT(*),
                    COUNT(DISTINCT ip_hash)
             FROM cms_page_views
             WHERE DATE(created_at) < CURDATE()
             GROUP BY DATE(created_at)"
        );

        // Mazání raw dat starších než retence
        $days = max(1, (int)getSetting('stats_retention_days', '90'));
        $pdo->prepare(
            "DELETE FROM cms_page_views WHERE created_at < DATE_SUB(CURDATE(), INTERVAL ? DAY)"
        )->execute([$days]);

    } catch (\PDOException $e) {
        // Tabulky nemusí existovat
    }
}

/** Automatické dokončení proběhlých rezervací (lazy update) */
function autoCompleteBookings(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (!isModuleEnabled('reservations')) return;
    try {
        $pdo = db_connect();
        // confirmed → completed
        $pdo->exec(
            "UPDATE cms_res_bookings SET status = 'completed', updated_at = NOW()
             WHERE status = 'confirmed'
               AND (booking_date < CURDATE() OR (booking_date = CURDATE() AND end_time < CURTIME()))"
        );
        // pending → cancelled (termín vypršel bez schválení)
        $pdo->exec(
            "UPDATE cms_res_bookings
             SET status = 'cancelled', updated_at = NOW(), cancelled_at = NOW(),
                 admin_note = CASE
                     WHEN COALESCE(admin_note, '') = '' THEN 'Automaticky zrušeno – termín vypršel bez schválení'
                     ELSE CONCAT(admin_note, '\nAutomaticky zrušeno – termín vypršel bez schválení')
                 END
             WHERE status = 'pending'
               AND (booking_date < CURDATE() OR (booking_date = CURDATE() AND end_time < CURTIME()))"
        );
    } catch (\PDOException $e) {
        // Tabulka nemusí existovat
    }
}

/** Výchozí pořadí modulů v navigaci */
function navModuleDefaults(): array
{
    return [
        'blog'      => ['/blog/index.php',       'Blog'],
        'news'      => ['/news/index.php',        'Novinky'],
        'events'    => ['/events/index.php',      'Akce'],
        'podcast'   => ['/podcast/index.php',     'Podcasty'],
        'gallery'   => ['/gallery/index.php',     'Galerie'],
        'places'    => ['/places/index.php',      'Zajímavá místa'],
        'downloads' => ['/downloads/index.php',   'Ke stažení'],
        'food'      => ['/food/index.php',        'Jídelní lístek'],
        'chat'      => ['/chat/index.php',        'Chat'],
        'polls'     => ['/polls/index.php',       'Ankety'],
        'faq'       => ['/faq/index.php',         'FAQ'],
        'board'     => ['/board/index.php',       boardModulePublicLabel()],
        'reservations' => ['/reservations/index.php', 'Rezervace'],
        'contact'   => ['/contact/index.php',     'Kontakt'],
    ];
}

/** Vrátí aktuální pořadí klíčů modulů dle nastavení (nebo výchozí) */
function navModuleOrder(): array
{
    $defaults = array_keys(navModuleDefaults());
    $saved    = getSetting('nav_module_order', '');
    if ($saved === '') return $defaults;

    $order = array_filter(explode(',', $saved), fn($k) => isset(navModuleDefaults()[$k]));
    $order = array_values($order);
    // Přidej nové moduly, které v uloženém pořadí chybí
    foreach ($defaults as $k) {
        if (!in_array($k, $order, true)) $order[] = $k;
    }
    return $order;
}

/** Navigace webu – zobrazí jen povolené moduly v nastavitelném pořadí */
function siteNav(string $current = ''): string
{
    $b   = BASE_URL;
    $cur = function(string $p) use ($current) {
        return $current === $p ? ' aria-current="page"' : '';
    };
    $li  = function(string $href, string $label, string $page) use ($b, $cur) {
        return '<li><a href="' . $b . $href . '"' . $cur($page) . '>' . $label . '</a></li>' . "\n";
    };

    $nav  = '<nav aria-label="Hlavní navigace"><ul>' . "\n";
    $nav .= $li('/index.php', 'Domů', 'home');

    // Statické stránky zobrazované v navigaci (za Domů, před moduly)
    try {
        $pages = db_connect()->query(
            "SELECT title, slug FROM cms_pages
             WHERE show_in_nav = 1 AND is_published = 1
             ORDER BY nav_order, title"
        )->fetchAll();
        foreach ($pages as $p) {
            $nav .= '<li><a href="' . pagePublicPath($p) . '"'
                   . ($current === 'page:' . $p['slug'] ? ' aria-current="page"' : '')
                   . '>' . h($p['title']) . '</a></li>' . "\n";
        }
    } catch (\PDOException $e) {
        // Tabulka cms_pages ještě neexistuje
    }

    $moduleMap = navModuleDefaults();
    foreach (navModuleOrder() as $key) {
        if (!isModuleEnabled($key) || !isset($moduleMap[$key])) {
            continue;
        }
        if ($key === 'blog' && isMultiBlog()) {
            foreach (getAllBlogs() as $blogEntry) {
                $blogHref = blogIndexPath($blogEntry);
                $blogNavKey = 'blog:' . $blogEntry['slug'];
                $nav .= '<li><a href="' . h($blogHref) . '"'
                       . ($current === $blogNavKey ? ' aria-current="page"' : '')
                       . '>' . h((string)$blogEntry['name']) . '</a></li>' . "\n";
            }
        } else {
            [$href, $label] = $moduleMap[$key];
            $nav .= $li($href, $label, $key);
        }
    }

    if (isLoggedIn()) $nav .= $li('/admin/index.php', 'Administrace', 'admin');

    $nav .= '</ul></nav>';
    return $nav;
}
