<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ob_start();

require_once __DIR__ . '/../db.php';

$baseUrl = rtrim($argv[1] ?? 'http://localhost', '/');
$pdo     = db_connect();

$articleId = $pdo->query(
    "SELECT id FROM cms_articles WHERE status = 'published' ORDER BY id LIMIT 1"
)->fetchColumn();
$articleCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_articles WHERE status = 'published'"
)->fetchColumn();
$newsCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_news WHERE status = 'published'"
)->fetchColumn();
$boardCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_board WHERE status = 'published' AND is_published = 1
     AND (removal_date IS NULL OR removal_date >= CURDATE())"
)->fetchColumn();
$activePollCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_polls
     WHERE status = 'active'
       AND (start_date IS NULL OR start_date <= NOW())
       AND (end_date IS NULL OR end_date > NOW())"
)->fetchColumn();
$homeBlogCountSetting = max(0, (int)getSetting('home_blog_count', '5'));
$pageSlug = $pdo->query(
    "SELECT slug FROM cms_pages WHERE status = 'published' AND is_published = 1 ORDER BY id LIMIT 1"
)->fetchColumn();
$publicUserRow = $pdo->query(
    "SELECT id, email, first_name, last_name FROM cms_users WHERE role = 'public' AND is_confirmed = 1 ORDER BY id LIMIT 1"
)->fetch();
$podcastShowSlug = $pdo->query(
    "SELECT slug FROM cms_podcast_shows ORDER BY id LIMIT 1"
)->fetchColumn();
$foodCardId = $pdo->query(
    "SELECT id FROM cms_food_cards WHERE status = 'published' AND is_published = 1 ORDER BY is_current DESC, id LIMIT 1"
)->fetchColumn();
$galleryAlbumId = $pdo->query(
    "SELECT id FROM cms_gallery_albums ORDER BY id LIMIT 1"
)->fetchColumn();
$galleryPhotoId = $pdo->query(
    "SELECT id FROM cms_gallery_photos ORDER BY id LIMIT 1"
)->fetchColumn();
$pollDetailId = $pdo->query(
    "SELECT id FROM cms_polls ORDER BY created_at DESC, id DESC LIMIT 1"
)->fetchColumn();
$resourceRow = $pdo->query(
    "SELECT id, slug, max_advance_days FROM cms_res_resources WHERE is_active = 1 ORDER BY id LIMIT 1"
)->fetch();
$resourceSlug = $resourceRow['slug'] ?? null;
$reservationsBookDate = null;
if ($resourceRow) {
    $resourceId = (int)$resourceRow['id'];
    $advanceDays = max(0, (int)$resourceRow['max_advance_days']);

    $hoursStmt = $pdo->prepare(
        "SELECT day_of_week, is_closed FROM cms_res_hours WHERE resource_id = ?"
    );
    $hoursStmt->execute([$resourceId]);
    $hoursByDay = [];
    foreach ($hoursStmt->fetchAll() as $hourRow) {
        $hoursByDay[(int)$hourRow['day_of_week']] = (bool)$hourRow['is_closed'];
    }

    $blockedStmt = $pdo->prepare(
        "SELECT blocked_date FROM cms_res_blocked WHERE resource_id = ?"
    );
    $blockedStmt->execute([$resourceId]);
    $blockedDates = array_flip(array_column($blockedStmt->fetchAll(), 'blocked_date'));

    $probeDate = new DateTimeImmutable('today');
    for ($offset = 0; $offset <= $advanceDays; $offset++) {
        $candidate = $probeDate->modify('+' . $offset . ' days');
        $candidateStr = $candidate->format('Y-m-d');
        $candidateDow = ((int)$candidate->format('N')) - 1;
        if (!isset($hoursByDay[$candidateDow]) || $hoursByDay[$candidateDow]) {
            continue;
        }
        if (isset($blockedDates[$candidateStr])) {
            continue;
        }
        $reservationsBookDate = $candidateStr;
        break;
    }
}

$cleanup = [
    'public_user_ids' => [],
    'confirm_emails' => [],
    'subscriber_emails' => [],
];

if (!$publicUserRow) {
    $publicAuditEmail = 'runtimeaudit-public-' . bin2hex(random_bytes(6)) . '@example.test';
    $pdo->prepare(
        "INSERT INTO cms_users (email, password, first_name, last_name, role, is_superadmin, is_confirmed, created_at)
         VALUES (?, ?, ?, ?, 'public', 0, 1, NOW())"
    )->execute([
        $publicAuditEmail,
        password_hash('RuntimeAudit123!', PASSWORD_BCRYPT),
        'Runtime',
        'Audit',
    ]);
    $publicUserId = (int)$pdo->lastInsertId();
    $cleanup['public_user_ids'][] = $publicUserId;
    $publicUserRow = [
        'id' => $publicUserId,
        'email' => $publicAuditEmail,
        'first_name' => 'Runtime',
        'last_name' => 'Audit',
    ];
}

$auditSessionId = 'runtimeauditadmin';
session_write_close();
session_id($auditSessionId);
session_start();
$_SESSION['cms_logged_in'] = true;
$_SESSION['cms_superadmin'] = true;
$_SESSION['cms_user_id'] = 1;
$_SESSION['cms_user_name'] = 'Runtime Audit';
$_SESSION['cms_user_role'] = 'admin';
$adminCsrfToken = csrfToken();
session_write_close();

$publicAuditSessionId = 'runtimeauditpublic';
session_id($publicAuditSessionId);
session_start();
$_SESSION['cms_logged_in'] = true;
$_SESSION['cms_superadmin'] = false;
$_SESSION['cms_user_id'] = (int)$publicUserRow['id'];
$_SESSION['cms_user_name'] = trim(((string)$publicUserRow['first_name']) . ' ' . ((string)$publicUserRow['last_name'])) ?: (string)$publicUserRow['email'];
$_SESSION['cms_user_role'] = 'public';
session_write_close();

