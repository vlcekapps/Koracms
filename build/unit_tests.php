<?php

declare(strict_types=1);

/**
 * Kora CMS – Unit testy pro kritické funkce.
 * Spuštění: php build/unit_tests.php
 */

require_once __DIR__ . '/unit_test_bootstrap.php';

// ─── Assertion helpers ──────────────────────────────────────────────────────

$_TEST_PASS = 0;
$_TEST_FAIL = 0;
$_TEST_SECTION = '';

function test_section(string $name): void
{
    global $_TEST_SECTION;
    $_TEST_SECTION = $name;
    echo "\n--- {$name} ---\n";
}

function assert_equals(mixed $expected, mixed $actual, string $label): void
{
    global $_TEST_PASS, $_TEST_FAIL, $_TEST_SECTION;
    if ($expected === $actual) {
        $_TEST_PASS++;
    } else {
        $_TEST_FAIL++;
        $expStr = var_export($expected, true);
        $actStr = var_export($actual, true);
        echo "  FAIL [{$_TEST_SECTION}] {$label}\n";
        echo "    expected: {$expStr}\n";
        echo "    actual:   {$actStr}\n";
    }
}

function assert_true(mixed $value, string $label): void
{
    assert_equals(true, $value, $label);
}

function assert_false(mixed $value, string $label): void
{
    assert_equals(false, $value, $label);
}

function assert_contains(string $needle, string $haystack, string $label): void
{
    global $_TEST_PASS, $_TEST_FAIL, $_TEST_SECTION;
    if (str_contains($haystack, $needle)) {
        $_TEST_PASS++;
    } else {
        $_TEST_FAIL++;
        echo "  FAIL [{$_TEST_SECTION}] {$label}\n";
        echo "    expected to contain: {$needle}\n";
        echo "    in: " . mb_substr($haystack, 0, 200) . "\n";
    }
}

function assert_admin_route_module_requirement(
    string $scriptPath,
    string $expectedModule,
    string $expectedMessageFragment,
    string $label
): void {
    $requirement = adminRouteModuleRequirement($scriptPath);

    assert_true($requirement !== null, $label . ' requirement exists');
    assert_equals($expectedModule, $requirement['module'] ?? null, $label . ' module key');
    assert_contains($expectedMessageFragment, (string)($requirement['message'] ?? ''), $label . ' message');
}

function assert_admin_route_module_requirement_map_entry(
    string $file,
    string $expectedModule,
    string $expectedMessage
): void {
    $requirement = adminRouteModuleRequirement('/admin/' . $file);
    $label = 'admin route map ' . $expectedModule . '/' . $file;

    assert_true($requirement !== null, $label . ' requirement exists');
    assert_equals($expectedModule, $requirement['module'] ?? null, $label . ' module key');
    assert_equals($expectedMessage, $requirement['message'] ?? null, $label . ' message');
}

function test_session_string(string $key): string
{
    return isset($_SESSION[$key]) && is_string($_SESSION[$key]) ? $_SESSION[$key] : '';
}

function test_failure_count(): int
{
    global $_TEST_FAIL;

    return is_int($_TEST_FAIL) ? $_TEST_FAIL : 0;
}

// ═══════════════════════════════════════════════════════════════════════════
// TESTY
// ═══════════════════════════════════════════════════════════════════════════

// ─── 1. h() – XSS escaping ──────────────────────────────────────────────────

test_section('h()');

assert_equals('&lt;script&gt;alert(1)&lt;/script&gt;', h('<script>alert(1)</script>'), 'XSS script tag escaped');
assert_equals('&quot;onmouseover=&quot;alert(1)&quot;', h('"onmouseover="alert(1)"'), 'double quotes escaped');
assert_contains('&#039;', h("it's"), 'single quotes escaped');
assert_equals('', h(null), 'null returns empty string');
assert_equals('', h(''), 'empty string returns empty');
assert_equals('Příliš žluťoučký kůň', h('Příliš žluťoučký kůň'), 'UTF-8 passes through');
assert_equals('a &amp; b', h('a & b'), 'ampersand escaped');

// ─── 2. inputInt() ──────────────────────────────────────────────────────────

test_section('inputInt()');

$_GET['test_id'] = '42';
assert_equals(42, inputInt('get', 'test_id'), 'valid integer from GET');

$_GET['test_id'] = '0';
assert_equals(null, inputInt('get', 'test_id'), 'zero returns null (min_range=1)');

$_GET['test_id'] = '-5';
assert_equals(null, inputInt('get', 'test_id'), 'negative returns null');

$_GET['test_id'] = 'abc';
assert_equals(null, inputInt('get', 'test_id'), 'non-numeric returns null');

assert_equals(null, inputInt('get', 'nonexistent'), 'missing key returns null');

// ─── 3. internalRedirectTarget() ────────────────────────────────────────────

test_section('internalRedirectTarget()');

assert_equals('/fallback', internalRedirectTarget('', '/fallback'), 'empty string returns default');
assert_equals('/admin/index.php', internalRedirectTarget('/admin/index.php'), 'valid internal path accepted');
assert_equals('', internalRedirectTarget('https://evil.com/phish'), 'absolute external URL rejected');
assert_equals('', internalRedirectTarget('//evil.com/phish'), 'protocol-relative URL rejected');
assert_equals('', internalRedirectTarget('javascript:alert(1)'), 'javascript: scheme rejected');
assert_equals('', internalRedirectTarget('http://user:pass@evil.com'), 'URL with credentials rejected');
// Po stripnuti \r\n zbude validni interni cesta – to je ok, CRLF injekce je eliminovana
assert_equals('/adminSet-Cookie: evil=1', internalRedirectTarget("/admin\r\nSet-Cookie: evil=1"), 'CRLF stripped from redirect');
assert_equals('', internalRedirectTarget("/admin\x00evil"), 'null byte rejected');
assert_equals('/page.php?id=5#section', internalRedirectTarget('/page.php?id=5#section'), 'query and fragment preserved');
assert_equals('', internalRedirectTarget('admin/index.php'), 'relative path (no leading /) rejected');

test_section('safePublicReturnTarget()');

assert_equals('/clanek?foo=1', safePublicReturnTarget('/clanek?foo=1', '/subscribe.php'), 'safe public return keeps ordinary internal URL');
assert_equals('/subscribe.php', safePublicReturnTarget('https://evil.example/phish', '/subscribe.php'), 'safe public return rejects external URL');
assert_equals('/subscribe.php', safePublicReturnTarget('/reset_password.php?token=secret', '/subscribe.php'), 'safe public return rejects password reset token URL');
assert_equals('/subscribe.php', safePublicReturnTarget('/reservations/cancel_booking.php?token=0123456789abcdef0123456789abcdef', '/subscribe.php'), 'safe public return rejects reservation cancellation token URL');

test_section('storedRedirectTarget()');

assert_equals('/nova-stranka', storedRedirectTarget('/nova-stranka'), 'stored redirect accepts internal path');
assert_equals('https://example.com/nova-stranka', storedRedirectTarget('https://example.com/nova-stranka'), 'stored redirect accepts https URL');
assert_equals('http://example.com/nova-stranka', storedRedirectTarget('http://example.com/nova-stranka'), 'stored redirect accepts http URL');
assert_equals('', storedRedirectTarget('//example.com/nova-stranka'), 'stored redirect rejects protocol-relative URL');
assert_equals('', storedRedirectTarget('javascript:alert(1)'), 'stored redirect rejects javascript scheme');
assert_equals('', storedRedirectTarget('https://user:pass@example.com/private'), 'stored redirect rejects URL credentials');
assert_equals('', storedRedirectTarget("https://example.com\r\nSet-Cookie: evil=1"), 'stored redirect rejects CRLF injection');

test_section('adminLoginRedirectTarget()');

assert_equals('/admin/widgets.php', adminLoginRedirectTarget('/admin/widgets.php'), 'admin login redirect accepts admin path');
assert_equals('/admin/widgets.php?tab=footer', adminLoginRedirectTarget('/admin/widgets.php?tab=footer'), 'admin login redirect keeps query string');
assert_equals('/migrate.php', adminLoginRedirectTarget('/migrate.php'), 'admin login redirect accepts migrate confirmation');
assert_equals('/admin/index.php', adminLoginRedirectTarget('/page.php', '/admin/index.php'), 'admin login redirect rejects public internal path');
assert_equals('/admin/index.php', adminLoginRedirectTarget('/admin/login.php', '/admin/index.php'), 'admin login redirect rejects login loop');
assert_equals('/admin/index.php', adminLoginRedirectTarget('/admin/login_2fa.php', '/admin/index.php'), 'admin login redirect rejects 2FA loop');
assert_equals('/admin/index.php', adminLoginRedirectTarget('https://evil.example/phish', '/admin/index.php'), 'admin login redirect rejects external URL');
assert_equals('/admin/index.php', adminLoginRedirectTarget('//evil.example/phish', '/admin/index.php'), 'admin login redirect rejects protocol-relative URL');
assert_equals('/admin/index.php', adminLoginRedirectTarget("/admin/widgets.php\x00evil", '/admin/index.php'), 'admin login redirect rejects control characters');

