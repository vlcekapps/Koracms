<?php

// Statistiky návštěvnosti, auto-complete rezervací, navigace – extrahováno z db.php

// ─────────────────────────────── Statistiky ──────────────────────────────────

/**
 * Zaznamená zobrazení stránky (jedno volání za request).
 * Přeskočí adminy a známé boty. Kontroluje visitor_tracking_enabled.
 */
function statsNormalizeReferrerHost(string $host): string
{
    $host = strtolower(trim($host));
    if (str_contains($host, ':') && !str_starts_with($host, '[')) {
        $host = (string)preg_replace('/:\d+\z/', '', $host);
    }

    return trim($host, ". \t\n\r\0\x0B");
}

/**
 * @return list<string>
 */
function statsOwnReferrerHosts(): array
{
    $hosts = [];

    $baseHost = parse_url(defined('BASE_URL') ? BASE_URL : '', PHP_URL_HOST);
    if (is_string($baseHost)) {
        $hosts[] = $baseHost;
    }

    $requestHost = $_SERVER['HTTP_HOST'] ?? '';
    if (is_string($requestHost) && $requestHost !== '') {
        $hosts[] = $requestHost;
    }

    $normalizedHosts = [];
    foreach ($hosts as $host) {
        $host = statsNormalizeReferrerHost($host);
        if ($host === '') {
            continue;
        }

        $normalizedHosts[] = $host;
        if (str_starts_with($host, 'www.')) {
            $normalizedHosts[] = substr($host, 4);
        } elseif (!str_contains($host, ':')) {
            $normalizedHosts[] = 'www.' . $host;
        }
    }

    return array_values(array_unique($normalizedHosts));
}

function statsNormalizeReferrer(string $referrer): string
{
    $referrer = trim($referrer);
    if ($referrer === '' || preg_match('/[\x00-\x1F\x7F]/', $referrer) === 1) {
        return '';
    }

    $parts = parse_url($referrer);
    if (!is_array($parts)) {
        return '';
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return '';
    }

    if (isset($parts['user']) || isset($parts['pass'])) {
        return '';
    }

    $host = isset($parts['host'])
        ? statsNormalizeReferrerHost($parts['host'])
        : '';
    if ($host === '' || in_array($host, statsOwnReferrerHosts(), true)) {
        return '';
    }

    $path = isset($parts['path']) && $parts['path'] !== ''
        ? $parts['path']
        : '/';
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }

    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

    return mb_substr($scheme . '://' . $host . $port . $path, 0, 500);
}

function statsReferrerDisplayLabel(string $referrer): string
{
    $normalized = statsNormalizeReferrer($referrer);
    if ($normalized === '') {
        return '';
    }

    $parts = parse_url($normalized);
    if (!is_array($parts) || !isset($parts['host'])) {
        return '';
    }

    $host = statsNormalizeReferrerHost($parts['host']);
    $path = $parts['path'] ?? '';

    return $host . ($path === '' || $path === '/' ? '' : $path);
}

function statsNormalizePagePath(string $pageUrl): string
{
    $pageUrl = trim($pageUrl);
    if ($pageUrl === '' || preg_match('/[\x00-\x1F\x7F]/', $pageUrl) === 1) {
        return '/';
    }

    $parts = parse_url($pageUrl);
    if (is_array($parts)) {
        $path = (string)($parts['path'] ?? '/');
    } else {
        $path = preg_replace('/[?#].*$/', '', $pageUrl) ?? '/';
    }

    $path = trim($path);
    if ($path === '') {
        $path = '/';
    }
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }

    return mb_substr($path, 0, 500);
}

function statsContentPathHash(string $path): string
{
    return hash('sha256', statsNormalizePagePath($path));
}

function statsPageTypeModuleKey(string $pageType): string
{
    $pageType = trim($pageType);
    if ($pageType === 'page') {
        return 'pages';
    }

    return moduleStatsPageTypeMap()[$pageType] ?? '';
}

function statsContentModuleLabel(string $moduleKey): string
{
    if ($moduleKey === 'pages') {
        return 'Statické stránky';
    }

    $definition = coreModuleDefinitions()[$moduleKey] ?? null;
    if (is_array($definition)) {
        $label = trim((string)$definition['admin_label']);
        if ($label !== '') {
            return $label;
        }
    }

    return $moduleKey;
}

function statsContentModuleIsVisible(string $moduleKey): bool
{
    if ($moduleKey === 'pages') {
        return true;
    }

    return $moduleKey !== '' && isset(coreModuleDefinitions()[$moduleKey]) && isModuleEnabled($moduleKey);
}

