<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../db.php';

$baseUrl = rtrim($argv[1] ?? 'http://localhost', '/');
$pdo     = db_connect();

$articleId = $pdo->query(
    "SELECT id FROM cms_articles WHERE status = 'published' ORDER BY id LIMIT 1"
)->fetchColumn();
$podcastShowSlug = $pdo->query(
    "SELECT slug FROM cms_podcast_shows ORDER BY id LIMIT 1"
)->fetchColumn();
$resourceSlug = $pdo->query(
    "SELECT slug FROM cms_res_resources WHERE is_active = 1 ORDER BY id LIMIT 1"
)->fetchColumn();

$auditSessionId = 'runtimeauditadmin';
session_write_close();
session_id($auditSessionId);
session_start();
$_SESSION['cms_logged_in'] = true;
$_SESSION['cms_superadmin'] = true;
$_SESSION['cms_user_id'] = 1;
$_SESSION['cms_user_name'] = 'Runtime Audit';
$_SESSION['cms_user_role'] = 'admin';
session_write_close();

$pages = [
    ['label' => 'home', 'url' => $baseUrl . '/'],
    ['label' => 'search', 'url' => $baseUrl . '/search.php?q=test'],
    ['label' => 'public_login', 'url' => $baseUrl . '/public_login.php'],
    ['label' => 'register', 'url' => $baseUrl . '/register.php'],
    ['label' => 'reset_password', 'url' => $baseUrl . '/reset_password.php'],
    ['label' => 'subscribe', 'url' => $baseUrl . '/subscribe.php'],
    ['label' => 'contact', 'url' => $baseUrl . '/contact/index.php'],
    ['label' => 'chat', 'url' => $baseUrl . '/chat/index.php'],
    ['label' => 'admin_settings', 'url' => $baseUrl . '/admin/settings.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_statistics', 'url' => $baseUrl . '/admin/statistics.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
];

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
}

if ($articleId) {
    $pages[] = ['label' => 'blog_article', 'url' => $baseUrl . '/blog/article.php?id=' . urlencode((string)$articleId)];
}
if ($resourceSlug) {
    $pages[] = ['label' => 'reservations_resource', 'url' => $baseUrl . '/reservations/resource.php?slug=' . urlencode((string)$resourceSlug)];
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

$failures = 0;

foreach ($pages as $page) {
    $result = fetchUrl($page['url'], $page['cookie'] ?? '');
    $issues = [];

    if (!str_contains($result['status'], '200')) {
        $issues[] = 'unexpected status: ' . $result['status'];
    }
    $issues = array_merge($issues, analyzeHtml($result['body']));

    if ($page['label'] === 'public_login') {
        $issues = array_merge($issues, analyzeHeaders($result['headers']));

        $probe = fetchUrl($baseUrl . '/public_login.php?redirect=' . rawurlencode('https://example.com/phish'));
        if (preg_match('/name="redirect"\s+value="([^"]*)"/', $probe['body'], $matches) === 1
            && $matches[1] === 'https://example.com/phish') {
            $issues[] = 'external redirect leaked into login form';
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

exit($failures > 0 ? 1 : 0);