test_section('adminRouteModuleRequirement()');

foreach (adminRouteModuleRequirements() as $moduleKey => $requirement) {
    foreach ($requirement['files'] as $file) {
        assert_admin_route_module_requirement_map_entry($file, $moduleKey, $requirement['message']);
    }
}

assert_equals('Podcasty', moduleAdminLabel('podcast'), 'module admin label keeps admin wording');
assert_equals(
    'Přístup odepřen. Modul Podcasty není povolen.',
    adminRouteModuleDisabledMessage('podcast'),
    'admin disabled module message uses admin label'
);
assert_equals(
    'Přístup odepřen. Modul neznamy není povolen.',
    adminRouteModuleDisabledMessage('neznamy'),
    'admin disabled module message falls back to module key'
);

assert_admin_route_module_requirement('/admin/form_save.php', 'forms', 'Formuláře', 'forms save route is guarded');
assert_admin_route_module_requirement('/admin/form_submission_action.php', 'forms', 'Formuláře', 'forms submission action route is guarded');
assert_admin_route_module_requirement('/admin/blog_save.php', 'blog', 'Blog', 'blog save route is guarded');
assert_admin_route_module_requirement('/admin/comment_bulk.php', 'blog', 'Blog', 'blog comment bulk route is guarded');
assert_admin_route_module_requirement('/ADMIN/NEWS_SAVE.PHP', 'news', 'Novinky', 'news route matching is case-insensitive');
assert_admin_route_module_requirement('C:\\laragon\\www\\admin\\gallery_photo_reorder.php', 'gallery', 'Galerie', 'windows path separators are normalized');
assert_admin_route_module_requirement('/admin/newsletter_send.php', 'newsletter', 'Newsletter', 'newsletter send route is guarded');
assert_admin_route_module_requirement('/admin/res_booking_save.php', 'reservations', 'Rezervace', 'reservation save route is guarded');
assert_equals(null, adminRouteModuleRequirement('/admin/index.php'), 'admin dashboard is not tied to a module');
assert_equals(null, adminRouteModuleRequirement('/forms/index.php'), 'public module path is ignored');

test_section('module public entrypoints');

$modulePublicEntryPoints = modulePublicEntryPoints();
$modulePublicPathMap = modulePublicPathModuleMap();
assert_true(knownModuleKey('blog'), 'known module key recognizes blog');
assert_false(knownModuleKey('unknown_module'), 'known module key rejects unknown module');
assert_equals('Blog', moduleDefinition('blog')['label'] ?? null, 'module definition returns blog metadata');
assert_equals(null, moduleDefinition('unknown_module'), 'module definition returns null for unknown module');
assert_true(in_array('/blog/article.php', $modulePublicEntryPoints['blog'] ?? [], true), 'blog article route is declared as a public module entrypoint');
assert_true(in_array('/blog/series.php', $modulePublicEntryPoints['blog'] ?? [], true), 'blog series route is declared as a public module entrypoint');
assert_true(in_array('/board/subscribe.php', $modulePublicEntryPoints['board'] ?? [], true), 'board subscription route is declared as a public module entrypoint');
assert_true(in_array('/podcast/audio.php', $modulePublicEntryPoints['podcast'] ?? [], true), 'podcast audio endpoint is declared as a public module entrypoint');
assert_true(in_array('/subscribe.php', $modulePublicEntryPoints['newsletter'] ?? [], true), 'newsletter subscribe route is declared as a public module entrypoint');
assert_true(in_array('/forms/index.php', $modulePublicEntryPoints['forms'] ?? [], true), 'forms public route is declared even without main navigation');
assert_equals([], $modulePublicEntryPoints['statistics'] ?? null, 'statistics has no standalone public entrypoint');
assert_equals('blog', $modulePublicPathMap['/blog/page.php'] ?? null, 'blog static page public path maps to blog module');
assert_equals('board', $modulePublicPathMap['/board/subscribe.php'] ?? null, 'board subscribe public path maps to board module');
assert_equals('newsletter', $modulePublicPathMap['/subscribe.php'] ?? null, 'newsletter subscribe public path maps to newsletter module');

test_section('module admin entrypoints');

$moduleAdminEntryPoints = moduleAdminEntryPoints();
$moduleAdminPathMap = moduleAdminPathModuleMap();
assert_true(in_array('/admin/blogs.php', $moduleAdminEntryPoints['blog'] ?? [], true), 'blog overview is declared as an admin module entrypoint');
assert_true(in_array('/admin/blog_series.php', $moduleAdminEntryPoints['blog'] ?? [], true), 'blog series admin is declared as an admin module entrypoint');
assert_true(in_array('/admin/res_resources.php', $moduleAdminEntryPoints['reservations'] ?? [], true), 'reservation resources are declared as admin module entrypoints');
assert_equals('blog', $moduleAdminPathMap['/admin/blogs.php'] ?? null, 'blog admin path maps to blog module');
assert_equals('statistics', $moduleAdminPathMap['/admin/statistics.php'] ?? null, 'statistics admin path maps to statistics module');

test_section('admin command helpers');

$commandArticleItem = adminCommandItem(
    'screen',
    'blog.articles',
    'Články blogu',
    'Správa článků, konceptů a publikace.',
    '/admin/blog.php',
    'blog',
    ''
);
$commandMediaItem = adminCommandItem(
    'screen',
    'media',
    'Knihovna médií',
    'Nahrávání a správa obrázků.',
    '/admin/media.php',
    '',
    ''
);
assert_true(adminCommandItemMatches($commandArticleItem, 'clanky publikace'), 'command search normalizes diacritics and multiple tokens');
assert_false(adminCommandItemMatches($commandArticleItem, 'rezervace'), 'command search rejects unrelated token');
assert_equals([$commandMediaItem], adminCommandFilterItems([$commandArticleItem, $commandMediaItem], 'knihovna', 5), 'command filter returns matching item');
assert_equals([$commandArticleItem], adminCommandFilterItems([$commandArticleItem, $commandMediaItem], '', 1), 'command filter respects limit');
assert_equals([$commandArticleItem, $commandMediaItem], adminCommandDedupeItems([$commandArticleItem, $commandArticleItem, $commandMediaItem]), 'command dedupe keeps first item per type/key');

test_section('module content reference types');

$contentReferenceTypes = moduleContentReferenceTypeLabels();
$contentReferenceTypeMap = contentReferenceTypeModuleMap();
assert_equals('Články blogu', $contentReferenceTypes['blog']['blog'] ?? null, 'blog picker label comes from manifest');
assert_equals('blog', $contentReferenceTypeMap['blog'] ?? null, 'blog picker type maps to blog module');
assert_equals('events', $contentReferenceTypeMap['event'] ?? null, 'event picker type maps to events module');
assert_equals('downloads', $contentReferenceTypeMap['download'] ?? null, 'download picker type maps to downloads module');
assert_equals('forms', $contentReferenceTypeMap['forms'] ?? null, 'forms picker type maps to forms module');
assert_false(isset($contentReferenceTypes['statistics']), 'statistics module has no content picker source');

test_section('module search result types');

$searchResultTypes = moduleSearchResultTypeLabels();
$searchResultTypeMap = searchResultTypeModuleMap();
assert_equals('Článek', $searchResultTypes['blog']['blog'] ?? null, 'blog search label comes from manifest');
assert_equals('blog', $searchResultTypeMap['blog'] ?? null, 'blog search result type maps to blog module');
assert_equals('gallery', $searchResultTypeMap['gallery_album'] ?? null, 'gallery album search result type maps to gallery module');
assert_equals('podcast', $searchResultTypeMap['podcast_episode'] ?? null, 'podcast episode search result type maps to podcast module');
assert_equals('reservations', $searchResultTypeMap['reservation_resource'] ?? null, 'reservation resource search result type maps to reservations module');
assert_false(isset($searchResultTypes['statistics']), 'statistics module has no public search result type');

test_section('module sitemap sections');

