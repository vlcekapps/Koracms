<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/theme.php';

define('KORA_VERSION', trim(file_get_contents(__DIR__ . '/VERSION')));

function db_connect(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        global $server, $user, $pass, $database;
        $dsn = "mysql:host={$server};dbname={$database};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inputInt(string $source, string $key): ?int
{
    $arr = ($source === 'get') ? $_GET : $_POST;
    $val = filter_var($arr[$key] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return ($val !== false) ? (int)$val : null;
}

// ──────────────────────────────── Nastavení (cms_settings) ────────────────────

function getSettings(): array
{
    global $_CMS_SETTINGS;
    if (!isset($_CMS_SETTINGS)) {
        try {
            $rows = db_connect()->query("SELECT `key`, value FROM cms_settings")->fetchAll();
            $_CMS_SETTINGS = array_column($rows, 'value', 'key');
        } catch (\PDOException $e) {
            $_CMS_SETTINGS = [];
        }
    }
    return $_CMS_SETTINGS;
}

function clearSettingsCache(): void
{
    global $_CMS_SETTINGS;
    unset($_CMS_SETTINGS);
}

function getSetting(string $key, string $default = ''): string
{
    return getSettings()[$key] ?? $default;
}

function saveSetting(string $key, string $value): void
{
    db_connect()
        ->prepare("INSERT INTO cms_settings (`key`, value) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE value = VALUES(value)")
        ->execute([$key, $value]);
    clearSettingsCache();
}

function isModuleEnabled(string $module): bool
{
    return getSetting('module_' . $module, '0') === '1';
}

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
                'home_author_visibility' => 'show',
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
                'home_author_visibility' => 'show',
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
                'home_featured_module' => 'news',
                'home_author_visibility' => 'hide',
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
                'home_author_visibility' => 'hide',
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

    $themeKey = resolveThemeName((string)($profileConfig['theme'] ?? defaultThemeName()));

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

// ────────────────────────────── Pomocné funkce ────────────────────────────────

/** Formátuje datum česky: 18. března 2026, 14:30 */
function commentStatusDefinitions(): array
{
    return [
        'pending' => ['label' => 'Čekající', 'public' => false],
        'approved' => ['label' => 'Schválený', 'public' => true],
        'spam' => ['label' => 'Spam', 'public' => false],
        'trash' => ['label' => 'Koš', 'public' => false],
    ];
}

function normalizeCommentStatus(string $status): string
{
    $normalized = trim(mb_strtolower($status));
    return array_key_exists($normalized, commentStatusDefinitions()) ? $normalized : 'pending';
}

function commentStatusLabel(string $status): string
{
    $definitions = commentStatusDefinitions();
    $normalized = normalizeCommentStatus($status);
    return $definitions[$normalized]['label'];
}

function commentStatusIsPublic(string $status): bool
{
    $definitions = commentStatusDefinitions();
    $normalized = normalizeCommentStatus($status);
    return !empty($definitions[$normalized]['public']);
}

function commentStatusApprovalValue(string $status): int
{
    return commentStatusIsPublic($status) ? 1 : 0;
}

function commentsEnabledGlobally(): bool
{
    return getSetting('comments_enabled', '1') === '1';
}

function commentModerationMode(): string
{
    $mode = trim(getSetting('comment_moderation_mode', 'always'));
    return in_array($mode, ['always', 'known', 'none'], true) ? $mode : 'always';
}

function commentCloseDays(): int
{
    return max(0, (int)getSetting('comment_close_days', '0'));
}

function commentNotifyAdminEnabled(): bool
{
    return getSetting('comment_notify_admin', '1') === '1';
}

function commentNotifyAuthorOnApproveEnabled(): bool
{
    return getSetting('comment_notify_author_approve', '0') === '1';
}

function commentNotificationEmail(): string
{
    $candidates = [
        trim(getSetting('comment_notify_email', '')),
        trim(getSetting('contact_email', '')),
        trim(getSetting('admin_email', '')),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return $candidate;
        }
    }

    return '';
}

/**
 * @return list<string>
 */
function commentListSetting(string $key): array
{
    $value = str_replace("\r", '', getSetting($key, ''));
    $items = array_filter(array_map('trim', explode("\n", $value)), static fn(string $item): bool => $item !== '');
    return array_values(array_unique($items));
}

/**
 * @return list<string>
 */
function commentBlockedEmails(): array
{
    return commentListSetting('comment_blocked_emails');
}

/**
 * @return list<string>
 */
function commentSpamPhrases(): array
{
    return commentListSetting('comment_spam_words');
}

function blockedCommentEmailRule(string $authorEmail): string
{
    $normalizedEmail = mb_strtolower(trim($authorEmail));
    if ($normalizedEmail === '') {
        return '';
    }

    foreach (commentBlockedEmails() as $rule) {
        $normalizedRule = mb_strtolower(trim($rule));
        if ($normalizedRule === '') {
            continue;
        }

        if ($normalizedRule[0] === '@' && str_ends_with($normalizedEmail, $normalizedRule)) {
            return $rule;
        }

        if ($normalizedEmail === $normalizedRule) {
            return $rule;
        }
    }

    return '';
}

function matchedCommentSpamPhrase(string $authorName, string $content): string
{
    $haystack = mb_strtolower($authorName . "\n" . $content);
    foreach (commentSpamPhrases() as $phrase) {
        $normalizedPhrase = mb_strtolower(trim($phrase));
        if ($normalizedPhrase !== '' && mb_strpos($haystack, $normalizedPhrase) !== false) {
            return $phrase;
        }
    }

    return '';
}

function articleCommentsClosedByAge(array $article): bool
{
    $days = commentCloseDays();
    if ($days <= 0) {
        return false;
    }

    $reference = trim((string)($article['publish_at'] ?? ''));
    if ($reference === '') {
        $reference = trim((string)($article['created_at'] ?? ''));
    }
    if ($reference === '') {
        return false;
    }

    $referenceTs = strtotime($reference);
    if ($referenceTs === false) {
        return false;
    }

    return $referenceTs < strtotime('-' . $days . ' days');
}

function articleCommentsState(array $article): array
{
    if (!commentsEnabledGlobally()) {
        return [
            'enabled' => false,
            'reason' => 'global_disabled',
            'message' => 'Komentáře jsou na tomto webu vypnuté.',
        ];
    }

    if ((int)($article['comments_enabled'] ?? 1) !== 1) {
        return [
            'enabled' => false,
            'reason' => 'article_disabled',
            'message' => 'Komentáře jsou u tohoto článku vypnuté.',
        ];
    }

    if (articleCommentsClosedByAge($article)) {
        return [
            'enabled' => false,
            'reason' => 'closed_by_age',
            'message' => 'Komentáře jsou u starších článků uzavřené.',
        ];
    }

    return [
        'enabled' => true,
        'reason' => '',
        'message' => '',
    ];
}

/**
 * @return array{status:string, public_result:string}
 */
function determineCommentStatus(PDO $pdo, string $authorName, string $authorEmail, string $content): array
{
    $blockedEmailRule = blockedCommentEmailRule($authorEmail);
    if ($blockedEmailRule !== '') {
        return ['status' => 'spam', 'public_result' => 'pending'];
    }

    $spamPhrase = matchedCommentSpamPhrase($authorName, $content);
    if ($spamPhrase !== '') {
        return ['status' => 'spam', 'public_result' => 'pending'];
    }

    $mode = commentModerationMode();
    if ($mode === 'none') {
        return ['status' => 'approved', 'public_result' => 'approved'];
    }

    if ($mode === 'known') {
        $normalizedEmail = mb_strtolower(trim($authorEmail));
        if ($normalizedEmail !== '') {
            try {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM cms_comments
                     WHERE LOWER(author_email) = ? AND status = 'approved'"
                );
                $stmt->execute([$normalizedEmail]);
                if ((int)$stmt->fetchColumn() > 0) {
                    return ['status' => 'approved', 'public_result' => 'approved'];
                }
            } catch (\PDOException $e) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM cms_comments
                     WHERE LOWER(author_email) = ? AND is_approved = 1"
                );
                $stmt->execute([$normalizedEmail]);
                if ((int)$stmt->fetchColumn() > 0) {
                    return ['status' => 'approved', 'public_result' => 'approved'];
                }
            }
        }
    }

    return ['status' => 'pending', 'public_result' => 'pending'];
}