/**
 * @return array<string,string>
 */
function statsContentModuleOptions(): array
{
    $modules = ['pages' => 'Statické stránky'];
    foreach (array_keys(moduleStatsPageTypes()) as $moduleKey) {
        $modules[$moduleKey] = statsContentModuleLabel($moduleKey);
    }

    return array_filter(
        $modules,
        static fn (string $label, string $moduleKey): bool => $label !== '' && statsContentModuleIsVisible($moduleKey),
        ARRAY_FILTER_USE_BOTH
    );
}

function statsNormalizeContentModuleFilter(string $moduleKey): string
{
    $moduleKey = trim($moduleKey);

    return isset(statsContentModuleOptions()[$moduleKey]) ? $moduleKey : 'all';
}

/**
 * @return array{0:string,1:string}
 */
function statsPreviousDateRange(string $dateFrom, string $dateTo): array
{
    try {
        $from = new \DateTimeImmutable($dateFrom);
        $to = new \DateTimeImmutable($dateTo);
    } catch (\Exception $e) {
        $to = new \DateTimeImmutable();
        $from = $to->modify('-30 days');
    }

    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }

    $days = $from->diff($to)->days + 1;
    $previousTo = $from->modify('-1 day');
    $previousFrom = $previousTo->modify('-' . ($days - 1) . ' days');

    return [$previousFrom->format('Y-m-d'), $previousTo->format('Y-m-d')];
}

/**
 * @return array{title:string,path:string}
 */