$sitemapSections = moduleSitemapSections();
$sitemapSectionMap = sitemapSectionModuleMap();
assert_equals('Blogy a články', $sitemapSections['blog']['blog'] ?? null, 'blog sitemap section label comes from manifest');
assert_equals('Kategorie blogu', $sitemapSections['blog']['blog_categories'] ?? null, 'blog category sitemap section label comes from manifest');
assert_equals('Štítky blogu', $sitemapSections['blog']['blog_tags'] ?? null, 'blog tag sitemap section label comes from manifest');
assert_equals('blog', $sitemapSectionMap['blog'] ?? null, 'blog sitemap section maps to blog module');
assert_equals('blog', $sitemapSectionMap['blog_categories'] ?? null, 'blog category sitemap section maps to blog module');
assert_equals('blog', $sitemapSectionMap['blog_tags'] ?? null, 'blog tag sitemap section maps to blog module');
assert_equals('board', $sitemapSectionMap['board_categories'] ?? null, 'board category sitemap section maps to board module');
assert_equals('gallery', $sitemapSectionMap['gallery_photos'] ?? null, 'gallery photos sitemap section maps to gallery module');
assert_equals('podcast', $sitemapSectionMap['podcast_episodes'] ?? null, 'podcast episodes sitemap section maps to podcast module');
assert_equals('forms', $sitemapSectionMap['forms'] ?? null, 'forms sitemap section maps to forms module');
assert_false(isset($sitemapSections['statistics']), 'statistics module has no sitemap section');

test_section('blog taxonomy landing links');

assert_equals('linuxovy-koutek', blogCategorySlug('Linuxový koutek'), 'category slug normalizes Czech diacritics');
assert_equals('nvda-tip', blogTagSlug('NVDA tip'), 'tag slug uses shared blog taxonomy normalization');
assert_equals('technicka-podpora', contactTopicSlug('Technická podpora'), 'contact topic slug normalizes Czech diacritics');
assert_equals(
    'KNT-20260401-AB12',
    contactReferenceCode(new DateTimeImmutable('2026-04-01 09:23:56'), 'ab-12'),
    'contact reference code uses date and normalized suffix'
);
assert_equals(
    'tema@example.test',
    contactNotificationRecipient('global@example.test', ['recipient_email' => 'tema@example.test']),
    'contact topic recipient overrides global contact email'
);
assert_equals(
    'global@example.test',
    contactNotificationRecipient('global@example.test', ['recipient_email' => 'neplatny-email']),
    'contact topic recipient falls back to global contact email'
);
assert_equals('', contactNotificationRecipient('neplatny-email', ['recipient_email' => '']), 'invalid contact email fallback stays empty');
assert_equals(
    'linuxovy-koutek-3',
    nextBlogTaxonomySlug('Linuxový koutek', ['linuxovy-koutek', 'linuxovy-koutek-2'], 'kategorie'),
    'taxonomy slug helper deduplicates within existing slugs'
);
assert_equals(
    'kategorie',
    nextBlogTaxonomySlug('', [], 'Kategorie'),
    'taxonomy slug helper uses fallback for empty input'
);
assert_equals(
    '/snd/kategorie/linuxovy-koutek',
    blogCategoryRequestPath(['slug' => 'snd'], ['id' => 12, 'name' => 'Linuxový koutek', 'slug' => 'linuxovy-koutek']),
    'category landing request path uses clean blog URL'
);
assert_equals(
    '/snd/stitky/nvda-tip?q=screenreader',
    blogTagRequestPath(['slug' => 'snd'], ['id' => 8, 'name' => 'NVDA tip', 'slug' => 'nvda-tip'], ['q' => 'screenreader']),
    'tag landing request path keeps compatible query filters'
);
assert_equals(
    '/blog/index.php?kat=5',
    blogCategoryRequestPath(['slug' => 'blog'], ['id' => 5, 'name' => 'Starší kategorie', 'slug' => '']),
    'category landing path falls back to legacy query filter without slug'
);
assert_equals(
    '/snd/jak-cist-web',
    articlePublicRequestPath(['id' => 7, 'slug' => 'jak-cist-web', 'blog_id' => 3, 'blog_slug' => 'snd']),
    'article canonical request path uses blog slug and article slug'
);
assert_equals(
    '/snd/serie/prvni-serie',
    str_replace(BASE_URL, '', blogSeriesPath(['slug' => 'snd'], ['id' => 4, 'slug' => 'prvni-serie'])),
    'series canonical path uses clean blog route'
);
assert_true(
    blogArticleIsPubliclyReachable(['slug' => 'verejny-clanek', 'status' => 'published', 'deleted_at' => null, 'publish_at' => null, 'unpublish_at' => null]),
    'published article with slug is publicly reachable'
);
assert_false(
    blogArticleIsPubliclyReachable(['slug' => 'koncept', 'status' => 'draft', 'deleted_at' => null, 'publish_at' => null, 'unpublish_at' => null]),
    'draft article is not publicly reachable'
);
assert_false(
    blogArticleIsPubliclyReachable(['slug' => 'budouci-clanek', 'status' => 'published', 'deleted_at' => null, 'publish_at' => date('Y-m-d H:i:s', time() + 3600), 'unpublish_at' => null]),
    'future article is not publicly reachable yet'
);
assert_false(
    blogArticleIsPubliclyReachable(['slug' => 'stazeny-clanek', 'status' => 'published', 'deleted_at' => null, 'publish_at' => null, 'unpublish_at' => date('Y-m-d H:i:s', time() - 3600)]),
    'unpublished article is no longer publicly reachable'
);
assert_false(
    blogArticleIsPubliclyReachable(['slug' => '', 'status' => 'published', 'deleted_at' => null, 'publish_at' => null, 'unpublish_at' => null]),
    'article without slug is not a canonical public redirect target'
);
assert_equals('dulezita-oznameni', boardCategorySlug('Důležitá oznámení'), 'board category slug normalizes Czech diacritics');
assert_equals('/board/kategorie/dulezita-oznameni', boardCategoryRequestPath(['id' => 4, 'slug' => 'dulezita-oznameni']), 'board category clean request path uses slug');
assert_equals('/board/index.php?kat=4', boardCategoryRequestPath(['id' => 4, 'slug' => '']), 'board category request path falls back to legacy filter');
assert_equals([2, 5], normalizeBoardSubscriberCategoryIds(['5', 2, 2, 99, 0, -1], [2, 5, 8]), 'board subscriber category IDs are valid, positive and deduplicated');
assert_true(
    boardIsPubliclyReachable([
        'slug' => 'verejna-polozka',
        'status' => 'published',
        'is_published' => 1,
        'deleted_at' => null,
        'posted_date' => date('Y-m-d'),
        'publish_at' => null,
        'unpublish_at' => null,
    ]),
    'published board item is publicly reachable'
);
assert_false(
    boardIsPubliclyReachable([
        'slug' => 'budouci-polozka',
        'status' => 'published',
        'is_published' => 1,
        'deleted_at' => null,
        'posted_date' => date('Y-m-d', time() + 86400),
        'publish_at' => null,
        'unpublish_at' => null,
    ]),
    'future board item is not publicly reachable'
);
assert_true(
    shouldSendBoardPublicationNotice(
        ['slug' => 'polozka', 'status' => 'draft', 'is_published' => 0, 'deleted_at' => null, 'posted_date' => date('Y-m-d')],
        ['slug' => 'polozka', 'status' => 'published', 'is_published' => 1, 'deleted_at' => null, 'posted_date' => date('Y-m-d')]
    ),
    'board notice is sent when item becomes public'
);
assert_false(
    shouldSendBoardPublicationNotice(
        ['slug' => 'polozka', 'status' => 'published', 'is_published' => 1, 'deleted_at' => null, 'posted_date' => date('Y-m-d')],
        ['slug' => 'polozka', 'status' => 'published', 'is_published' => 1, 'deleted_at' => null, 'posted_date' => date('Y-m-d')]
    ),
    'board notice is not sent for ordinary edit of public item'
);

test_section('navigation links');