function notifyAdminAboutPendingComment(array $article, string $authorName, string $authorEmail, string $content): void
{
    if (!commentNotifyAdminEnabled()) {
        return;
    }

    $recipient = commentNotificationEmail();
    if ($recipient === '') {
        return;
    }

    $siteName = getSetting('site_name', 'Kora CMS');
    $articleUrl = articlePublicUrl($article);
    $adminUrl = siteUrl('/admin/comments.php?filter=pending');
    $safeEmail = trim($authorEmail) !== '' ? $authorEmail : 'neuveden';
    $message = "Na webu {$siteName} čeká nový komentář na schválení.\n\n"
        . "Článek: " . (string)($article['title'] ?? 'Článek') . "\n"
        . "Autor: {$authorName}\n"
        . "E-mail: {$safeEmail}\n\n"
        . "Komentář:\n{$content}\n\n"
        . "Článek: {$articleUrl}\n"
        . "Moderace: {$adminUrl}\n";

    sendMail($recipient, 'Nový komentář čeká na schválení', $message);
}

function loadCommentModerationContext(PDO $pdo, int $commentId): ?array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.article_id, c.author_name, c.author_email, c.content, c.status, c.is_approved,
                    c.created_at, a.title AS article_title, a.slug AS article_slug
             FROM cms_comments c
             LEFT JOIN cms_articles a ON a.id = c.article_id
             WHERE c.id = ?"
        );
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
    } catch (\PDOException $e) {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.article_id, c.author_name, c.author_email, c.content,
                    CASE WHEN c.is_approved = 1 THEN 'approved' ELSE 'pending' END AS status,
                    c.is_approved, c.created_at, a.title AS article_title, a.slug AS article_slug
             FROM cms_comments c
             LEFT JOIN cms_articles a ON a.id = c.article_id
             WHERE c.id = ?"
        );
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
    }

    return $comment ?: null;
}

function notifyAuthorAboutApprovedComment(array $comment): void
{
    if (!commentNotifyAuthorOnApproveEnabled()) {
        return;
    }

    $recipient = trim((string)($comment['author_email'] ?? ''));
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $articleId = (int)($comment['article_id'] ?? 0);
    if ($articleId <= 0) {
        return;
    }

    $siteName = getSetting('site_name', 'Kora CMS');
    $articleTitle = trim((string)($comment['article_title'] ?? '')) ?: 'Článek';
    $articleUrl = articlePublicUrl([
        'id' => $articleId,
        'slug' => (string)($comment['article_slug'] ?? ''),
    ]);
    $authorName = trim((string)($comment['author_name'] ?? ''));
    $greetingName = $authorName !== '' ? $authorName : 'dobrý den';
    $message = "Dobrý den"
        . ($greetingName !== 'dobrý den' ? " {$greetingName}" : '')
        . ",\n\n"
        . "na webu {$siteName} byl schválen váš komentář a je nyní veřejně viditelný.\n\n"
        . "Článek: {$articleTitle}\n"
        . "Odkaz na článek: {$articleUrl}\n\n"
        . "Váš komentář:\n"
        . (string)($comment['content'] ?? '')
        . "\n\nDěkujeme.\n";

    sendMail($recipient, 'Váš komentář byl schválen', $message);
}

function setCommentModerationStatus(PDO $pdo, int $commentId, string $status): bool
{
    $comment = loadCommentModerationContext($pdo, $commentId);
    if (!$comment) {
        return false;
    }

    $normalizedStatus = normalizeCommentStatus($status);
    $previousStatus = normalizeCommentStatus((string)($comment['status'] ?? 'pending'));

    try {
        $pdo->prepare(
            "UPDATE cms_comments SET status = ?, is_approved = ? WHERE id = ?"
        )->execute([$normalizedStatus, commentStatusApprovalValue($normalizedStatus), $commentId]);
    } catch (\PDOException $e) {
        $pdo->prepare(
            "UPDATE cms_comments SET is_approved = ? WHERE id = ?"
        )->execute([commentStatusApprovalValue($normalizedStatus), $commentId]);
    }

    if ($normalizedStatus === 'approved' && $previousStatus !== 'approved') {
        notifyAuthorAboutApprovedComment($comment);
    }

    return true;
}

function pendingCommentCount(): int
{
    if (!isModuleEnabled('blog')) {
        return 0;
    }

    try {
        return (int)db_connect()->query(
            "SELECT COUNT(*) FROM cms_comments WHERE status = 'pending'"
        )->fetchColumn();
    } catch (\PDOException $e) {
        try {
            return (int)db_connect()->query(
                "SELECT COUNT(*) FROM cms_comments WHERE is_approved = 0"
            )->fetchColumn();
        } catch (\PDOException $fallbackError) {
            return 0;
        }
    }
}

