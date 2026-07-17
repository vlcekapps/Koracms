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

test_section('publicCaptchaErrorMessage()');

$publicCaptchaErrorMessage = publicCaptchaErrorMessage();
assert_contains('Chybná odpověď na ověřovací otázku.', $publicCaptchaErrorMessage, 'captcha error identifies invalid answer');
assert_contains('Zkuste výpočet znovu a zadejte jen číslo.', $publicCaptchaErrorMessage, 'captcha error suggests how to fix the answer');

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
assert_equals('/subscribe.php', safePublicReturnTarget('/reservations/calendar.php?token=0123456789abcdef0123456789abcdef', '/subscribe.php'), 'safe public return rejects reservation calendar token URL');
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
assert_equals('content_manage_shared', adminRouteCapability('/admin/convert_content.php'), 'content conversion requires shared content management');

test_section('module public entrypoints');

$modulePublicEntryPoints = modulePublicEntryPoints();
$modulePublicPathMap = modulePublicPathModuleMap();
assert_true(knownModuleKey('blog'), 'known module key recognizes blog');
assert_false(knownModuleKey('unknown_module'), 'known module key rejects unknown module');
assert_equals('Blog', moduleDefinition('blog')['label'] ?? null, 'module definition returns blog metadata');
assert_equals(null, moduleDefinition('unknown_module'), 'module definition returns null for unknown module');
assert_equals('module_blog', moduleSettingKey('blog'), 'module setting key uses the shared module_ prefix');
assert_equals('module_blog', moduleSettingKey(' blog '), 'module setting key trims accidental whitespace');
assert_true(in_array('/blog/article.php', $modulePublicEntryPoints['blog'] ?? [], true), 'blog article route is declared as a public module entrypoint');
assert_true(in_array('/blog/series.php', $modulePublicEntryPoints['blog'] ?? [], true), 'blog series route is declared as a public module entrypoint');
assert_true(in_array('/board/subscribe.php', $modulePublicEntryPoints['board'] ?? [], true), 'board subscription route is declared as a public module entrypoint');
assert_true(in_array('/podcast/audio.php', $modulePublicEntryPoints['podcast'] ?? [], true), 'podcast audio endpoint is declared as a public module entrypoint');
assert_true(in_array('/subscribe.php', $modulePublicEntryPoints['newsletter'] ?? [], true), 'newsletter subscribe route is declared as a public module entrypoint');
assert_true(in_array('/forms/index.php', $modulePublicEntryPoints['forms'] ?? [], true), 'forms public route is declared even without main navigation');
assert_true(in_array('/reservations/calendar.php', $modulePublicEntryPoints['reservations'] ?? [], true), 'reservation calendar route is declared as a public module entrypoint');
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
assert_equals('/admin/blog.php', modulePrimaryAdminPath('blog'), 'blog primary admin path comes from first manifest admin path');
assert_equals('/admin/statistics.php', modulePrimaryAdminPath('statistics'), 'statistics primary admin path comes from module manifest');
assert_equals('', modulePrimaryAdminPath('unknown_module'), 'unknown module has no primary admin path');
assert_equals('blog_manage_own', moduleAdminCapability('blog'), 'blog admin capability comes from module manifest');
assert_equals('bookings_manage', moduleAdminCapability('reservations'), 'reservation admin capability comes from module manifest');
assert_equals('admin_access', moduleAdminCapability('unknown_module'), 'unknown module admin capability falls back safely');

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
assert_equals('Měsíční archivy blogu', $sitemapSections['blog']['blog_archives'] ?? null, 'blog archive sitemap section label comes from manifest');
assert_equals('blog', $sitemapSectionMap['blog'] ?? null, 'blog sitemap section maps to blog module');
assert_equals('blog', $sitemapSectionMap['blog_categories'] ?? null, 'blog category sitemap section maps to blog module');
assert_equals('blog', $sitemapSectionMap['blog_tags'] ?? null, 'blog tag sitemap section maps to blog module');
assert_equals('blog', $sitemapSectionMap['blog_archives'] ?? null, 'blog archive sitemap section maps to blog module');
assert_equals('board', $sitemapSectionMap['board_categories'] ?? null, 'board category sitemap section maps to board module');
assert_equals('gallery', $sitemapSectionMap['gallery_photos'] ?? null, 'gallery photos sitemap section maps to gallery module');
assert_equals('podcast', $sitemapSectionMap['podcast_episodes'] ?? null, 'podcast episodes sitemap section maps to podcast module');
assert_equals('forms', $sitemapSectionMap['forms'] ?? null, 'forms sitemap section maps to forms module');
assert_false(isset($sitemapSections['statistics']), 'statistics module has no sitemap section');

test_section('module stats page types');

$statsPageTypes = moduleStatsPageTypes();
$statsPageTypeMap = moduleStatsPageTypeMap();
assert_equals(['article'], $statsPageTypes['blog'] ?? null, 'blog stats page type comes from manifest');
assert_equals('blog', $statsPageTypeMap['article'] ?? null, 'article stats page type maps to blog module');
assert_equals('gallery', $statsPageTypeMap['gallery_photo'] ?? null, 'gallery photo stats page type maps to gallery module');
assert_equals('podcast', $statsPageTypeMap['podcast_episode'] ?? null, 'podcast episode stats page type maps to podcast module');
assert_equals('food', $statsPageTypeMap['food_card'] ?? null, 'food card stats page type maps to food module');
assert_equals('forms', $statsPageTypeMap['form'] ?? null, 'form stats page type maps to forms module');
assert_false(isset($statsPageTypes['statistics']), 'statistics module has no measured public content type');

test_section('module database tables');

$moduleDatabaseTables = moduleDatabaseTables();
$moduleDatabaseTableMap = moduleDatabaseTableModuleMap();
assert_true(in_array('cms_articles', $moduleDatabaseTables['blog'] ?? [], true), 'blog database tables come from manifest');
assert_true(in_array('cms_board_publication_events', $moduleDatabaseTables['board'] ?? [], true), 'board supporting table comes from manifest');
assert_true(in_array('cms_res_booking_events', $moduleDatabaseTables['reservations'] ?? [], true), 'reservation history table comes from manifest');
assert_equals('blog', $moduleDatabaseTableMap['cms_blog_series'] ?? null, 'blog series table maps to blog module');
assert_equals('food', $moduleDatabaseTableMap['cms_food_order_items'] ?? null, 'food order item table maps to food module');
assert_equals('statistics', $moduleDatabaseTableMap['cms_stats_content_daily'] ?? null, 'content statistics table maps to statistics module');
assert_false(isset($moduleDatabaseTableMap['cms_users']), 'shared core user table has no module owner');

test_section('blog taxonomy landing links');

