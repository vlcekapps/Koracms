<?php

/**
 * @return array{
 *     type:string,
 *     key:string,
 *     label:string,
 *     description:string,
 *     url:string,
 *     module:string,
 *     badge:string,
 *     pin_available:bool,
 *     pinned?:bool
 * }
 */
function adminCommandItem(
    string $type,
    string $key,
    string $label,
    string $description,
    string $url,
    string $module = '',
    string $badge = '',
    bool $pinAvailable = true
): array {
    return [
        'type' => $type,
        'key' => $key,
        'label' => $label,
        'description' => $description,
        'url' => $url,
        'module' => $module,
        'badge' => $badge,
        'pin_available' => $pinAvailable,
    ];
}

function adminCommandCurrentUserId(): int
{
    return (int)(currentUserId() ?? 0);
}

function adminCommandCanUseItem(?string $capability = null, ?string $moduleKey = null): bool
{
    if ($moduleKey !== null && $moduleKey !== '' && !isModuleEnabled($moduleKey)) {
        return false;
    }

    if ($capability === null || $capability === '') {
        return true;
    }

    return currentUserHasCapability($capability);
}

/**
 * @return list<array<string,mixed>>
 */
function adminCommandBaseRegistry(): array
{
    $base = BASE_URL . '/admin/';
    $items = [
        ['screen', 'dashboard', 'Přehled administrace', 'Úvodní dashboard administrace.', $base . 'index.php', '', '', 'admin_access'],
        ['screen', 'profile', 'Můj profil', 'Úprava vlastního účtu, hesla a zabezpečení.', $base . 'profile.php', '', '', 'admin_access'],
        ['screen', 'review_queue', 'Ke schválení', 'Obsah a zprávy čekající na kontrolu.', $base . 'review_queue.php', '', '', null, 'review_queue'],
        ['screen', 'blog.articles', 'Články blogu', 'Správa článků, konceptů a publikace.', $base . 'blog.php', 'blog', '', 'blog_manage_own'],
        ['screen', 'blog.blogs', 'Správa blogů', 'Blogy, týmy, kategorie a nastavení blogů.', $base . 'blogs.php', 'blog', '', 'blog_taxonomies_manage'],
        ['screen', 'media', 'Knihovna médií', 'Nahrávání a správa obrázků, audia, videa a souborů.', $base . 'media.php', '', '', 'content_manage_shared'],
        ['screen', 'pages', 'Stránky webu', 'Globální i blogové statické stránky.', $base . 'pages.php', '', '', 'content_manage_shared'],
        ['screen', 'news', 'Novinky', 'Správa novinek a oznámení.', $base . 'news.php', 'news', '', 'news_manage_own'],
        ['screen', 'forms', 'Formuláře', 'Form builder, přijaté odpovědi a workflow.', $base . 'forms.php', 'forms', '', 'content_manage_shared'],
        ['screen', 'events', 'Události', 'Akce, kalendář a ICS export.', $base . 'events.php', 'events', '', 'content_manage_shared'],
        ['screen', 'events.types', 'Typy akcí', 'Správa veřejných typů akcí a jejich landing stránek.', $base . 'event_types.php', 'events', '', 'content_manage_shared'],
        ['screen', 'gallery', 'Galerie', 'Alba a fotografie.', $base . 'gallery_albums.php', 'gallery', '', 'content_manage_shared'],
        ['screen', 'downloads', 'Ke stažení', 'Soubory, verze a kategorie ke stažení.', $base . 'downloads.php', 'downloads', '', 'content_manage_shared'],
        ['screen', 'downloads.series', 'Série ke stažení', 'Správa verzovacích řad a aktuálních vydání.', $base . 'download_series.php', 'downloads', '', 'content_manage_shared'],
        ['screen', 'food', 'Jídelní a nápojové lístky', 'Lístky, strukturované položky, denní nabídky a výživové údaje.', $base . 'food.php', 'food', '', 'content_manage_shared'],
        ['screen', 'food.orders', 'Objednávkové poptávky', 'Nezávazné poptávky odeslané z jídelních a nápojových lístků.', $base . 'food_orders.php', 'food', '', 'content_manage_shared'],
        ['screen', 'reservations', 'Rezervace', 'Rezervace, zdroje a lokality.', $base . 'res_bookings.php', 'reservations', '', 'bookings_manage'],
        ['screen', 'newsletter', 'Newsletter', 'Odběratelé, rozesílky a historie newsletteru.', $base . 'newsletter.php', 'newsletter', '', 'newsletter_manage'],
        ['screen', 'contact.messages', 'Kontaktní zprávy', 'Zprávy z kontaktního formuláře, stav a odpovědi.', $base . 'contact.php', 'contact', '', 'messages_manage'],
        ['screen', 'contact.topics', 'Témata kontaktu', 'Směrování dotazů a popisy témat kontaktního formuláře.', $base . 'contact_topics.php', 'contact', '', 'messages_manage'],
        ['screen', 'chat.messages', 'Chat zprávy', 'Moderovaný veřejný chat, soukromé dotazy a odpovědi.', $base . 'chat.php', 'chat', '', 'messages_manage'],
        ['screen', 'chat.topics', 'Témata chatu', 'Témata, popisy a veřejné landing stránky chatu.', $base . 'chat_topics.php', 'chat', '', 'messages_manage'],
        ['screen', 'statistics', 'Statistiky', 'Návštěvnost, referrery a nejčtenější obsah.', $base . 'statistics.php', 'statistics', '', 'statistics_view'],
        ['screen', 'widgets', 'Widgety', 'Skládání homepage, sidebaru a footeru.', $base . 'widgets.php', '', '', 'settings_manage'],
        ['screen', 'settings', 'Nastavení webu', 'Obecná nastavení, moduly, navigace a provoz.', $base . 'settings.php', '', '', 'settings_manage'],
        ['screen', 'users', 'Uživatelé a role', 'Účty, role, autoři a veřejné profily.', $base . 'users.php', '', '', 'users_manage'],
        ['screen', 'audit_log', 'Audit log', 'Poslední akce uživatelů a systému.', $base . 'audit_log.php', '', '', 'settings_manage'],
        ['action', 'new.article', 'Nový článek', 'Začít psát nový blogový článek.', $base . 'blog_form.php', 'blog', 'Akce', 'blog_manage_own'],
        ['action', 'new.page', 'Nová stránka', 'Vytvořit globální nebo blogovou statickou stránku.', $base . 'page_form.php', '', 'Akce', 'content_manage_shared'],
        ['action', 'upload.media', 'Nahrát média', 'Otevřít knihovnu médií a nahrát soubory.', $base . 'media.php', '', 'Akce', 'content_manage_shared'],
        ['action', 'new.form', 'Nový formulář', 'Vytvořit formulář ručně nebo ze šablony.', $base . 'form_form.php', 'forms', 'Akce', 'content_manage_shared'],
        ['action', 'new.event', 'Nová událost', 'Založit novou akci nebo termín.', $base . 'event_form.php', 'events', 'Akce', 'content_manage_shared'],
        ['action', 'new.booking', 'Nová rezervace', 'Přidat rezervaci z administrace.', $base . 'res_booking_add.php', 'reservations', 'Akce', 'bookings_manage'],
    ];

    $knownCommandUrls = [];
    foreach ($items as $item) {
        $url = (string)($item[4] ?? '');
        if ($url !== '') {
            $knownCommandUrls[$url] = true;
        }
    }

    foreach (coreModuleDefinitions() as $moduleKey => $definition) {
        $adminPaths = $definition['admin_paths'];
        $primaryAdminPath = $adminPaths[0];

        $url = BASE_URL . $primaryAdminPath;
        if (isset($knownCommandUrls[$url])) {
            continue;
        }

        $label = moduleAdminLabel((string)$moduleKey);
        $items[] = [
            'screen',
            'module.' . (string)$moduleKey,
            $label,
            'Správa modulu ' . $label . '.',
            $url,
            (string)$moduleKey,
            '',
            moduleAdminCapability((string)$moduleKey),
        ];
        $knownCommandUrls[$url] = true;
    }

    $result = [];
    foreach ($items as $item) {
        [$type, $key, $label, $description, $url, $module, $badge, $capability] = array_pad($item, 8, null);
        if ($key === 'review_queue') {
            if (!canAccessReviewQueue()) {
                continue;
            }
        } elseif (!adminCommandCanUseItem(is_string($capability) ? $capability : null, is_string($module) ? $module : null)) {
            continue;
        }

        $result[] = adminCommandItem(
            (string)$type,
            (string)$key,
            (string)$label,
            (string)$description,
            (string)$url,
            (string)$module,
            (string)$badge
        );
    }

    return $result;
}