function pendingReviewSummary(PDO $pdo): array
{
    $summary = [];

    $addSummaryItem = static function (string $key, string $label, string $category, string $url, string $sql) use ($pdo, &$summary): void {
        try {
            $count = (int)$pdo->query($sql)->fetchColumn();
        } catch (\PDOException $e) {
            $count = 0;
        }

        if ($count < 1) {
            return;
        }

        $summary[$key] = [
            'key' => $key,
            'label' => $label,
            'category' => $category,
            'count' => $count,
            'url' => $url,
        ];
    };

    if (isModuleEnabled('blog') && currentUserHasCapability('blog_approve')) {
        $addSummaryItem('articles', 'Články blogu', 'content', BASE_URL . '/admin/blog.php', "SELECT COUNT(*) FROM cms_articles WHERE status = 'pending'");
    }

    if (isModuleEnabled('news') && currentUserHasCapability('news_approve')) {
        $addSummaryItem('news', 'Novinky', 'content', BASE_URL . '/admin/news.php', "SELECT COUNT(*) FROM cms_news WHERE status = 'pending'");
    }

    if (currentUserHasCapability('content_approve_shared')) {
        $sharedModules = [
            ['key' => 'pages', 'enabled' => true, 'label' => 'Stránky', 'url' => BASE_URL . '/admin/pages.php', 'sql' => "SELECT COUNT(*) FROM cms_pages WHERE status = 'pending'"],
            ['key' => 'board', 'enabled' => isModuleEnabled('board'), 'label' => boardModulePublicLabel(), 'url' => BASE_URL . '/admin/board.php', 'sql' => "SELECT COUNT(*) FROM cms_board WHERE status = 'pending'"],
            ['key' => 'downloads', 'enabled' => isModuleEnabled('downloads'), 'label' => 'Ke stažení', 'url' => BASE_URL . '/admin/downloads.php', 'sql' => "SELECT COUNT(*) FROM cms_downloads WHERE status = 'pending'"],
            ['key' => 'events', 'enabled' => isModuleEnabled('events'), 'label' => 'Události', 'url' => BASE_URL . '/admin/events.php', 'sql' => "SELECT COUNT(*) FROM cms_events WHERE status = 'pending'"],
            ['key' => 'places', 'enabled' => isModuleEnabled('places'), 'label' => 'Zajímavá místa', 'url' => BASE_URL . '/admin/places.php', 'sql' => "SELECT COUNT(*) FROM cms_places WHERE status = 'pending'"],
            ['key' => 'podcasts', 'enabled' => isModuleEnabled('podcast'), 'label' => 'Podcasty', 'url' => BASE_URL . '/admin/podcast_shows.php', 'sql' => "SELECT COUNT(*) FROM cms_podcasts WHERE status = 'pending'"],
            ['key' => 'food', 'enabled' => isModuleEnabled('food'), 'label' => 'Jídelní lístky', 'url' => BASE_URL . '/admin/food.php', 'sql' => "SELECT COUNT(*) FROM cms_food_cards WHERE status = 'pending'"],
        ];

        foreach ($sharedModules as $moduleItem) {
            if (!$moduleItem['enabled']) {
                continue;
            }

            $addSummaryItem(
                $moduleItem['key'],
                $moduleItem['label'],
                'content',
                $moduleItem['url'],
                $moduleItem['sql']
            );
        }
    }

    if (isModuleEnabled('blog') && currentUserHasCapability('comments_manage')) {
        $addSummaryItem('comments', 'Komentáře', 'comments', BASE_URL . '/admin/comments.php?filter=pending', "SELECT COUNT(*) FROM cms_comments WHERE status = 'pending'");
    }

    if (isModuleEnabled('reservations') && currentUserHasCapability('bookings_manage')) {
        $addSummaryItem('reservations', 'Rezervace', 'reservations', BASE_URL . '/admin/res_bookings.php?status=pending', "SELECT COUNT(*) FROM cms_res_bookings WHERE status = 'pending'");
    }

    return array_values($summary);
}

function pendingReviewTotalCount(PDO $pdo): int
{
    return array_sum(array_column(pendingReviewSummary($pdo), 'count'));
}

function formatCzechDate(string $datetime): string
{
    static $months = [
        '', 'ledna', 'února', 'března', 'dubna', 'května', 'června',
        'července', 'srpna', 'září', 'října', 'listopadu', 'prosince',
    ];
    try { $dt = new \DateTime($datetime); } catch (\Exception $e) { return h($datetime); }
    return $dt->format('j') . '. ' . $months[(int)$dt->format('n')]
         . ' ' . $dt->format('Y, H:i');
}

/**
 * Odhadne dobu čtení textu v minutách (průměr 200 slov/min pro češtinu).
 */
function readingTime(string $text): int
{
    $plain = strip_tags($text);
    $words = preg_match_all('/\S+/u', $plain);
    return max(1, (int)round($words / 200));
}

// ─────────────────────────────── Statické stránky ────────────────────────

/**
 * Převede text na URL slug (podporuje českou diakritiku).
 */