$confirmToken = bin2hex(random_bytes(32));
$confirmEmail = 'runtimeaudit-confirm-' . bin2hex(random_bytes(6)) . '@example.test';
$pdo->prepare(
    "INSERT INTO cms_users (email, password, first_name, last_name, role, is_superadmin, is_confirmed, confirmation_token, created_at)
     VALUES (?, ?, ?, ?, 'public', 0, 0, ?, NOW())"
)->execute([
    $confirmEmail,
    password_hash('RuntimeAudit123!', PASSWORD_BCRYPT),
    'Confirm',
    'Audit',
    $confirmToken,
]);
$cleanup['confirm_emails'][] = $confirmEmail;

$subscribeConfirmToken = '';
$unsubscribeToken = '';
if (isModuleEnabled('newsletter')) {
    $subscribeConfirmToken = bin2hex(random_bytes(32));
    $subscribeConfirmEmail = 'runtimeaudit-newsletter-confirm-' . bin2hex(random_bytes(6)) . '@example.test';
    $pdo->prepare(
        "INSERT INTO cms_subscribers (email, token, confirmed) VALUES (?, ?, 0)"
    )->execute([$subscribeConfirmEmail, $subscribeConfirmToken]);
    $cleanup['subscriber_emails'][] = $subscribeConfirmEmail;

    $unsubscribeToken = bin2hex(random_bytes(32));
    $unsubscribeEmail = 'runtimeaudit-newsletter-unsub-' . bin2hex(random_bytes(6)) . '@example.test';
    $pdo->prepare(
        "INSERT INTO cms_subscribers (email, token, confirmed) VALUES (?, ?, 1)"
    )->execute([$unsubscribeEmail, $unsubscribeToken]);
    $cleanup['subscriber_emails'][] = $unsubscribeEmail;
}

$pages = [
    ['label' => 'home', 'url' => $baseUrl . '/'],
    ['label' => 'search', 'url' => $baseUrl . '/search.php?q=test'],
    ['label' => 'public_login', 'url' => $baseUrl . '/public_login.php'],
    ['label' => 'register', 'url' => $baseUrl . '/register.php'],
    ['label' => 'reset_password', 'url' => $baseUrl . '/reset_password.php'],
    ['label' => 'subscribe', 'url' => $baseUrl . '/subscribe.php'],
    ['label' => 'confirm_email', 'url' => $baseUrl . '/confirm_email.php?token=' . urlencode($confirmToken)],
    ['label' => 'contact', 'url' => $baseUrl . '/contact/index.php'],
    ['label' => 'chat', 'url' => $baseUrl . '/chat/index.php'],
    ['label' => 'public_profile', 'url' => $baseUrl . '/public_profile.php', 'cookie' => 'PHPSESSID=' . $publicAuditSessionId],
    ['label' => 'admin_settings', 'url' => $baseUrl . '/admin/settings.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_themes', 'url' => $baseUrl . '/admin/themes.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_statistics', 'url' => $baseUrl . '/admin/statistics.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
];

if (isModuleEnabled('newsletter')) {
    $pages[] = ['label' => 'subscribe_confirm', 'url' => $baseUrl . '/subscribe_confirm.php?token=' . urlencode($subscribeConfirmToken)];
    $pages[] = ['label' => 'unsubscribe', 'url' => $baseUrl . '/unsubscribe.php?token=' . urlencode($unsubscribeToken)];
}

if (isModuleEnabled('blog')) {
    $pages[] = ['label' => 'blog_index', 'url' => $baseUrl . '/blog/index.php'];
}
if (isModuleEnabled('board')) {
    $pages[] = ['label' => 'board_index', 'url' => $baseUrl . '/board/index.php'];
}
if (isModuleEnabled('downloads')) {
    $pages[] = ['label' => 'downloads_index', 'url' => $baseUrl . '/downloads/index.php'];
}
if (isModuleEnabled('events')) {
    $pages[] = ['label' => 'events_index', 'url' => $baseUrl . '/events/index.php'];
}
if (isModuleEnabled('faq')) {
    $pages[] = ['label' => 'faq_index', 'url' => $baseUrl . '/faq/index.php'];
}
if (isModuleEnabled('food')) {
    $pages[] = ['label' => 'food', 'url' => $baseUrl . '/food/index.php'];
    $pages[] = ['label' => 'food_archive', 'url' => $baseUrl . '/food/archive.php'];
    if ($foodCardId) {
        $pages[] = ['label' => 'food_card', 'url' => $baseUrl . '/food/card.php?id=' . urlencode((string)$foodCardId)];
    }
}
if (isModuleEnabled('gallery')) {
    $pages[] = ['label' => 'gallery_index', 'url' => $baseUrl . '/gallery/index.php'];
    if ($galleryAlbumId) {
        $pages[] = ['label' => 'gallery_album', 'url' => $baseUrl . '/gallery/album.php?id=' . urlencode((string)$galleryAlbumId)];
    }
    if ($galleryPhotoId) {
        $pages[] = ['label' => 'gallery_photo', 'url' => $baseUrl . '/gallery/photo.php?id=' . urlencode((string)$galleryPhotoId)];
    }
}
if (isModuleEnabled('news')) {
    $pages[] = ['label' => 'news_index', 'url' => $baseUrl . '/news/index.php'];
}
if (isModuleEnabled('places')) {
    $pages[] = ['label' => 'places_index', 'url' => $baseUrl . '/places/index.php'];
}
if (isModuleEnabled('podcast')) {
    $pages[] = ['label' => 'podcast_index', 'url' => $baseUrl . '/podcast/index.php'];
    if ($podcastShowSlug) {
        $pages[] = ['label' => 'podcast_show', 'url' => $baseUrl . '/podcast/show.php?slug=' . urlencode((string)$podcastShowSlug)];
    }
}
if (isModuleEnabled('polls')) {
    $pages[] = ['label' => 'polls_index', 'url' => $baseUrl . '/polls/index.php'];
    if ($pollDetailId) {
        $pages[] = ['label' => 'polls_detail', 'url' => $baseUrl . '/polls/index.php?id=' . urlencode((string)$pollDetailId)];
    }
}

if ($articleId) {
    $pages[] = ['label' => 'blog_article', 'url' => $baseUrl . '/blog/article.php?id=' . urlencode((string)$articleId)];
}
if ($pageSlug) {
    $pages[] = ['label' => 'static_page', 'url' => $baseUrl . '/page.php?slug=' . urlencode((string)$pageSlug)];
}
if ($resourceSlug) {
    $pages[] = ['label' => 'reservations_index', 'url' => $baseUrl . '/reservations/index.php'];
    $pages[] = ['label' => 'reservations_resource', 'url' => $baseUrl . '/reservations/resource.php?slug=' . urlencode((string)$resourceSlug)];
    $pages[] = ['label' => 'reservations_my', 'url' => $baseUrl . '/reservations/my.php', 'cookie' => 'PHPSESSID=' . $publicAuditSessionId];
    if ($reservationsBookDate) {
        $pages[] = [
            'label' => 'reservations_book',
            'url' => $baseUrl . '/reservations/book.php?slug=' . urlencode((string)$resourceSlug) . '&date=' . urlencode($reservationsBookDate),
            'cookie' => 'PHPSESSID=' . $publicAuditSessionId,
        ];
    }
}

/**
 * @return array{status:string,headers:array<int,string>,body:string}
 */
function fetchUrl(string $url, string $cookie = '', int $maxRedirects = 20): array
{
    $headers = [
        'User-Agent: KoraRuntimeAudit/1.0',
    ];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers) . "\r\n",
            'ignore_errors' => true,
            'timeout' => 15,
            'follow_location' => $maxRedirects > 0 ? 1 : 0,
            'max_redirects' => $maxRedirects,
        ],
    ]);

    $body = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = $responseHeaders[0] ?? 'HTTP status unknown';

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => is_string($body) ? $body : '',
    ];
}