function adminCommandNormalizeSearchText(string $value): string
{
    $value = mb_strtolower(normalizePlainText($value), 'UTF-8');
    return strtr($value, [
        'á' => 'a',
        'č' => 'c',
        'ď' => 'd',
        'é' => 'e',
        'ě' => 'e',
        'í' => 'i',
        'ň' => 'n',
        'ó' => 'o',
        'ř' => 'r',
        'š' => 's',
        'ť' => 't',
        'ú' => 'u',
        'ů' => 'u',
        'ý' => 'y',
        'ž' => 'z',
    ]);
}

/**
 * @param array<string,mixed> $item
 */
function adminCommandItemMatches(array $item, string $query): bool
{
    $query = adminCommandNormalizeSearchText($query);
    if ($query === '') {
        return true;
    }

    $haystack = adminCommandNormalizeSearchText(
        implode(' ', [
            (string)($item['label'] ?? ''),
            (string)($item['description'] ?? ''),
            (string)($item['badge'] ?? ''),
            (string)($item['module'] ?? ''),
        ])
    );

    foreach (preg_split('/\s+/u', $query) ?: [] as $token) {
        $token = trim($token);
        if ($token !== '' && !str_contains($haystack, $token)) {
            return false;
        }
    }

    return true;
}

/**
 * @param list<array<string,mixed>> $items
 * @return list<array<string,mixed>>
 */