function slugify(string $text): string
{
    $map = [
        'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n',
        'ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
        'Á'=>'a','Č'=>'c','Ď'=>'d','É'=>'e','Ě'=>'e','Í'=>'i','Ň'=>'n',
        'Ó'=>'o','Ř'=>'r','Š'=>'s','Ť'=>'t','Ú'=>'u','Ů'=>'u','Ý'=>'y','Ž'=>'z',
    ];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function articleSlug(string $value): string
{
    return slugify(trim($value));
}

function newsSlug(string $value): string
{
    return slugify(trim($value));
}

function eventSlug(string $value): string
{
    return slugify(trim($value));
}

function placeSlug(string $value): string
{
    return slugify(trim($value));
}

function boardSlug(string $value): string
{
    return slugify(trim($value));
}

function authorSlug(string $value): string
{
    return slugify(trim($value));
}

function normalizePlainText(string $text): string
{
    $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plain = preg_replace('/\s+/u', ' ', $plain);
    return trim((string)$plain);
}

function newsTitleCandidate(string $title, string $content = ''): string
{
    $normalizedTitle = trim($title);
    if ($normalizedTitle !== '') {
        return mb_substr($normalizedTitle, 0, 255);
    }

    $plain = normalizePlainText($content);
    if ($plain === '') {
        return 'Novinka';
    }

    return mb_strimwidth($plain, 0, 120, '…', 'UTF-8');
}

function newsExcerpt(string $content, int $limit = 220): string
{
    $plain = normalizePlainText($content);
    if ($plain === '') {
        return '';
    }

    return mb_strimwidth($plain, 0, $limit, '…', 'UTF-8');
}

function boardTypeLabel(string $type): string
{
    $definitions = boardTypeDefinitions();
    return $definitions[normalizeBoardType($type)]['label'];
}

function boardExcerpt(array $document, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($document['excerpt'] ?? ''));
    if ($explicitExcerpt !== '') {
        return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
    }

    $descriptionExcerpt = normalizePlainText((string)($document['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

function placeKindLabel(string $kind): string
{
    $definitions = placeKindDefinitions();
    return $definitions[normalizePlaceKind($kind)]['label'];
}

function placeExcerpt(array $place, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($place['excerpt'] ?? ''));
    if ($explicitExcerpt !== '') {
        return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
    }

    $descriptionExcerpt = normalizePlainText((string)($place['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

function placeImageUrl(array $place): string
{
    $filename = trim((string)($place['image_file'] ?? ''));
    if ($filename === '') {
        return '';
    }

    return BASE_URL . '/uploads/places/' . rawurlencode($filename);
}

function deletePlaceImageFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = __DIR__ . '/uploads/places/' . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadPlaceImage(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek musí být ve formátu JPEG, PNG, GIF, WebP nebo SVG.',
        ];
    }

    $directory = __DIR__ . '/uploads/places/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro obrázky míst se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('place_image_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo uložit.',
        ];
    }

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deletePlaceImageFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

function normalizePlaceUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    $validated = filter_var($value, FILTER_VALIDATE_URL);
    if (!is_string($validated) || !preg_match('#^https?://#i', $validated)) {
        return '';
    }

    return $validated;
}

function hydratePlacePresentation(array $place): array
{
    $place['slug'] = placeSlug((string)($place['slug'] ?? ''));
    $place['place_kind'] = normalizePlaceKind((string)($place['place_kind'] ?? 'sight'));
    $place['place_kind_label'] = placeKindLabel((string)$place['place_kind']);
    $place['excerpt_plain'] = placeExcerpt($place);
    $place['image_url'] = placeImageUrl($place);
    $place['url'] = normalizePlaceUrl((string)($place['url'] ?? ''));
    $place['address'] = trim((string)($place['address'] ?? ''));
    $place['locality'] = trim((string)($place['locality'] ?? ''));
    $place['contact_phone'] = trim((string)($place['contact_phone'] ?? ''));
    $place['contact_email'] = trim((string)($place['contact_email'] ?? ''));
    $place['opening_hours'] = trim((string)($place['opening_hours'] ?? ''));
    $place['has_contact'] = $place['contact_phone'] !== '' || $place['contact_email'] !== '';
    $place['full_address'] = trim(
        implode(', ', array_filter([
            $place['address'],
            $place['locality'],
        ], static fn(string $value): bool => $value !== ''))
    );

    $latitude = trim((string)($place['latitude'] ?? ''));
    $longitude = trim((string)($place['longitude'] ?? ''));
    $place['latitude'] = $latitude;
    $place['longitude'] = $longitude;
    $place['has_coordinates'] = $latitude !== '' && $longitude !== '';
    $place['map_url'] = $place['has_coordinates']
        ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($latitude . ',' . $longitude)
        : '';

    return $place;
}

function boardImageUrl(array $document): string
{
    $filename = trim((string)($document['image_file'] ?? ''));
    if ($filename === '') {
        return '';
    }

    return BASE_URL . '/uploads/board/images/' . rawurlencode($filename);
}

function deleteBoardImageFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = __DIR__ . '/uploads/board/images/' . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadBoardImage(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek musí být ve formátu JPEG, PNG, GIF, WebP nebo SVG.',
        ];
    }

    $directory = __DIR__ . '/uploads/board/images/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro obrázky vývěsky se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('board_image_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo uložit.',
        ];
    }

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deleteBoardImageFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

function hydrateBoardPresentation(array $document): array
{
    $document['board_type'] = normalizeBoardType((string)($document['board_type'] ?? 'document'));
    $document['board_type_label'] = boardTypeLabel((string)$document['board_type']);
    $document['excerpt_plain'] = boardExcerpt($document);
    $document['image_url'] = boardImageUrl($document);
    $document['contact_name'] = trim((string)($document['contact_name'] ?? ''));
    $document['contact_phone'] = trim((string)($document['contact_phone'] ?? ''));
    $document['contact_email'] = trim((string)($document['contact_email'] ?? ''));
    $document['has_contact'] = $document['contact_name'] !== ''
        || $document['contact_phone'] !== ''
        || $document['contact_email'] !== '';
    $document['is_pinned'] = (int)($document['is_pinned'] ?? 0);

    return $document;
}

function authorSlugCandidate(array $account): string
{
    $nickname = trim((string)($account['nickname'] ?? ''));
    if ($nickname !== '') {
        return $nickname;
    }

    $fullName = trim(
        trim((string)($account['first_name'] ?? '')) . ' ' . trim((string)($account['last_name'] ?? ''))
    );
    if ($fullName !== '') {
        return $fullName;
    }

    $email = trim((string)($account['email'] ?? ''));
    if ($email !== '') {
        $localPart = strstr($email, '@', true);
        return $localPart !== false && $localPart !== '' ? $localPart : $email;
    }

    return 'autor';
}

function appendUrlQuery(string $path, array $params): string
{
    $query = http_build_query(array_filter(
        $params,
        static fn($value): bool => $value !== null && $value !== ''
    ));

    if ($query === '') {
        return $path;
    }

    return $path . (str_contains($path, '?') ? '&' : '?') . $query;
}

function articlePublicRequestPath(array $article): string
{
    $slug = articleSlug((string)($article['slug'] ?? ''));
    if ($slug !== '') {
        return '/blog/' . rawurlencode($slug);
    }

    return '/blog/article.php?id=' . (int)($article['id'] ?? 0);
}

function articlePublicPath(array $article, array $query = []): string
{
    return BASE_URL . appendUrlQuery(articlePublicRequestPath($article), $query);
}

function articlePublicUrl(array $article, array $query = []): string
{
    return siteUrl(appendUrlQuery(articlePublicRequestPath($article), $query));
}

function articlePreviewPath(array $article): string
{
    $previewToken = trim((string)($article['preview_token'] ?? ''));
    return articlePublicPath($article, $previewToken !== '' ? ['preview' => $previewToken] : []);
}

function newsPublicRequestPath(array $news): string
{
    $slug = newsSlug((string)($news['slug'] ?? ''));
    if ($slug !== '') {
        return '/news/' . rawurlencode($slug);
    }

    return '/news/article.php?id=' . (int)($news['id'] ?? 0);
}

function newsPublicPath(array $news, array $query = []): string
{
    return BASE_URL . appendUrlQuery(newsPublicRequestPath($news), $query);
}

function newsPublicUrl(array $news, array $query = []): string
{
    return siteUrl(appendUrlQuery(newsPublicRequestPath($news), $query));
}

function boardPublicRequestPath(array $document): string
{
    $slug = boardSlug((string)($document['slug'] ?? ''));
    if ($slug !== '') {
        return '/board/' . rawurlencode($slug);
    }

    return '/board/document.php?id=' . (int)($document['id'] ?? 0);
}

function boardPublicPath(array $document, array $query = []): string
{
    return BASE_URL . appendUrlQuery(boardPublicRequestPath($document), $query);
}

function boardPublicUrl(array $document, array $query = []): string
{
    return siteUrl(appendUrlQuery(boardPublicRequestPath($document), $query));
}