function statsContentResolve(PDO $pdo, string $pageType, int $pageRefId, string $fallbackPath): array
{
    $fallbackPath = statsNormalizePagePath($fallbackPath);
    $fallback = [
        'title' => $fallbackPath,
        'path' => BASE_URL . $fallbackPath,
    ];

    if ($pageRefId <= 0) {
        return $fallback;
    }

    static $cache = [];
    $cacheKey = $pageType . ':' . $pageRefId . ':' . $fallbackPath;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    try {
        $row = null;
        switch ($pageType) {
            case 'article':
                $stmt = $pdo->prepare(
                    "SELECT a.id, a.title, a.slug, b.slug AS blog_slug
                     FROM cms_articles a
                     LEFT JOIN cms_blogs b ON b.id = a.blog_id
                     WHERE a.id = ?
                     LIMIT 1"
                );
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = [
                        'title' => trim((string)$row['title']),
                        'path' => articlePublicPath($row),
                    ];
                    return $cache[$cacheKey];
                }
                break;

            case 'page':
                $stmt = $pdo->prepare(
                    "SELECT p.id, p.title, p.slug, p.blog_id, b.slug AS blog_slug
                     FROM cms_pages p
                     LEFT JOIN cms_blogs b ON b.id = p.blog_id
                     WHERE p.id = ?
                     LIMIT 1"
                );
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = [
                        'title' => trim((string)$row['title']),
                        'path' => pagePublicPath($row),
                    ];
                    return $cache[$cacheKey];
                }
                break;

            case 'news':
                $stmt = $pdo->prepare("SELECT id, title, slug FROM cms_news WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = ['title' => trim((string)$row['title']), 'path' => newsPublicPath($row)];
                    return $cache[$cacheKey];
                }
                break;

            case 'board':
                $stmt = $pdo->prepare("SELECT id, title, slug FROM cms_board WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = ['title' => trim((string)$row['title']), 'path' => boardPublicPath($row)];
                    return $cache[$cacheKey];
                }
                break;

            case 'event':
                $stmt = $pdo->prepare("SELECT id, title, slug FROM cms_events WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = ['title' => trim((string)$row['title']), 'path' => eventPublicPath($row)];
                    return $cache[$cacheKey];
                }
                break;

            case 'faq':
                $stmt = $pdo->prepare("SELECT id, question, slug FROM cms_faqs WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = ['title' => trim((string)$row['question']), 'path' => faqPublicPath($row)];
                    return $cache[$cacheKey];
                }
                break;

            case 'poll':
                $stmt = $pdo->prepare("SELECT id, question, slug FROM cms_polls WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = ['title' => trim((string)$row['question']), 'path' => pollPublicPath($row)];
                    return $cache[$cacheKey];
                }
                break;

            case 'download':
                $stmt = $pdo->prepare("SELECT id, title, slug FROM cms_downloads WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = ['title' => trim((string)$row['title']), 'path' => downloadPublicPath($row)];
                    return $cache[$cacheKey];
                }
                break;

            case 'appmarket_app':
                $stmt = $pdo->prepare("SELECT id, name, slug FROM cms_appmarket_apps WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = [
                        'title' => trim((string)$row['name']),
                        'path' => appmarketAppPath($row),
                    ];
                    return $cache[$cacheKey];
                }
                break;

            case 'appmarket_release':
                $stmt = $pdo->prepare(
                    "SELECT r.id, r.version_name, r.version_code, a.name, a.slug
                     FROM cms_appmarket_releases r
                     INNER JOIN cms_appmarket_apps a ON a.id = r.app_id
                     WHERE r.id = ?
                     LIMIT 1"
                );
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = [
                        'title' => trim((string)$row['name'] . ' ' . (string)$row['version_name']),
                        'path' => appmarketReleasePath($row, (int)$row['version_code']),
                    ];
                    return $cache[$cacheKey];
                }
                break;

            case 'food_card':
                $stmt = $pdo->prepare("SELECT id, title, slug FROM cms_food_cards WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = ['title' => trim((string)$row['title']), 'path' => foodCardPublicPath($row)];
                    return $cache[$cacheKey];
                }
                break;

            case 'gallery_album':
                $stmt = $pdo->prepare("SELECT id, name, slug FROM cms_gallery_albums WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = ['title' => trim((string)$row['name']), 'path' => galleryAlbumPublicPath($row)];
                    return $cache[$cacheKey];
                }
                break;

            case 'gallery_photo':
                $stmt = $pdo->prepare("SELECT id, title, slug FROM cms_gallery_photos WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $title = trim((string)($row['title'] ?? ''));
                    $cache[$cacheKey] = [
                        'title' => $title !== '' ? $title : 'Fotografie #' . $pageRefId,
                        'path' => galleryPhotoPublicPath($row),
                    ];
                    return $cache[$cacheKey];
                }
                break;

            case 'podcast_show':
                $stmt = $pdo->prepare("SELECT id, title, slug FROM cms_podcast_shows WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = ['title' => trim((string)$row['title']), 'path' => podcastShowPublicPath($row)];
                    return $cache[$cacheKey];
                }
                break;

            case 'podcast_episode':
                $stmt = $pdo->prepare(
                    "SELECT e.id, e.title, e.slug, s.slug AS show_slug
                     FROM cms_podcasts e
                     INNER JOIN cms_podcast_shows s ON s.id = e.show_id
                     WHERE e.id = ?
                     LIMIT 1"
                );
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = ['title' => trim((string)$row['title']), 'path' => podcastEpisodePublicPath($row)];
                    return $cache[$cacheKey];
                }
                break;

            case 'place':
                $stmt = $pdo->prepare("SELECT id, name, slug FROM cms_places WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = ['title' => trim((string)$row['name']), 'path' => placePublicPath($row)];
                    return $cache[$cacheKey];
                }
                break;

            case 'form':
                $stmt = $pdo->prepare("SELECT id, title, slug FROM cms_forms WHERE id = ? LIMIT 1");
                $stmt->execute([$pageRefId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $cache[$cacheKey] = ['title' => trim((string)$row['title']), 'path' => formPublicPath($row)];
                    return $cache[$cacheKey];
                }
                break;
        }
    } catch (\PDOException $e) {
    }

    $cache[$cacheKey] = $fallback;
    return $fallback;
}

/**
 * @return list<array{stat_date:string,page_type:string,page_ref_id:int,normalized_path:string,path_hash:string,module_key:string,title_snapshot:string,total_views:int,unique_visitors:int}>
 */
