<?php

function isSocialPreviewCrawler(): bool
{
    $userAgent = strtolower(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')));
    if ($userAgent === '') {
        return false;
    }

    return preg_match(
        '/facebookexternalhit|facebot|twitterbot|linkedinbot|slackbot|discordbot|whatsapp|telegrambot|pinterest|vkshare|skypeuripreview|embedly|quora link preview|outbrain|applebot/',
        $userAgent
    ) === 1;
}

function koraRequestId(): string
{
    $cachedRequestId = $GLOBALS['_KORA_REQUEST_ID'] ?? null;
    if (is_string($cachedRequestId) && $cachedRequestId !== '') {
        return $cachedRequestId;
    }

    $incomingRequestId = trim((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
    if ($incomingRequestId !== '' && preg_match('/\A[A-Za-z0-9._:-]{8,128}\z/', $incomingRequestId) === 1) {
        $requestId = $incomingRequestId;
    } else {
        $requestId = bin2hex(random_bytes(12));
    }

    $GLOBALS['_KORA_REQUEST_ID'] = $requestId;
    return $requestId;
}

function koraLogValue(mixed $value): string|int|float|bool|null
{
    if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
        return $value;
    }

    if (is_string($value)) {
        return mb_substr($value, 0, 1000);
    }

    if ($value instanceof \Throwable) {
        return get_class($value) . ': ' . mb_substr($value->getMessage(), 0, 1000);
    }

    if (is_array($value)) {
        return '[array:' . count($value) . ']';
    }

    return '[' . get_debug_type($value) . ']';
}

/**
 * @param array<string,mixed> $context
 */
function koraLog(string $level, string $message, array $context = []): void
{
    $normalizedContext = [];
    foreach ($context as $key => $value) {
        if ($key === '') {
            continue;
        }
        $normalizedContext[$key] = koraLogValue($value);
    }

    $requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $record = [
        'time' => date(DATE_ATOM),
        'level' => $level,
        'message' => $message,
        'request_id' => koraRequestId(),
        'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
        'path' => is_string($requestPath) ? $requestPath : '',
    ];
    if ($normalizedContext !== []) {
        $record['context'] = $normalizedContext;
    }

    $encodedRecord = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log($encodedRecord !== false ? $encodedRecord : '[koraLog] ' . $level . ': ' . $message);
}

$requestId = koraRequestId();
if (!headers_sent()) {
    header('X-Request-ID: ' . $requestId);
}

$isSocialPreviewCrawler = isSocialPreviewCrawler();
if ($isSocialPreviewCrawler && session_status() === PHP_SESSION_NONE) {
    session_cache_limiter('');
}

function sendSocialPreviewCacheHeaders(): void
{
    if (
        (defined('KORA_FORCE_NO_STORE_NO_INDEX') && KORA_FORCE_NO_STORE_NO_INDEX === true)
        || isSensitiveNoStoreNoIndexRequestPath()
    ) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    header_remove('Set-Cookie');
    header_remove('Cache-Control');
    header_remove('Pragma');
    header_remove('Expires');
    header('Cache-Control: public, max-age=300, s-maxage=300', true);
    header('Vary: User-Agent', false);
}

function isSensitiveNoStoreNoIndexRequestPath(?string $requestUri = null): bool
{
    $requestPath = (string)(parse_url($requestUri ?? (string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    if ($requestPath === '') {
        return false;
    }

    return in_array($requestPath, [
        BASE_URL . '/csp-report.php',
        BASE_URL . '/health.php',
        BASE_URL . '/confirm_email.php',
        BASE_URL . '/subscribe_confirm.php',
        BASE_URL . '/unsubscribe.php',
        BASE_URL . '/newsletter_widget_subscribe.php',
        BASE_URL . '/reset_password.php',
        BASE_URL . '/reservations/cancel_booking.php',
        BASE_URL . '/public_logout.php',
        BASE_URL . '/admin/logout.php',
    ], true);
}

function isAdminRequestPath(?string $requestUri = null): bool
{
    $requestPath = (string)(parse_url($requestUri ?? (string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    if ($requestPath === '') {
        return false;
    }

    return str_starts_with($requestPath, BASE_URL . '/admin/')
        || $requestPath === BASE_URL . '/migrate.php';
}

function sendNoStoreNoIndexHeaders(): void
{
    $GLOBALS['_KORA_FORCE_NO_STORE_NO_INDEX'] = true;
    if (!defined('KORA_FORCE_NO_STORE_NO_INDEX')) {
        define('KORA_FORCE_NO_STORE_NO_INDEX', true);
    }

    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Referrer-Policy: no-referrer');
}

function sendAdminNoStoreHeaders(): void
{
    sendNoStoreNoIndexHeaders();
}

function sendOperationalJsonHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    sendNoStoreNoIndexHeaders();
}

function sendAdminJsonHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    sendAdminNoStoreHeaders();
}

function sendAdminDownloadHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    sendAdminNoStoreHeaders();
    header('X-Content-Type-Options: nosniff');
}

/**
 * @param list<string> $allowedMethods
 * @return list<string>
 */
function normalizeHttpMethods(array $allowedMethods): array
{
    $normalizedAllowedMethods = [];
    foreach ($allowedMethods as $allowedMethod) {
        $normalizedAllowedMethod = strtoupper(trim($allowedMethod));
        if ($normalizedAllowedMethod !== '' && preg_match('/\A[A-Z]+\z/', $normalizedAllowedMethod) === 1) {
            $normalizedAllowedMethods[] = $normalizedAllowedMethod;
        }
    }
    $normalizedAllowedMethods = array_values(array_unique($normalizedAllowedMethods));
    if ($normalizedAllowedMethods === []) {
        $normalizedAllowedMethods = ['GET'];
    }

    return $normalizedAllowedMethods;
}

/**
 * @param list<string> $allowedMethods
 */
function requireHttpMethods(array $allowedMethods): string
{
    $normalizedAllowedMethods = normalizeHttpMethods($allowedMethods);
    $requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($requestMethod, $normalizedAllowedMethods, true)) {
        sendNoStoreNoIndexHeaders();
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Allow: ' . implode(', ', $normalizedAllowedMethods));
        http_response_code(405);
        echo "Method not allowed\n";
        exit;
    }

    return $requestMethod;
}

/**
 * @param list<string> $allowedMethods
 * @param array<string,mixed> $payload
 */
function requireJsonHttpMethods(array $allowedMethods, array $payload = ['status' => 'method_not_allowed']): string
{
    $normalizedAllowedMethods = normalizeHttpMethods($allowedMethods);
    $requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($requestMethod, $normalizedAllowedMethods, true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            sendNoStoreNoIndexHeaders();
            header('Allow: ' . implode(', ', $normalizedAllowedMethods));
        }
        http_response_code(405);
        echo json_encode(
            $payload + ['request_id' => koraRequestId()],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    return $requestMethod;
}

if ($isSocialPreviewCrawler && function_exists('header_register_callback')) {
    header_register_callback('sendSocialPreviewCacheHeaders');
} elseif ($isSocialPreviewCrawler) {
    register_shutdown_function('sendSocialPreviewCacheHeaders');
}

if (session_status() === PHP_SESSION_NONE) {
    if ($isSocialPreviewCrawler) {
        $_SESSION = [];
    } else {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 0');
header('X-Download-Options: noopen');
header('X-Permitted-Cross-Domain-Policies: none');
header('Referrer-Policy: same-origin');
header('Cross-Origin-Opener-Policy: same-origin');
header('Origin-Agent-Cluster: ?1');
header('Permissions-Policy: accelerometer=(), browsing-topics=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
if (!$isSocialPreviewCrawler && isAdminRequestPath()) {
    sendAdminNoStoreHeaders();
}

// CSP nonce – per-request nonce pro inline skripty a styly.
// 'unsafe-inline' zůstává jako fallback pro prohlížeče bez podpory nonce
// a pro inline style atributy (nonce nepokrývá style="...").
$_CSP_NONCE = base64_encode(random_bytes(16));
// CSP se skládá dřív, než jsou dostupná nastavení z DB, proto allowlist drží
// jen externí zdroje, které první CMS šablony samy umí vložit.
$_CSP_EXTRA_SCRIPT = ' https://www.googletagmanager.com https://cdn.jsdelivr.net https://cdn.quilljs.com';
$_CSP_EXTRA_STYLE = ' https://cdn.jsdelivr.net https://cdn.quilljs.com';
$_CSP_EXTRA_FONT = ' https://cdn.jsdelivr.net https://cdn.quilljs.com';
$_CSP_EXTRA_CONNECT = ' https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com';
$cspStylePolicy = "style-src 'self' 'nonce-{$_CSP_NONCE}' 'unsafe-inline'{$_CSP_EXTRA_STYLE}; style-src-elem 'self' 'nonce-{$_CSP_NONCE}' 'unsafe-inline'{$_CSP_EXTRA_STYLE}; style-src-attr 'unsafe-inline'";
$cspPolicy = "default-src 'self'; script-src 'self' 'nonce-{$_CSP_NONCE}' 'unsafe-inline'{$_CSP_EXTRA_SCRIPT}; {$cspStylePolicy}; img-src 'self' data:; font-src 'self'{$_CSP_EXTRA_FONT}; connect-src 'self'{$_CSP_EXTRA_CONNECT}; media-src 'self' https: data: blob:; frame-src 'self' https:; frame-ancestors 'none'";
header('Content-Security-Policy: ' . $cspPolicy);
header('Content-Security-Policy-Report-Only: ' . $cspPolicy . '; report-uri ' . BASE_URL . '/csp-report.php');
if ($isSocialPreviewCrawler) {
    sendSocialPreviewCacheHeaders();
}

// ── 301/302 přesměrování z tabulky cms_redirects ─────────────────────────────
(function () {
    $requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
    if (str_starts_with($requestPath, BASE_URL . '/admin/')) {
        return;
    }
    try {
        $stmt = db_connect()->prepare(
            "SELECT id, new_path, status_code FROM cms_redirects WHERE old_path = ? LIMIT 1"
        );
        $stmt->execute([$requestPath]);
        $redirect = $stmt->fetch();
        if ($redirect) {
            $redirectTarget = storedRedirectTarget((string)($redirect['new_path'] ?? ''), '');
            if ($redirectTarget === '') {
                koraLog('warning', 'stored redirect skipped unsafe target', [
                    'redirect_id' => (int)($redirect['id'] ?? 0),
                    'new_path_hash' => hash('sha256', (string)($redirect['new_path'] ?? '')),
                ]);

                return;
            }

            $statusCode = in_array((int)($redirect['status_code'] ?? 301), [301, 302], true)
                ? (int)$redirect['status_code']
                : 301;
            db_connect()->prepare("UPDATE cms_redirects SET hit_count = hit_count + 1 WHERE id = ?")->execute([$redirect['id']]);
            header('Location: ' . $redirectTarget, true, $statusCode);
            exit;
        }
    } catch (\PDOException $e) {
        // Tabulka ještě neexistuje – přeskočit
    }
})();

/**
 * Vrátí CSP nonce pro aktuální request.
 * Použití: <script nonce="<?= cspNonce() ?>"> nebo <style nonce="<?= cspNonce() ?>">
 */
function cspNonce(): string
{
    global $_CSP_NONCE;
    return $_CSP_NONCE ?? '';
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['cms_logged_in']);
}

function isSuperAdmin(): bool
{
    return !empty($_SESSION['cms_superadmin']);
}

/**
 * @return array<string, array{label:string, staff:bool, capabilities:list<string>, legacy?:bool}>
 */
function roleDefinitions(): array
{
    return [
        'public' => [
            'label' => 'Veřejný uživatel',
            'staff' => false,
            'capabilities' => [],
        ],
        'author' => [
            'label' => 'Autor',
            'staff' => true,
            'capabilities' => [
                'admin_access',
                'blog_manage_own',
                'news_manage_own',
            ],
        ],
        'editor' => [
            'label' => 'Editor',
            'staff' => true,
            'capabilities' => [
                'admin_access',
                'blog_manage_own',
                'blog_manage_all',
                'blog_taxonomies_manage',
                'blog_approve',
                'news_manage_own',
                'news_manage_all',
                'news_approve',
                'content_manage_shared',
                'content_approve_shared',
            ],
        ],
        'moderator' => [
            'label' => 'Moderátor',
            'staff' => true,
            'capabilities' => [
                'admin_access',
                'comments_manage',
                'messages_manage',
            ],
        ],
        'booking_manager' => [
            'label' => 'Správce rezervací',
            'staff' => true,
            'capabilities' => [
                'admin_access',
                'bookings_manage',
            ],
        ],
        'admin' => [
            'label' => 'Admin',
            'staff' => true,
            'capabilities' => [
                'admin_access',
                'blog_manage_own',
                'blog_manage_all',
                'blog_taxonomies_manage',
                'blog_approve',
                'news_manage_own',
                'news_manage_all',
                'news_approve',
                'content_manage_shared',
                'content_approve_shared',
                'comments_manage',
                'messages_manage',
                'bookings_manage',
                'newsletter_manage',
                'settings_manage',
                'users_manage',
                'statistics_view',
                'import_export_manage',
            ],
        ],
        'collaborator' => [
            'label' => 'Správce obsahu',
            'legacy' => true,
            'staff' => true,
            'capabilities' => [
                'admin_access',
                'blog_manage_own',
                'blog_manage_all',
                'blog_taxonomies_manage',
                'blog_approve',
                'news_manage_own',
                'news_manage_all',
                'news_approve',
                'content_manage_shared',
                'content_approve_shared',
                'comments_manage',
                'messages_manage',
                'bookings_manage',
                'newsletter_manage',
                'settings_manage',
                'statistics_view',
                'import_export_manage',
            ],
        ],
    ];
}

function normalizeUserRole(?string $role): string
{
    $normalized = trim((string)$role);
    $definitions = roleDefinitions();
    return isset($definitions[$normalized]) ? $normalized : 'public';
}

function userRoleLabel(string $role): string
{
    $definitions = roleDefinitions();
    $normalized = normalizeUserRole($role);
    return $definitions[$normalized]['label'] ?? 'Neznámá role';
}

/**
 * @return array<string, string>
 */
function staffRoleOptions(?string $currentRole = null): array
{
    $currentRole = normalizeUserRole($currentRole);
    $options = [];
    foreach (roleDefinitions() as $roleKey => $definition) {
        if ($roleKey === 'public') {
            $options[$roleKey] = $definition['label'];
            continue;
        }
        if (!empty($definition['legacy']) && $currentRole !== $roleKey) {
            continue;
        }
        $options[$roleKey] = $definition['label'];
    }
    return $options;
}

function roleHasCapability(string $role, string $capability, bool $superadmin = false): bool
{
    if ($superadmin) {
        return true;
    }

    $definitions = roleDefinitions();
    $normalized = normalizeUserRole($role);
    return in_array($capability, $definitions[$normalized]['capabilities'] ?? [], true);
}

function currentUserHasCapability(string $capability): bool
{
    return roleHasCapability(currentUserRole(), $capability, isSuperAdmin());
}

function currentUserRoleIs(string $role): bool
{
    return currentUserRole() === normalizeUserRole($role);
}

function adminForbidden(string $message): void
{
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8"><title>403</title></head>'
       . '<body><p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
       . '<p><a href="' . BASE_URL . '/admin/index.php">Zpět</a></p></body></html>';
    exit;
}

function requireCapability(string $capability, string $message = ''): void
{
    requireLogin(BASE_URL . '/admin/login.php');
    if (!currentUserHasCapability($capability)) {
        adminForbidden($message !== '' ? $message : 'Přístup odepřen. Pro tuto část administrace nemáte potřebné oprávnění.');
    }
}

function canManageOwnBlogOnly(): bool
{
    return currentUserHasCapability('blog_manage_own') && !currentUserHasCapability('blog_manage_all');
}

function canManageOwnNewsOnly(): bool
{
    return currentUserHasCapability('news_manage_own') && !currentUserHasCapability('news_manage_all');
}

function canAccessReviewQueue(): bool
{
    return currentUserHasCapability('blog_approve')
        || currentUserHasCapability('news_approve')
        || currentUserHasCapability('content_approve_shared')
        || currentUserHasCapability('comments_manage')
        || currentUserHasCapability('bookings_manage');
}

function adminRouteCapability(?string $scriptPath = null): ?string
{
    $path = strtolower(str_replace('\\', '/', $scriptPath ?? ($_SERVER['SCRIPT_NAME'] ?? '')));
    if ($path === '' || !str_contains($path, '/admin/')) {
        return null;
    }

    $file = basename($path);

    if (in_array($file, ['index.php', 'profile.php', 'logout.php'], true)) {
        return 'admin_access';
    }

    if (in_array($file, ['users.php', 'user_form.php', 'user_save.php', 'user_delete.php'], true)) {
        return 'users_manage';
    }

    if (in_array($file, ['themes.php', 'theme_preview.php'], true)) {
        return null;
    }

    if (in_array($file, ['settings.php', 'settings_modules.php', 'settings_display.php'], true)) {
        return 'settings_manage';
    }

    if ($file === 'statistics.php') {
        return 'statistics_view';
    }

    if (in_array($file, ['comments.php', 'comment_action.php', 'comment_bulk.php', 'comment_approve.php', 'comment_delete.php'], true)) {
        return 'comments_manage';
    }

    if (in_array($file, [
        'chat.php',
        'chat_bulk.php',
        'chat_delete.php',
        'chat_action.php',
        'chat_message.php',
        'chat_update.php',
        'chat_reply.php',
        'contact.php',
        'contact_delete.php',
        'contact_action.php',
        'contact_bulk.php',
        'contact_message.php',
    ], true)) {
        return 'messages_manage';
    }

    if (str_starts_with($file, 'res_')) {
        return 'bookings_manage';
    }

    if (in_array($file, [
        'newsletter.php',
        'newsletter_form.php',
        'newsletter_send.php',
        'newsletter_subscriber.php',
        'newsletter_subscriber_action.php',
        'newsletter_subscriber_delete.php',
        'newsletter_history.php',
    ], true)) {
        return 'newsletter_manage';
    }

    if (in_array($file, ['export.php', 'import.php'], true)) {
        return 'import_export_manage';
    }

    if (in_array($file, ['blog.php', 'blog_form.php', 'blog_save.php', 'blog_delete.php', 'blog_bulk.php'], true)) {
        return 'blog_manage_own';
    }

    if (in_array($file, ['blog_cats.php', 'blog_tags.php', 'blog_cat_delete.php', 'blog_tag_delete.php'], true)) {
        return 'blog_taxonomies_manage';
    }

    if (in_array($file, ['news.php', 'news_form.php', 'news_save.php', 'news_delete.php'], true)) {
        return 'news_manage_own';
    }

    if (
        str_starts_with($file, 'board') ||
        str_starts_with($file, 'download') ||
        str_starts_with($file, 'dl_') ||
        str_starts_with($file, 'event') ||
        in_array($file, ['events.php'], true) ||
        str_starts_with($file, 'faq') ||
        str_starts_with($file, 'food') ||
        str_starts_with($file, 'gallery_') ||
        str_starts_with($file, 'page') ||
        str_starts_with($file, 'place') ||
        in_array($file, ['places.php'], true) ||
        str_starts_with($file, 'podcast') ||
        str_starts_with($file, 'poll') ||
        $file === 'nav_reorder.php'
    ) {
        return 'content_manage_shared';
    }

    return 'admin_access';
}

function currentUserId(): ?int
{
    return isset($_SESSION['cms_user_id']) ? (int)$_SESSION['cms_user_id'] : null;
}

function currentUserDisplayName(): string
{
    return $_SESSION['cms_user_name'] ?? '';
}

/**
 * Vrátí pouze bezpečný interní redirect v rámci tohoto webu.
 * Zahazuje externí URL, protocol-relative URL i neplatné cesty.
 */
function internalRedirectTarget(string $target, string $default = ''): string
{
    $target = trim(str_replace(["\r", "\n"], '', $target));
    if ($target === '') {
        return $default;
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $target)) {
        return $default;
    }

    $parts = parse_url($target);
    if ($parts === false) {
        return $default;
    }

    if (isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return $default;
    }

    $path = $parts['path'] ?? '';
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        return $default;
    }

    if (BASE_URL !== '' && $path !== BASE_URL && !str_starts_with($path, BASE_URL . '/')) {
        return $default;
    }

    $query    = isset($parts['query']) ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    return $path . $query . $fragment;
}

function safePublicReturnTarget(string $target, string $default = ''): string
{
    $safeDefault = internalRedirectTarget($default, BASE_URL . '/index.php');
    $safeTarget = internalRedirectTarget($target, '');
    if ($safeTarget === '') {
        return $safeDefault;
    }

    if (isSensitiveNoStoreNoIndexRequestPath($safeTarget)) {
        return $safeDefault;
    }

    return $safeTarget;
}

/**
 * Vrátí bezpečný cíl pro ručně uložené 301/302 přesměrování.
 * Na rozdíl od návratových URL dovoluje i záměrnou úplnou http/https adresu.
 */
function storedRedirectTarget(string $target, string $default = ''): string
{
    $target = trim($target);
    if ($target === '') {
        return $default;
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $target)) {
        return $default;
    }

    $parts = parse_url($target);
    if ($parts === false) {
        return $default;
    }

    if (!isset($parts['scheme']) && !isset($parts['host'])) {
        return internalRedirectTarget($target, $default);
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || !isset($parts['host'])) {
        return $default;
    }

    if (isset($parts['user']) || isset($parts['pass'])) {
        return $default;
    }

    return $target;
}

function normalizeHttpExternalUrl(string $target, bool $prependScheme = true): string
{
    $target = trim($target);
    if ($target === '') {
        return '';
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $target)) {
        return '';
    }

    if (!preg_match('#^https?://#i', $target)) {
        if (!$prependScheme || str_starts_with($target, '/')) {
            return '';
        }

        $target = 'https://' . $target;
    }

    $validated = filter_var($target, FILTER_VALIDATE_URL);
    if (!is_string($validated)) {
        return '';
    }

    $parts = parse_url($validated);
    if (!is_array($parts)) {
        return '';
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = trim((string)($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return '';
    }

    if (isset($parts['user']) || isset($parts['pass'])) {
        return '';
    }

    return $validated;
}

function adminLoginRedirectTarget(string $target, string $default = ''): string
{
    $safeTarget = internalRedirectTarget($target, '');
    if ($safeTarget === '') {
        return $default;
    }

    $safePath = (string)(parse_url($safeTarget, PHP_URL_PATH) ?? '');
    if ($safePath === BASE_URL . '/admin/login.php' || $safePath === BASE_URL . '/admin/login_2fa.php') {
        return $default;
    }

    if (
        str_starts_with($safePath, BASE_URL . '/admin/')
        || $safePath === BASE_URL . '/migrate.php'
    ) {
        return $safeTarget;
    }

    return $default;
}

function requireLogin(string $loginUrl = '/admin/login.php'): void
{
    if (!isLoggedIn()) {
        $target = $loginUrl;
        if (str_contains($loginUrl, '/admin/')) {
            $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
            $parsedPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
            $parsedQuery = (string)(parse_url($requestUri, PHP_URL_QUERY) ?? '');
            $currentTarget = $parsedPath !== ''
                ? $parsedPath . ($parsedQuery !== '' ? '?' . $parsedQuery : '')
                : '';
            $safeRedirect = adminLoginRedirectTarget($currentTarget, '');
            if (
                $safeRedirect !== ''
                && $safeRedirect !== BASE_URL . '/admin/login.php'
                && $safeRedirect !== BASE_URL . '/admin/login_2fa.php'
            ) {
                $separator = str_contains($loginUrl, '?') ? '&' : '?';
                $target .= $separator . 'redirect=' . urlencode($safeRedirect);
            }
        }
        header('Location: ' . $target);
        exit;
    }

    // Veřejní uživatelé nemají přístup do administrace
    if (isPublicUser() && str_contains($loginUrl, '/admin/')) {
        header('Location: ' . BASE_URL . '/public_profile.php');
        exit;
    }

    // Upgrade staré session (bez user_id) na nový formát s cms_users
    if (!isset($_SESSION['cms_user_id'])) {
        try {
            $u = db_connect()->query(
                "SELECT id, email, first_name, last_name, nickname, is_superadmin, role
                 FROM cms_users WHERE is_superadmin = 1 LIMIT 1"
            )->fetch();
            if ($u) {
                $name = $u['nickname'] !== '' ? $u['nickname']
                      : trim($u['first_name'] . ' ' . $u['last_name']);
                if ($name === '') {
                    $name = $u['email'];
                }
                $_SESSION['cms_user_id']    = (int)$u['id'];
                $_SESSION['cms_user_email'] = $u['email'];
                $_SESSION['cms_user_name']  = $name;
                $_SESSION['cms_superadmin'] = (bool)$u['is_superadmin'];
                $_SESSION['cms_user_role']  = normalizeUserRole($u['role'] ?? 'admin');
            }
        } catch (\PDOException $e) {
            // cms_users ještě neexistuje – ponecháme session beze změny
        }
    }

    if (str_contains($loginUrl, '/admin/')) {
        $requiredCapability = adminRouteCapability();
        if ($requiredCapability !== null && !currentUserHasCapability($requiredCapability)) {
            adminForbidden('Přístup odepřen. Pro tuto část administrace nemáte potřebné oprávnění.');
        }
    }
}

function requireSuperAdmin(): void
{
    requireLogin(BASE_URL . '/admin/login.php');
    if (!isSuperAdmin()) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8"><title>403</title></head>'
           . '<body><p>Přístup odepřen. Tato funkce je vyhrazena hlavnímu administrátorovi.</p>'
           . '<p><a href="' . BASE_URL . '/admin/index.php">Zpět</a></p></body></html>';
        exit;
    }
}

/**
 * Přihlásí uživatele – uloží data do session.
 */
function loginUser(int $id, string $email, bool $superadmin, string $displayName, string $role = 'admin'): void
{
    session_regenerate_id(true);
    $_SESSION['cms_logged_in']  = true;
    $_SESSION['cms_user_id']    = $id;
    $_SESSION['cms_user_email'] = $email;
    $_SESSION['cms_superadmin'] = $superadmin;
    $_SESSION['cms_user_name']  = $displayName;
    $_SESSION['cms_user_role']  = normalizeUserRole($role);
}

function isPublicUser(): bool
{
    return currentUserRole() === 'public';
}

function currentUserRole(): string
{
    return normalizeUserRole($_SESSION['cms_user_role'] ?? 'public');
}

/**
 * Vyžaduje přihlášení veřejného uživatele.
 * Pokud není přihlášen, přesměruje na veřejný login.
 */
function requirePublicLogin(string $redirect = ''): void
{
    if (!isLoggedIn()) {
        $url = BASE_URL . '/public_login.php';
        $safeRedirect = internalRedirectTarget($redirect, '');
        if ($safeRedirect !== '') {
            $url .= '?redirect=' . urlencode($safeRedirect);
        }
        header('Location: ' . $url);
        exit;
    }
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]
        );
    }
    if (!headers_sent()) {
        header('Clear-Site-Data: "cache"');
    }
    session_destroy();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Interní: rotuje CSRF token a uchová předchozí pro podporu multi-tab.
 */
function csrfRotate(): void
{
    $_SESSION['csrf_token_prev'] = $_SESSION['csrf_token'] ?? '';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Vygeneruje příklad z násobilky (2–9 × 2–9) a uloží správnou odpověď do session.
 * Vrátí text příkladu, např. "3 × 7".
 */
function captchaGenerate(): string
{
    $a = random_int(2, 9);
    $b = random_int(2, 9);
    $_SESSION['captcha_answer'] = $a * $b;
    return "{$a} × {$b}";
}

/**
 * Ověří uživatelovu odpověď na CAPTCHA. Jednorázové – odpověď ze session se po ověření odstraní.
 */
function captchaVerify(string $input): bool
{
    $expected = $_SESSION['captcha_answer'] ?? null;
    unset($_SESSION['captcha_answer']);
    if ($expected === null) {
        return false;
    }
    return (int)trim($input) === (int)$expected;
}

/**
 * Rate limiting – přeruší požadavek s HTTP 429, pokud IP překročí limit.
 * $action  – identifikátor akce (login, comment, contact, chat)
 * $max     – maximální počet pokusů v časovém okně
 * $window  – délka okna v sekundách
 */
function rateLimitKey(string $action, string $identifier): string
{
    return hash('sha256', $identifier . '|' . $action);
}

function rateLimitRetryAfter(int $window): int
{
    return max(1, $window);
}

/**
 * @param null|callable():void $onExceeded
 */
function rateLimitApply(string $key, int $max, int $window, ?callable $onExceeded = null): void
{
    try {
        $pdo = db_connect();

        // Vyčistit expirované záznamy
        $pdo->prepare("DELETE FROM cms_rate_limit
                    WHERE window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)")
            ->execute([$window]);

        // Atomický upsert – eliminuje TOCTOU race condition
        $pdo->prepare(
            "INSERT INTO cms_rate_limit (id, attempts, window_start) VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE
                attempts = IF(window_start < DATE_SUB(NOW(), INTERVAL ? SECOND), 1, attempts + 1),
                window_start = IF(window_start < DATE_SUB(NOW(), INTERVAL ? SECOND), NOW(), window_start)"
        )->execute([$key, $window, $window]);

        // Zkontrolovat aktuální stav po upsertu
        $stmt = $pdo->prepare("SELECT attempts FROM cms_rate_limit WHERE id = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if ($row && (int)$row['attempts'] > $max) {
            if ($onExceeded !== null) {
                $onExceeded();
                exit;
            }

            sendNoStoreNoIndexHeaders();
            header('Content-Type: text/html; charset=UTF-8');
            header('Retry-After: ' . rateLimitRetryAfter($window));
            http_response_code(429);
            $requestId = koraRequestId();
            echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8"><title>Příliš mnoho pokusů</title>'
               . '<link rel="stylesheet" href="' . h(BASE_URL . '/assets/error.css') . '"></head>'
               . '<body class="error-page"><h1>Příliš mnoho pokusů</h1>'
               . '<p>Zkuste to prosím za chvíli.</p>'
               . '<p class="error-page__request">Kód požadavku pro podporu: <code>' . h($requestId) . '</code></p>'
               . '</body></html>';
            exit;
        }
    } catch (\PDOException $e) {
        koraLog('warning', 'rateLimit failed', ['exception' => $e]);
    }
}

/**
 * @param null|callable():void $onExceeded
 */
function rateLimit(string $action, int $max = 10, int $window = 60, ?callable $onExceeded = null): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    rateLimitApply(rateLimitKey($action, $ip), $max, $window, $onExceeded);
}

/**
 * @param null|callable():void $onExceeded
 */
function rateLimitSubject(string $action, string $subject, int $max = 10, int $window = 60, ?callable $onExceeded = null): void
{
    $normalizedSubject = strtolower(trim($subject));
    if ($normalizedSubject === '') {
        return;
    }

    // Subject limit chrání konkrétní účet/token napříč IP adresami, aniž by ukládal e-mail v databázi.
    rateLimitApply(rateLimitKey($action, 'subject:' . $normalizedSubject), $max, $window, $onExceeded);
}

/**
 * Vrátí HTML skrytého honeypot pole.
 */
function honeypotField(): string
{
    static $honeypotCounter = 0;
    $honeypotCounter++;
    $honeypotId = 'hp_website_' . $honeypotCounter;

    return '<div class="honeypot-field" aria-hidden="true">'
         . '<label for="' . h($honeypotId) . '">Website</label>'
         . '<input type="text" id="' . h($honeypotId) . '" name="hp_website" tabindex="-1" autocomplete="off">'
         . '</div>';
}

/**
 * Vrátí true, pokud bot vyplnil honeypot pole (= spam).
 */
function honeypotTriggered(): bool
{
    return ($_POST['hp_website'] ?? '') !== '';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    // Aktuální token
    $current  = $_SESSION['csrf_token'] ?? '';
    // Předchozí token (multi-tab: uživatel má otevřených více formulářů)
    $previous = $_SESSION['csrf_token_prev'] ?? '';

    if (($current !== '' && hash_equals($current, $token))
        || ($previous !== '' && hash_equals($previous, $token))
    ) {
        csrfRotate();
        return;
    }

    http_response_code(403);
    echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8"><title>403</title></head>'
       . '<body><p>Neplatný bezpečnostní token. Vraťte se zpět a akci opakujte.</p></body></html>';
    exit;
}