assert_equals('linuxovy-koutek', blogCategorySlug('Linuxový koutek'), 'category slug normalizes Czech diacritics');
assert_equals('nvda-tip', blogTagSlug('NVDA tip'), 'tag slug uses shared blog taxonomy normalization');
$taxonomyFeedBlog = ['slug' => 'Technický blog'];
assert_equals(
    '/feed.php?blog=technicky-blog&category=linuxovy-koutek',
    str_replace(BASE_URL, '', blogCategoryFeedPath($taxonomyFeedBlog, ['slug' => 'Linuxový koutek'])),
    'category feed path keeps canonical blog and category scope'
);
assert_equals(
    '/feed.php?blog=technicky-blog&tag=nvda-tip',
    str_replace(BASE_URL, '', blogTagFeedPath($taxonomyFeedBlog, ['slug' => 'NVDA tip'])),
    'tag feed path keeps canonical blog and tag scope'
);
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
assert_equals('obecna-diskuze', chatTopicSlug('Obecná diskuze'), 'chat topic slug normalizes Czech diacritics');
assert_equals(
    'CHT-20260401-AB12',
    chatReferenceCode(new DateTimeImmutable('2026-04-01 09:23:56'), 'ab-12'),
    'chat reference code uses date and normalized suffix'
);
assert_equals('public', normalizeChatConversationType('bad-value'), 'invalid chat conversation type falls back to public');
assert_equals('support', normalizeChatConversationType('support'), 'support chat conversation type accepted');
assert_equals('pending', normalizeChatReplyStatus('bad-value'), 'invalid chat reply status falls back to pending');
assert_equals('approved', normalizeChatReplyStatus('approved'), 'approved chat reply status accepted');
assert_true(chatMessageIsPinned(['is_pinned' => 1, 'pinned_until' => null]), 'chat pinned message without expiration is pinned');
assert_true(chatMessageIsPinned(['is_pinned' => 1, 'pinned_until' => '2026-04-02 12:00:00'], new DateTimeImmutable('2026-04-01 12:00:00')), 'chat pinned message before expiration is pinned');
assert_false(chatMessageIsPinned(['is_pinned' => 1, 'pinned_until' => '2026-04-01 11:00:00'], new DateTimeImmutable('2026-04-01 12:00:00')), 'chat pinned message after expiration is not pinned');
assert_equals('/chat/tema/obecne', str_replace(BASE_URL, '', chatTopicPath(['slug' => 'obecne'])), 'chat topic path uses clean URL');
assert_equals('/chat/zprava/42', str_replace(BASE_URL, '', chatMessagePath(['id' => 42])), 'chat message path uses clean URL');
assert_equals('prednasky-a-workshopy', eventTypeSlug('Přednášky a workshopy'), 'event type slug normalizes Czech diacritics');
assert_equals(
    '/events/typ/prednasky',
    str_replace(BASE_URL, '', eventTypePath(['id' => 3, 'title' => 'Přednášky', 'slug' => 'prednasky'])),
    'event type canonical path uses clean URL'
);
assert_equals(
    'daily',
    normalizeEventRecurrenceFrequency('daily'),
    'event recurrence accepts daily frequency'
);
assert_equals(
    'none',
    normalizeEventRecurrenceFrequency('yearly'),
    'event recurrence rejects unsupported frequency'
);
assert_equals(
    '2026-04-15 18:00',
    eventRecurrenceShift(new DateTimeImmutable('2026-04-01 18:00'), 'weekly', 2)->format('Y-m-d H:i'),
    'event recurrence shifts weekly dates'
);
assert_equals(
    '2026-06-01 09:30',
    eventRecurrenceShift(new DateTimeImmutable('2026-04-01 09:30'), 'monthly', 2)->format('Y-m-d H:i'),
    'event recurrence shifts monthly dates'
);

test_section('poll voting helpers');

assert_equals('single', pollVoteMode(''), 'empty poll vote mode falls back to single');
assert_equals('multiple', pollVoteMode('multiple'), 'multiple poll vote mode accepted');
assert_equals('after_vote', pollResultsVisibility('bad-value'), 'invalid poll result visibility falls back');
assert_equals('closed', pollResultsVisibility('closed'), 'closed poll result visibility accepted');
assert_false(pollAllowsMultipleChoices(['vote_mode' => 'single']), 'single poll does not allow multiple choices');
assert_true(pollAllowsMultipleChoices(['vote_mode' => 'multiple']), 'multiple poll allows multiple choices');
assert_equals(1, pollConfiguredMaxChoices(['vote_mode' => 'single', 'max_choices' => 5], 8), 'single poll max choices is one');
assert_equals(3, pollConfiguredMaxChoices(['vote_mode' => 'multiple', 'max_choices' => 3], 8), 'multiple poll uses configured max choices');
assert_equals(2, pollConfiguredMaxChoices(['vote_mode' => 'multiple', 'max_choices' => null], 5), 'multiple poll empty max choices defaults to two');
assert_equals(4, pollConfiguredMaxChoices(['vote_mode' => 'multiple', 'max_choices' => 10], 4), 'multiple poll max choices is capped by options');
assert_equals([2, 4], pollSelectedOptionIds(['2', 'bad', 4, '2', '0']), 'selected poll option ids normalize and deduplicate');
assert_equals(hash('sha256', '127.0.0.1|poll_7'), pollVoterHash('127.0.0.1', 7), 'poll voter hash preserves legacy format');
assert_false(pollResultsAreVisible(['results_visibility' => 'hidden', 'state' => 'closed'], true, true), 'hidden poll results stay hidden');
assert_true(pollResultsAreVisible(['results_visibility' => 'always', 'state' => 'active'], false, false), 'always-visible poll results are public');
assert_true(pollResultsAreVisible(['results_visibility' => 'closed', 'state' => 'closed'], false, false), 'closed visibility shows closed poll results');
assert_false(pollResultsAreVisible(['results_visibility' => 'closed', 'state' => 'active'], true, true), 'closed visibility hides active poll results');
assert_true(pollResultsAreVisible(['results_visibility' => 'after_vote', 'state' => 'active'], true, false), 'after-vote visibility shows results to voter');
assert_equals(33.3, pollResultPercentage(1, 3), 'poll result percentage rounds to one decimal');
assert_equals(0.0, pollResultPercentage(1, 0), 'poll result percentage handles zero voters');
assert_equals('1 výběr', pollVoteSelectionLabel(1, true), 'poll multi selection singular label');
assert_equals('3 výběry', pollVoteSelectionLabel(3, true), 'poll multi selection Czech plural label');
assert_equals('5 hlasů', pollVoteSelectionLabel(5, false), 'poll single vote label');

test_section('reservation reminders and calendar');