/**
 * @param array<string,string> $fields
 * @return array{status:string,headers:array<int,string>,body:string}
 */
function postUrl(string $url, array $fields, string $cookie = '', int $maxRedirects = 20): array
{
    $headers = [
        'User-Agent: KoraRuntimeAudit/1.0',
        'Content-Type: application/x-www-form-urlencoded',
    ];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => http_build_query($fields),
            'ignore_errors' => true,
            'timeout' => 15,
            'follow_location' => $maxRedirects > 0 ? 1 : 0,
            'max_redirects' => $maxRedirects,
        ],
    ]);

    $body = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = $responseHeaders[0] ?? 'HTTP status unknown';

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => is_string($body) ? $body : '',
    ];
}

/**
 * @param array<string,string> $fields
 * @param array<string,array{path:string,filename:string,type?:string}> $files
 * @return array{status:string,headers:array<int,string>,body:string}
 */
function postMultipartUrl(string $url, array $fields, array $files, string $cookie = '', int $maxRedirects = 20): array
{
    $boundary = '----KoraRuntimeAudit' . bin2hex(random_bytes(8));
    $eol = "\r\n";
    $body = '';

    foreach ($fields as $fieldName => $fieldValue) {
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="' . $fieldName . '"' . $eol . $eol;
        $body .= $fieldValue . $eol;
    }

    foreach ($files as $fieldName => $file) {
        $contents = (string)file_get_contents($file['path']);
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="' . $fieldName . '"; filename="' . $file['filename'] . '"' . $eol;
        $body .= 'Content-Type: ' . ($file['type'] ?? 'application/octet-stream') . $eol . $eol;
        $body .= $contents . $eol;
    }

    $body .= '--' . $boundary . '--' . $eol;

    $headers = [
        'User-Agent: KoraRuntimeAudit/1.0',
        'Content-Type: multipart/form-data; boundary=' . $boundary,
    ];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 20,
            'follow_location' => $maxRedirects > 0 ? 1 : 0,
            'max_redirects' => $maxRedirects,
        ],
    ]);

    $responseBody = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = $responseHeaders[0] ?? 'HTTP status unknown';

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => is_string($responseBody) ? $responseBody : '',
    ];
}

/**
 * @return list<string>
 */