function statsBuildRawContentDailyRows(PDO $pdo, string $dateFrom, string $dateTo): array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT DATE(created_at) AS stat_date,
                    page_url,
                    page_type,
                    COALESCE(page_ref_id, 0) AS page_ref_id,
                    ip_hash
             FROM cms_page_views
             WHERE created_at >= ?
               AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
             ORDER BY created_at"
        );
        $stmt->execute([$dateFrom, $dateTo]);
    } catch (\PDOException $e) {
        return [];
    }

    /** @var array<string,array{stat_date:string,page_type:string,page_ref_id:int,normalized_path:string,path_hash:string,module_key:string,total_views:int,visitor_hashes:array<string,bool>}> $buckets */
    $buckets = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $pageType = trim((string)($row['page_type'] ?? ''));
        $moduleKey = statsPageTypeModuleKey($pageType);
        if ($moduleKey === '') {
            continue;
        }

        $normalizedPath = statsNormalizePagePath((string)($row['page_url'] ?? ''));
        $pathHash = statsContentPathHash($normalizedPath);
        $pageRefId = max(0, (int)($row['page_ref_id'] ?? 0));
        $statDate = (string)($row['stat_date'] ?? '');
        if ($statDate === '') {
            continue;
        }

        $bucketKey = implode('|', [$statDate, $pageType, (string)$pageRefId, $pathHash]);
        if (!isset($buckets[$bucketKey])) {
            $buckets[$bucketKey] = [
                'stat_date' => $statDate,
                'page_type' => $pageType,
                'page_ref_id' => $pageRefId,
                'normalized_path' => $normalizedPath,
                'path_hash' => $pathHash,
                'module_key' => $moduleKey,
                'total_views' => 0,
                'visitor_hashes' => [],
            ];
        }

        $buckets[$bucketKey]['total_views']++;
        $ipHash = trim((string)($row['ip_hash'] ?? ''));
        if ($ipHash !== '') {
            $buckets[$bucketKey]['visitor_hashes'][$ipHash] = true;
        }
    }

    $dailyRows = [];
    foreach ($buckets as $bucket) {
        $content = statsContentResolve($pdo, $bucket['page_type'], $bucket['page_ref_id'], $bucket['normalized_path']);
        $title = trim($content['title']);
        if ($title === '') {
            $title = $bucket['normalized_path'];
        }

        $dailyRows[] = [
            'stat_date' => $bucket['stat_date'],
            'page_type' => $bucket['page_type'],
            'page_ref_id' => $bucket['page_ref_id'],
            'normalized_path' => $bucket['normalized_path'],
            'path_hash' => $bucket['path_hash'],
            'module_key' => $bucket['module_key'],
            'title_snapshot' => mb_substr($title, 0, 255),
            'total_views' => $bucket['total_views'],
            'unique_visitors' => count($bucket['visitor_hashes']),
        ];
    }

    return $dailyRows;
}

/**
 * @param list<array{stat_date:string,page_type:string,page_ref_id:int,normalized_path:string,path_hash:string,module_key:string,title_snapshot:string,total_views:int,unique_visitors:int}> $dailyRows
 */
function statsUpsertContentDailyRows(PDO $pdo, array $dailyRows): void
{
    if ($dailyRows === []) {
        return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO cms_stats_content_daily
            (stat_date, page_type, page_ref_id, normalized_path, path_hash, module_key, title_snapshot, total_views, unique_visitors)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            normalized_path = VALUES(normalized_path),
            module_key = VALUES(module_key),
            title_snapshot = VALUES(title_snapshot),
            total_views = VALUES(total_views),
            unique_visitors = VALUES(unique_visitors),
            updated_at = NOW()"
    );

    foreach ($dailyRows as $row) {
        $stmt->execute([
            $row['stat_date'],
            $row['page_type'],
            $row['page_ref_id'],
            $row['normalized_path'],
            $row['path_hash'],
            $row['module_key'],
            $row['title_snapshot'],
            $row['total_views'],
            $row['unique_visitors'],
        ]);
    }
}

/**
 * @return list<string>
 */
function statsContentDailyMismatchDates(PDO $pdo, int $limit = 7): array
{
    $limit = max(1, min(30, $limit));

    try {
        $pageTypes = array_values(array_unique(array_merge(['page'], array_keys(moduleStatsPageTypeMap()))));
        $pageTypePlaceholders = implode(',', array_fill(0, count($pageTypes), '?'));
        $stmt = $pdo->prepare(
            "SELECT raw_stats.stat_date
             FROM (
                SELECT DATE(created_at) AS stat_date,
                       COUNT(*) AS raw_views
                FROM cms_page_views
                WHERE created_at < CURDATE()
                  AND page_type IN ({$pageTypePlaceholders})
                GROUP BY DATE(created_at)
             ) raw_stats
             LEFT JOIN (
                SELECT stat_date,
                       SUM(total_views) AS aggregate_views
                FROM cms_stats_content_daily
                GROUP BY stat_date
             ) content_stats ON content_stats.stat_date = raw_stats.stat_date
             WHERE COALESCE(content_stats.aggregate_views, -1) <> raw_stats.raw_views
             ORDER BY raw_stats.stat_date DESC
             LIMIT {$limit}"
        );
        $stmt->execute($pageTypes);

        return array_values(array_filter(
            array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)),
            static fn (string $statDate): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $statDate) === 1
        ));
    } catch (\PDOException $e) {
        return [];
    }
}

function statsAggregateContentDaily(PDO $pdo): void
{
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM cms_stats_content_daily WHERE stat_date = ?");
        foreach (statsContentDailyMismatchDates($pdo) as $statDate) {
            $deleteStmt->execute([$statDate]);
            statsUpsertContentDailyRows(
                $pdo,
                statsBuildRawContentDailyRows($pdo, $statDate, $statDate)
            );
        }
    } catch (\PDOException $e) {
    }
}