function eventPublicRequestPath(array $event): string
{
    $slug = eventSlug((string)($event['slug'] ?? ''));
    if ($slug !== '') {
        return '/events/' . rawurlencode($slug);
    }

    return '/events/event.php?id=' . (int)($event['id'] ?? 0);
}

function placePublicRequestPath(array $place): string
{
    $slug = placeSlug((string)($place['slug'] ?? ''));
    if ($slug !== '') {
        return '/places/' . rawurlencode($slug);
    }

    return '/places/place.php?id=' . (int)($place['id'] ?? 0);
}

function placePublicPath(array $place, array $query = []): string
{
    return BASE_URL . appendUrlQuery(placePublicRequestPath($place), $query);
}

function placePublicUrl(array $place, array $query = []): string
{
    return siteUrl(appendUrlQuery(placePublicRequestPath($place), $query));
}

function eventPublicPath(array $event, array $query = []): string
{
    return BASE_URL . appendUrlQuery(eventPublicRequestPath($event), $query);
}

function eventPublicUrl(array $event, array $query = []): string
{
    return siteUrl(appendUrlQuery(eventPublicRequestPath($event), $query));
}

function uniqueArticleSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = articleSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'clanek';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_articles WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueEventSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = eventSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'udalost';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_events WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniquePlaceSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = placeSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'misto';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_places WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueBoardSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = boardSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'dokument';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_board WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueNewsSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = newsSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'novinka';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_news WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueAuthorSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = authorSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'autor';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_users WHERE author_slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function authorRoleValue(array $author): string
{
    return trim((string)($author['author_role'] ?? $author['role'] ?? ''));
}

function authorPublicSlugValue(array $author): string
{
    return authorSlug((string)($author['author_slug'] ?? $author['slug'] ?? ''));
}

function authorPublicEnabled(array $author): bool
{
    return (int)($author['author_public_enabled'] ?? 0) === 1
        && authorRoleValue($author) !== 'public'
        && authorPublicSlugValue($author) !== '';
}

function authorDisplayName(array $author): string
{
    $preferred = trim((string)($author['author_name'] ?? ''));
    if ($preferred !== '') {
        return $preferred;
    }

    $nickname = trim((string)($author['nickname'] ?? ''));
    if ($nickname !== '') {
        return $nickname;
    }

    $fullName = trim(
        trim((string)($author['first_name'] ?? '')) . ' ' . trim((string)($author['last_name'] ?? ''))
    );
    if ($fullName !== '') {
        return $fullName;
    }

    return trim((string)($author['email'] ?? ''));
}

function normalizeAuthorWebsite(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    $validated = filter_var($value, FILTER_VALIDATE_URL);
    if (!is_string($validated) || !preg_match('#^https?://#i', $validated)) {
        return '';
    }

    return $validated;
}

function authorPublicRequestPath(array $author): string
{
    if (!authorPublicEnabled($author)) {
        return '';
    }

    return '/author/' . rawurlencode(authorPublicSlugValue($author));
}

function authorPublicPath(array $author): string
{
    $path = authorPublicRequestPath($author);
    return $path !== '' ? BASE_URL . $path : '';
}

function authorPublicUrl(array $author): string
{
    $path = authorPublicRequestPath($author);
    return $path !== '' ? siteUrl($path) : '';
}

function authorAvatarUrl(array $author): string
{
    $avatarFile = trim((string)($author['author_avatar'] ?? ''));
    if ($avatarFile === '') {
        return '';
    }

    return BASE_URL . '/uploads/authors/' . rawurlencode($avatarFile);
}

function hydrateAuthorPresentation(array $author): array
{
    $author['author_display_name'] = authorDisplayName($author);
    $author['author_public_path'] = authorPublicPath($author);
    $author['author_public_url'] = authorPublicUrl($author);
    $author['author_avatar_url'] = authorAvatarUrl($author);
    $author['author_website_url'] = normalizeAuthorWebsite((string)($author['author_website'] ?? ''));
    return $author;
}

function hydrateNewsPresentation(array $news): array
{
    $news['title'] = newsTitleCandidate((string)($news['title'] ?? ''), (string)($news['content'] ?? ''));
    $news['slug'] = newsSlug((string)($news['slug'] ?? ''));
    $news['excerpt'] = newsExcerpt((string)($news['content'] ?? ''));
    $news['public_path'] = newsPublicPath($news);
    $news['public_url'] = newsPublicUrl($news);

    if (array_key_exists('author_public_enabled', $news) || array_key_exists('author_slug', $news) || array_key_exists('author_name', $news)) {
        $news = hydrateAuthorPresentation($news);
    }

    return $news;
}

function fetchPublicAuthorBySlug(PDO $pdo, string $slug): ?array
{
    $normalizedSlug = authorSlug($slug);
    if ($normalizedSlug === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, email, first_name, last_name, nickname, role,
                author_public_enabled, author_slug, author_bio, author_avatar, author_website
         FROM cms_users
         WHERE author_slug = ? AND author_public_enabled = 1 AND role != 'public'
         LIMIT 1"
    );
    $stmt->execute([$normalizedSlug]);
    $author = $stmt->fetch();

    return $author ? hydrateAuthorPresentation($author) : null;
}

function fetchPublicAuthorById(PDO $pdo, int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, email, first_name, last_name, nickname, role,
                author_public_enabled, author_slug, author_bio, author_avatar, author_website
         FROM cms_users
         WHERE id = ? AND author_public_enabled = 1 AND role != 'public'
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $author = $stmt->fetch();

    return $author ? hydrateAuthorPresentation($author) : null;
}

function resolveHomeAuthor(PDO $pdo): ?array
{
    $selectedAuthorId = (int)getSetting('home_author_user_id', '0');
    if ($selectedAuthorId > 0) {
        return fetchPublicAuthorById($pdo, $selectedAuthorId);
    }

    $authors = $pdo->query(
        "SELECT id, email, first_name, last_name, nickname, role,
                author_public_enabled, author_slug, author_bio, author_avatar, author_website
         FROM cms_users
         WHERE author_public_enabled = 1 AND role != 'public'
         ORDER BY is_superadmin DESC, id ASC
         LIMIT 2"
    )->fetchAll();

    if (count($authors) !== 1) {
        return null;
    }

    return hydrateAuthorPresentation($authors[0]);
}

function deleteAuthorAvatarFile(string $filename): void
{
    $safeFilename = basename(trim($filename));
    if ($safeFilename === '') {
        return;
    }

    $path = __DIR__ . '/uploads/authors/' . $safeFilename;
    if (is_file($path)) {
        unlink($path);
    }
}

function storeUploadedAuthorAvatar(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE || trim((string)($file['name'] ?? '')) === '') {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Avatar se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Avatar se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Avatar musí být ve formátu JPEG, PNG, GIF, WebP nebo SVG.',
        ];
    }

    $directory = __DIR__ . '/uploads/authors/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro avatary se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('author_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Avatar se nepodařilo uložit.',
        ];
    }

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deleteAuthorAvatarFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