function analyzeHtml(string $html): array
{
    $issues = [];
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $idCounts = [];
    foreach ($xpath->query('//*[@id]') as $node) {
        $id = $node->getAttribute('id');
        $idCounts[$id] = ($idCounts[$id] ?? 0) + 1;
    }
    foreach ($idCounts as $id => $count) {
        if ($count > 1) {
            $issues[] = "duplicate id: {$id} ({$count}x)";
        }
    }

    foreach (['aria-describedby', 'aria-labelledby', 'aria-controls'] as $attr) {
        foreach ($xpath->query('//*[@' . $attr . ']') as $node) {
            $targets = preg_split('/\s+/', trim($node->getAttribute($attr))) ?: [];
            foreach ($targets as $targetId) {
                if ($targetId !== '' && !isset($idCounts[$targetId])) {
                    $issues[] = "{$attr} missing target: {$targetId}";
                }
            }
        }
    }

    foreach ($xpath->query('//img') as $img) {
        if (!$img->hasAttribute('alt')) {
            $issues[] = 'img without alt';
        }
    }

    $fields = $xpath->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button") and not(@type="reset")] | //select | //textarea');
    foreach ($fields as $field) {
        $id = $field->getAttribute('id');
        if ($id === '') {
            continue;
        }
        $labels = $xpath->query('//label[@for="' . $id . '"]');
        if ($labels->length === 0 && !$field->hasAttribute('aria-label')) {
            $issues[] = "field without label: #{$id}";
        }
    }

    if (str_contains($html, 'Warning:') || str_contains($html, 'Fatal error:')) {
        $issues[] = 'php warning/error rendered in HTML';
    }

    if (str_contains($html, 'class="skip-link"') && !str_contains($html, '.skip-link')) {
        $issues[] = 'skip-link without CSS definition';
    }

    if (str_contains($html, 'class="sr-only"') && !str_contains($html, '.sr-only')) {
        $issues[] = 'sr-only helper without CSS definition';
    }

    $tabs = $xpath->query('//*[@role="tab"]');
    if ($tabs->length > 0) {
        $selectedCount = 0;
        foreach ($tabs as $tab) {
            if (!$tab->hasAttribute('tabindex')) {
                $issues[] = 'tab without tabindex';
            }
            if (strtolower($tab->nodeName) === 'button' && strtolower($tab->getAttribute('type')) !== 'button') {
                $issues[] = 'tab button missing type=button';
            }
            if ($tab->getAttribute('aria-selected') === 'true') {
                $selectedCount++;
            }
        }

        if ($selectedCount !== 1) {
            $issues[] = 'tablist must have exactly one selected tab';
        }

        $panels = $xpath->query('//*[@role="tabpanel"]');
        if ($panels->length !== $tabs->length) {
            $issues[] = 'tab/panel count mismatch';
        }
    }

    return array_values(array_unique($issues));
}

/**
 * @param array<int,string> $headers
 * @return list<string>
 */
function analyzeHeaders(array $headers): array
{
    $required = [
        'X-Content-Type-Options:',
        'X-Frame-Options:',
        'Referrer-Policy:',
        'Content-Security-Policy:',
    ];

    $issues = [];
    foreach ($required as $prefix) {
        $found = false;
        foreach ($headers as $header) {
            if (stripos($header, $prefix) === 0) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $issues[] = 'missing header: ' . rtrim($prefix, ':');
        }
    }
    return $issues;
}

/**
 * @return list<string>
 */
function analyzeUxHeuristics(string $html, string $label): array
{
    $issues = [];
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $skipLinks = $xpath->query('//a[@href="#obsah" and contains(concat(" ", normalize-space(@class), " "), " skip-link ")]');
    if ($skipLinks->length === 0) {
        $issues[] = 'missing skip link to #obsah';
    }

    $mainNodes = $xpath->query('//main[@id="obsah"]');
    if ($mainNodes->length === 0) {
        $issues[] = 'missing main#obsah landmark';
    } elseif ($mainNodes->length > 1) {
        $issues[] = 'multiple main#obsah landmarks';
    }

    $h1Nodes = $xpath->query('//h1');
    if ($h1Nodes->length === 0) {
        $issues[] = 'missing h1 heading';
    } elseif ($h1Nodes->length > 1) {
        $issues[] = 'multiple h1 headings';
    }

    foreach ($xpath->query('//h1 | //h2 | //h3') as $heading) {
        if (trim($heading->textContent) === '') {
            $issues[] = 'empty heading element';
            break;
        }
    }

    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " section-subtitle ")]') as $subtitle) {
        if (trim($subtitle->textContent) === '') {
            $issues[] = 'empty section subtitle';
            break;
        }
    }

    if ($label === 'home') {
        $homeSections = $xpath->query('//*[@data-home-section]');
        $homeFallback = $xpath->query('//*[@id="obsah-priprava"]');
        if ($homeSections->length === 0 && $homeFallback->length === 0) {
            $issues[] = 'home missing content sections and fallback state';
        }

        $ctaSections = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " home-section--cta ")]');
        if ($ctaSections->length > 1) {
            $issues[] = 'home renders multiple CTA sections';
        }
    }

    return array_values(array_unique($issues));
}

$failures = 0;

foreach ($pages as $page) {
    $result = fetchUrl($page['url'], $page['cookie'] ?? '');
    $issues = [];

    if (!str_contains($result['status'], '200')) {
        $issues[] = 'unexpected status: ' . $result['status'];
    }
    $issues = array_merge($issues, analyzeHtml($result['body']));
    $issues = array_merge($issues, analyzeUxHeuristics($result['body'], $page['label']));

    if ($page['label'] === 'public_login') {
        $issues = array_merge($issues, analyzeHeaders($result['headers']));

        $probe = fetchUrl($baseUrl . '/public_login.php?redirect=' . rawurlencode('https://example.com/phish'));
        if (preg_match('/name="redirect"\s+value="([^"]*)"/', $probe['body'], $matches) === 1
            && $matches[1] === 'https://example.com/phish') {
            $issues[] = 'external redirect leaked into login form';
        }
    }

    if ($page['label'] === 'home' && getSetting('visitor_counter_enabled', '0') === '1') {
        if (!str_contains($result['body'], 'class="visitor-counter__item"')) {
            $issues[] = 'visitor counter does not expose individual statistic items';
        }
        if (str_contains($result['body'], ' · Dnes:') || str_contains($result['body'], ' · Měsíc:') || str_contains($result['body'], ' · Celkem:')) {
            $issues[] = 'visitor counter still uses visual dot separators in footer output';
        }
    }

    if ($page['label'] === 'home') {
        foreach ([
            'Featured modul',
            'Další kroky',
            'Co chcete udělat dál?',
            'Rychlé akce pomohou návštěvníkovi dostat se k důležitému obsahu bez zbytečného hledání.',
        ] as $legacySnippet) {
            if (str_contains($result['body'], $legacySnippet)) {
                $issues[] = 'home still contains legacy copy: ' . $legacySnippet;
            }
        }
    }

    if ($page['label'] === 'admin_settings') {
        if (!str_contains($result['body'], 'name="site_profile"')) {
            $issues[] = 'site profile setting is missing';
        }
        if (!str_contains($result['body'], 'name="apply_site_profile"')) {
            $issues[] = 'site profile preset toggle is missing';
        }
        if (!str_contains($result['body'], 'value="custom"')) {
            $issues[] = 'custom site profile option is missing';
        }
    }

    if ($page['label'] === 'admin_themes') {
        if (!str_contains($result['body'], 'name="active_theme"')) {
            $issues[] = 'theme selector is missing';
        }
        if (!str_contains($result['body'], 'Kora Default')) {
            $issues[] = 'default theme metadata is missing';
        }
        if (!str_contains($result['body'], 'theme-card__preview')) {
            $issues[] = 'theme preview cards are missing';
        }
        if (!str_contains($result['body'], 'theme_settings[accent]')) {
            $issues[] = 'theme settings form is missing';
        }
        if (!str_contains($result['body'], 'theme_settings[home_layout]')) {
            $issues[] = 'home layout setting is missing';
        }
        if (!str_contains($result['body'], 'theme_settings[home_featured_module]')) {
            $issues[] = 'homepage featured module setting is missing';
        }
        if (!str_contains($result['body'], 'theme_settings[home_cta_visibility]')) {
            $issues[] = 'homepage CTA visibility setting is missing';
        }
        if (!str_contains($result['body'], 'name="theme_package"')) {
            $issues[] = 'theme package import form is missing';
        }
        if (!str_contains($result['body'], 'name="export_theme"')) {
            $issues[] = 'theme package export form is missing';
        }
    }

    echo '=== ' . $page['label'] . " ===\n";
    if ($issues === []) {
        echo "OK\n";
        continue;
    }

    $failures++;
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
}