/**
 * @return list<array{key:string,module_key:string,module_label:string,page_type:string,page_ref_id:int,normalized_path:string,title:string,path:string,views:int,unique_visitors:int}>
 */
function statsLoadContentTrendRows(PDO $pdo, string $dateFrom, string $dateTo, string $moduleFilter = 'all'): array
{
    /** @var array<string,array{key:string,module_key:string,module_label:string,page_type:string,page_ref_id:int,normalized_path:string,title:string,path:string,views:int,unique_visitors:int}> $rows */
    $rows = [];
    $addRow = static function (
        string $pageType,
        int $pageRefId,
        string $moduleKey,
        string $normalizedPath,
        string $title,
        int $views,
        int $uniqueVisitors
    ) use (&$rows, $moduleFilter): void {
        if (!statsContentModuleIsVisible($moduleKey)) {
            return;
        }
        if ($moduleFilter !== 'all' && $moduleFilter !== $moduleKey) {
            return;
        }

        $normalizedPath = statsNormalizePagePath($normalizedPath);
        $key = implode('|', [$pageType, (string)$pageRefId, statsContentPathHash($normalizedPath)]);
        if (!isset($rows[$key])) {
            $rows[$key] = [
                'key' => $key,
                'module_key' => $moduleKey,
                'module_label' => statsContentModuleLabel($moduleKey),
                'page_type' => $pageType,
                'page_ref_id' => $pageRefId,
                'normalized_path' => $normalizedPath,
                'title' => $title !== '' ? $title : $normalizedPath,
                'path' => BASE_URL . $normalizedPath,
                'views' => 0,
                'unique_visitors' => 0,
            ];
        }

        $rows[$key]['views'] += max(0, $views);
        $rows[$key]['unique_visitors'] += max(0, $uniqueVisitors);
    };

    try {
        $stmt = $pdo->prepare(
            "SELECT page_type,
                    page_ref_id,
                    normalized_path,
                    module_key,
                    MAX(title_snapshot) AS title_snapshot,
                    SUM(total_views) AS views,
                    SUM(unique_visitors) AS unique_visitors
             FROM cms_stats_content_daily
             WHERE stat_date >= ?
               AND stat_date <= ?
             GROUP BY page_type, page_ref_id, normalized_path, path_hash, module_key"
        );
        $stmt->execute([$dateFrom, $dateTo]);
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $addRow(
                (string)($row['page_type'] ?? ''),
                (int)($row['page_ref_id'] ?? 0),
                (string)($row['module_key'] ?? ''),
                (string)($row['normalized_path'] ?? '/'),
                (string)($row['title_snapshot'] ?? ''),
                (int)($row['views'] ?? 0),
                (int)($row['unique_visitors'] ?? 0)
            );
        }
    } catch (\PDOException $e) {
    }

    $today = date('Y-m-d');
    if ($dateFrom <= $today && $dateTo >= $today) {
        foreach (statsBuildRawContentDailyRows($pdo, $today, $today) as $row) {
            $addRow(
                $row['page_type'],
                $row['page_ref_id'],
                $row['module_key'],
                $row['normalized_path'],
                $row['title_snapshot'],
                $row['total_views'],
                $row['unique_visitors']
            );
        }
    }

    foreach ($rows as $key => $row) {
        $resolved = statsContentResolve($pdo, $row['page_type'], $row['page_ref_id'], $row['normalized_path']);
        if ($resolved['title'] !== '') {
            $rows[$key]['title'] = $resolved['title'];
        }
        if ($resolved['path'] !== '') {
            $rows[$key]['path'] = $resolved['path'];
        }
    }

    uasort(
        $rows,
        static fn (array $a, array $b): int => $b['views'] <=> $a['views'] ?: strcmp($a['title'], $b['title'])
    );

    return array_values($rows);
}