// ─────────────────────────────── Galerie ──────────────────────────────────

/**
 * Sestaví drobečkový trail od kořene po dané album.
 * Vrací pole [ ['id'=>…, 'name'=>…], … ] od nejstaršího k aktuálnímu.
 */
function gallery_breadcrumb(int $albumId): array
{
    $pdo   = db_connect();
    $trail = [];
    $id    = $albumId;
    $seen  = [];
    while ($id !== null && !in_array($id, $seen, true)) {
        $seen[] = $id;
        $stmt   = $pdo->prepare("SELECT id, name, parent_id FROM cms_gallery_albums WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) break;
        array_unshift($trail, ['id' => (int)$row['id'], 'name' => $row['name']]);
        $id = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
    }
    return $trail;
}

/**
 * Vrátí URL náhledové miniatury alba.
 * Priorita: cover_photo_id → první fotka v albu → první podalbum (rekurze max. 4×).
 */
function gallery_cover_url(int $albumId, int $depth = 0): string
{
    if ($depth > 4) return '';
    $pdo  = db_connect();
    $base = BASE_URL . '/uploads/gallery/thumbs/';

    $stmt = $pdo->prepare("SELECT cover_photo_id FROM cms_gallery_albums WHERE id = ?");
    $stmt->execute([$albumId]);
    $album = $stmt->fetch();
    if ($album && $album['cover_photo_id']) {
        $s = $pdo->prepare("SELECT filename FROM cms_gallery_photos WHERE id = ?");
        $s->execute([$album['cover_photo_id']]);
        $p = $s->fetch();
        if ($p) return $base . rawurlencode($p['filename']);
    }

    $stmt = $pdo->prepare(
        "SELECT filename FROM cms_gallery_photos WHERE album_id = ? ORDER BY sort_order, id LIMIT 1"
    );
    $stmt->execute([$albumId]);
    $photo = $stmt->fetch();
    if ($photo) return $base . rawurlencode($photo['filename']);

    $stmt = $pdo->prepare(
        "SELECT id FROM cms_gallery_albums WHERE parent_id = ? ORDER BY name LIMIT 1"
    );
    $stmt->execute([$albumId]);
    $sub = $stmt->fetch();
    if ($sub) return gallery_cover_url((int)$sub['id'], $depth + 1);

    return '';
}

/**
 * Vytvoří miniaturu obrázku (max. $maxDim px na delší straně).
 * Vrátí true při úspěchu, false při selhání.
 */
function gallery_make_thumb(string $src, string $dst, int $maxDim = 300): bool
{
    $info = @getimagesize($src);
    if (!$info) return false;
    [$w, $h, $type] = $info;

    $image = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_GIF  => @imagecreatefromgif($src),
        IMAGETYPE_WEBP => @imagecreatefromwebp($src),
        default        => false,
    };
    if (!$image) return false;

    if ($w <= $maxDim && $h <= $maxDim) {
        $newW = $w;
        $newH = $h;
    } elseif ($w >= $h) {
        $newW = $maxDim;
        $newH = (int)round($h * $maxDim / $w);
    } else {
        $newH = $maxDim;
        $newW = (int)round($w * $maxDim / $h);
    }

    $thumb = imagecreatetruecolor($newW, $newH);
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }
    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);

    $ok = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($thumb, $dst, 85),
        IMAGETYPE_PNG  => imagepng($thumb, $dst, 6),
        IMAGETYPE_GIF  => imagegif($thumb, $dst),
        IMAGETYPE_WEBP => imagewebp($thumb, $dst, 85),
        default        => false,
    };
    imagedestroy($image);
    imagedestroy($thumb);
    return (bool)$ok;
}

/**
 * Zpracuje obsah přes Parsedown (Markdown + HTML).
 * Markdown syntaxe se převede na HTML, existující HTML projde beze změny.
 */
function renderContent(string $text): string
{
    static $parsedown = null;
    if ($parsedown === null) {
        require_once __DIR__ . '/lib/Parsedown.php';
        $parsedown = new Parsedown();
        $parsedown->setSafeMode(false);
    }
    return $parsedown->text($text);
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 0) . ' kB';
    return $bytes . ' B';
}

function moduleFileUrl(string $module, int $id): string
{
    return BASE_URL . '/' . trim($module, '/') . '/file.php?id=' . $id;
}

function safeDownloadName(string $preferredName, string $fallbackName = 'soubor'): string
{
    $clean = static function (string $value): string {
        return trim(str_replace(["\r", "\n", "\0"], '', basename($value)));
    };

    $preferred = $clean($preferredName);
    if ($preferred !== '') {
        return $preferred;
    }

    $fallback = $clean($fallbackName);
    return $fallback !== '' ? $fallback : 'soubor';
}

function safeDownloadAsciiFallback(string $downloadName): string
{
    $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName);
    $fallback = trim((string)$fallback, '._');
    if ($fallback !== '') {
        return $fallback;
    }

    $extension = preg_replace('/[^A-Za-z0-9]+/', '', pathinfo($downloadName, PATHINFO_EXTENSION));
    return 'download' . ($extension !== '' ? '.' . $extension : '');
}

function sendFileDownloadNotFound(string $message = 'Soubor nebyl nalezen.'): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function sendStoredFileDownload(string $path, string $downloadName): void
{
    if (!is_file($path) || !is_readable($path)) {
        sendFileDownloadNotFound();
    }

    $downloadName = safeDownloadName($downloadName, basename($path));
    $asciiFallback = safeDownloadAsciiFallback($downloadName);
    $mimeType = 'application/octet-stream';

    try {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedType = $finfo->file($path);
        if (is_string($detectedType) && $detectedType !== '') {
            $mimeType = $detectedType;
        }
    } catch (\Throwable $e) {
        error_log('sendStoredFileDownload: ' . $e->getMessage());
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: attachment; filename="' . addcslashes($asciiFallback, "\\\"") . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    $written = readfile($path);
    if ($written === false) {
        error_log('sendStoredFileDownload: nepodařilo se odeslat soubor ' . $path);
    }
    exit;
}

/**
 * Vrátí HTML patičky s ikonami sociálních sítí a odkazem na RSS.
 * Automaticky přidá cookie lištu (pokud je povolena).
 */