assert_equals('/kontakt', navigationLinkUrl('/kontakt'), 'navigation link accepts internal path');
assert_equals('https://example.com', navigationLinkUrl('https://example.com'), 'navigation link accepts https URL');
assert_equals('', navigationLinkUrl('javascript:alert(1)'), 'navigation link rejects unsafe scheme');
assert_equals(
    'Externí – otevře se v novém okně',
    newWindowLinkLabel('Externí'),
    'newWindowLinkLabel announces a new window'
);
assert_equals(
    'Externí – Přístupný popis – otevře se v novém okně',
    newWindowLinkLabel('Externí', 'Přístupný popis'),
    'newWindowLinkLabel keeps supplemental accessible text'
);
assert_equals(
    'Odkaz – otevře se v novém okně',
    newWindowLinkLabel(''),
    'newWindowLinkLabel has a safe fallback label'
);
assert_equals(
    '<span class="sr-only"> – otevře se v novém okně</span>',
    newWindowLinkSrOnlySuffix(),
    'newWindowLinkSrOnlySuffix renders hidden DOM text for visible links'
);
assert_contains(
    'target="_blank"',
    navigationLinkAnchorAttributes(['url' => 'https://example.com', 'title' => 'Externí', 'target_blank' => 1]),
    'navigation link can open in new window'
);
assert_contains(
    'rel="noopener noreferrer"',
    navigationLinkAnchorAttributes(['url' => 'https://example.com', 'title' => 'Externí', 'target_blank' => 1]),
    'navigation link new window uses noopener noreferrer'
);
assert_equals(
    false,
    str_contains(
        navigationLinkAnchorAttributes(['url' => 'https://example.com', 'title' => 'Externí', 'alt_text' => 'Přístupný popis']),
        'aria-label'
    ),
    'navigation link attributes do not override visible text with aria-label'
);
assert_equals(
    false,
    str_contains(
        navigationLinkAnchorAttributes(['url' => 'https://example.com', 'title' => 'Externí', 'target_blank' => 1]),
        'aria-label'
    ),
    'navigation link new window announcement is rendered as hidden text'
);
assert_equals(
    '<span class="sr-only"> – Přístupný popis</span>',
    navigationLinkAccessibleSuffix(['alt_text' => 'Přístupný popis']),
    'navigation link optional accessible description is rendered as hidden text'
);
assert_equals(
    '<span class="sr-only"> – Přístupný popis</span><span class="sr-only"> – otevře se v novém okně</span>',
    navigationLinkAccessibleSuffix(['alt_text' => 'Přístupný popis', 'target_blank' => 1]),
    'navigation link hidden suffix combines description and new-window announcement'
);

// ─── 4. Rate-limit keys ─────────────────────────────────────────────────────

test_section('external URL normalizers');

assert_equals('https://example.com/path', normalizeHttpExternalUrl('example.com/path'), 'external URL helper prepends https');
assert_equals('http://example.com/path', normalizeHttpExternalUrl('http://example.com/path'), 'external URL helper accepts http');
assert_equals('https://example.com/path', normalizeHttpExternalUrl(' https://example.com/path '), 'external URL helper trims whitespace');
assert_equals('', normalizeHttpExternalUrl('/kontakt'), 'external URL helper rejects internal path');
assert_equals('', normalizeHttpExternalUrl('//example.com/path'), 'external URL helper rejects protocol-relative URL');
assert_equals('', normalizeHttpExternalUrl('javascript:alert(1)'), 'external URL helper rejects unsafe scheme');
assert_equals('', normalizeHttpExternalUrl('https://user:pass@example.com/path'), 'external URL helper rejects credentials');
assert_equals('', normalizeHttpExternalUrl("https://example.com\npath"), 'external URL helper rejects control characters');
assert_equals('', normalizeHttpExternalUrl('example.com/path', false), 'external URL helper can require explicit scheme');
assert_equals('https://podcast.example/show', normalizePodcastWebsiteUrl('podcast.example/show'), 'podcast website URL uses shared external helper');
assert_equals('', normalizePodcastWebsiteUrl('https://user:pass@example.com/show'), 'podcast website URL rejects credentials');
assert_equals('https://downloads.example/item', normalizeDownloadExternalUrl('downloads.example/item'), 'download external URL uses shared external helper');
assert_equals('', normalizeDownloadExternalUrl('/downloads/local'), 'download external URL rejects internal path');
assert_equals('https://places.example', normalizePlaceUrl('places.example'), 'place URL uses shared external helper');
assert_equals('', normalizePlaceUrl('//places.example'), 'place URL rejects protocol-relative URL');
assert_equals('https://social.example/profile', normalizeWidgetExternalUrl('social.example/profile'), 'widget external URL uses shared external helper');
assert_equals('', normalizeWidgetExternalUrl('//social.example/profile'), 'widget external URL rejects protocol-relative URL');
assert_equals('', normalizeWidgetExternalUrl('https://user:pass@social.example/profile'), 'widget external URL rejects credentials');
assert_equals('https://author.example/profile', normalizeAuthorWebsite('author.example/profile'), 'author website URL uses shared external helper');
assert_equals('', normalizeAuthorWebsite('/author/local'), 'author website URL rejects internal path');
assert_equals('https://example.com/problem', normalizePublicFormUrlFieldValue('https://example.com/problem'), 'public form URL field accepts explicit https URL');
assert_equals('', normalizePublicFormUrlFieldValue('example.com/problem'), 'public form URL field requires explicit scheme');
assert_equals('', normalizePublicFormUrlFieldValue('ftp://example.com/file'), 'public form URL field rejects non-http scheme');
assert_equals('', normalizePublicFormUrlFieldValue('https://user:pass@example.com/problem'), 'public form URL field rejects credentials');
assert_equals('https://example.com/hook', normalizeFormWebhookUrl('https://example.com/hook'), 'webhook URL accepts explicit public https URL');
assert_equals('', normalizeFormWebhookUrl('example.com/hook'), 'webhook URL requires explicit scheme');
assert_equals('', normalizeFormWebhookUrl('http://example.com/hook'), 'webhook URL rejects non-https scheme');
assert_equals('', normalizeFormWebhookUrl('https://user:pass@example.com/hook'), 'webhook URL rejects credentials');
assert_equals('', normalizeFormWebhookUrl('https://localhost/hook'), 'webhook URL rejects localhost host');

test_section('stats referrer normalizer');

$_SERVER['HTTP_HOST'] = 'pvlcek.cz';
assert_equals(
    'https://obchod.pvlcek.cz/produkt/testovaciprodukt',
    statsNormalizeReferrer('https://obchod.pvlcek.cz/produkt/testovaciprodukt?token=secret#detail'),
    'stats referrer keeps external host and path but drops query and fragment'
);
assert_equals(
    'obchod.pvlcek.cz/produkt/testovaciprodukt',
    statsReferrerDisplayLabel('https://obchod.pvlcek.cz/produkt/testovaciprodukt?token=secret#detail'),
    'stats referrer display label omits scheme'
);
assert_equals('', statsNormalizeReferrer('https://pvlcek.cz/snd'), 'stats referrer rejects exact own host');
assert_equals('', statsNormalizeReferrer('https://www.pvlcek.cz/snd'), 'stats referrer rejects www variant of own host');
assert_equals('', statsNormalizeReferrer('javascript:alert(1)'), 'stats referrer rejects unsafe scheme');
assert_equals('', statsNormalizeReferrer('https://user:pass@example.com/private'), 'stats referrer rejects URL credentials');
unset($_SERVER['HTTP_HOST']);

test_section('normalizeHttpMethods()');

assert_equals(['GET', 'POST'], normalizeHttpMethods(['get', ' POST ', 'GET']), 'method normalizer trims, uppercases and deduplicates');
assert_equals(['PATCH'], normalizeHttpMethods(['', 'get-post', '123', ' PATCH ']), 'method normalizer ignores invalid tokens');
assert_equals(['GET'], normalizeHttpMethods([]), 'method normalizer falls back to GET');
assert_equals(['HEAD', 'OPTIONS'], normalizeHttpMethods(['head', 'options']), 'method normalizer keeps uncommon valid methods');

test_section('rateLimitKey()');

assert_equals(hash('sha256', '127.0.0.1|login'), rateLimitKey('login', '127.0.0.1'), 'IP rate-limit key format preserved');
assert_equals(64, strlen(rateLimitKey('login_email', 'subject:admin@example.test')), 'rate-limit key is fixed-length hash');
assert_false(
    rateLimitKey('login', '127.0.0.1') === rateLimitKey('login_email', 'subject:admin@example.test'),
    'IP and subject rate-limit keys do not collide'
);
assert_equals(300, rateLimitRetryAfter(300), 'rate-limit Retry-After keeps the configured window');
assert_equals(1, rateLimitRetryAfter(0), 'rate-limit Retry-After never drops below one second');

// ─── 5. Request ID and structured logs ──────────────────────────────────────

test_section('Request ID and structured logs');

$GLOBALS['_KORA_REQUEST_ID'] = null;
$_SERVER['HTTP_X_REQUEST_ID'] = 'request-1234';
assert_equals('request-1234', koraRequestId(), 'valid incoming request ID accepted');
assert_equals('request-1234', koraRequestId(), 'request ID is stable within one request');

$GLOBALS['_KORA_REQUEST_ID'] = null;
$_SERVER['HTTP_X_REQUEST_ID'] = '../bad';
$generatedRequestId = koraRequestId();
assert_true(preg_match('/\A[a-f0-9]{24}\z/', $generatedRequestId) === 1, 'invalid incoming request ID is replaced');