function trackPageView(string $pageType = 'other', ?int $refId = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (getSetting('visitor_tracking_enabled', '0') !== '1') {
        return;
    }
    if (isset($_SESSION['cms_user_id'])) {
        return;
    } // admin/spolupracovník

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|wget|curl/i', $ua)) {
        return;
    }

    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ipHash  = hash('sha256', $ip . '|' . date('Y-m-d'));
    $pageUrl = mb_substr(($_SERVER['REQUEST_URI'] ?? '/'), 0, 500);
    $ref     = statsNormalizeReferrer(is_string($_SERVER['HTTP_REFERER'] ?? null) ? $_SERVER['HTTP_REFERER'] : '');

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
 *
 * @return array{online:int, today:int, month:int, total:int}
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
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo = db_connect();
        statsAggregateContentDaily($pdo);

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
    if ($done) {
        return;
    }
    $done = true;
    if (!isModuleEnabled('reservations')) {
        return;
    }
    try {
        $pdo = db_connect();
        // confirmed → completed
        $completedIds = array_map(
            'intval',
            $pdo->query(
                "SELECT id
                 FROM cms_res_bookings
                 WHERE status = 'confirmed'
                   AND (booking_date < CURDATE() OR (booking_date = CURDATE() AND end_time < CURTIME()))"
            )->fetchAll(PDO::FETCH_COLUMN)
        );
        $pdo->exec(
            "UPDATE cms_res_bookings SET status = 'completed', updated_at = NOW()
             WHERE status = 'confirmed'
               AND (booking_date < CURDATE() OR (booking_date = CURDATE() AND end_time < CURTIME()))"
        );
        foreach ($completedIds as $bookingId) {
            reservationRecordBookingEvent($pdo, $bookingId, 'auto_completed', 'Rezervace byla automaticky dokončena po uplynutí termínu.');
        }

        // pending → cancelled (termín vypršel bez schválení)
        $cancelledIds = array_map(
            'intval',
            $pdo->query(
                "SELECT id
                 FROM cms_res_bookings
                 WHERE status = 'pending'
                   AND (booking_date < CURDATE() OR (booking_date = CURDATE() AND end_time < CURTIME()))"
            )->fetchAll(PDO::FETCH_COLUMN)
        );
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
        foreach ($cancelledIds as $bookingId) {
            reservationRecordBookingEvent($pdo, $bookingId, 'auto_cancelled', 'Rezervace byla automaticky zrušena, protože termín vypršel bez schválení.');
        }
    } catch (\PDOException $e) {
        // Tabulka nemusí existovat
    }
}

/**
 * Výchozí pořadí modulů v navigaci.
 *
 * @return array<string, array{0:string, 1:string}>
 */
function navModuleDefaults(): array
{
    return moduleNavigationDefaults();
}

/**
 * Vrátí aktuální pořadí klíčů modulů dle nastavení (nebo výchozí).
 *
 * @return list<string>
 */
function navModuleOrder(): array
{
    $defaults = array_keys(navModuleDefaults());
    $saved    = getSetting('nav_module_order', '');
    if ($saved === '') {
        return $defaults;
    }

    $order = array_filter(explode(',', $saved), fn ($k) => isset(navModuleDefaults()[$k]));
    $order = array_values($order);
    // Přidej nové moduly, které v uloženém pořadí chybí
    foreach ($defaults as $k) {
        if (!in_array($k, $order, true)) {
            $order[] = $k;
        }
    }
    return $order;
}

/**
 * @param array<string,mixed> $form
 * @return string[]
 */
function formPublicNavigationStatusParts(array $form): array
{
    $parts = ['Formulář'];

    if ((int)($form['show_in_nav'] ?? 0) !== 1) {
        $parts[] = 'mimo navigaci';
    }
    if ((int)($form['is_active'] ?? 0) !== 1) {
        $parts[] = 'nezveřejněný';
    }
    if (!isModuleEnabled('forms')) {
        $parts[] = 'modul vypnutý';
    }
    if (trim((string)($form['title'] ?? '')) === '') {
        $parts[] = 'bez názvu';
    }

    return $parts;
}

/**
 * @param array<string,mixed> $form
 */
function formVisibleInPublicNavigation(array $form): bool
{
    if (!isModuleEnabled('forms')) {
        return false;
    }

    if ((int)($form['show_in_nav'] ?? 0) !== 1) {
        return false;
    }

    if ((int)($form['is_active'] ?? 0) !== 1) {
        return false;
    }

    return trim((string)($form['title'] ?? '')) !== '';
}

/**
 * @return array<int, array<string,mixed>>
 */
function loadPublicNavigationForms(): array
{
    if (!isModuleEnabled('forms')) {
        return [];
    }

    try {
        $rows = db_connect()->query(
            "SELECT id, title, slug, is_active, show_in_nav
             FROM cms_forms
             ORDER BY title, id"
        )->fetchAll();
    } catch (\PDOException $e) {
        return [];
    }

    $visibleForms = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !formVisibleInPublicNavigation($row)) {
            continue;
        }

        $visibleForms[(int)($row['id'] ?? 0)] = $row;
    }

    return $visibleForms;
}