function adminCommandFilterItems(array $items, string $query, int $limit): array
{
    $filtered = [];
    foreach ($items as $item) {
        if (!adminCommandItemMatches($item, $query)) {
            continue;
        }
        $filtered[] = $item;
        if (count($filtered) >= $limit) {
            break;
        }
    }

    return $filtered;
}

/**
 * @param list<array<string,mixed>> $items
 * @return list<array<string,mixed>>
 */
function adminCommandDedupeItems(array $items): array
{
    $seen = [];
    $deduped = [];
    foreach ($items as $item) {
        $dedupeKey = (string)($item['type'] ?? '') . ':' . (string)($item['key'] ?? '');
        if ($dedupeKey === ':' || isset($seen[$dedupeKey])) {
            continue;
        }
        $seen[$dedupeKey] = true;
        $deduped[] = $item;
    }

    return $deduped;
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function adminCommandContentItem(string $entityType, array $row): array
{
    $id = (int)($row['id'] ?? 0);
    $title = trim((string)($row['title'] ?? ''));
    if ($title === '') {
        $title = 'Položka #' . $id;
    }

    $base = BASE_URL . '/admin/';
    return match ($entityType) {
        'blog' => adminCommandItem('content', 'blog:' . $id, $title, 'Článek blogu' . adminCommandSuffix($row), $base . 'blog_form.php?id=' . $id, 'blog', 'Článek'),
        'page' => adminCommandItem('content', 'page:' . $id, $title, ((int)($row['blog_id'] ?? 0) > 0 ? 'Statická stránka blogu' : 'Statická stránka') . adminCommandSuffix($row), $base . 'page_form.php?id=' . $id, '', 'Stránka'),
        'news' => adminCommandItem('content', 'news:' . $id, $title, 'Novinka' . adminCommandSuffix($row), $base . 'news_form.php?id=' . $id, 'news', 'Novinka'),
        'event' => adminCommandItem('content', 'event:' . $id, $title, 'Událost' . adminCommandSuffix($row), $base . 'event_form.php?id=' . $id, 'events', 'Událost'),
        'form' => adminCommandItem('content', 'form:' . $id, $title, 'Formulář' . adminCommandSuffix($row), $base . 'form_form.php?id=' . $id, 'forms', 'Formulář'),
        'media' => adminCommandItem('content', 'media:' . $id, $title, 'Médium v knihovně' . adminCommandSuffix($row), $base . 'media.php?edit=' . $id, '', 'Médium'),
        default => adminCommandItem('content', $entityType . ':' . $id, $title, 'Obsah webu' . adminCommandSuffix($row), $base . 'index.php', '', 'Obsah'),
    };
}

/**
 * @param array<string,mixed> $row
 */
function adminCommandSuffix(array $row): string
{
    $status = trim((string)($row['status'] ?? ''));
    $createdAt = trim((string)($row['created_at'] ?? ''));
    $parts = [];
    if ($status !== '') {
        $parts[] = $status === 'published' ? 'publikováno' : $status;
    }
    if ($createdAt !== '') {
        $parts[] = formatCzechDate($createdAt);
    }

    return $parts === [] ? '' : ' · ' . implode(', ', $parts);
}

/**
 * @return list<array<string,mixed>>
 */
function adminCommandSearchContent(PDO $pdo, string $query, int $limit): array
{
    $query = trim($query);
    if (mb_strlen($query, 'UTF-8') < 2) {
        return [];
    }

    $items = [];
    $like = '%' . $query . '%';

    $sources = [
        'blog' => [
            'enabled' => adminCommandCanUseItem('blog_manage_own', 'blog'),
            'sql' => currentUserHasCapability('blog_manage_all')
                ? "SELECT id, title, status, created_at FROM cms_articles WHERE deleted_at IS NULL AND (title LIKE ? OR slug LIKE ? OR perex LIKE ? OR content LIKE ?) ORDER BY updated_at DESC, id DESC LIMIT ?"
                : "SELECT id, title, status, created_at FROM cms_articles WHERE deleted_at IS NULL AND author_id = ? AND (title LIKE ? OR slug LIKE ? OR perex LIKE ? OR content LIKE ?) ORDER BY updated_at DESC, id DESC LIMIT ?",
            'params' => currentUserHasCapability('blog_manage_all') ? [$like, $like, $like, $like] : [adminCommandCurrentUserId(), $like, $like, $like, $like],
        ],
        'page' => [
            'enabled' => adminCommandCanUseItem('content_manage_shared'),
            'sql' => "SELECT id, title, status, blog_id, created_at FROM cms_pages WHERE deleted_at IS NULL AND (title LIKE ? OR slug LIKE ? OR content LIKE ?) ORDER BY updated_at DESC, id DESC LIMIT ?",
            'params' => [$like, $like, $like],
        ],
        'news' => [
            'enabled' => adminCommandCanUseItem('news_manage_own', 'news'),
            'sql' => currentUserHasCapability('news_manage_all')
                ? "SELECT id, title, status, created_at FROM cms_news WHERE deleted_at IS NULL AND (title LIKE ? OR slug LIKE ? OR content LIKE ?) ORDER BY updated_at DESC, id DESC LIMIT ?"
                : "SELECT id, title, status, created_at FROM cms_news WHERE deleted_at IS NULL AND author_id = ? AND (title LIKE ? OR slug LIKE ? OR content LIKE ?) ORDER BY updated_at DESC, id DESC LIMIT ?",
            'params' => currentUserHasCapability('news_manage_all') ? [$like, $like, $like] : [adminCommandCurrentUserId(), $like, $like, $like],
        ],
        'event' => [
            'enabled' => adminCommandCanUseItem('content_manage_shared', 'events'),
            'sql' => "SELECT id, title, status, created_at FROM cms_events WHERE deleted_at IS NULL AND (title LIKE ? OR slug LIKE ? OR description LIKE ? OR location LIKE ?) ORDER BY updated_at DESC, id DESC LIMIT ?",
            'params' => [$like, $like, $like, $like],
        ],
        'form' => [
            'enabled' => adminCommandCanUseItem('content_manage_shared', 'forms'),
            'sql' => "SELECT id, title, created_at FROM cms_forms WHERE title LIKE ? OR slug LIKE ? OR description LIKE ? ORDER BY updated_at DESC, id DESC LIMIT ?",
            'params' => [$like, $like, $like],
        ],
        'media' => [
            'enabled' => adminCommandCanUseItem('content_manage_shared'),
            'sql' => "SELECT id, COALESCE(NULLIF(original_name, ''), filename) AS title, created_at FROM cms_media WHERE filename LIKE ? OR original_name LIKE ? OR alt_text LIKE ? OR caption LIKE ? OR credit LIKE ? ORDER BY created_at DESC, id DESC LIMIT ?",
            'params' => [$like, $like, $like, $like, $like],
        ],
    ];

    foreach ($sources as $entityType => $source) {
        if (!$source['enabled']) {
            continue;
        }
        try {
            $stmt = $pdo->prepare((string)$source['sql']);
            $stmt->execute([...$source['params'], max(1, min(10, $limit))]);
            foreach ($stmt->fetchAll() as $row) {
                $items[] = adminCommandContentItem($entityType, $row);
                if (count($items) >= $limit) {
                    return $items;
                }
            }
        } catch (\PDOException $e) {
            koraLog('warning', 'admin command search source failed', ['source' => $entityType, 'exception' => $e]);
        }
    }

    return $items;
}

function adminCommandShortcutTableAvailable(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    try {
        $pdo->query('SELECT 1 FROM cms_admin_shortcuts LIMIT 1');
        $available = true;
    } catch (\PDOException $e) {
        $available = false;
    }

    return $available;
}

/**
 * @return array<string,bool>
 */
function adminCommandPinnedLookup(PDO $pdo, int $userId): array
{
    if ($userId <= 0 || !adminCommandShortcutTableAvailable($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT item_type, item_key FROM cms_admin_shortcuts WHERE user_id = ?');
    $stmt->execute([$userId]);
    $lookup = [];
    foreach ($stmt->fetchAll() as $row) {
        $lookup[(string)$row['item_type'] . ':' . (string)$row['item_key']] = true;
    }

    return $lookup;
}

/**
 * @param list<array<string,mixed>> $items
 * @param array<string,bool> $pinnedLookup
 * @return list<array<string,mixed>>
 */
function adminCommandDecoratePinned(array $items, array $pinnedLookup): array
{
    foreach ($items as &$item) {
        $item['pinned'] = isset($pinnedLookup[(string)$item['type'] . ':' . (string)$item['key']]);
    }
    unset($item);

    return $items;
}

/**
 * @return list<array<string,mixed>>
 */
function adminCommandSearch(PDO $pdo, string $query, int $limit = 20): array
{
    $limit = max(1, min(50, $limit));
    $baseItems = adminCommandFilterItems(adminCommandBaseRegistry(), $query, $limit);
    $remaining = max(0, $limit - count($baseItems));
    $contentItems = $remaining > 0 ? adminCommandSearchContent($pdo, $query, $remaining) : [];

    return adminCommandDecoratePinned(
        adminCommandDedupeItems([...$baseItems, ...$contentItems]),
        adminCommandPinnedLookup($pdo, adminCommandCurrentUserId())
    );
}

/**
 * @return array<string,mixed>|null
 */
function adminCommandResolveContentItem(PDO $pdo, string $itemKey): ?array
{
    if (!preg_match('/^([a-z_]+):([1-9][0-9]*)$/', $itemKey, $matches)) {
        return null;
    }

    $entityType = $matches[1];
    $id = (int)$matches[2];
    $sources = [
        'blog' => [
            'enabled' => adminCommandCanUseItem('blog_manage_own', 'blog'),
            'sql' => currentUserHasCapability('blog_manage_all')
                ? 'SELECT id, title, status, created_at FROM cms_articles WHERE id = ? AND deleted_at IS NULL'
                : 'SELECT id, title, status, created_at FROM cms_articles WHERE id = ? AND author_id = ? AND deleted_at IS NULL',
            'params' => currentUserHasCapability('blog_manage_all') ? [$id] : [$id, adminCommandCurrentUserId()],
        ],
        'page' => ['enabled' => adminCommandCanUseItem('content_manage_shared'), 'sql' => 'SELECT id, title, status, blog_id, created_at FROM cms_pages WHERE id = ? AND deleted_at IS NULL'],
        'news' => [
            'enabled' => adminCommandCanUseItem('news_manage_own', 'news'),
            'sql' => currentUserHasCapability('news_manage_all')
                ? 'SELECT id, title, status, created_at FROM cms_news WHERE id = ? AND deleted_at IS NULL'
                : 'SELECT id, title, status, created_at FROM cms_news WHERE id = ? AND author_id = ? AND deleted_at IS NULL',
            'params' => currentUserHasCapability('news_manage_all') ? [$id] : [$id, adminCommandCurrentUserId()],
        ],
        'event' => ['enabled' => adminCommandCanUseItem('content_manage_shared', 'events'), 'sql' => 'SELECT id, title, status, created_at FROM cms_events WHERE id = ? AND deleted_at IS NULL'],
        'form' => ['enabled' => adminCommandCanUseItem('content_manage_shared', 'forms'), 'sql' => 'SELECT id, title, created_at FROM cms_forms WHERE id = ?'],
        'media' => ['enabled' => adminCommandCanUseItem('content_manage_shared'), 'sql' => "SELECT id, COALESCE(NULLIF(original_name, ''), filename) AS title, created_at FROM cms_media WHERE id = ?"],
    ];

    if (!isset($sources[$entityType]) || !$sources[$entityType]['enabled']) {
        return null;
    }

    $stmt = $pdo->prepare((string)$sources[$entityType]['sql']);
    $stmt->execute($sources[$entityType]['params'] ?? [$id]);
    $row = $stmt->fetch();
    return $row ? adminCommandContentItem($entityType, $row) : null;
}

/**
 * @return array<string,mixed>|null
 */
function adminCommandResolveItem(PDO $pdo, string $itemType, string $itemKey): ?array
{
    if (!in_array($itemType, ['screen', 'action', 'content'], true) || $itemKey === '') {
        return null;
    }

    if ($itemType === 'content') {
        return adminCommandResolveContentItem($pdo, $itemKey);
    }

    foreach (adminCommandBaseRegistry() as $item) {
        if ((string)$item['type'] === $itemType && (string)$item['key'] === $itemKey) {
            return $item;
        }
    }

    return null;
}

/**
 * @return list<array<string,mixed>>
 */
function adminCommandPinnedItems(PDO $pdo, int $userId): array
{
    if ($userId <= 0 || !adminCommandShortcutTableAvailable($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT item_type, item_key
         FROM cms_admin_shortcuts
         WHERE user_id = ?
         ORDER BY sort_order, id'
    );
    $stmt->execute([$userId]);
    $items = [];
    $lookup = adminCommandPinnedLookup($pdo, $userId);
    foreach ($stmt->fetchAll() as $row) {
        $item = adminCommandResolveItem($pdo, (string)$row['item_type'], (string)$row['item_key']);
        if ($item !== null) {
            $items[] = $item;
        }
    }

    return adminCommandDecoratePinned($items, $lookup);
}

/**
 * @return array<string,mixed>|null
 */
function adminCommandPinItem(PDO $pdo, int $userId, string $itemType, string $itemKey): ?array
{
    if ($userId <= 0 || !adminCommandShortcutTableAvailable($pdo)) {
        return null;
    }

    $item = adminCommandResolveItem($pdo, $itemType, $itemKey);
    if ($item === null || empty($item['pin_available'])) {
        return null;
    }

    $nextOrderStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM cms_admin_shortcuts WHERE user_id = ?');
    $nextOrderStmt->execute([$userId]);
    $sortOrder = (int)$nextOrderStmt->fetchColumn();

    $pdo->prepare(
        "INSERT INTO cms_admin_shortcuts (user_id, item_type, item_key, label, url, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE label = VALUES(label), url = VALUES(url)"
    )->execute([
        $userId,
        (string)$item['type'],
        (string)$item['key'],
        (string)$item['label'],
        (string)$item['url'],
        $sortOrder,
    ]);

    $item['pinned'] = true;
    return $item;
}

function adminCommandUnpinItem(PDO $pdo, int $userId, string $itemType, string $itemKey): bool
{
    if ($userId <= 0 || !adminCommandShortcutTableAvailable($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare('DELETE FROM cms_admin_shortcuts WHERE user_id = ? AND item_type = ? AND item_key = ?');
    $stmt->execute([$userId, $itemType, $itemKey]);
    return $stmt->rowCount() > 0;
}
