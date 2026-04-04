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

// ─── 4. slugify() ───────────────────────────────────────────────────────────

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

// ─── 5. formatCzechDate() ───────────────────────────────────────────────────

test_section('formatCzechDate()');

assert_equals('15. ledna 2024, 10:30', formatCzechDate('2024-01-15 10:30:00'), 'standard datetime January');
assert_equals('25. prosince 2024, 00:00', formatCzechDate('2024-12-25 00:00:00'), 'December date');
assert_equals('', formatCzechDate(''), 'empty string returns empty (bugfix)');
assert_equals('not-a-date', formatCzechDate('not-a-date'), 'invalid date returns escaped input');

// ─── 6. readingTime() ───────────────────────────────────────────────────────

test_section('readingTime()');

assert_equals(1, readingTime(''), 'empty text = 1 min');
assert_equals(1, readingTime('hello'), 'single word = 1 min');
assert_equals(1, readingTime(str_repeat('word ', 200)), '200 words = 1 min');
assert_equals(2, readingTime(str_repeat('word ', 400)), '400 words = 2 min');

// ─── 7. paginateArray() ────────────────────────────────────────────────────

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

// ─── 8. formatFileSize() ───────────────────────────────────────────────────

test_section('formatFileSize()');

assert_equals('500 B', formatFileSize(500), 'bytes range');
assert_equals('1 kB', formatFileSize(1024), '1 kilobyte');
assert_equals('2 kB', formatFileSize(2048), '2 kilobytes');
assert_equals('1 MB', formatFileSize(1048576), '1 megabyte');
assert_equals('0 B', formatFileSize(0), 'zero bytes');

// ─── 9. mailSanitizeHeaderValue() ──────────────────────────────────────────

test_section('mailSanitizeHeaderValue()');

assert_equals('test Bcc: evil@hacker.com', mailSanitizeHeaderValue("test\r\nBcc: evil@hacker.com"), 'CRLF stripped (header injection prevention)');
assert_equals('Hello World', mailSanitizeHeaderValue('Hello World'), 'clean value passes through');
assert_equals('', mailSanitizeHeaderValue(''), 'empty string');

// ─── 10. base32Decode() + totpCalculate() + totpUri() ──────────────────────

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
assert_equals('', normalizeContentEmbedUrl("https://example.com\nevil"), 'URL with newline rejected');
assert_equals('', normalizeContentEmbedUrl('javascript:alert(1)'), 'javascript: rejected');

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
assert_equals($token1, $_SESSION['csrf_token_prev'] ?? '', 'previous token preserved');

// Simulovat multi-tab: predchozi token je stale validni
$_POST['csrf_token'] = $token1;
verifyCsrf(); // stary token = previous, musi projit

$token3 = csrfToken();
assert_true($token2 !== $token3, 'CSRF token rotated after prev-token verify');

// ─── 15. relativeTime() ─────────────────────────────────────────��───────────

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

exit($_TEST_FAIL > 0 ? 1 : 0);