if (!empty($cleanup['public_user_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['public_user_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_users WHERE id IN ({$placeholders})")->execute($cleanup['public_user_ids']);
}
if (!empty($cleanup['confirm_emails'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['confirm_emails']), '?'));
    $pdo->prepare("DELETE FROM cms_users WHERE email IN ({$placeholders})")->execute($cleanup['confirm_emails']);
}
if (!empty($cleanup['subscriber_emails'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['subscriber_emails']), '?'));
    $pdo->prepare("DELETE FROM cms_subscribers WHERE email IN ({$placeholders})")->execute($cleanup['subscriber_emails']);
}

$installProbe = fetchUrl($baseUrl . '/install.php', '', 0);
echo "=== install_guard ===\n";
if (!str_contains($installProbe['status'], '302')) {
    echo "- unexpected status: {$installProbe['status']}\n";
    $failures++;
} elseif (!in_array('Location: /admin/index.php', $installProbe['headers'], true)) {
    echo "- install.php does not redirect to /admin/index.php on installed site\n";
    $failures++;
} else {
    echo "OK\n";
}

$migrateProbe = fetchUrl($baseUrl . '/migrate.php', '', 0);
echo "=== migrate_guard ===\n";
if (!str_contains($migrateProbe['status'], '302')) {
    echo "- unexpected status: {$migrateProbe['status']}\n";
    $failures++;
} elseif (!in_array('Location: /admin/login.php', $migrateProbe['headers'], true)) {
    echo "- migrate.php does not redirect anonymous user to /admin/login.php\n";
    $failures++;
} else {
    echo "OK\n";
}

$migrateConfirm = fetchUrl($baseUrl . '/migrate.php', 'PHPSESSID=' . $auditSessionId, 0);
echo "=== migrate_confirm ===\n";
if (!str_contains($migrateConfirm['status'], '200')) {
    echo "- unexpected status: {$migrateConfirm['status']}\n";
    $failures++;
} elseif (!str_contains($migrateConfirm['body'], 'Spustit migraci') || str_contains($migrateConfirm['body'], 'Hotovo.')) {
    echo "- migrate.php GET does not show confirmation screen safely\n";
    $failures++;
} else {
    echo "OK\n";
}