function siteFooter(): string
{
    trackPageView();

    $year     = date('Y');
    $siteName = h(getSetting('site_name', 'Kora CMS'));
    $b        = BASE_URL;
    $links    = '';

    $socials = [
        'social_facebook'  => ['Facebook',  'https://www.facebook.com/'],
        'social_youtube'   => ['YouTube',   'https://www.youtube.com/'],
        'social_instagram' => ['Instagram', 'https://www.instagram.com/'],
        'social_twitter'   => ['X/Twitter', 'https://x.com/'],
    ];
    foreach ($socials as $key => [$label, $prefix]) {
        $url = getSetting($key, '');
        if ($url !== '') {
            $links .= '<a href="' . h($url) . '" rel="noopener noreferrer" target="_blank">' . $label . '</a> ';
        }
    }
    $links .= '<a href="' . $b . '/feed.php">RSS</a>';

    $version = KORA_VERSION;

    return "<footer>\n"
         . "  <p>&copy; {$year} {$siteName}</p>\n"
         . "  <p>{$links}</p>\n"
         . "  <p><a href=\"{$b}/search.php\">Vyhledávání</a>"
         . (isModuleEnabled('newsletter') ? " · <a href=\"{$b}/subscribe.php\">Odběr novinek</a>" : '')
         . "</p>\n"
         . (isModuleEnabled('reservations')
             ? "  <p>"
               . (isLoggedIn()
                   ? (isPublicUser()
                       ? "<a href=\"{$b}/reservations/my.php\">Moje rezervace</a> · <a href=\"{$b}/public_profile.php\">Můj profil</a> · <a href=\"{$b}/public_logout.php\">Odhlásit se</a>"
                       : '')
                   : "<a href=\"{$b}/public_login.php\">Přihlášení</a> · <a href=\"{$b}/register.php\">Registrace</a>")
               . "</p>\n"
             : '')
         . "  <p><small><a href=\"https://koracms.pvlcek.cz\" rel=\"noopener noreferrer\" target=\"_blank\">Kora CMS {$version}</a></small></p>\n"
         . (getSetting('visitor_counter_enabled', '0') === '1'
             ? (function () {
                   $vs = getVisitorStats();
                    $f  = fn(int $n) => number_format($n, 0, ',', "\u{00a0}");
                    $items = [
                        'Online' => $f($vs['online']),
                        'Dnes' => $f($vs['today']),
                        'Měsíc' => $f($vs['month']),
                        'Celkem' => $f($vs['total']),
                    ];

                    $html = "  <div class=\"visitor-counter-block\" aria-labelledby=\"visitor-counter-heading\">\n"
                          . "    <p id=\"visitor-counter-heading\" class=\"sr-only\">Statistiky návštěvnosti</p>\n"
                          . "    <ul class=\"visitor-counter\">\n";

                    foreach ($items as $label => $value) {
                        $html .= "      <li class=\"visitor-counter__item\">"
                              . "<span class=\"visitor-counter__label\">" . h($label) . ":</span> "
                              . "<strong class=\"visitor-counter__value\">{$value}</strong>"
                              . "</li>\n";
                    }

                    return $html
                         . "    </ul>\n"
                         . "  </div>\n";
               })()
             : '')
         . "</footer>\n"
         . cookieBanner();
}

/**
 * Vrátí HTML cookie lišty – zobrazí se jen při první návštěvě.
 * Volbu uloží do cookie cms_cookie (1=přijato, 0=odmítnuto) na 365 dní.
 */
function cookieBanner(): string
{
    if (getSetting('cookie_consent_enabled', '0') !== '1') return '';
    $text = h(getSetting('cookie_consent_text',
        'Tento web používá soubory cookies ke zlepšení vašeho zážitku z prohlížení.'));
    return <<<HTML
<div id="cookie-banner" role="dialog" aria-labelledby="cookie-heading" aria-modal="true"
     style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9000;
            background:#222;color:#fff;padding:1rem 1.5rem;
            box-shadow:0 -2px 8px rgba(0,0,0,.4)">
  <p id="cookie-heading" style="margin:0 0 .75rem">
    <strong>Soubory cookies</strong> &ndash; {$text}
  </p>
  <div style="display:flex;gap:.75rem;flex-wrap:wrap">
    <button id="cookie-accept" type="button"
            style="padding:.4rem 1rem;background:#4caf50;border:none;color:#fff;
                   cursor:pointer;border-radius:3px;font-size:1rem">Přijmout</button>
    <button id="cookie-decline" type="button"
            style="padding:.4rem 1rem;background:#777;border:none;color:#fff;
                   cursor:pointer;border-radius:3px;font-size:1rem">Odmítnout</button>
  </div>
</div>
<script>
(function(){
  function getCk(n){var v='; '+document.cookie,p=v.split('; '+n+'=');if(p.length===2)return p.pop().split(';').shift();}
  function setCk(n,v,d){var e=new Date();e.setTime(e.getTime()+(d*864e5));document.cookie=n+'='+v+';expires='+e.toUTCString()+';path=/;SameSite=Lax';}
  var b=document.getElementById('cookie-banner');
  var ac=document.getElementById('cookie-accept');
  var dc=document.getElementById('cookie-decline');
  if(!getCk('cms_cookie')){b.style.display='block';setTimeout(function(){ac.focus();},50);}
  function hide(v){setCk('cms_cookie',v,365);b.style.display='none';}
  ac.addEventListener('click',function(){hide('1');});
  dc.addEventListener('click',function(){hide('0');});
  b.addEventListener('keydown',function(e){
    if(e.key!=='Tab')return;
    var els=b.querySelectorAll('button');
    if(e.shiftKey&&document.activeElement===els[0]){e.preventDefault();els[els.length-1].focus();}
    else if(!e.shiftKey&&document.activeElement===els[els.length-1]){e.preventDefault();els[0].focus();}
  });
})();
</script>
HTML;
}

/**
 * Vrátí HTML meta tagů pro SEO a Open Graph.
 *
 * @param array{title?:string,description?:string,image?:string,url?:string,type?:string} $meta
 */
function seoMeta(array $meta = []): string
{
    $siteName = h(getSetting('site_name', 'Kora CMS'));
    $b        = BASE_URL;
    $title = isset($meta['title'])       ? h($meta['title'])       : $siteName;
    $desc  = isset($meta['description']) ? h($meta['description']) : h(getSetting('site_description', ''));
    $image = $meta['image'] ?? '';
    $url   = isset($meta['url'])         ? h($meta['url'])         : '';
    $type  = isset($meta['type'])        ? h($meta['type'])        : 'website';

    if ($image === '') {
        $def = getSetting('og_image_default', '');
        if ($def !== '') $image = h($b . '/uploads/' . $def);
    } else {
        $image = h($image);
    }

    $out  = "  <meta name=\"description\" content=\"{$desc}\">\n";
    $out .= "  <meta property=\"og:type\" content=\"{$type}\">\n";
    $out .= "  <meta property=\"og:title\" content=\"{$title}\">\n";
    $out .= "  <meta property=\"og:site_name\" content=\"{$siteName}\">\n";
    if ($desc  !== '') $out .= "  <meta property=\"og:description\" content=\"{$desc}\">\n";
    if ($image !== '') $out .= "  <meta property=\"og:image\" content=\"{$image}\">\n";
    if ($url   !== '') $out .= "  <meta property=\"og:url\" content=\"{$url}\">\n";
    return $out;
}