assert_equals('[array:2]', koraLogValue(['a' => 1, 'b' => 2]), 'array log context summarized');
assert_contains('RuntimeException: Testovací chyba', (string)koraLogValue(new RuntimeException('Testovací chyba')), 'throwable log context summarized');

$GLOBALS['_KORA_REQUEST_ID'] = 'json-test-id';
$jsonResponsePayload = json_decode(jsonResponsePayload(['status' => 'ok', 'message' => 'Příliš žluťoučký kůň']), true);
assert_equals('ok', $jsonResponsePayload['status'] ?? null, 'JSON response payload keeps status');
assert_equals('Příliš žluťoučký kůň', $jsonResponsePayload['message'] ?? null, 'JSON response payload preserves UTF-8 text');
assert_equals('json-test-id', $jsonResponsePayload['request_id'] ?? null, 'JSON response payload adds request ID');

$jsonResponsePayloadWithoutRequestId = json_decode(jsonResponsePayload(['status' => 'ok'], false), true);
assert_false(array_key_exists('request_id', is_array($jsonResponsePayloadWithoutRequestId) ? $jsonResponsePayloadWithoutRequestId : []), 'JSON response payload can omit request ID');

unset($_SERVER['HTTP_X_REQUEST_ID']);
$GLOBALS['_KORA_REQUEST_ID'] = null;

// ─── 6. SQL backup identifier helpers ───────────────────────────────────────

test_section('SQL backup identifiers');

assert_true(koraSqlIdentifierAllowed('cms_articles'), 'CMS table identifier allowed');
assert_true(koraSqlIdentifierAllowed('cms_2026_backup'), 'alphanumeric underscore identifier allowed');
assert_false(koraSqlIdentifierAllowed('cms-users'), 'hyphenated identifier rejected');
assert_false(koraSqlIdentifierAllowed('cms_users;DROP'), 'SQL fragment identifier rejected');
assert_equals('`cms_articles`', koraSqlQuoteIdentifier('cms_articles'), 'identifier quoted with backticks');
assert_equals('`id`, `title`', koraSqlQuoteIdentifierList(['id', 'title']), 'identifier list quoted');

if (!class_exists('KoraBackupQuoteTestPdo')) {
    class KoraBackupQuoteTestPdo extends PDO
    {
        public function __construct()
        {
        }

        public function quote(string $string, int $type = PDO::PARAM_STR): string|false
        {
            return "'" . str_replace("'", "''", $string) . "'";
        }
    }
}

$backupQuotePdo = new KoraBackupQuoteTestPdo();
assert_equals('NULL', koraSqlQuoteValue($backupQuotePdo, null), 'backup SQL NULL value preserved');
assert_equals("'O''Brien'", koraSqlQuoteValue($backupQuotePdo, "O'Brien"), 'backup SQL string value quoted through PDO');

$invalidIdentifierRejected = false;
try {
    koraSqlQuoteIdentifier('cms_users`evil');
} catch (InvalidArgumentException $e) {
    $invalidIdentifierRejected = true;
}
assert_true($invalidIdentifierRejected, 'invalid quoted identifier throws');

// ─── 7. Upload helpers ─────────────────────────────────────────────────────

test_section('Upload helpers');

assert_false(koraUploadHasFile([]), 'empty upload field has no file');
assert_false(koraUploadHasFile(['name' => '', 'error' => UPLOAD_ERR_NO_FILE]), 'UPLOAD_ERR_NO_FILE has no file');
assert_true(koraUploadHasFile(['name' => 'soubor.txt', 'error' => UPLOAD_ERR_OK]), 'UPLOAD_ERR_OK with name has file');
assert_equals('jpg', koraUploadSanitizeExtension('fotka.JPG'), 'safe extension normalized');
assert_equals('', koraUploadSanitizeExtension('soubor.bad-ext'), 'unsafe extension rejected');
assert_true(koraUploadMimeIsSvg('image/svg+xml'), 'SVG MIME detected');

$uploadTmp = tempnam(sys_get_temp_dir(), 'kora-upload-test-');
file_put_contents($uploadTmp, 'hello');
$uploadInspection = koraInspectUploadedFile(
    [
        'name' => 'Dokument.TXT',
        'tmp_name' => $uploadTmp,
        'size' => filesize($uploadTmp),
        'error' => UPLOAD_ERR_OK,
    ],
    [
        'require_uploaded_file' => false,
        'allowed_mime_map' => ['text/plain' => 'txt'],
        'max_bytes' => 1024,
    ]
);
assert_true((bool)($uploadInspection['ok'] ?? false), 'valid local test upload accepted when upload check disabled');
assert_equals('Dokument.TXT', $uploadInspection['original_name'] ?? '', 'original upload name preserved');
assert_equals('txt', $uploadInspection['extension'] ?? '', 'extension comes from MIME allowlist');

$tooLargeUpload = koraInspectUploadedFile(
    [
        'name' => 'Dokument.TXT',
        'tmp_name' => $uploadTmp,
        'size' => filesize($uploadTmp),
        'error' => UPLOAD_ERR_OK,
    ],
    [
        'require_uploaded_file' => false,
        'max_bytes' => 2,
        'too_large_error' => 'too-large',
    ]
);
assert_false((bool)($tooLargeUpload['ok'] ?? false), 'too large upload rejected');
assert_equals('too-large', $tooLargeUpload['error'] ?? '', 'too large upload returns configured error');

$invalidTargetUpload = koraStoreInspectedUpload($uploadInspection, sys_get_temp_dir(), '../evil.txt');
assert_false((bool)($invalidTargetUpload['ok'] ?? false), 'unsafe target filename rejected before move');
@unlink($uploadTmp);

// ─── 7. slugify() ───────────────────────────────────────────────────────────

test_section('slugify()');

assert_equals('hello-world', slugify('Hello World'), 'basic ASCII lowercase');
assert_equals('prilis-zlutoucky-kun', slugify('Příliš žluťoučký kůň'), 'Czech diacritics transliterated');
assert_equals('foo-bar-baz', slugify('foo@bar!baz'), 'special chars become hyphens');
assert_equals('hello', slugify('---hello---'), 'leading/trailing hyphens stripped');
assert_equals('a-b-c', slugify('a   b   c'), 'multiple spaces become single hyphen');
assert_equals('', slugify(''), 'empty string');
assert_equals('article-42', slugify('Article 42'), 'numbers preserved');
assert_equals('cestina', slugify('ČEŠTINA'), 'uppercase Czech chars');

// Rozšířená diakritika (nové z quality review)
assert_equals('laska', slugify('Láska'), 'Slovak l with caron');
assert_equals('strasse', slugify('Straße'), 'German eszett');
assert_equals('aerger', slugify('Ärger'), 'German umlaut ae');

// ─── 6. formatCzechDate() ───────────────────────────────────────────────────

test_section('formatCzechDate()');

assert_equals('15. ledna 2024, 10:30', formatCzechDate('2024-01-15 10:30:00'), 'standard datetime January');
assert_equals('25. prosince 2024, 00:00', formatCzechDate('2024-12-25 00:00:00'), 'December date');
assert_equals('', formatCzechDate(''), 'empty string returns empty (bugfix)');
assert_equals('not-a-date', formatCzechDate('not-a-date'), 'invalid date returns escaped input');

// ─── 7. readingTime() ───────────────────────────────────────────────────────

test_section('readingTime()');

assert_equals(1, readingTime(''), 'empty text = 1 min');
assert_equals(1, readingTime('hello'), 'single word = 1 min');
assert_equals(1, readingTime(str_repeat('word ', 200)), '200 words = 1 min');
assert_equals(2, readingTime(str_repeat('word ', 400)), '400 words = 2 min');
assert_equals(
    'přibližná doba čtení 1 min, přečteno 3 krát',
    articleReadingMeta('krátký článek', 3),
    'articleReadingMeta uses descriptive public copy'
);
assert_equals('1 novinka', newsCountLabel(1), 'news count singular');
assert_equals('3 novinky', newsCountLabel(3), 'news count plural 2-4');
assert_equals('12 novinek', newsCountLabel(12), 'news count plural teen');
assert_equals('25 novinek', newsCountLabel(25), 'news count plural many');
assert_equals('vse', normalizeAuthorContentType(''), 'empty author content filter falls back to all');
assert_equals('clanky', normalizeAuthorContentType('Články'), 'author content filter accepts Czech label');
assert_equals('novinky', normalizeAuthorContentType('novinky'), 'author content filter accepts news');
assert_equals('vse', normalizeAuthorContentType('externi'), 'invalid author content filter falls back to all');
assert_equals([12, 9, 7], normalizeRelatedArticleIds(['12', 9, 9, 0, -1, '7'], 0), 'related article IDs are positive and deduplicated');
assert_equals([5, 8], normalizeRelatedArticleIds([5, 10, '8', 10], 10), 'related article IDs exclude the current article');
assert_equals('serie-o-zpivajicich-hodinach', blogSeriesSlug('Série o zpívajících hodinách'), 'blog series slug normalizes Czech titles');
assert_equals([12, 9, 7], normalizeBlogSeriesIds(['12', 9, 9, 0, -1, '7']), 'blog series IDs are positive and deduplicated');