$originalActiveTheme = getSetting('active_theme', defaultThemeName());
echo "=== theme_catalog ===\n";
try {
    $catalogIssues = [];
    foreach (availableThemes() as $themeKey) {
        saveSetting('active_theme', $themeKey);
        clearSettingsCache();

        $themeHomeProbe = fetchUrl($baseUrl . '/', '', 0);
        $themeAssetProbe = fetchUrl($baseUrl . '/themes/' . rawurlencode($themeKey) . '/assets/public.css', '', 0);
        $previewAssetUrl = themePreviewAssetUrl($themeKey);

        if (!str_contains($themeHomeProbe['status'], '200')) {
            $catalogIssues[] = "{$themeKey}: unexpected homepage status {$themeHomeProbe['status']}";
        }
        if (!str_contains($themeAssetProbe['status'], '200')) {
            $catalogIssues[] = "{$themeKey}: theme stylesheet is not reachable";
        }
        if (!str_contains($themeHomeProbe['body'], '/themes/' . rawurlencode($themeKey) . '/assets/public.css')) {
            $catalogIssues[] = "{$themeKey}: homepage does not reference its theme stylesheet";
        }
        if (!str_contains($themeHomeProbe['body'], 'theme-' . $themeKey)) {
            $catalogIssues[] = "{$themeKey}: homepage body class is missing";
        }
        if ($previewAssetUrl !== '') {
            $previewAssetProbe = fetchUrl($baseUrl . parse_url($previewAssetUrl, PHP_URL_PATH), '', 0);
            if (!str_contains($previewAssetProbe['status'], '200')) {
                $catalogIssues[] = "{$themeKey}: preview asset is not reachable";
            }
        }
    }

    if ($catalogIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($catalogIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
} finally {
    saveSetting('active_theme', $originalActiveTheme);
    clearSettingsCache();
}

echo "=== theme_live_preview ===\n";
try {
    session_id($auditSessionId);
    session_start();
    clearThemePreview();
    session_write_close();

    $availablePreviewThemes = availableThemes();
    $previewTheme = $availablePreviewThemes[0] ?? defaultThemeName();
    foreach ($availablePreviewThemes as $themeKey) {
        if ($themeKey !== $originalActiveTheme) {
            $previewTheme = $themeKey;
            break;
        }
    }

    $previewIssues = [];
    $previewStart = postUrl(
        $baseUrl . '/admin/themes.php',
        [
            'csrf_token' => $adminCsrfToken,
            'form_action' => 'preview_theme',
            'active_theme' => $previewTheme,
            'preview_redirect' => '/index.php',
        ],
        'PHPSESSID=' . $auditSessionId,
        0
    );
    if (!str_contains($previewStart['status'], '302')) {
        $previewIssues[] = 'preview start did not redirect safely';
    }

    $previewHome = fetchUrl($baseUrl . '/index.php', 'PHPSESSID=' . $auditSessionId, 0);
    if (!str_contains($previewHome['status'], '200')) {
        $previewIssues[] = 'preview homepage did not load';
    }
    if (!str_contains($previewHome['body'], '/themes/' . rawurlencode($previewTheme) . '/assets/public.css')) {
        $previewIssues[] = 'preview homepage did not switch theme stylesheet';
    }
    if (!str_contains($previewHome['body'], 'theme-preview-banner')) {
        $previewIssues[] = 'preview banner was not rendered';
    }

    $previewStop = postUrl(
        $baseUrl . '/admin/theme_preview.php',
        [
            'csrf_token' => $adminCsrfToken,
            'preview_action' => 'clear',
            'redirect_target' => '/index.php',
        ],
        'PHPSESSID=' . $auditSessionId,
        0
    );
    if (!str_contains($previewStop['status'], '302')) {
        $previewIssues[] = 'preview stop did not redirect safely';
    }

    $postPreviewHome = fetchUrl($baseUrl . '/index.php', 'PHPSESSID=' . $auditSessionId, 0);
    if (str_contains($postPreviewHome['body'], 'theme-preview-banner')) {
        $previewIssues[] = 'preview banner remained visible after stopping preview';
    }
    if (!str_contains($postPreviewHome['body'], '/themes/' . rawurlencode($originalActiveTheme) . '/assets/public.css')) {
        $previewIssues[] = 'homepage did not return to the active theme after preview';
    }

    if ($previewIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($previewIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
} finally {
    session_id($auditSessionId);
    session_start();
    clearThemePreview();
    session_write_close();
}

echo "=== theme_package_roundtrip ===\n";
$roundtripThemeKey = 'runtimeaudit-theme-' . bin2hex(random_bytes(4));
$roundtripZipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $roundtripThemeKey . '.zip';
try {
    $packageManifest = [
        'key' => $roundtripThemeKey,
        'package' => [
            'type' => themePortablePackageType(),
            'schema' => themePortablePackageSchema(),
            'mode' => themePortablePackageMode(),
            'base_theme' => defaultThemeName(),
        ],
        'name' => 'Runtime Audit Theme',
        'version' => '1.0.0',
        'author' => 'Runtime Audit',
        'description' => 'Dočasný portable ZIP balíček pro runtime audit.',
        'preview' => [
            'summary' => 'Kontrola bezpečného importu a exportu šablon.',
            'colors' => ['#edf3ff', '#225577', '#a35c1a'],
        ],
        'settings_defaults' => [
            'header_layout' => 'split',
            'palette_preset' => 'slate',
            'accent' => '#225577',
            'accent_strong' => '#163b5c',
            'warm' => '#a35c1a',
            'font_pairing' => 'modern',
            'container_width' => 'wide',
            'home_layout' => 'compact',
        ],
    ];
    $packageCss = '@import url("../../default/assets/public.css");' . "\n"
        . 'body.theme-' . $roundtripThemeKey . ' { --radius-lg: 1.15rem; }' . "\n"
        . '.theme-' . $roundtripThemeKey . ' .site-header__panel { border-color: rgba(var(--accent-rgb), 0.22); }' . "\n";

    if (!themeCreateZipArchive($roundtripZipPath, [
        $roundtripThemeKey . '/theme.json' => json_encode(
            $packageManifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . "\n",
        $roundtripThemeKey . '/assets/public.css' => $packageCss,
    ])) {
        throw new RuntimeException('Nepodařilo se vytvořit ZIP balíček pro runtime audit.');
    }

    $roundtripIssues = [];
    $importResult = postMultipartUrl(
        $baseUrl . '/admin/themes.php',
        [
            'csrf_token' => $adminCsrfToken,
            'form_action' => 'import_theme_package',
        ],
        [
            'theme_package' => [
                'path' => $roundtripZipPath,
                'filename' => basename($roundtripZipPath),
                'type' => 'application/zip',
            ],
        ],
        'PHPSESSID=' . $auditSessionId,
        0
    );
    if (!str_contains($importResult['status'], '200')) {
        $roundtripIssues[] = 'theme package import did not return 200';
    }
    if (!themeExists($roundtripThemeKey)) {
        $roundtripIssues[] = 'imported theme directory was not created';
    }

    $activateResult = postUrl(
        $baseUrl . '/admin/themes.php',
        [
            'csrf_token' => $adminCsrfToken,
            'form_action' => 'activate_theme',
            'active_theme' => $roundtripThemeKey,
            'preview_redirect' => '/index.php',
        ],
        'PHPSESSID=' . $auditSessionId,
        0
    );
    if (!str_contains($activateResult['status'], '200')) {
        $roundtripIssues[] = 'imported theme activation did not return 200';
    }

    clearSettingsCache();
    $roundtripHome = fetchUrl($baseUrl . '/', '', 0);
    if (!str_contains($roundtripHome['status'], '200')) {
        $roundtripIssues[] = 'imported theme homepage did not load';
    }
    if (!str_contains($roundtripHome['body'], '/themes/' . rawurlencode($roundtripThemeKey) . '/assets/public.css')) {
        $roundtripIssues[] = 'imported theme stylesheet was not referenced on homepage';
    }
    if (!str_contains($roundtripHome['body'], '--accent:#225577')) {
        $roundtripIssues[] = 'imported theme defaults were not rendered into CSS variables';
    }
    if (!str_contains($roundtripHome['body'], 'page-stack--home-compact')) {
        $roundtripIssues[] = 'imported theme homepage layout default was not applied';
    }

    $exportResult = postUrl(
        $baseUrl . '/admin/themes.php',
        [
            'csrf_token' => $adminCsrfToken,
            'form_action' => 'export_theme_package',
            'export_theme' => $roundtripThemeKey,
        ],
        'PHPSESSID=' . $auditSessionId,
        0
    );
    if (!str_contains($exportResult['status'], '200')) {
        $roundtripIssues[] = 'theme package export did not return 200';
    }
    $zipHeaderFound = false;
    foreach ($exportResult['headers'] as $header) {
        if (stripos($header, 'Content-Type: application/zip') === 0) {
            $zipHeaderFound = true;
            break;
        }
    }
    if (!$zipHeaderFound) {
        $roundtripIssues[] = 'theme package export did not return application/zip';
    }
    if (!str_starts_with($exportResult['body'], 'PK')) {
        $roundtripIssues[] = 'theme package export body is not a ZIP file';
    } else {
        $exportZipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $roundtripThemeKey . '-export.zip';
        file_put_contents($exportZipPath, $exportResult['body']);
        $exportZip = themeReadZipArchive($exportZipPath);
        if (!$exportZip['ok']) {
            $roundtripIssues[] = 'exported ZIP package could not be opened';
        } else {
            $exportedManifest = $exportZip['files'][$roundtripThemeKey . '/theme.json'] ?? null;
            $exportedCss = $exportZip['files'][$roundtripThemeKey . '/assets/public.css'] ?? null;
            if (!is_string($exportedManifest)) {
                $roundtripIssues[] = 'exported ZIP package is missing theme.json';
            }
            if (!is_string($exportedCss)) {
                $roundtripIssues[] = 'exported ZIP package is missing assets/public.css';
            }
        }
        @unlink($exportZipPath);
    }

    if ($roundtripIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($roundtripIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
} catch (Throwable $exception) {
    $failures++;
    echo '- ' . $exception->getMessage() . "\n";
} finally {
    saveSetting('active_theme', $originalActiveTheme);
    clearSettingsCache();
    if (themeExists($roundtripThemeKey)) {
        themeDeleteDirectory(themeDirectoryPath($roundtripThemeKey));
    }
    @unlink($roundtripZipPath);
}

$activeThemeSettingsKey = themeSettingStorageKey($originalActiveTheme);
$originalThemeSettings = getSetting($activeThemeSettingsKey, '');
$originalNewsletterModule = getSetting('module_newsletter', '0');
echo "=== theme_home_composer ===\n";
try {
    $newsModuleEnabled = isModuleEnabled('news');
    $blogModuleEnabled = isModuleEnabled('blog');
    $boardModuleEnabled = isModuleEnabled('board');
    $pollModuleEnabled = isModuleEnabled('polls');
    $newsletterModuleEnabled = isModuleEnabled('newsletter');

    $composerSettings = [
        'home_layout' => 'balanced',
        'home_hero_visibility' => 'hide',
        'home_featured_module' => $newsletterModuleEnabled
            ? 'newsletter'
            : ($blogModuleEnabled && $articleId
                ? 'blog'
                : ($newsModuleEnabled && $newsCount > 0 ? 'news' : 'none')),
        'home_primary_order' => 'blog_news',
        'home_utility_order' => $newsletterModuleEnabled
            ? 'newsletter_cta_board_poll'
            : 'cta_board_poll_newsletter',
        'home_news_visibility' => $newsModuleEnabled && $newsCount > 0 ? 'hide' : 'show',
        'home_blog_visibility' => $blogModuleEnabled && $articleId ? 'show' : 'hide',
        'home_board_visibility' => $boardModuleEnabled && $boardCount > 0 ? 'show' : 'hide',
        'home_poll_visibility' => $pollModuleEnabled && $activePollCount > 0 ? 'show' : 'hide',
        'home_newsletter_visibility' => $newsletterModuleEnabled ? 'show' : 'hide',
        'home_cta_visibility' => 'show',
    ];
    $expectedFeaturedModule = (string)$composerSettings['home_featured_module'];
    $expectedHomeBlogItems = $blogModuleEnabled ? min($articleCount, $homeBlogCountSetting) : 0;
    if ($expectedFeaturedModule === 'blog' && $expectedHomeBlogItems > 0) {
        $expectedHomeBlogItems--;
    }

    saveThemeSettings($composerSettings, $originalActiveTheme);
    clearSettingsCache();

    $composerProbe = fetchUrl($baseUrl . '/', '', 0);
    $composerIssues = [];
    if (!str_contains($composerProbe['status'], '200')) {
        $composerIssues[] = 'homepage composer probe did not load';
    }
    if (str_contains($composerProbe['body'], 'data-home-section="hero"')) {
        $composerIssues[] = 'hero block remained visible after hiding it';
    }
    if (!str_contains($composerProbe['body'], 'data-home-section="cta"')) {
        $composerIssues[] = 'CTA block was not rendered';
    }
    if (!str_contains($composerProbe['body'], 'data-home-section="featured"')) {
        $composerIssues[] = 'featured block was not rendered';
    }
    if ($newsModuleEnabled && $newsCount > 0 && str_contains($composerProbe['body'], 'data-home-section="news"')) {
        $composerIssues[] = 'news block remained visible after hiding it';
    }
    if ($expectedHomeBlogItems > 0 && !str_contains($composerProbe['body'], 'data-home-section="blog"')) {
        $composerIssues[] = 'blog block was not rendered';
    }

    $blogPos = strpos($composerProbe['body'], 'data-home-section="blog"');
    $boardPos = strpos($composerProbe['body'], 'data-home-section="board"');
    $ctaPos = strpos($composerProbe['body'], 'data-home-section="cta"');
    $newsletterPos = strpos($composerProbe['body'], 'data-home-section="newsletter"');

    if (
        $expectedHomeBlogItems > 0
        && $boardModuleEnabled
        && $boardCount > 0
        && $blogPos !== false
        && $boardPos !== false
        && $blogPos > $boardPos
    ) {
        $composerIssues[] = 'primary blog section was rendered after utility board section';
    }
    if ($newsletterModuleEnabled && $newsletterPos !== false && $ctaPos !== false && $newsletterPos > $ctaPos) {
        $composerIssues[] = 'newsletter utility block was rendered after CTA despite configured order';
    }

    saveSetting('module_newsletter', '0');
    clearSettingsCache();

    $adminComposerProbe = fetchUrl($baseUrl . '/admin/themes.php', 'PHPSESSID=' . $auditSessionId, 0);
    if (!str_contains($adminComposerProbe['status'], '200')) {
        $composerIssues[] = 'admin themes page did not load after disabling newsletter module';
    }
    if (str_contains($adminComposerProbe['body'], 'theme_settings[home_newsletter_visibility]')) {
        $composerIssues[] = 'newsletter visibility setting remained visible in admin after disabling module';
    }
    if (str_contains($adminComposerProbe['body'], '<option value="newsletter"')) {
        $composerIssues[] = 'newsletter featured option remained visible in admin after disabling module';
    }

    $newsletterDisabledSettings = $composerSettings;
    $newsletterDisabledSettings['home_featured_module'] = 'newsletter';
    $newsletterDisabledSettings['home_newsletter_visibility'] = 'show';
    $newsletterDisabledSettings['home_utility_order'] = 'newsletter_cta_board_poll';
    saveThemeSettings($newsletterDisabledSettings, $originalActiveTheme);
    clearSettingsCache();

    $newsletterDisabledProbe = fetchUrl($baseUrl . '/', '', 0);
    if (!str_contains($newsletterDisabledProbe['status'], '200')) {
        $composerIssues[] = 'homepage did not load after disabling newsletter module';
    }
    if (str_contains($newsletterDisabledProbe['body'], 'data-feature-source="newsletter"')) {
        $composerIssues[] = 'newsletter remained selected as featured source after disabling module';
    }
    if (str_contains($newsletterDisabledProbe['body'], 'data-home-section="newsletter"')) {
        $composerIssues[] = 'newsletter block remained rendered after disabling module';
    }
    if (!str_contains($newsletterDisabledProbe['body'], 'data-home-section="cta"')) {
        $composerIssues[] = 'CTA block disappeared after disabling newsletter module';
    }

    if ($composerIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($composerIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
} finally {
    saveSetting('module_newsletter', $originalNewsletterModule);
    saveSetting($activeThemeSettingsKey, $originalThemeSettings);
    clearSettingsCache();
}

echo "=== theme_fallback ===\n";
try {
    saveSetting('active_theme', 'runtimeaudit-missing-theme');
    clearSettingsCache();

    $fallbackProbe = fetchUrl($baseUrl . '/', '', 0);
    $fallbackIssues = [];
    if (!str_contains($fallbackProbe['status'], '200')) {
        $fallbackIssues[] = 'unexpected status: ' . $fallbackProbe['status'];
    }
    if (!str_contains($fallbackProbe['body'], '/themes/default/assets/public.css')) {
        $fallbackIssues[] = 'default theme stylesheet was not used during fallback';
    }
    if (!str_contains($fallbackProbe['body'], 'theme-default')) {
        $fallbackIssues[] = 'default theme body class was not rendered during fallback';
    }

    if ($fallbackIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($fallbackIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
} finally {
    saveSetting('active_theme', $originalActiveTheme);
    clearSettingsCache();
}

echo "=== theme_customization ===\n";
try {
    saveThemeSettings([
        'header_layout' => 'split',
        'palette_preset' => 'slate',
        'accent' => '#8b2d12',
        'accent_strong' => '#61200d',
        'warm' => '#7c4c14',
        'font_pairing' => 'modern',
        'container_width' => 'wide',
        'home_layout' => 'editorial',
    ], $originalActiveTheme);
    clearSettingsCache();

    $customizationProbe = fetchUrl($baseUrl . '/', '', 0);
    $customizationIssues = [];
    if (!str_contains($customizationProbe['status'], '200')) {
        $customizationIssues[] = 'unexpected status: ' . $customizationProbe['status'];
    }
    if (!str_contains($customizationProbe['body'], '--accent:#8b2d12')) {
        $customizationIssues[] = 'custom accent variable was not rendered';
    }
    if (!str_contains($customizationProbe['body'], '--container:80rem')) {
        $customizationIssues[] = 'custom container width was not rendered';
    }
    if (!str_contains($customizationProbe['body'], '--font-display:"Trebuchet MS", "Franklin Gothic Medium", sans-serif')) {
        $customizationIssues[] = 'custom typography preset was not rendered';
    }
    if (!str_contains($customizationProbe['body'], 'site-header--split')) {
        $customizationIssues[] = 'header layout variant was not rendered';
    }
    if (!str_contains($customizationProbe['body'], 'page-stack--home-editorial')) {
        $customizationIssues[] = 'homepage layout variant was not rendered';
    }

    if ($customizationIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($customizationIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
} finally {
    saveSetting($activeThemeSettingsKey, $originalThemeSettings);
    clearSettingsCache();
}

exit($failures > 0 ? 1 : 0);