$reservationTestBooking = [
    'id' => 42,
    'resource_name' => 'Masáž zad',
    'resource_slug' => 'masaz-zad',
    'booking_date' => '2026-04-02',
    'start_time' => '09:00:00',
    'end_time' => '10:00:00',
    'calendar_token' => '0123456789abcdef0123456789abcdef',
    'confirmation_token' => 'fedcba9876543210fedcba9876543210',
    'party_size' => 2,
    'guest_name' => 'Pavel Vlček',
    'reminders_enabled' => 1,
    'reminder_hours_before' => 2,
    'reminder_sent_at' => null,
    'status' => 'confirmed',
];
$reservationIcs = reservationBuildIcs($reservationTestBooking);
assert_equals('rezervace-masaz-zad-20260402.ics', reservationIcsFilename($reservationTestBooking), 'reservation ICS filename is stable');
assert_contains('BEGIN:VCALENDAR', $reservationIcs, 'reservation ICS starts calendar');
assert_contains('SUMMARY:Rezervace: Masáž zad', $reservationIcs, 'reservation ICS contains resource summary');
assert_contains('Zákazník: Pavel Vlček', $reservationIcs, 'reservation ICS contains customer name');
assert_contains('/reservations/cancel_booking.php?token=', $reservationIcs, 'reservation ICS contains safe cancellation link when token exists');
assert_false(
    reservationReminderIsDue($reservationTestBooking, new DateTimeImmutable('2026-04-02 06:59:00')),
    'reservation reminder is not due before reminder window'
);
assert_true(
    reservationReminderIsDue($reservationTestBooking, new DateTimeImmutable('2026-04-02 07:00:00')),
    'reservation reminder is due at reminder window'
);
$reservationTestBooking['reminder_sent_at'] = '2026-04-02 07:00:00';
assert_false(
    reservationReminderIsDue($reservationTestBooking, new DateTimeImmutable('2026-04-02 07:30:00')),
    'reservation reminder is not due after it was sent'
);
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
assert_equals('enable', normalizeArticlePreviewAction(' Enable '), 'article preview enable action normalized');
assert_equals('rotate', normalizeArticlePreviewAction('ROTATE'), 'article preview rotate action normalized');
assert_equals('revoke', normalizeArticlePreviewAction(' revoke '), 'article preview revoke action normalized');
assert_equals('', normalizeArticlePreviewAction('delete'), 'unknown article preview action rejected');
$generatedArticlePreviewToken = generateArticlePreviewToken();
assert_true(isValidArticlePreviewToken($generatedArticlePreviewToken), 'generated article preview token has safe format');
assert_equals(32, strlen($generatedArticlePreviewToken), 'generated article preview token has expected length');
assert_false(isValidArticlePreviewToken('not-a-valid-preview-token'), 'invalid article preview token rejected');
assert_false(isValidArticlePreviewToken(strtoupper($generatedArticlePreviewToken)), 'noncanonical uppercase preview token rejected');
assert_contains(
    'preview=' . $generatedArticlePreviewToken,
    articlePreviewPath([
        'id' => 7,
        'slug' => 'jak-cist-web',
        'blog_id' => 3,
        'blog_slug' => 'snd',
        'preview_token' => $generatedArticlePreviewToken,
    ]),
    'active article preview path contains generated token'
);
assert_equals(
    '/snd/serie/prvni-serie',
    str_replace(BASE_URL, '', blogSeriesPath(['slug' => 'snd'], ['id' => 4, 'slug' => 'prvni-serie'])),
    'series canonical path uses clean blog route'
);
assert_equals('2026-07', normalizeBlogArchiveKey(' 2026-07 '), 'blog archive key normalized');
assert_equals('', normalizeBlogArchiveKey('2026-13'), 'invalid blog archive month rejected');
assert_equals('', normalizeBlogArchiveKey('0999-12'), 'unsupported blog archive year rejected');
assert_equals(
    '/snd/archiv/2026/07',
    str_replace(BASE_URL, '', blogArchivePath(['slug' => 'snd'], '2026-07')),
    'blog archive canonical path uses clean blog route'
);
assert_equals(
    '/snd/archiv/2026/07?q=test',
    str_replace(BASE_URL, '', blogArchivePath(['slug' => 'snd'], '2026-07', ['q' => 'test'])),
    'blog archive canonical path preserves optional filters'
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
assert_equals('casto-kladene-dotazy', faqCategorySlug('Často kladené dotazy'), 'FAQ category slug normalizes Czech diacritics');
assert_equals('/faq/kategorie/instalace', faqCategoryRequestPath(['id' => 9, 'slug' => 'instalace']), 'FAQ category clean request path uses slug');
assert_equals('/faq/index.php?kat=9', faqCategoryRequestPath(['id' => 9, 'slug' => '']), 'FAQ category request path falls back to legacy filter');
assert_equals(
    [
        ['id' => 1, 'name' => 'Rodič', 'parent_id' => null],
        ['id' => 2, 'name' => 'Potomek', 'parent_id' => 1],
    ],
    faqCategoryBreadcrumbs([
        1 => ['id' => 1, 'name' => 'Rodič', 'parent_id' => null],
        2 => ['id' => 2, 'name' => 'Potomek', 'parent_id' => 1],
    ], 2),
    'FAQ category breadcrumbs walk parent chain'
);
assert_equals(
    [1, 2, 3],
    faqCategoryDescendantIds([
        1 => [['id' => 2], ['id' => 3]],
        2 => [['id' => 0]],
    ], 1),
    'FAQ category descendant helper returns selected category and valid children'
);
assert_equals('1 otázka', faqCountLabel(1), 'FAQ count label handles singular');
assert_equals('3 otázky', faqCountLabel(3), 'FAQ count label handles Czech plural 2-4');
assert_equals('12 otázek', faqCountLabel(12), 'FAQ count label handles Czech plural exception');
$previousRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
$previousUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'KoraUnitTest/1.0';
$faqFeedbackHash = faqFeedbackVisitorHash(11);
assert_equals($faqFeedbackHash, faqFeedbackVisitorHash(11), 'FAQ feedback visitor hash is stable for same visitor and FAQ');
assert_false($faqFeedbackHash === faqFeedbackVisitorHash(12), 'FAQ feedback visitor hash changes per FAQ');
if ($previousRemoteAddr === null) {
    unset($_SERVER['REMOTE_ADDR']);
} else {
    $_SERVER['REMOTE_ADDR'] = $previousRemoteAddr;
}
if ($previousUserAgent === null) {
    unset($_SERVER['HTTP_USER_AGENT']);
} else {
    $_SERVER['HTTP_USER_AGENT'] = $previousUserAgent;
}
assert_equals('ceske-navody', downloadCategorySlug('České návody'), 'download category slug normalizes Czech diacritics');
assert_equals('kora-cms-4-x', downloadSeriesSlug('Kora CMS 4.x'), 'download series slug normalizes version-like titles');
assert_equals(
    '/downloads/kategorie/ceske-navody',
    str_replace(BASE_URL, '', downloadCategoryPath(['id' => 7, 'name' => 'České návody', 'slug' => 'ceske-navody'])),
    'download category canonical path uses clean URL'
);
assert_equals(
    '/downloads/index.php?kat=7',
    str_replace(BASE_URL, '', downloadCategoryPath(['id' => 7, 'name' => 'Starší kategorie', 'slug' => ''])),
    'download category path falls back to legacy query filter without slug'
);
assert_equals(
    '/downloads/serie/kora-cms-4-x',
    str_replace(BASE_URL, '', downloadSeriesPath(['id' => 3, 'title' => 'Kora CMS 4.x', 'slug' => 'kora-cms-4-x'])),
    'download series canonical path uses clean URL'
);
assert_equals(
    '/downloads/index.php',
    str_replace(BASE_URL, '', downloadSeriesPath(['id' => 3, 'title' => 'Bez slugu', 'slug' => ''])),
    'download series path falls back to downloads index without slug'
);
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

test_section('theme portable package guardrails');

assert_false(
    in_array('php', themePortablePackageAllowedExtensions(), true),
    'portable theme package does not allow PHP files'
);
$themeLayoutOverrideValidation = themePortablePackageFileValidation('layouts/base.php', '<?php echo "override";');
assert_false(
    $themeLayoutOverrideValidation['valid'],
    'portable theme package rejects layout overrides'
);
assert_contains(
    'jen `theme.json` a soubory v `assets/`',
    $themeLayoutOverrideValidation['error'],
    'layout override rejection explains portable static scope'
);
$themePartialOverrideValidation = themePortablePackageFileValidation('partials/footer.php', '<?php echo "override";');
assert_false(
    $themePartialOverrideValidation['valid'],
    'portable theme package rejects footer partial overrides'
);
$themeViewOverrideValidation = themePortablePackageFileValidation('views/home.php', '<?php echo "override";');
assert_false(
    $themeViewOverrideValidation['valid'],
    'portable theme package rejects view overrides'
);
$themePhpAssetValidation = themePortablePackageFileValidation('assets/footer.php', '<?php echo "override";');
assert_false(
    $themePhpAssetValidation['valid'],
    'portable theme package rejects PHP assets'
);
assert_contains(
    'nepovolený typ assetu',
    $themePhpAssetValidation['error'],
    'PHP asset rejection explains forbidden asset type'
);
$themeCssAssetValidation = themePortablePackageFileValidation('assets/public.css', '.site-footer { display: block; }');
assert_true(
    $themeCssAssetValidation['valid'],
    'portable theme package still allows CSS assets'
);
$nonDefaultThemeKeys = array_values(array_filter(
    availableThemes(),
    static fn (string $themeKey): bool => $themeKey !== defaultThemeName()
));
if ($nonDefaultThemeKeys !== []) {
    $sampleThemeKey = $nonDefaultThemeKeys[0];
    assert_equals(
        themeLayoutPath('base', defaultThemeName()),
        themeLayoutPath('base', $sampleThemeKey),
        'non-default portable theme inherits default base layout'
    );
    assert_equals(
        themePartialPath('footer', defaultThemeName()),
        themePartialPath('footer', $sampleThemeKey),
        'non-default portable theme inherits default footer partial'
    );
    assert_equals(
        themeViewPath('home', defaultThemeName()),
        themeViewPath('home', $sampleThemeKey),
        'non-default portable theme inherits default homepage view'
    );
}

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
assert_true(str_starts_with(newPodcastFeedGuid(), 'urn:uuid:'), 'podcast feed GUID generator uses immutable UUID URN');
assert_equals('legacy-guid-42', normalizePodcastFeedGuid(" legacy-guid-42\n"), 'podcast feed GUID normalizer preserves imported identifiers');
assert_equals('audio/mpeg', normalizePodcastAudioMimeType(' Audio/MPEG '), 'podcast audio MIME normalizer accepts audio types');
assert_equals('', normalizePodcastAudioMimeType('text/html'), 'podcast audio MIME normalizer rejects non-audio types');
assert_equals(123456, normalizePodcastAudioFileSize('123456'), 'podcast enclosure size accepts whole bytes');
assert_equals(0, normalizePodcastAudioFileSize('-1'), 'podcast enclosure size rejects negative values');
assert_equals(0.0, podcastChapterStartSeconds('0:00'), 'podcast chapter parser accepts zero timestamp');
assert_equals(3723.5, podcastChapterStartSeconds('1:02:03.500'), 'podcast chapter parser accepts hours and milliseconds');
assert_equals(90.0, podcastChapterStartSeconds('90'), 'podcast chapter parser accepts seconds');
assert_equals(null, podcastChapterStartSeconds('1:99'), 'podcast chapter parser rejects invalid time');
assert_equals('1:02:03.5', podcastChapterTimeLabel(3723.5), 'podcast chapter formatter keeps useful milliseconds');
$podcastChapterPayload = podcastChaptersPayload([
    ['start_time_seconds' => '60', 'title' => 'Druhá část', 'url' => 'example.test/topic'],
    ['start_time_seconds' => '0', 'title' => 'Úvod', 'image_url' => 'https://cdn.example.test/chapter.jpg'],
]);
assert_equals('1.2.0', $podcastChapterPayload['version'], 'podcast chapters use current JSON schema version');
assert_equals('Úvod', $podcastChapterPayload['chapters'][0]['title'], 'podcast chapters are ordered by start time');
assert_equals('https://example.test/topic', $podcastChapterPayload['chapters'][1]['url'], 'podcast chapter links use external URL normalizer');
assert_equals('host', normalizePodcastPersonRole('HOST'), 'podcast person role normalizer accepts supported role');
assert_equals('guest', normalizePodcastPersonRole('inventor'), 'podcast person role normalizer falls back safely');
assert_equals('Tvůrčí tým', podcastPersonGroupLabel('crew'), 'podcast person group exposes Czech label');
assert_equals('', normalizePodcastPersonUrl('javascript:alert(1)'), 'podcast person URL rejects unsafe scheme');
$podcastPersonTag = podcastPersonFeedTag([
    'name' => 'Jana Nováková',
    'role_key' => 'guest',
    'group_key' => 'cast',
    'profile_url' => 'https://example.test/jana',
]);
assert_contains('<podcast:person role="guest" group="cast" href="https://example.test/jana">', $podcastPersonTag, 'podcast person feed tag exposes normalized attributes');
assert_contains('Jana Nováková</podcast:person>', $podcastPersonTag, 'podcast person feed tag keeps UTF-8 name');
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

test_section('widget metadata semantics');

$downloadWidgetMeta = widgetMetaText([
    'Software',
    '0.10.1',
    '17. června 2026, 00:00',
    'Android',
]);
assert_equals(
    'Software 0.10.1 17. června 2026, 00:00 Android',
    $downloadWidgetMeta,
    'download widget metadata keeps text separators for screen readers'
);
assert_equals('Software Android', widgetMetaText([' Software ', '', ' Android ']), 'widget metadata skips empty values and trims labels');

test_section('podcast episode accessibility metadata');

$podcastEpisodeWithTranscriptOnly = [
    'id' => 42,
    'show_id' => 7,
    'show_slug' => 'testovaci-porad',
    'title' => 'Epizoda s přepisem',
    'slug' => 'epizoda-s-prepisem',
    'description' => '',
    'transcript' => '<p>Plný přepis epizody slouží jako textová alternativa audia.</p>',
    'audio_file' => '',
    'audio_url' => '',
    'image_file' => '',
    'subtitle' => '',
    'created_at' => '2026-07-04 12:00:00',
    'status' => 'published',
];
assert_equals(
    'Plný přepis epizody slouží jako textová alternativa audia.',
    podcastEpisodeExcerpt($podcastEpisodeWithTranscriptOnly, 120),
    'podcast episode excerpt falls back to transcript when description is empty'
);
$hydratedPodcastEpisode = hydratePodcastEpisodePresentation($podcastEpisodeWithTranscriptOnly);
assert_equals(
    'Plný přepis epizody slouží jako textová alternativa audia.',
    $hydratedPodcastEpisode['transcript_plain'],
    'podcast episode hydration exposes plain transcript text'
);
assert_equals(
    'Plný přepis epizody slouží jako textová alternativa audia.',
    $hydratedPodcastEpisode['feed_summary'],
    'podcast episode feed summary can fall back to transcript'
);
assert_equals(
    '<p>Plný přepis epizody slouží jako textová alternativa audia.</p>',
    podcastEpisodeRevisionSnapshot($podcastEpisodeWithTranscriptOnly)['transcript'],
    'podcast episode revision snapshot keeps transcript content'
);
$podcastHealthIssues = podcastFeedHealthIssues(
    ['feed_guid' => 'show-guid', 'title' => 'Pořad', 'description' => 'Popis', 'author' => 'Autor', 'category' => 'Technology', 'cover_image' => 'cover.webp'],
    [[
        'id' => 42,
        'title' => 'Externí epizoda',
        'feed_guid' => 'episode-guid',
        'audio_file' => '',
        'audio_url' => 'https://cdn.example.test/episode.mp3',
        'audio_mime_type' => '',
        'audio_file_size' => 0,
        'transcript' => 'Textový přepis.',
    ]]
);
assert_equals(2, count($podcastHealthIssues), 'podcast feed health reports missing external enclosure metadata');
assert_contains('MIME typ', $podcastHealthIssues[0]['message'], 'podcast feed health explains missing external MIME type');

test_section('form field autocomplete purpose');

assert_equals('email', formFieldAutocompletePurpose('email', 'email_pro_odpoved', 'E-mail pro odpověď'), 'email fields expose email autocomplete');
assert_equals('tel', formFieldAutocompletePurpose('tel', 'telefon', 'Telefon'), 'tel fields expose tel autocomplete');
assert_equals('url', formFieldAutocompletePurpose('url', 'adresa_stranky', 'Adresa stránky'), 'url fields expose url autocomplete');
assert_equals('name', formFieldAutocompletePurpose('text', 'full_name', 'Jméno'), 'full name text fields expose name autocomplete');
assert_equals('given-name', formFieldAutocompletePurpose('text', 'first_name', 'Křestní jméno'), 'first name text fields expose given-name autocomplete');
assert_equals('family-name', formFieldAutocompletePurpose('text', 'last_name', 'Příjmení'), 'last name text fields expose family-name autocomplete');
assert_equals('organization', formFieldAutocompletePurpose('text', 'firma', 'Firma'), 'organization text fields expose organization autocomplete');
assert_equals('organization-title', formFieldAutocompletePurpose('text', 'job_title', 'Pracovní pozice'), 'job title text fields expose organization-title autocomplete');
assert_equals('organization-title', formFieldAutocompletePurpose('text', 'organization_title', 'Organization title'), 'organization title text fields prefer organization-title over organization autocomplete');
assert_equals('street-address', formFieldAutocompletePurpose('text', 'street_address', 'Ulice a číslo'), 'street address text fields expose street-address autocomplete');
assert_equals('address-line1', formFieldAutocompletePurpose('text', 'address_line_1', 'Adresa řádek 1'), 'address line 1 text fields expose address-line1 autocomplete');
assert_equals('address-line2', formFieldAutocompletePurpose('text', 'address_line_2', 'Adresa řádek 2'), 'address line 2 text fields expose address-line2 autocomplete');
assert_equals('postal-code', formFieldAutocompletePurpose('text', 'postal_code', 'PSČ'), 'postal code text fields expose postal-code autocomplete');
assert_equals('address-level2', formFieldAutocompletePurpose('text', 'city', 'Město'), 'city text fields expose address-level2 autocomplete');
assert_equals('address-level1', formFieldAutocompletePurpose('text', 'region', 'Kraj'), 'region text fields expose address-level1 autocomplete');
assert_equals('country-name', formFieldAutocompletePurpose('text', 'country', 'Země'), 'country text fields expose country-name autocomplete');
assert_equals('bday', formFieldAutocompletePurpose('date', 'birth_date', 'Datum narození'), 'birth date fields expose bday autocomplete');
assert_equals('', formFieldAutocompletePurpose('text', 'web_address', 'Adresa webu'), 'web address text fields do not get postal address autocomplete');
assert_equals('', formFieldAutocompletePurpose('text', 'username', 'Uživatelské jméno'), 'username-like text fields are not treated as personal name');
assert_equals('', formFieldAutocompletePurpose('text', 'tema_pozadavku', 'Téma požadavku'), 'generic text fields do not get autocomplete');

test_section('Form Builder error suggestions');

assert_equals(
    'Vyplňte pole „Jméno“. Pokud si nejste jistí, použijte nápovědu u pole.',
    publicFormRequiredFieldErrorMessage('Jméno', 'text'),
    'required text field suggests using the field help'
);
assert_equals(
    'Zaškrtněte pole „Souhlas“, aby bylo možné formulář odeslat.',
    publicFormRequiredFieldErrorMessage('Souhlas', 'consent'),
    'required consent field suggests checking the field'
);
assert_equals(
    'Nahrajte soubor v poli „Příloha“. Řiďte se povoleným typem a velikostí uvedenou u pole.',
    publicFormRequiredFieldErrorMessage('Příloha', 'file'),
    'required file field suggests upload type and size guidance'
);
assert_equals(
    'Vyberte možnost v poli „Typ požadavku“.',
    publicFormRequiredFieldErrorMessage('Typ požadavku', 'select'),
    'required select field suggests choosing an offered option'
);
assert_equals(
    'Zadejte do pole „E-mail“ úplnou e-mailovou adresu ve tvaru jmeno@example.cz.',
    publicFormEmailFieldErrorMessage('E-mail'),
    'email field suggests a complete address example'
);
assert_equals(
    'Zadejte do pole „Web projektu“ úplnou adresu začínající http:// nebo https:// bez přihlašovacích údajů.',
    publicFormUrlFieldErrorMessage('Web projektu'),
    'URL field suggests explicit http/https address without credentials'
);
assert_equals(
    'Vyberte v poli „Typ požadavku“ jen možnost nabídnutou formulářem.',
    publicFormOptionFieldErrorMessage('Typ požadavku'),
    'option field suggests selecting offered choices only'
);
assert_equals(
    'Pole „Příloha“: Vybraný typ souboru není v tomto poli povolený. Zkontrolujte povolený typ a velikost souboru uvedenou u pole.',
    publicFormUploadFieldErrorMessage('Příloha', 'Vybraný typ souboru není v tomto poli povolený.'),
    'upload field appends actionable type and size guidance'
);

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

test_section('stats content trends helpers');

assert_equals('/snd/clanek', statsNormalizePagePath('/snd/clanek?token=secret#detail'), 'stats content path drops query and fragment');
assert_equals('/snd/clanek', statsNormalizePagePath('https://pvlcek.cz/snd/clanek?token=secret#detail'), 'stats content path keeps only absolute URL path');
assert_equals('/', statsNormalizePagePath("bad\npath"), 'stats content path rejects control characters');
assert_equals(hash('sha256', '/snd/clanek'), statsContentPathHash('/snd/clanek?token=secret'), 'stats content path hash is based on normalized path');
assert_equals('food', statsPageTypeModuleKey('food_card'), 'food card views map to food module');
assert_equals('gallery', statsPageTypeModuleKey('gallery_photo'), 'gallery photo views map to gallery module');
assert_equals('forms', statsPageTypeModuleKey('form'), 'public form views map to forms module');
assert_equals('', statsPageTypeModuleKey('token_endpoint'), 'unknown or token endpoints do not map to content trends');
assert_equals(['2026-04-07', '2026-04-09'], statsPreviousDateRange('2026-04-10', '2026-04-12'), 'previous period has the same inclusive length');
assert_equals('all', statsNormalizeContentModuleFilter('definitely-not-a-module'), 'unknown content module filter falls back to all');

test_section('media collection metadata helpers');

assert_equals('archiv-akci', normalizeMediaCollectionSlug('Archiv akcí'), 'media collection slug keeps Czech diacritics normalized');
assert_equals('kolekce', normalizeMediaCollectionSlug(''), 'empty media collection slug falls back to kolekce');
assert_equals('https://creativecommons.org/licenses/by/4.0/', normalizeMediaLicenseUrl('https://creativecommons.org/licenses/by/4.0/'), 'media license URL accepts HTTPS');
assert_equals('', normalizeMediaLicenseUrl('javascript:alert(1)'), 'media license URL rejects unsafe scheme');
assert_equals(
    'missing_alt',
    mediaMetadataStatus(['mime_type' => 'image/jpeg', 'alt_text' => '', 'credit' => 'Pavel Vlček', 'license_label' => 'CC BY 4.0']),
    'image media without alt text reports missing alt'
);
assert_equals(
    'missing_credit_license',
    mediaMetadataStatus(['mime_type' => 'application/pdf', 'alt_text' => '', 'credit' => '', 'license_label' => '']),
    'non-image media without rights metadata reports missing credit or license'
);
assert_equals(
    'complete',
    mediaMetadataStatus(['mime_type' => 'image/png', 'alt_text' => 'Logo projektu', 'credit' => 'Pavel Vlček', 'license_label' => 'Vlastní licence']),
    'media with alt and rights metadata is complete'
);

test_section('gallery photo metadata helpers');

$galleryPhotoWithAlt = [
    'id' => 10,
    'filename' => 'hodiny.jpg',
    'title' => 'Kukací hodiny',
    'slug' => 'kukaci-hodiny',
    'alt_text' => 'Kukací hodiny zavěšené na stěně.',
    'caption' => 'Hodiny po opravě',
    'credit' => 'Pavel Vlček',
    'license_label' => 'CC BY 4.0',
    'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
    'taken_at' => '2026-04-01',
];
$hydratedGalleryPhoto = hydrateGalleryPhotoPresentation($galleryPhotoWithAlt);
assert_equals('Kukací hodiny zavěšené na stěně.', $hydratedGalleryPhoto['alt_text_resolved'], 'explicit gallery photo alt text wins');
assert_equals('Hodiny po opravě', $hydratedGalleryPhoto['caption_text'], 'gallery photo caption prefers explicit caption');
assert_equals('1. dubna 2026', $hydratedGalleryPhoto['taken_at_label'], 'gallery photo taken date has Czech label');
assert_equals('https://creativecommons.org/licenses/by/4.0/', normalizeGalleryLicenseUrl('https://creativecommons.org/licenses/by/4.0/'), 'gallery license URL accepts HTTPS');
assert_equals('', normalizeGalleryLicenseUrl('javascript:alert(1)'), 'gallery license URL rejects unsafe scheme');

$galleryPhotoWithoutAlt = hydrateGalleryPhotoPresentation([
    'id' => 11,
    'filename' => 'letecky-den.jpg',
    'title' => '',
    'slug' => 'letecky-den',
    'caption' => 'Letadlo při průletu nad letištěm.',
]);
assert_equals('Letadlo při průletu nad letištěm.', $galleryPhotoWithoutAlt['alt_text_resolved'], 'gallery photo alt falls back to caption');

$galleryPhotoStructuredData = galleryPhotoStructuredData($hydratedGalleryPhoto, [
    'id' => 2,
    'name' => 'Album test',
    'slug' => 'album-test',
]);
assert_contains('"caption":"Hodiny po opravě"', $galleryPhotoStructuredData, 'gallery structured data contains caption');
assert_contains('"creditText":"Pavel Vlček"', $galleryPhotoStructuredData, 'gallery structured data contains credit');
assert_contains('"license":"https://creativecommons.org/licenses/by/4.0/"', $galleryPhotoStructuredData, 'gallery structured data contains license URL');

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
assert_equals('vtt', mediaAllowedMimeMap()['text/vtt'] ?? '', 'media library accepts WebVTT caption files');

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

test_section('food structured menu helpers');

assert_equals([1, 7], normalizeFoodAllergenList(['1', '15', '7', '1', 'x']), 'food allergens keep only valid unique values');
assert_equals([1, 3], normalizeFoodAllergenList('1,3,99'), 'food allergens parse comma-separated storage value');
assert_equals(['vegetarian', 'spicy'], normalizeFoodDietaryFlags(['vegetarian', 'unknown', 'spicy', 'vegetarian']), 'food dietary flags keep only known unique values');
assert_equals('129 Kč', foodPriceLabel('129.00', 'CZK'), 'food price renders Czech currency label');
assert_equals('129,90 Kč (za porci)', foodPriceLabel('129.90', 'CZK', 'za porci'), 'food price renders decimal and note');
assert_equals('Dle domluvy', foodPriceLabel(null, 'CZK', 'Dle domluvy'), 'food price can be empty with note');
assert_equals('2026-07-02', normalizeFoodServingDate('2026-07-02'), 'food serving date accepts valid ISO date');
assert_equals('', normalizeFoodServingDate('2026-02-31'), 'food serving date rejects impossible date');
assert_equals('11:30', normalizeFoodServingTime('11:30'), 'food serving time accepts HH:MM');
assert_equals('', normalizeFoodServingTime('24:00'), 'food serving time rejects invalid hour');
assert_contains('2. července 2026', foodSectionServingLabel([
    'serving_date' => '2026-07-02',
    'serving_time_from' => '11:00',
    'serving_time_to' => '14:00',
    'serving_note' => 'Denní menu',
]), 'food serving label contains Czech date');
assert_contains('11:00–14:00', foodSectionServingLabel([
    'serving_date' => '2026-07-02',
    'serving_time_from' => '11:00',
    'serving_time_to' => '14:00',
]), 'food serving label contains time range');
assert_equals(2100, normalizeFoodNutritionIntegerInput('2100'), 'food nutrition integer accepts whole number');
assert_equals('12.50', normalizeFoodNutritionDecimalInput('12,5'), 'food nutrition decimal normalizes Czech comma');
assert_equals(false, normalizeFoodNutritionDecimalInput('-1'), 'food nutrition decimal rejects negative values');
assert_equals('12,50 g', foodNutritionDecimalLabel('12.50', 'g'), 'food nutrition decimal renders Czech label');

$foodSectionsFixture = [
    [
        'title' => 'Polévky',
        'serving_date' => '2026-07-02',
        'items' => [
            ['title' => 'Česnečka'],
            ['title' => 'Rajská polévka'],
        ],
    ],
    [
        'title' => 'Hlavní jídla',
        'items' => [
            ['title' => 'Smažený sýr'],
        ],
    ],
];
assert_true(foodCardHasStructuredItems($foodSectionsFixture), 'food structured menu detects existing items');
assert_equals(3, foodCardStructuredItemCount($foodSectionsFixture), 'food structured menu counts items across sections');
assert_equals(['Česnečka', 'Rajská polévka'], foodCardItemPreviewLabels($foodSectionsFixture, 2), 'food structured menu preview keeps order and limit');
assert_equals([
    [
        'title' => 'Polévky',
        'serving_date' => '2026-07-02',
        'items' => [
            ['title' => 'Česnečka'],
            ['title' => 'Rajská polévka'],
        ],
    ],
], foodFilterSectionsByServingDate([
    [
        'title' => 'Polévky',
        'serving_date' => '2026-07-02',
        'items' => [
            ['title' => 'Česnečka'],
            ['title' => 'Rajská polévka'],
        ],
    ],
    [
        'title' => 'Hlavní jídla',
        'serving_date' => '2026-07-03',
        'items' => [
            ['title' => 'Smažený sýr'],
        ],
    ],
], '2026-07-02'), 'food serving date filter keeps only matching sections');

$foodFilterState = normalizeFoodStructuredFilters([
    'dieta' => ['vegetarian', 'gluten_free', 'unknown'],
    'bez_alergenu' => ['1', '7', '99'],
    'pouze_dostupne' => '1',
]);
assert_equals(['vegetarian', 'gluten_free'], $foodFilterState['dietary_flags'], 'food filters normalize dietary flags');
assert_equals([1, 7], $foodFilterState['excluded_allergens'], 'food filters normalize excluded allergens');
assert_true($foodFilterState['active'], 'food filters detect active state');

$filteredFoodSectionsFixture = [
    [
        'title' => 'Hlavní jídla',
        'items' => [
            [
                'title' => 'Veganský salát',
                'dietary_flag_values' => ['vegan', 'gluten_free'],
                'allergen_values' => [],
                'is_available' => 1,
            ],
            [
                'title' => 'Smažený sýr',
                'dietary_flag_values' => ['vegetarian'],
                'allergen_values' => [1, 7],
                'is_available' => 1,
            ],
            [
                'title' => 'Archivní polévka',
                'dietary_flag_values' => ['vegan', 'gluten_free'],
                'allergen_values' => [],
                'is_available' => 0,
            ],
        ],
    ],
];
$matchingFoodSections = foodFilterStructuredSections($filteredFoodSectionsFixture, normalizeFoodStructuredFilters([
    'dieta' => ['vegan', 'gluten_free'],
    'bez_alergenu' => [1, 7],
    'pouze_dostupne' => '1',
]));
assert_equals(1, foodCardStructuredItemCount($matchingFoodSections), 'food filters keep only matching structured items');
assert_equals('Veganský salát', $matchingFoodSections[0]['items'][0]['title'], 'food filters keep expected item');
assert_equals([
    ['number' => 1, 'label' => 'Obiloviny obsahující lepek'],
    ['number' => 7, 'label' => 'Mléko'],
], foodStructuredAllergenLegend($filteredFoodSectionsFixture), 'food allergen legend lists used allergens');
$foodFilterSql = foodStructuredFilterExistsSql($foodFilterState);
assert_contains('EXISTS (SELECT 1 FROM cms_food_items fi', $foodFilterSql['sql'], 'food filter SQL uses structured items EXISTS');
assert_equals(['vegetarian', 'gluten_free', 1, 7], $foodFilterSql['params'], 'food filter SQL keeps stable parameter order');
assert_equals([
    ['label' => 'Porce', 'value' => '1 porce'],
    ['label' => 'Energie', 'value' => '2100 kJ'],
    ['label' => 'Energie', 'value' => '500 kcal'],
    ['label' => 'Bílkoviny', 'value' => '21,50 g'],
    ['label' => 'Sacharidy', 'value' => '30 g'],
    ['label' => 'Tuky', 'value' => '18,25 g'],
    ['label' => 'Sůl', 'value' => '2 g'],
], foodItemNutritionLabels([
    'portion_label' => '1 porce',
    'energy_kcal' => 500,
    'energy_kj' => 2100,
    'protein_g' => '21.50',
    'carbs_g' => '30.00',
    'fat_g' => '18.25',
    'salt_g' => '2.00',
]), 'food nutrition labels render filled values');
assert_equals('new', normalizeFoodOrderStatus('bad-status'), 'food order status falls back to new');
$foodOrderSnapshot = foodBuildOrderSnapshot([
    10 => [
        'id' => 10,
        'title' => 'Smažený sýr',
        'price_amount' => '129.90',
        'price_currency' => 'CZK',
        'price_note' => 'za porci',
    ],
    11 => [
        'id' => 11,
        'title' => 'Polévka',
        'price_amount' => null,
        'price_currency' => 'CZK',
        'price_note' => '',
    ],
], [
    10 => 2,
    11 => 1,
]);
assert_equals(2, count($foodOrderSnapshot['items']), 'food order snapshot keeps selected items');
assert_equals('259.80', $foodOrderSnapshot['total'], 'food order snapshot totals priced items');
assert_equals('Smažený sýr', $foodOrderSnapshot['items'][0]['item_title'], 'food order snapshot stores item title');

$foodStructuredData = foodCardStructuredData([
    'title' => 'Testovací lístek',
    'slug' => 'testovaci-listek',
    'description' => 'Krátký popis lístku',
    'sections' => [
        [
            'title' => 'Hlavní jídla',
            'description' => 'Teplá jídla',
            'items' => [
                [
                    'title' => 'Smažený sýr',
                    'description' => 'S bramborem a tatarkou',
                    'price_amount' => '129.00',
                    'price_currency' => 'CZK',
                    'is_available' => 1,
                    'has_nutrition' => true,
                    'energy_kcal' => 500,
                    'protein_g' => '21.50',
                    'image_url' => BASE_URL . '/uploads/media/syr.jpg',
                ],
            ],
        ],
    ],
]);
assert_contains('"hasMenuSection"', $foodStructuredData, 'food structured data contains MenuSection');
assert_contains('"@type":"MenuItem"', $foodStructuredData, 'food structured data contains MenuItem');
assert_contains('"price":"129.00"', $foodStructuredData, 'food structured data contains price');
assert_contains('"priceCurrency":"CZK"', $foodStructuredData, 'food structured data keeps ISO currency');
assert_contains('"image":"', $foodStructuredData, 'food structured data contains item image');
assert_contains('"@type":"NutritionInformation"', $foodStructuredData, 'food structured data contains nutrition information');

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
$audioTranscriptShortcodeHtml = renderContentShortcodes('[audio src="/uploads/audio.mp3" mime="audio/mpeg" transcript="/uploads/audio-prepis.html" transcript_label="Přepis rozhovoru"][/audio]');
assert_contains(
    '<p class="embedded-media__transcript"><a href="/uploads/audio-prepis.html">Přepis rozhovoru</a></p>',
    $audioTranscriptShortcodeHtml,
    'audio shortcode can render a transcript link'
);
$videoCaptionShortcodeHtml = renderContentShortcodes('[video src="/uploads/video.mp4" mime="video/mp4" captions="/uploads/video.cs.vtt" srclang="cs" caption_label="České titulky" descriptions="/uploads/video-popis.cs.vtt" description_label="Zvukový popis" transcript="/uploads/video-prepis.html"][/video]');
assert_contains(
    '<track kind="captions" src="/uploads/video.cs.vtt" srclang="cs" label="České titulky" default>',
    $videoCaptionShortcodeHtml,
    'video shortcode can render a WebVTT captions track'
);
assert_contains(
    '<track kind="descriptions" src="/uploads/video-popis.cs.vtt" srclang="cs" label="Zvukový popis">',
    $videoCaptionShortcodeHtml,
    'video shortcode can render a WebVTT audio description track'
);
assert_contains(
    '<p class="embedded-media__transcript"><a href="/uploads/video-prepis.html">Přepis videa</a></p>',
    $videoCaptionShortcodeHtml,
    'video shortcode can render a transcript link'
);
$invalidVideoCaptionHtml = renderContentVideoShortcode('/uploads/video.mp4', 'video/mp4', '', '/uploads/video.txt');
assert_true($invalidVideoCaptionHtml !== null, 'video shortcode still renders when optional caption URL is not WebVTT');
assert_false(str_contains((string)$invalidVideoCaptionHtml, '<track'), 'video shortcode ignores non-WebVTT caption URLs');
$invalidVideoDescriptionHtml = renderContentVideoShortcode('/uploads/video.mp4', 'video/mp4', '', '', 'cs', '', '', '', '/uploads/video-popis.txt');
assert_true($invalidVideoDescriptionHtml !== null, 'video shortcode still renders when optional description URL is not WebVTT');
assert_false(str_contains((string)$invalidVideoDescriptionHtml, 'kind="descriptions"'), 'video shortcode ignores non-WebVTT audio description URLs');
$youtubeTranscriptHtml = renderContentShortcodes('[video transcript="/uploads/youtube-prepis.html" transcript_label="Přepis záznamu"]https://www.youtube.com/watch?v=yIdGMYUmfgg[/video]');
assert_contains('Přepis záznamu', $youtubeTranscriptHtml, 'YouTube video shortcode can render a separate transcript link');

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

test_section('Podcast platformy a navigace epizod');

assert_equals('český rozhovor', normalizePodcastDiscoveryQuery("  český\nrozhovor  "), 'podcast discovery query normalized');
assert_equals('Technologie', normalizePodcastCategoryFilter('  Technologie  '), 'podcast category filter normalized');
assert_equals('spotify', normalizePodcastPlatformKey('SPOTIFY'), 'known podcast platform normalized');
assert_equals('other', normalizePodcastPlatformKey('unknown-service'), 'unknown podcast platform uses other');
assert_equals('Spotify', podcastPlatformLabel(['platform_key' => 'spotify', 'label' => '']), 'podcast platform default label');
assert_equals('Moje aplikace', podcastPlatformLabel(['platform_key' => 'other', 'label' => 'Moje aplikace']), 'podcast platform custom label');
assert_equals('https://example.test/show', normalizePodcastPlatformUrl('https://example.test/show'), 'safe podcast platform URL accepted');
assert_equals('', normalizePodcastPlatformUrl('javascript:alert(1)'), 'unsafe podcast platform URL rejected');
$podcastNeighborEpisodes = [
    ['id' => 10, 'title' => 'První'],
    ['id' => 20, 'title' => 'Druhá'],
    ['id' => 30, 'title' => 'Třetí'],
];
$podcastMiddleNeighbors = podcastEpisodeNeighbors($podcastNeighborEpisodes, 20);
assert_equals(10, (int)$podcastMiddleNeighbors['previous']['id'], 'podcast previous episode resolved');
assert_equals(30, (int)$podcastMiddleNeighbors['next']['id'], 'podcast next episode resolved');
assert_equals(null, podcastEpisodeNeighbors($podcastNeighborEpisodes, 10)['previous'], 'first podcast episode has no previous');
assert_equals(null, podcastEpisodeNeighbors($podcastNeighborEpisodes, 30)['next'], 'last podcast episode has no next');
assert_equals(['previous' => null, 'next' => null], podcastEpisodeNeighbors($podcastNeighborEpisodes, 99), 'missing podcast episode has no neighbors');

// ═══════════════════════════════════════════════════════════════════════════
// SOUHRN
// ═══════════════════════════════════════════════════════════════════════════

echo "\n══════════════════════════════════════\n";
echo "  Celkem: " . ($_TEST_PASS + $_TEST_FAIL) . " testů\n";
echo "  OK:     {$_TEST_PASS}\n";
echo "  FAIL:   {$_TEST_FAIL}\n";
echo "══════════════════════════════════════\n";

exit(test_failure_count() > 0 ? 1 : 0);