/**
 * Vrátí HTML administrátorské lišty (viditelné jen přihlášeným uživatelům).
 *
 * @param string $editUrl URL tlačítka "Upravit" – prázdné = pouze odkaz na admin
 */
function adminBar(string $editUrl = ''): string
{
    if (!isLoggedIn()) return '';
    $b   = BASE_URL;
    $out = '<div id="admin-bar" role="navigation" aria-label="Administrace webu"'
         . ' style="position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#222;'
         . 'color:#fff;display:flex;align-items:center;gap:.5rem;padding:.45rem .75rem;'
         . 'font-size:.85rem">'
         . '<a href="' . $b . '/admin/index.php" style="color:#ddd;text-decoration:none;display:inline-flex;'
         . 'align-items:center;min-height:2rem;padding:.35rem .6rem;border-radius:4px"><span aria-hidden="true">&#9881;</span> Admin</a>';
    if ($editUrl !== '') {
        $out .= ' <a href="' . h($editUrl) . '" style="color:#ffd700;text-decoration:none;display:inline-flex;'
              . 'align-items:center;min-height:2rem;padding:.35rem .6rem;border-radius:4px">&#9998; Upravit</a>';
    }
    $out .= '<span style="margin-left:auto">'
          . '<a href="' . $b . '/admin/logout.php" style="color:#ddd;text-decoration:none;display:inline-flex;'
          . 'align-items:center;min-height:2rem;padding:.35rem .6rem;border-radius:4px">Odhlásit se</a>'
          . '</span>';
    $out .= '</div>';
    return $out;
}

/**
 * Vrátí sdílené a11y styly pro skip link, screen-reader text a focus ring.
 */
function publicA11yStyleTag(): string
{
    return "<style>\n"
         . "  .skip-link { position:absolute; left:-9999px; top:auto; }\n"
         . "  .skip-link:focus { left:1rem; top:1rem; z-index:9999; background:#fff; color:#000;"
         . " padding:.5rem 1rem; border:2px solid #000; text-decoration:none; }\n"
         . "  .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden;"
         . " clip:rect(0,0,0,0); white-space:nowrap; border:0; }\n"
         . "  :focus-visible { outline:3px solid #005fcc; outline-offset:2px; }\n"
         . "</style>\n";
}

/**
 * Vrátí HTML tagu <link rel="icon"> pro favicon (pokud je nastaven).
 */
function faviconTag(): string
{
    $favicon = getSetting('site_favicon', '');
    if ($favicon === '') return '';
    $url = h(BASE_URL . '/uploads/site/' . $favicon);
    return "  <link rel=\"icon\" href=\"{$url}\">\n"
         . "  <link rel=\"apple-touch-icon\" href=\"{$url}\">\n";
}

/**
 * Pokud je zapnut režim údržby, zobrazí stránku údržby a ukončí skript.
 * Přihlášení administrátoři nejsou omezeni.
 */
function checkMaintenanceMode(): void
{
    if (getSetting('maintenance_mode', '0') !== '1') return;
    if (isLoggedIn()) return;
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if (str_ends_with($script, DIRECTORY_SEPARATOR . 'maintenance.php')) return;
    include __DIR__ . '/maintenance.php';
    exit;
}

/**
 * Zapíše záznam do audit logu (cms_log).
 */
function logAction(string $action, string $detail = ''): void
{
    try {
        db_connect()->prepare("INSERT INTO cms_log (action, detail) VALUES (?, ?)")
            ->execute([$action, $detail]);
    } catch (\PDOException $e) {
        // Tabulka ještě neexistuje
    }
}

/**
 * Vrátí absolutní URL včetně schématu a domény – pro použití v e-mailech.
 */
function siteUrl(string $path = ''): string
{
    $base = BASE_URL;
    if ($base === '' || !str_starts_with($base, 'http')) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $scheme . '://' . $host . $base;
    }
    return $base . $path;
}

/**
 * Odešle e-mail v UTF-8. Vrátí true při úspěchu.
 */
function sendMail(string $to, string $subject, string $body): bool
{
    $from        = getSetting('contact_email', 'noreply@localhost');
    $safeSubject = preg_replace('/[\r\n]/', '', $subject);
    $safeFrom    = preg_replace('/[\r\n]/', '', $from);
    $safeTo      = preg_replace('/[\r\n]/', '', $to);

    $smtpHost = ini_get('SMTP') ?: 'localhost';
    $smtpPort = (int)(ini_get('smtp_port') ?: 25);

    $smtp = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 5);
    if (!$smtp) {
        error_log("sendMail FAILED: connect {$smtpHost}:{$smtpPort} – {$errstr}");
        return false;
    }

    // Čtení SMTP odpovědi (včetně víceřádkových)
    $read = function () use ($smtp): string {
        $response = '';
        while (($line = fgets($smtp, 512)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    };

    $read(); // 220 greeting
    fwrite($smtp, "EHLO localhost\r\n");
    $read(); // 250 capabilities
    fwrite($smtp, "MAIL FROM:<{$safeFrom}>\r\n");
    $read();
    fwrite($smtp, "RCPT TO:<{$safeTo}>\r\n");
    $read();
    fwrite($smtp, "DATA\r\n");
    $read(); // 354 go ahead

    $msg = "From: {$safeFrom}\r\n"
         . "To: {$safeTo}\r\n"
         . "Subject: {$safeSubject}\r\n"
         . "Reply-To: {$safeFrom}\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "MIME-Version: 1.0\r\n"
         . "\r\n"
         . str_replace("\n.", "\n..", $body) . "\r\n.\r\n";

    fwrite($smtp, $msg);
    $dataResp = $read();
    fwrite($smtp, "QUIT\r\n");
    fclose($smtp);

    $ok = str_starts_with(trim($dataResp), '250');
    if (!$ok) {
        error_log("sendMail FAILED: SMTP said: {$dataResp}");
    }
    return $ok;
}

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
            $nav .= '<li><a href="' . $b . '/page.php?slug=' . rawurlencode($p['slug']) . '"'
                  . ($current === 'page:' . $p['slug'] ? ' aria-current="page"' : '')
                  . '>' . h($p['title']) . '</a></li>' . "\n";
        }
    } catch (\PDOException $e) {
        // Tabulka cms_pages ještě neexistuje
    }

    $moduleMap = navModuleDefaults();
    foreach (navModuleOrder() as $key) {
        if (isModuleEnabled($key) && isset($moduleMap[$key])) {
            [$href, $label] = $moduleMap[$key];
            $nav .= $li($href, $label, $key);
        }
    }

    if (isLoggedIn()) $nav .= $li('/admin/index.php', 'Administrace', 'admin');

    $nav .= '</ul></nav>';
    return $nav;
}