/** Navigace webu – zobrazí jen povolené moduly v nastavitelném pořadí */
function siteNav(string $current = ''): string
{
    $b   = BASE_URL;
    $cur = function (string $p) use ($current) {
        return $current === $p ? ' aria-current="page"' : '';
    };
    $li  = function (string $href, string $label, string $page) use ($b, $cur) {
        return '<li><a href="' . $b . $href . '"' . $cur($page) . '>' . $label . '</a></li>' . "\n";
    };

    $navHeadingId = 'site-nav-heading';
    $nav = '<nav aria-labelledby="' . $navHeadingId . '">';
    $nav .= '<h2 id="' . $navHeadingId . '" class="sr-only">Hlavní navigace</h2><ul>' . "\n";
    $nav .= $li('/index.php', 'Domů', 'home');

    $unifiedOrder = getSetting('nav_order_unified', '');

    if ($unifiedOrder !== '') {
        // Unified navigace – stránky, moduly, blogy a formuláře dohromady
        $moduleMap = navModuleDefaults();
        $visibleBlogEntries = [];
        if (isModuleEnabled('blog')) {
            foreach (getAllBlogs() as $blogEntry) {
                if (!(int)($blogEntry['show_in_nav'] ?? 1)) {
                    continue;
                }
                $visibleBlogEntries[(int)$blogEntry['id']] = $blogEntry;
            }
        }
        $visibleForms = loadPublicNavigationForms();
        $visibleLinks = [];
        foreach (loadNavigationLinks(db_connect(), null, true) as $linkEntry) {
            $visibleLinks[(int)$linkEntry['id']] = $linkEntry;
        }
        $pagesMap = [];
        try {
            $pageRows = db_connect()->query(
                "SELECT id, title, slug FROM cms_pages
                 WHERE blog_id IS NULL
                   AND show_in_nav = 1
                   AND is_published = 1
                   AND COALESCE(status,'published') = 'published'
                   AND deleted_at IS NULL"
            )->fetchAll();
            foreach ($pageRows as $p) {
                $pagesMap[(int)$p['id']] = $p;
            }
        } catch (\PDOException $e) {
        }

        $renderedEntries = [];
        $renderUnifiedEntry = static function (string $entry) use (&$nav, &$renderedEntries, $moduleMap, $pagesMap, $visibleBlogEntries, $visibleForms, $visibleLinks, $li, $cur, $current): void {
            if ($entry === '' || isset($renderedEntries[$entry])) {
                return;
            }

            if (str_starts_with($entry, 'module:')) {
                $mKey = substr($entry, 7);
                if (!isModuleEnabled($mKey) || !isset($moduleMap[$mKey])) {
                    return;
                }
                if ($mKey === 'blog') {
                    foreach ($visibleBlogEntries as $blogId => $blogEntry) {
                        $blogEntryKey = 'blog:' . $blogId;
                        if (isset($renderedEntries[$blogEntryKey])) {
                            continue;
                        }
                        $blogHref = blogIndexPath($blogEntry);
                        $blogNavKey = 'blog:' . $blogEntry['slug'];
                        $nav .= '<li><a href="' . h($blogHref) . '"' . $cur($blogNavKey) . '>' . h((string)$blogEntry['name']) . '</a></li>' . "\n";
                        $renderedEntries[$blogEntryKey] = true;
                    }
                    $renderedEntries[$entry] = true;
                    return;
                }

                [$href, $label] = $moduleMap[$mKey];
                $nav .= $li($href, $label, $mKey);
                $renderedEntries[$entry] = true;
                return;
            }

            if (str_starts_with($entry, 'page:')) {
                $pageId = (int)substr($entry, 5);
                if (!isset($pagesMap[$pageId])) {
                    return;
                }
                $p = $pagesMap[$pageId];
                $nav .= '<li><a href="' . pagePublicPath($p) . '"' . ($current === 'page:' . $p['slug'] ? ' aria-current="page"' : '') . '>' . h($p['title']) . '</a></li>' . "\n";
                $renderedEntries[$entry] = true;
                return;
            }

            if (str_starts_with($entry, 'blog:')) {
                $blogId = (int)substr($entry, 5);
                if (!isset($visibleBlogEntries[$blogId])) {
                    return;
                }
                $blogEntry = $visibleBlogEntries[$blogId];
                $blogHref = blogIndexPath($blogEntry);
                $blogNavKey = 'blog:' . $blogEntry['slug'];
                $nav .= '<li><a href="' . h($blogHref) . '"' . $cur($blogNavKey) . '>' . h((string)$blogEntry['name']) . '</a></li>' . "\n";
                $renderedEntries[$entry] = true;
                return;
            }

            if (str_starts_with($entry, 'form:')) {
                $formId = (int)substr($entry, 5);
                if (!isset($visibleForms[$formId])) {
                    return;
                }
                $form = $visibleForms[$formId];
                $nav .= '<li><a href="' . h(formPublicPath($form)) . '"' . ($current === 'form:' . $formId ? ' aria-current="page"' : '') . '>' . h((string)$form['title']) . '</a></li>' . "\n";
                $renderedEntries[$entry] = true;
                return;
            }

            if (str_starts_with($entry, 'link:')) {
                $linkId = (int)substr($entry, 5);
                if (!isset($visibleLinks[$linkId])) {
                    return;
                }
                $attributes = navigationLinkAnchorAttributes($visibleLinks[$linkId]);
                if ($attributes === '') {
                    return;
                }
                $nav .= '<li><a ' . $attributes . '>' . h((string)$visibleLinks[$linkId]['title'])
                    . navigationLinkAccessibleSuffix($visibleLinks[$linkId]) . '</a></li>' . "\n";
                $renderedEntries[$entry] = true;
            }
        };

        foreach (explode(',', $unifiedOrder) as $entry) {
            $entry = trim($entry);
            $renderUnifiedEntry($entry);
        }

        foreach (array_keys($moduleMap) as $mKey) {
            if ($mKey === 'blog') {
                foreach (array_keys($visibleBlogEntries) as $blogId) {
                    $renderUnifiedEntry('blog:' . $blogId);
                }
                continue;
            }
            $renderUnifiedEntry('module:' . $mKey);
        }
        foreach (array_keys($pagesMap) as $pageId) {
            $renderUnifiedEntry('page:' . $pageId);
        }
        foreach (array_keys($visibleForms) as $formId) {
            $renderUnifiedEntry('form:' . $formId);
        }
        foreach (array_keys($visibleLinks) as $linkId) {
            $renderUnifiedEntry('link:' . $linkId);
        }
    } else {
        // Fallback: starý systém (stránky, pak moduly)
        try {
            $pages = db_connect()->query(
                "SELECT title, slug FROM cms_pages
                 WHERE blog_id IS NULL
                   AND show_in_nav = 1
                   AND is_published = 1
                 ORDER BY nav_order, title"
            )->fetchAll();
            foreach ($pages as $p) {
                $nav .= '<li><a href="' . pagePublicPath($p) . '"'
                       . ($current === 'page:' . $p['slug'] ? ' aria-current="page"' : '')
                       . '>' . h($p['title']) . '</a></li>' . "\n";
            }
        } catch (\PDOException $e) {
        }

        $moduleMap = navModuleDefaults();
        foreach (navModuleOrder() as $key) {
            if (!isModuleEnabled($key) || !isset($moduleMap[$key])) {
                continue;
            }
            if ($key === 'blog') {
                $visibleBlogs = array_filter(getAllBlogs(), static fn (array $blog): bool => (int)($blog['show_in_nav'] ?? 1) !== 0);
                if (count($visibleBlogs) === 0) {
                    continue;
                }
            }
            if ($key === 'blog' && isMultiBlog()) {
                foreach (getAllBlogs() as $blogEntry) {
                    if (!(int)($blogEntry['show_in_nav'] ?? 1)) {
                        continue;
                    }
                    $blogHref = blogIndexPath($blogEntry);
                    $blogNavKey = 'blog:' . $blogEntry['slug'];
                    $nav .= '<li><a href="' . h($blogHref) . '"' . $cur($blogNavKey) . '>' . h((string)$blogEntry['name']) . '</a></li>' . "\n";
                }
            } else {
                [$href, $label] = $moduleMap[$key];
                $nav .= $li($href, $label, $key);
            }
        }

        foreach (loadPublicNavigationForms() as $form) {
            $nav .= '<li><a href="' . h(formPublicPath($form)) . '"' . ($current === 'form:' . (int)$form['id'] ? ' aria-current="page"' : '') . '>' . h((string)$form['title']) . '</a></li>' . "\n";
        }

        foreach (loadNavigationLinks(db_connect(), null, true) as $linkEntry) {
            $attributes = navigationLinkAnchorAttributes($linkEntry);
            if ($attributes === '') {
                continue;
            }
            $nav .= '<li><a ' . $attributes . '>' . h((string)$linkEntry['title'])
                . navigationLinkAccessibleSuffix($linkEntry) . '</a></li>' . "\n";
        }
    }

    if (isLoggedIn()) {
        $nav .= $li('/admin/index.php', 'Administrace', 'admin');
    }

    $nav .= '</ul></nav>';
    return $nav;
}