$emptyToc = buildBlogArticleTableOfContents('<p>Krátký článek bez nadpisu.</p>');
assert_equals([], $emptyToc['items'], 'article TOC stays empty without headings');
assert_equals('<p>Krátký článek bez nadpisu.</p>', $emptyToc['html'], 'article TOC keeps content without headings unchanged');

$singleToc = buildBlogArticleTableOfContents('<h2>První část</h2><p>Text.</p>');
assert_equals([['level' => 2, 'id' => 'prvni-cast', 'title' => 'První část']], $singleToc['items'], 'article TOC extracts a single h2 heading');
assert_contains('<h2 id="prvni-cast">První část</h2>', $singleToc['html'], 'article TOC adds an id to h2');

$duplicateToc = buildBlogArticleTableOfContents('<h2>První část</h2><h3>První část</h3>');
assert_equals('prvni-cast-2', $duplicateToc['items'][1]['id'] ?? '', 'article TOC deduplicates generated heading ids');
assert_contains('<h3 id="prvni-cast-2">První část</h3>', $duplicateToc['html'], 'article TOC writes deduplicated h3 id');

$manualIdToc = buildBlogArticleTableOfContents('<h2 id="vlastni-kotva">Ruční nadpis</h2><h3 class="sr-only">Skrytý nadpis</h3><h2> </h2>');
assert_equals([['level' => 2, 'id' => 'vlastni-kotva', 'title' => 'Ruční nadpis']], $manualIdToc['items'], 'article TOC preserves manual ids and ignores hidden or empty headings');
assert_contains('<h2 id="vlastni-kotva">Ruční nadpis</h2>', $manualIdToc['html'], 'article TOC keeps manual heading id unchanged');

assert_equals(
    '3 články, 2 novinky',
    authorContentSummaryLabel([
        'article_count' => 3,
        'news_count' => 2,
        'articles_enabled' => true,
        'news_enabled' => true,
    ]),
    'author content summary combines enabled content types'
);
assert_equals(
    '4 články',
    authorContentSummaryLabel([
        'article_count' => 4,
        'news_count' => 2,
        'articles_enabled' => true,
        'news_enabled' => false,
    ]),
    'author content summary hides disabled modules'
);

// ─── 8. paginateArray() ────────────────────────────────────────────────────

test_section('paginateArray()');

$p = paginateArray(100, 10, 1);
assert_equals(10, $p['perPage'], 'perPage = 10');
assert_equals(100, $p['total'], 'total = 100');
assert_equals(10, $p['totalPages'], 'totalPages = 10');
assert_equals(1, $p['page'], 'page = 1');
assert_equals(0, $p['offset'], 'offset = 0');

$p = paginateArray(25, 10, 99);
assert_equals(3, $p['page'], 'page clamped to max');

$p = paginateArray(25, 10, 0);
assert_equals(1, $p['page'], 'page clamped to 1 when 0');

$p = paginateArray(0, 10, 1);
assert_equals(0, $p['total'], 'zero items');
assert_equals(1, $p['totalPages'], 'zero items = 1 page');

$p = paginateArray(10, 0, 1);
assert_equals(1, $p['perPage'], 'perPage 0 treated as 1');

$p = paginateArray(-5, 10, 1);
assert_equals(0, $p['total'], 'negative total treated as 0');

test_section('renderPager()');

$pagerHtml = renderPager(2, 3, '/items?', 'Strankovani testu', 'Predchozi', 'Dalsi');
assert_contains('class="sr-only">Strankovani testu</h2>', $pagerHtml, 'pager renders a real hidden heading');
assert_contains('<nav aria-labelledby="pager-heading-', $pagerHtml, 'pager nav is labelled by heading');
assert_false(str_contains($pagerHtml, '<nav aria-label='), 'pager no longer uses aria-label-only nav');
assert_contains('rel="prev"', $pagerHtml, 'pager keeps previous relation');
assert_contains('rel="next"', $pagerHtml, 'pager keeps next relation');
assert_contains('class="sr-only">Stránkování</h2>', renderPager(1, 2, '/items?', ''), 'pager uses fallback heading when label is empty');
assert_equals('', renderPager(1, 1, '/items?', 'Strankovani testu'), 'single-page pager stays empty');

// ─── 9. formatFileSize() ───────────────────────────────────────────────────

test_section('formatFileSize()');

assert_equals('500 B', formatFileSize(500), 'bytes range');
assert_equals('1 kB', formatFileSize(1024), '1 kilobyte');
assert_equals('2 kB', formatFileSize(2048), '2 kilobytes');
assert_equals('1 MB', formatFileSize(1048576), '1 megabyte');
assert_equals('0 B', formatFileSize(0), 'zero bytes');

// ─── 10. storedFileContentDisposition() ─────────────────────────────────────

test_section('storedFileContentDisposition()');

assert_equals(
    'inline; filename="soubor_s_mezerou.pdf"; filename*=UTF-8\'\'soubor%20s%20mezerou.pdf',
    storedFileContentDisposition('inline', 'soubor s mezerou.pdf'),
    'inline disposition keeps ASCII fallback and UTF-8 filename'
);
assert_contains(
    "filename*=UTF-8''%C5%BElu%C5%A5ou%C4%8Dk%C3%BD%20k%C5%AF%C5%88.pdf",
    storedFileContentDisposition('attachment', 'žluťoučký kůň.pdf'),
    'Czech filename preserved through RFC 5987 filename*'
);
assert_equals(
    'attachment; filename="soubor.pdf"; filename*=UTF-8\'\'soubor.pdf',
    storedFileContentDisposition('bad disposition', 'soubor.pdf'),
    'unknown disposition falls back to attachment'
);

test_section('stored file conditional validators');

$testStoredFileEtag = storedFileEtag(__FILE__, 1234, 1700000000);
assert_true(
    str_starts_with($testStoredFileEtag, 'W/"')
        && str_ends_with($testStoredFileEtag, '"')
        && str_contains($testStoredFileEtag, dechex(1700000000) . '-' . dechex(1234)),
    'stored file ETag is weak and includes stable file metadata'
);
assert_equals(
    'Tue, 14 Nov 2023 22:13:20 GMT',
    storedFileHttpDate(1700000000),
    'stored file HTTP date is GMT formatted'
);
assert_true(
    storedFileIfNoneMatchMatches('W/"abc"', '"abc"'),
    'weak and strong ETag validators compare by opaque value'
);
assert_true(
    storedFileIfNoneMatchMatches('W/"abc"', 'W/"other", W/"abc"'),
    'If-None-Match list matches any candidate'
);
assert_true(
    storedFileIfNoneMatchMatches('W/"abc"', '*'),
    'If-None-Match wildcard matches stored file ETag'
);
assert_false(
    storedFileIfNoneMatchMatches('W/"abc"', 'W/"other"'),
    'different If-None-Match value does not match'
);

$previousIfNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
$previousIfModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
try {
    $_SERVER['HTTP_IF_NONE_MATCH'] = 'W/"abc"';
    unset($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    assert_true(
        storedFileRequestValidatorsMatch('W/"abc"', 1700000000),
        'request validators match If-None-Match'
    );

    $_SERVER['HTTP_IF_NONE_MATCH'] = 'W/"other"';
    $_SERVER['HTTP_IF_MODIFIED_SINCE'] = storedFileHttpDate(1800000000);
    assert_false(
        storedFileRequestValidatorsMatch('W/"abc"', 1700000000),
        'non-matching If-None-Match takes precedence over If-Modified-Since'
    );

    unset($_SERVER['HTTP_IF_NONE_MATCH']);
    $_SERVER['HTTP_IF_MODIFIED_SINCE'] = storedFileHttpDate(1700000000);
    assert_true(
        storedFileRequestValidatorsMatch('W/"abc"', 1700000000),
        'request validators match current If-Modified-Since'
    );

    $_SERVER['HTTP_IF_MODIFIED_SINCE'] = storedFileHttpDate(1600000000);
    assert_false(
        storedFileRequestValidatorsMatch('W/"abc"', 1700000000),
        'stale If-Modified-Since does not match'
    );
} finally {
    if ($previousIfNoneMatch === null) {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
    } else {
        $_SERVER['HTTP_IF_NONE_MATCH'] = $previousIfNoneMatch;
    }
    if ($previousIfModifiedSince === null) {
        unset($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    } else {
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $previousIfModifiedSince;
    }
}

// ─── 10. mailSanitizeHeaderValue() ─────────────────────────────────────────

test_section('mailSanitizeHeaderValue()');

assert_equals('test Bcc: evil@hacker.com', mailSanitizeHeaderValue("test\r\nBcc: evil@hacker.com"), 'CRLF stripped (header injection prevention)');
assert_equals('Hello World', mailSanitizeHeaderValue('Hello World'), 'clean value passes through');
assert_equals('', mailSanitizeHeaderValue(''), 'empty string');

// ─── 11. SEO canonical URL ─────────────────────────────────────────────────

test_section('seoCanonicalUrl()');

$oldHttps = $_SERVER['HTTPS'] ?? null;
$oldHttpHost = $_SERVER['HTTP_HOST'] ?? null;
$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'example.test';

assert_equals('https://example.test/clanek', seoCanonicalUrl('/clanek'), 'relative path converted to absolute canonical URL');
assert_equals('https://example.com/clanek?strana=2', seoCanonicalUrl('https://example.com/clanek?strana=2#cast'), 'absolute canonical URL keeps query and drops fragment');
assert_equals('', seoCanonicalUrl('javascript:alert(1)'), 'unsafe canonical URL rejected');
assert_equals('', seoCanonicalUrl('//example.com/clanek'), 'protocol-relative canonical URL rejected');
assert_equals('', seoCanonicalUrl('https://user:pass@example.com/clanek'), 'canonical URL with credentials rejected');
assert_equals('', seoCanonicalUrl("https://example.com/clanek\nSet-Cookie: evil=1"), 'canonical URL with control characters rejected');
assert_contains('<link rel="canonical" href="https://example.com/clanek">', seoMeta(['url' => 'https://example.com/clanek']), 'seoMeta renders canonical link');
assert_contains('<meta property="og:url" content="https://example.test/index.php">', seoMeta(['url' => '/index.php']), 'seoMeta converts relative og:url to absolute URL');
assert_contains('<meta property="og:image" content="https://example.test/uploads/articles/foto.jpg">', seoMeta(['image' => '/uploads/articles/foto.jpg']), 'seoMeta converts relative og:image to absolute URL');
assert_contains('<meta property="og:image:width" content="1200">', seoMeta(['image' => '/uploads/articles/foto.jpg', 'image_width' => 1200, 'image_height' => 630, 'image_type' => 'image/jpeg', 'image_alt' => 'Popis obrázku']), 'seoMeta renders explicit og:image width');
assert_contains('<meta property="og:image:height" content="630">', seoMeta(['image' => '/uploads/articles/foto.jpg', 'image_width' => 1200, 'image_height' => 630, 'image_type' => 'image/jpeg', 'image_alt' => 'Popis obrázku']), 'seoMeta renders explicit og:image height');
assert_contains('<meta property="og:image:type" content="image/jpeg">', seoMeta(['image' => '/uploads/articles/foto.jpg', 'image_width' => 1200, 'image_height' => 630, 'image_type' => 'image/jpeg', 'image_alt' => 'Popis obrázku']), 'seoMeta renders explicit og:image MIME type');
assert_contains('<meta property="og:image:alt" content="Popis obrázku">', seoMeta(['image' => '/uploads/articles/foto.jpg', 'image_width' => 1200, 'image_height' => 630, 'image_type' => 'image/jpeg', 'image_alt' => 'Popis obrázku']), 'seoMeta renders og:image alt text');
assert_contains('<meta property="og:updated_time" content="2026-05-18T12:00:00+00:00">', seoMeta(['updated_time' => '2026-05-18 12:00:00 UTC']), 'seoMeta renders Open Graph update time');
assert_contains('<meta name="twitter:card" content="summary_large_image">', seoMeta(['image' => '/uploads/articles/foto.jpg']), 'seoMeta uses large twitter card when image exists');
assert_contains('<meta name="twitter:title" content="Titulek článku">', seoMeta(['title' => 'Titulek článku']), 'seoMeta renders twitter title');
$structuredDataHtml = structuredDataScript([
    '@context' => 'https://schema.org',
    '@type' => 'Thing',
    'name' => 'Žluťoučký kůň',
]);
assert_contains('<script type="application/ld+json" nonce="', $structuredDataHtml, 'structuredDataScript renders CSP nonce');
assert_contains('"@type":"Thing"', $structuredDataHtml, 'structuredDataScript renders JSON-LD payload');
assert_false(str_contains($structuredDataHtml, '<script type="application/ld+json">'), 'structuredDataScript does not render raw non-nonced script');

$_SERVER['HTTP_USER_AGENT'] = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';
assert_equals(true, isSocialPreviewCrawler(), 'Facebook crawler is detected as social preview crawler');
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
assert_equals(false, isSocialPreviewCrawler(), 'Regular browser is not detected as social preview crawler');

if ($oldHttps === null) {
    unset($_SERVER['HTTPS']);
} else {
    $_SERVER['HTTPS'] = $oldHttps;
}
if ($oldHttpHost === null) {
    unset($_SERVER['HTTP_HOST']);
} else {
    $_SERVER['HTTP_HOST'] = $oldHttpHost;
}

// ─── 12. base32Decode() + totpCalculate() + totpUri() ──────────────────────

test_section('base32Decode()');

// GEZDGNBVGY3TQOJQ = base32 of '1234567890' (10 bytes)
$decoded = base32Decode('GEZDGNBVGY3TQOJQ');
assert_equals('1234567890', $decoded, 'known base32 decode (10 bytes)');

assert_equals('', base32Decode(''), 'empty input');
assert_equals('f', base32Decode('MY'), 'short base32');

test_section('totpCalculate()');

// GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ = base32 of '12345678901234567890' (20 bytes)
// RFC 6238 test vector: time=59 → counter=1 → code 287082
$code = totpCalculate('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59, 6, 30);
assert_equals('287082', $code, 'RFC 6238 test vector t=59');

// time=0 → counter=0
$code0 = totpCalculate('GEZDGNBVGY3TQOJQ', 0, 6, 30);
assert_equals(6, strlen($code0), 'TOTP code is 6 digits');

test_section('totpUri()');

$uri = totpUri('JBSWY3DPBI', 'user@example.com', 'TestCMS');
assert_contains('otpauth://totp/', $uri, 'URI starts with otpauth://totp/');
assert_contains('secret=JBSWY3DPBI', $uri, 'URI contains secret');
assert_contains('issuer=TestCMS', $uri, 'URI contains issuer');

// ─── 11. parseContentShortcodeAttributes() ─────────────────────────────────

test_section('parseContentShortcodeAttributes()');

assert_equals(['src' => 'file.mp3'], parseContentShortcodeAttributes('src="file.mp3"'), 'double-quoted attribute');
assert_equals(['src' => 'file.mp3'], parseContentShortcodeAttributes("src='file.mp3'"), 'single-quoted attribute');
assert_equals(['src' => 'file.mp3'], parseContentShortcodeAttributes('src=file.mp3'), 'unquoted attribute');
assert_equals([], parseContentShortcodeAttributes(''), 'empty string');
assert_equals(['src' => 'file.mp3'], parseContentShortcodeAttributes('SRC="file.mp3"'), 'keys lowercased');

// ─── 12. normalizeContentEmbedUrl() ────────────────────────────────────────

test_section('normalizeContentEmbedUrl()');

assert_equals('', normalizeContentEmbedUrl(''), 'empty string');
assert_equals('https://example.com/audio.mp3', normalizeContentEmbedUrl('https://example.com/audio.mp3'), 'valid HTTPS URL');
assert_equals('/uploads/file.mp3', normalizeContentEmbedUrl('/uploads/file.mp3'), 'absolute path accepted');
assert_equals('', normalizeContentEmbedUrl('//example.com/audio.mp3'), 'protocol-relative URL rejected');
assert_equals('', normalizeContentEmbedUrl('https://user:pass@example.com/audio.mp3'), 'URL with credentials rejected');
assert_equals('', normalizeContentEmbedUrl("https://example.com\nevil"), 'URL with newline rejected');
assert_equals('', normalizeContentEmbedUrl('javascript:alert(1)'), 'javascript: rejected');
assert_equals('kCy8R5fGHxY', contentYouTubeVideoId('https://www.youtube.com/watch?v=kCy8R5fGHxY'), 'YouTube watch URL id parsed');
assert_equals('yIdGMYUmfgg', contentYouTubeVideoId('https://youtu.be/yIdGMYUmfgg?t=26s'), 'youtu.be URL id parsed');
assert_equals(26, contentYouTubeStartSeconds('https://www.youtube.com/watch?v=yIdGMYUmfgg&t=26s'), 'YouTube t parameter parsed');
$youtubeShortcodeHtml = renderContentShortcodes('[video]https://www.youtube.com/watch?v=yIdGMYUmfgg&amp;t=26s[/video]');
assert_contains(
    'https://www.youtube-nocookie.com/embed/yIdGMYUmfgg?start=26',
    $youtubeShortcodeHtml,
    'YouTube video shortcode renders privacy-friendly iframe'
);

test_section('content shortcode heading semantics');

assert_contains('aria-labelledby="content-video-heading-', $youtubeShortcodeHtml, 'YouTube video shortcode card is labelled by a real heading');
assert_false(str_contains($youtubeShortcodeHtml, 'aria-label="Video:'), 'YouTube video shortcode no longer uses aria-label-only section');

$codeShortcodeHtml = renderContentCodeShortcode('kopirovat do schranky');
assert_true($codeShortcodeHtml !== null, 'code shortcode renders non-empty block');
assert_contains('aria-labelledby="content-code-heading-', (string)$codeShortcodeHtml, 'code shortcode block is labelled by a real heading');
assert_contains('class="sr-only">Kopírovatelný obsah</h2>', (string)$codeShortcodeHtml, 'code shortcode heading text is available to screen readers');
assert_false(str_contains((string)$codeShortcodeHtml, '<section class="content-code-block" aria-label='), 'code shortcode no longer uses aria-label-only section');

$pdfShortcodeHtml = renderContentPdfShortcode('https://example.com/dokument.pdf', 'Ukázka PDF', 'application/pdf');
assert_true($pdfShortcodeHtml !== null, 'pdf shortcode renders valid external pdf card');
assert_contains('aria-labelledby="content-pdf-heading-', (string)$pdfShortcodeHtml, 'pdf shortcode card is labelled by a real heading');
assert_contains('class="sr-only">PDF dokument: Ukázka PDF</h2>', (string)$pdfShortcodeHtml, 'pdf shortcode heading includes document title');
assert_false(str_contains((string)$pdfShortcodeHtml, 'aria-label="PDF dokument:'), 'pdf shortcode no longer uses aria-label-only section');

$contentCardHtml = renderContentEmbedCard([
    'title' => 'Ukázkový odkaz',
    'url' => '/ukazka',
    'eyebrow' => 'Obsah webu',
]);
assert_contains('aria-labelledby="content-card-heading-', $contentCardHtml, 'content embed card is labelled by a real heading');
assert_false(str_contains($contentCardHtml, 'aria-label='), 'content embed card no longer uses aria-label-only section');

$interactiveEmbedHtml = renderContentInteractiveEmbed([
    'title' => 'Ukázkový formulář',
    'url' => '/forms/ukazka',
    'embed_url' => '/forms/ukazka?embed=1',
    'eyebrow' => 'Formulář',
]);
assert_contains('aria-labelledby="content-interactive-heading-', $interactiveEmbedHtml, 'interactive embed card is labelled by a real heading');
assert_false(str_contains($interactiveEmbedHtml, 'aria-label='), 'interactive embed card no longer uses aria-label-only section');

// ─── 13. githubIssueParseUrl() ───────────────────────────────────────────────

test_section('githubIssueParseUrl()');

$parsedIssue = githubIssueParseUrl('https://github.com/vlcekapps/Koracms/issues/123');
assert_equals('vlcekapps/Koracms', $parsedIssue['repository'] ?? '', 'repository parsed from issue URL');
assert_equals(123, $parsedIssue['number'] ?? 0, 'issue number parsed from issue URL');
assert_equals('https://github.com/vlcekapps/Koracms/issues/123', $parsedIssue['url'] ?? '', 'canonical issue URL returned');

$parsedIssueWithFragment = githubIssueParseUrl('https://github.com/vlcekapps/Koracms/issues/123#issuecomment-1');
assert_equals('https://github.com/vlcekapps/Koracms/issues/123', $parsedIssueWithFragment['url'] ?? '', 'issue URL with fragment parsed');
assert_equals(null, githubIssueParseUrl('https://example.com/vlcekapps/Koracms/issues/123'), 'non-GitHub issue URL rejected');

// ─── 13. userHas2FA() / userHasPasskey() ───────────────────────────────────

test_section('userHas2FA() / userHasPasskey()');

assert_true(userHas2FA(['totp_secret' => 'ABCDEFGH']), '2FA active with secret');
assert_false(userHas2FA(['totp_secret' => '']), '2FA inactive with empty secret');
assert_false(userHas2FA([]), '2FA inactive with missing key');

assert_true(userHasPasskey(['passkey_credentials' => '[{"id":"abc"}]']), 'passkey with credentials');
assert_false(userHasPasskey(['passkey_credentials' => '[]']), 'passkey empty array');
assert_false(userHasPasskey(['passkey_credentials' => '']), 'passkey empty string');
assert_false(userHasPasskey([]), 'passkey missing key');

// ─── 14. CSRF rotace ────────────────────────────────────────────────────────

test_section('CSRF rotace');

// Vygenerovat token
$_SESSION = [];
$token1 = csrfToken();
assert_true(strlen($token1) === 64, 'CSRF token is 64 hex chars');

// Simulovat uspesnou verifikaci
$_POST['csrf_token'] = $token1;
verifyCsrf(); // nema ukoncit skript, protoze token je validni

$token2 = csrfToken();
assert_true($token1 !== $token2, 'CSRF token rotated after verify');
assert_equals($token1, test_session_string('csrf_token_prev'), 'previous token preserved');

// Simulovat multi-tab: predchozi token je stale validni
$_POST['csrf_token'] = $token1;
verifyCsrf(); // stary token = previous, musi projit

$token3 = csrfToken();
assert_true($token2 !== $token3, 'CSRF token rotated after prev-token verify');

// Content-lock heartbeat smí token ověřit opakovaně bez rotace.
$_SESSION = [];
$heartbeatToken = csrfToken();
$_POST['csrf_token'] = $heartbeatToken;
verifyCsrf(false);
assert_equals($heartbeatToken, csrfToken(), 'CSRF token preserved after non-rotating verify');
assert_equals('', test_session_string('csrf_token_prev'), 'non-rotating verify does not rewrite previous token');
verifyCsrf(false);
assert_equals($heartbeatToken, csrfToken(), 'CSRF token can be reused for non-rotating heartbeat');

// --- 15. relativeTime() ------------------------------------------------------

test_section('relativeTime()');

assert_equals('–', relativeTime(null), 'null returns dash');
assert_equals('–', relativeTime(''), 'empty string returns dash');
assert_equals('–', relativeTime('not-a-date'), 'invalid date returns dash');
assert_equals('právě teď', relativeTime(date('Y-m-d H:i:s')), 'now returns prave ted');
assert_equals('právě teď', relativeTime(date('Y-m-d H:i:s', time() + 60)), 'future returns prave ted');
assert_contains('minut', relativeTime(date('Y-m-d H:i:s', time() - 300)), '5 min ago contains minutami');
assert_contains('hodin', relativeTime(date('Y-m-d H:i:s', time() - 7200)), '2 hours ago contains hodin');

// ─── 16. validateDateTimeLocal() ────────────────────────────────────────────

test_section('validateDateTimeLocal()');

assert_equals('2024-06-15 14:30:00', validateDateTimeLocal('2024-06-15T14:30'), 'valid datetime-local');
assert_equals(null, validateDateTimeLocal(''), 'empty string returns null');
assert_equals(null, validateDateTimeLocal('not-a-date'), 'invalid format returns null');
assert_equals(null, validateDateTimeLocal('2024-13-01T10:00'), 'invalid month returns null');
assert_equals(null, validateDateTimeLocal('2024-06-15 14:30'), 'space instead of T returns null');
assert_equals('2024-01-01 00:00:00', validateDateTimeLocal('2024-01-01T00:00'), 'midnight');

// ═══════════════════════════════════════════════════════════════════════════
// SOUHRN
// ═══════════════════════════════════════════════════════════════════════════

echo "\n══════════════════════════════════════\n";
echo "  Celkem: " . ($_TEST_PASS + $_TEST_FAIL) . " testů\n";
echo "  OK:     {$_TEST_PASS}\n";
echo "  FAIL:   {$_TEST_FAIL}\n";
echo "══════════════════════════════════════\n";

exit(test_failure_count() > 0 ? 1 : 0);
