<?php

declare(strict_types=1);

namespace KoraRcEditorPublicationSelfTest;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

// Isolated production-code tests: no application bootstrap, credentials, sessions,
// HTTP requests or persistent database/filesystem writes.
define('BASE_URL', '');
define('KORA_VERSION', 'rc-selftest');

final class Response extends RuntimeException
{
    /** @param array<string, mixed> $data */
    public function __construct(public string $kind, public array $data = [])
    {
        parent::__construct($kind);
    }
}

function same(mixed $actual, mixed $expected, string $label): void
{
    $GLOBALS['checks']++;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

function source(string $path): string
{
    $contents = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (!is_string($contents)) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    return $contents;
}

function evaluate(string $code): mixed
{
    return eval('namespace ' . __NAMESPACE__ . '; use \PDO; use \PDOException; use \Throwable; use \DateTimeImmutable; use \DateTime; ' . $code);
}

function loadFunction(string $path, string $name): void
{
    if (preg_match('/^function ' . preg_quote($name, '/') . '\b.*?^\}/ms', source($path), $matches) !== 1) {
        throw new RuntimeException('Cannot extract production function ' . $name);
    }
    evaluate($matches[0]);
}

function runFile(string $path): Response
{
    $code = preg_replace('/^require_once [^\r\n]+;\R/m', '', source($path));
    ob_start();
    try {
        evaluate('?>' . $code);
        return new Response('html', ['html' => ob_get_contents()]);
    } catch (Response $response) {
        return $response;
    } finally {
        ob_end_clean();
    }
}

function header(string $value, bool $replace = true, int $responseCode = 0): void
{
    $GLOBALS['headers'][] = $value;
    if (str_starts_with($value, 'Location: ')) {
        throw new Response('redirect', ['location' => substr($value, 10)]);
    }
}
function headers_sent(): bool
{
    return false;
}
function db_connect(): mixed
{
    return $GLOBALS['testDb'];
}
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrfToken(): string
{
    return 'test-csrf';
}
function cspNonce(): string
{
    return 'test-nonce';
}
function checkMaintenanceMode(): void
{
}
function requireCapability(mixed ...$args): void
{
}
function requireLogin(mixed ...$args): void
{
}
function requireModuleEnabled(mixed ...$args): void
{
}
function verifyCsrf(): void
{
}
function isModuleEnabled(string $module): bool
{
    return $module !== 'places';
}
function getSetting(string $name, string $default = ''): string
{
    return $default;
}
function currentUserHasCapability(mixed ...$args): bool
{
    return true;
}
function currentUserDisplayName(): string
{
    return 'Test editor';
}
function currentUserId(): int
{
    return 1;
}
function canManageOwnNewsOnly(): bool
{
    return false;
}
/** @return null */
function acquireContentLock(mixed ...$args)
{
    return null;
}
function releaseContentLock(mixed ...$args): void
{
}
function adminHeader(string $title): void
{
    echo '<!doctype html><html lang="cs"><body><h1>' . h($title) . '</h1><main>';
}
function adminHtmlSnippetSupportMarkup(): string
{
    return '';
}
function renderAdminContentReferencePicker(mixed ...$args): void
{
}
function adminRenderContentLockRefreshScript(mixed ...$args): void
{
}
function newWindowLinkSrOnlySuffix(): string
{
    return '';
}
function internalRedirectTarget(string $target, string $fallback): string
{
    return $fallback;
}
function inputInt(string $source, string $key): ?int
{
    $input = $source === 'get' ? $_GET : $_POST;
    $value = filter_var($input[$key] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $value === false ? null : $value;
}
function pageSlug(string $slug): string
{
    return $slug;
}
function newsSlug(string $slug): string
{
    return $slug;
}
function eventSlug(string $slug): string
{
    return $slug;
}
function faqSlug(string $slug): string
{
    return $slug;
}
function podcastEpisodeSlug(string $slug): string
{
    return $slug;
}
function podcastShowSlug(string $slug): string
{
    return $slug;
}
function uniquePodcastEpisodeSlug(SaveDb $pdo, int $showId, string $slug, ?int $id): string
{
    return $slug;
}
function normalizePodcastEpisodeType(string $value): string
{
    return $value;
}
function normalizePodcastEpisodeExplicitMode(string $value): string
{
    return $value;
}
function normalizePodcastExplicitMode(string $value): string
{
    return $value;
}
function normalizePodcastShowType(string $value): string
{
    return $value;
}
function normalizePodcastEpisodeAudioUrl(string $value): string
{
    return $value;
}
function normalizePodcastAudioMimeType(string $value): string
{
    return $value;
}
function normalizePodcastAudioFileSize(string $value): int
{
    return (int)$value;
}
function normalizeEventKind(string $value): string
{
    return $value;
}
function normalizeEventRecurrenceFrequency(string $value): string
{
    return $value;
}
/** @return list<array<string, mixed>> */
function loadEventTypes(mixed ...$args): array
{
    return [['id' => 7, 'title' => 'Test type', 'legacy_key' => 'general', 'is_active' => 1]];
}
/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function hydrateEventPresentation(array $row): array
{
    return $row + ['image_url' => '', 'event_status_key' => 'upcoming'];
}
/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function hydrateNewsPresentation(array $row): array
{
    return $row;
}
/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function hydratePodcastShowPresentation(array $row): array
{
    return $row + ['cover_url' => '', 'public_path' => '/podcast/test', 'is_public' => false];
}
/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function hydratePodcastEpisodePresentation(array $row): array
{
    return $row + ['image_url' => '', 'public_path' => '/podcast/test/episode'];
}
/** @param array<string, mixed> $row */
function pagePublicPath(array $row): string
{
    return '/page.php?slug=' . ($row['slug'] ?? 'test');
}
/** @param array<string, mixed> $row */
function newsPublicPath(array $row): string
{
    return '/news/' . $row['slug'];
}
/** @param array<string, mixed> $row */
function newsPublicUrl(array $row): string
{
    return newsPublicPath($row);
}
/**
 * @param array<string, mixed> $row
 * @param array<string, mixed> $query
 */
function eventPublicPath(array $row, array $query = []): string
{
    return '/events/' . $row['slug'];
}
/** @param array<string, mixed> $row */
function eventPublicUrl(array $row): string
{
    return eventPublicPath($row);
}
/** @param array<string, mixed> $row */
function podcastEpisodePublicPath(array $row): string
{
    return '/podcast/test/' . $row['slug'];
}
/** @param array<string, mixed> $row */
function blogIndexPath(array $row): string
{
    return '/' . $row['slug'];
}
function siteUrl(string $path): string
{
    return $path;
}
/** @param array<string, mixed> $row */
function eventStructuredData(array $row): string
{
    return '';
}
function eventExcerpt(mixed ...$args): string
{
    return '';
}
function newsExcerpt(mixed ...$args): string
{
    return '';
}
function trackPageView(mixed ...$args): void
{
    $GLOBALS['views']++;
}
/** @param array<string, mixed> $args */
function renderPublicNotFoundPage(array $args): void
{
    sendNoStoreNoIndexHeaders();
    throw new Response('not-found');
}
/** @param array<string, mixed> $args */
function renderPublicPage(array $args): void
{
    throw new Response('page', $args);
}
/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function podcastEpisodeRevisionSnapshot(array $row): array
{
    return $row;
}
function saveRevision(mixed ...$args): void
{
    $GLOBALS['trace'][] = 'revision';
}
function upsertPathRedirect(mixed ...$args): void
{
    $GLOBALS['trace'][] = 'path-redirect';
}
function logAction(mixed ...$args): void
{
    $GLOBALS['trace'][] = 'audit';
}
function notifyPendingContent(mixed ...$args): void
{
    $GLOBALS['trace'][] = 'notify';
}
function koraLog(mixed ...$args): void
{
}
function podcastAudioMimeType(string $file): string
{
    return 'audio/mpeg';
}
/** @param array<string, mixed> $row */
function podcastEpisodeEnclosureLength(array $row): int
{
    return 42;
}
function newPodcastFeedGuid(): string
{
    return 'test-guid';
}
function deletePodcastAudioFile(string $file): void
{
    $GLOBALS['trace'][] = 'delete:' . $file;
}
function deletePodcastEpisodeImageFile(string $file): void
{
    $GLOBALS['trace'][] = 'delete:' . $file;
}
/**
 * @param array<string, mixed> $file
 * @return array{filename: string, uploaded: bool, error: string}
 */
function uploadPodcastAudioFile(array $file, string $existing = ''): array
{
    $GLOBALS['trace'][] = 'upload-audio';
    same($existing, '', 'replacement upload must not delete existing audio');
    return ['filename' => $file !== [] ? 'new.mp3' : '', 'uploaded' => $file !== [], 'error' => ''];
}
/**
 * @param array<string, mixed> $file
 * @return array{filename: string, uploaded: bool, error: string}
 */
function uploadPodcastEpisodeImage(array $file, string $existing = ''): array
{
    $GLOBALS['trace'][] = 'upload-image';
    same($existing, '', 'replacement upload must not delete existing image');
    return ['filename' => $file !== [] ? 'new.png' : '', 'uploaded' => $file !== [],
        'error' => ($file['name'] ?? '') === 'invalid.txt' ? 'invalid image' : ''];
}

final class SaveDb
{
    public bool $transaction = false;
    public bool $fail = false;
    /** @var list<mixed> */
    public array $saved = [];
    /** @var array<string, mixed> */
    public array $existing = ['id' => 1, 'show_id' => 1, 'slug' => 'old', 'status' => 'published',
        'audio_file' => 'old.mp3', 'image_file' => 'old.png'];
    public function prepare(string $sql): object
    {
        return new class ($this, $sql) {
            public function __construct(private SaveDb $db, private string $sql)
            {
            }
            /** @param list<mixed> $params */
            public function execute(array $params): bool
            {
                if (str_starts_with($this->sql, 'UPDATE cms_podcasts') || str_starts_with($this->sql, 'INSERT INTO cms_podcasts')) {
                    $GLOBALS['trace'][] = 'update';
                    if ($this->db->fail) {
                        throw new RuntimeException('Simulated persistence failure');
                    }
                    $this->db->saved = $params;
                }
                return true;
            }
            /** @return array<string, mixed> */
            public function fetch(): array
            {
                return str_contains($this->sql, 'cms_podcast_shows')
                    ? ['id' => 1, 'slug' => 'test', 'title' => 'Test show']
                    : $this->db->existing;
            }
        };
    }
    public function beginTransaction(): void
    {
        $this->transaction = true;
        $GLOBALS['trace'][] = 'begin';
    }
    public function lastInsertId(): string
    {
        return '2';
    }
    public function inTransaction(): bool
    {
        return $this->transaction;
    }
    public function rollBack(): void
    {
        $this->saved = [];
        $this->transaction = false;
        $GLOBALS['trace'][] = 'rollback';
    }
    public function commit(): void
    {
        $this->transaction = false;
        $GLOBALS['trace'][] = 'commit';
    }
}

function memoryDb(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-09-05 12:00:00', 0);
    $pdo->sqliteCreateFunction('CONCAT', static fn (...$parts): string => implode('', $parts));
    $pdo->exec("CREATE TABLE cms_blogs (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, description TEXT);
        INSERT INTO cms_blogs VALUES (1, 'blog', 'Blog', '');
        CREATE TABLE cms_pages (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, blog_id INT, content TEXT,
            status TEXT, is_published INT, deleted_at TEXT, publish_at TEXT, unpublish_at TEXT, preview_token TEXT);
        CREATE TABLE cms_users (id INT, nickname TEXT, first_name TEXT, last_name TEXT, email TEXT,
            author_public_enabled INT, author_slug TEXT, role TEXT);
        CREATE TABLE cms_news (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, content TEXT, meta_title TEXT,
            meta_description TEXT, created_at TEXT, updated_at TEXT, author_id INT, status TEXT,
            deleted_at TEXT, publish_at TEXT, unpublish_at TEXT, preview_token TEXT);
        CREATE TABLE cms_events (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, event_type_id INT, place_id INT,
            status TEXT, is_published INT, deleted_at TEXT, publish_at TEXT, unpublish_at TEXT, preview_token TEXT);
        CREATE TABLE cms_event_types (id INT, title TEXT, slug TEXT, legacy_key TEXT, description TEXT,
            meta_title TEXT, meta_description TEXT, is_active INT);
        CREATE TABLE cms_places (id INT, name TEXT, slug TEXT, address TEXT, locality TEXT, latitude TEXT,
            longitude TEXT, status TEXT, is_published INT, deleted_at TEXT);
        CREATE TABLE cms_faq_categories (id INT, name TEXT, sort_order INT);
        INSERT INTO cms_faq_categories VALUES (8, 'Category', 1);
        CREATE TABLE cms_podcast_shows (id INT, slug TEXT, title TEXT, description TEXT, status TEXT,
            category TEXT, cover_image TEXT);
        INSERT INTO cms_podcast_shows VALUES (1, 'test', 'Test', '', 'draft', '', '');
        INSERT INTO cms_pages VALUES (1, 'Page', 'fixture', NULL, 'Private body', 'published', 1, NULL,
            NULL, NULL, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        INSERT INTO cms_news VALUES (1, 'News', 'fixture', 'Private body', '', '', '2026-09-01', '2026-09-01',
            NULL, 'published', NULL, NULL, NULL, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        INSERT INTO cms_events VALUES (1, 'Event', 'fixture', NULL, NULL, 'published', 1, NULL,
            NULL, NULL, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');");
    return $pdo;
}

/** @param array<string, mixed> $get */
function publicRequest(string $path, array $get): Response
{
    $_GET = $get;
    $_SESSION = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $GLOBALS['headers'] = [];
    $GLOBALS['views'] = 0;
    return runFile($path);
}

function protectedPreview(Response $result, string $label): void
{
    same($result->kind, 'page', $label . ' renders');
    same($GLOBALS['views'], 0, $label . ' does not track views');
    foreach (['Cache-Control: no-store, max-age=0', 'X-Robots-Tag: noindex, nofollow, noarchive', 'Referrer-Policy: no-referrer'] as $expected) {
        same(in_array($expected, $GLOBALS['headers'], true), true, $label . ' ' . $expected);
    }
}

/** @return array{array<string, string|bool>, \DOMDocument} */
function formFields(string $html): array
{
    $doc = new \DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $fields = [];
    foreach ($doc->getElementsByTagName('*') as $element) {
        foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
            foreach (preg_split('/\s+/', trim($element->getAttribute($attribute))) ?: [] as $id) {
                if ($id !== '') {
                    same($doc->getElementById($id) !== null, true, 'existing ' . $attribute . ' target ' . $id);
                }
            }
        }
        $name = $element->getAttribute('name');
        if ($name === '') {
            continue;
        }
        if ($element->localName === 'textarea') {
            $fields[$name] = $element->textContent;
        } elseif ($element->localName === 'input') {
            $fields[$name] = $element->getAttribute('type') === 'checkbox'
                ? $element->hasAttribute('checked') : $element->getAttribute('value');
        } elseif ($element->localName === 'select') {
            foreach ($element->getElementsByTagName('option') as $option) {
                if (!isset($fields[$name]) || $option->hasAttribute('selected')) {
                    $fields[$name] = $option->getAttribute('value');
                }
            }
        }
    }
    return [$fields, $doc];
}

/** @return list<string> */
function operationTrace(): array
{
    return $GLOBALS['trace'];
}

try {
    $GLOBALS['checks'] = 0;
    $GLOBALS['trace'] = [];
    foreach (['adminEditorFormFields', 'adminEditorFormFlashStore', 'adminEditorFormFlashTake',
        'adminEditorDateTimeValue', 'adminFieldHasError', 'adminFieldErrorId', 'adminFieldAttributes',
        'adminRenderFieldError', 'adminFooter'] as $function) {
        loadFunction('admin/layout.php', $function);
    }
    loadFunction('lib/ui.php', 'validateDateTimeLocal');
    foreach (['isValidArticlePreviewToken', 'newsPublicVisibilitySql', 'eventPublicVisibilitySql'] as $function) {
        loadFunction('lib/presentation.php', $function);
    }
    loadFunction('auth.php', 'sendNoStoreNoIndexHeaders');

    if (($argv[1] ?? '') === '--autosave-js') {
        ob_start();
        adminFooter();
        $html = ob_get_clean();
        preg_match_all('~<script[^>]*>(.*?)</script>~s', $html, $scripts);
        foreach ($scripts[1] as $script) {
            if (str_contains($script, 'function gather()')) {
                echo $script;
                exit(0);
            }
        }
        throw new RuntimeException('Missing production autosave script');
    }

    set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
        throw new \ErrorException($message, 0, $level, $file, $line);
    });

    $pdo = memoryDb();
    $GLOBALS['testDb'] = $pdo;
    $GLOBALS['current_blog'] = ['id' => 1, 'slug' => 'blog', 'name' => 'Blog', 'description' => ''];
    foreach (['page.php' => null, 'blog/page.php' => 1] as $path => $blogId) {
        $pdo->prepare('UPDATE cms_pages SET blog_id = ?, status = ?, publish_at = ?, unpublish_at = ?, deleted_at = NULL')
            ->execute([$blogId, 'published', '2026-09-06 12:00:00', null]);
        $get = $blogId === null ? ['slug' => 'fixture'] : ['page_slug' => 'fixture'];
        same(publicRequest($path, $get)->kind, 'not-found', $path . ' scheduled page hidden');
        protectedPreview(publicRequest($path, $get + ['preview' => str_repeat('a', 32)]), $path . ' scheduled preview');
        $pdo->exec("UPDATE cms_pages SET publish_at = NULL, unpublish_at = '2026-09-05 12:00:00'");
        same(publicRequest($path, $get)->kind, 'not-found', $path . ' expired at exact boundary');
        protectedPreview(publicRequest($path, $get + ['preview' => str_repeat('a', 32)]), $path . ' expired preview');
        $pdo->exec("UPDATE cms_pages SET status = 'draft'");
        protectedPreview(publicRequest($path, $get + ['preview' => str_repeat('a', 32)]), $path . ' draft preview');
        foreach (['bad', str_repeat('A', 32), str_repeat('b', 32), ['not-a-token']] as $token) {
            same(publicRequest($path, $get + ['preview' => $token])->kind, 'not-found', $path . ' invalid preview');
        }
        $pdo->exec("UPDATE cms_pages SET deleted_at = '2026-09-05'");
        same(publicRequest($path, $get + ['preview' => str_repeat('a', 32)])->kind, 'not-found', $path . ' trashed preview');
        $pdo->exec("UPDATE cms_pages SET deleted_at = NULL, status = 'published', unpublish_at = NULL, publish_at = '2026-09-05 12:00:00'");
        same(publicRequest($path, $get)->kind, 'page', $path . ' public at exact publication boundary');
    }
    $GLOBALS['current_blog']['id'] = 2;
    same(publicRequest('blog/page.php', ['page_slug' => 'fixture', 'preview' => str_repeat('a', 32)])->kind, 'not-found', 'blog preview stays blog scoped');

    foreach (['news/article.php' => 'cms_news', 'events/event.php' => 'cms_events'] as $path => $table) {
        $pdo->exec("UPDATE {$table} SET status = 'draft', unpublish_at = '2026-09-01'");
        foreach ([['slug' => 'fixture'], ['id' => '1']] as $get) {
            protectedPreview(publicRequest($path, $get + ['preview' => str_repeat('a', 32)]), $path . ' preview without token-losing redirect');
            foreach (['bad', str_repeat('A', 32), str_repeat('b', 32), ['not-a-token']] as $token) {
                same(publicRequest($path, $get + ['preview' => $token])->kind, 'not-found', $path . ' invalid token');
            }
            $pdo->exec("UPDATE {$table} SET deleted_at = '2026-09-05'");
            same(publicRequest($path, $get + ['preview' => str_repeat('a', 32)])->kind, 'not-found', $path . ' deleted preview');
            $pdo->exec("UPDATE {$table} SET deleted_at = NULL");
        }
        same(publicRequest($path, ['slug' => 'fixture'])->kind, 'not-found', $path . ' no token no draft access');
    }

    foreach (['delete-invalid-image', 'replace-invalid-image', 'invalid-date', 'db-failure', 'replace-success', 'delete-success'] as $case) {
        $db = new SaveDb();
        $db->fail = $case === 'db-failure';
        $GLOBALS['testDb'] = $db;
        $GLOBALS['trace'] = [];
        $_POST = ['id' => '1', 'show_id' => '1', 'title' => 'Edited', 'slug' => 'edited', 'article_status' => 'published'];
        $_FILES = [];
        if (str_starts_with($case, 'delete')) {
            $_POST['audio_file_delete'] = '1';
            $_POST['image_file_delete'] = '1';
        }
        if (in_array($case, ['replace-invalid-image', 'db-failure', 'replace-success', 'invalid-date'], true)) {
            $_FILES = ['audio_file' => ['name' => 'new.mp3'], 'image_file' => ['name' => 'new.png']];
        }
        if (str_contains($case, 'invalid-image')) {
            $_FILES['image_file'] = ['name' => 'invalid.txt'];
        }
        if ($case === 'invalid-date') {
            $_POST['publish_at'] = '2026-02-30T12:00';
        }
        $result = runFile('admin/podcast_save.php');
        $trace = operationTrace();
        same($result->kind, 'redirect', $case . ' PRG');
        $success = str_ends_with($case, 'success');
        foreach (['old.mp3', 'old.png'] as $oldFile) {
            same(in_array('delete:' . $oldFile, $trace, true), $success, $case . ' original ' . $oldFile);
            if ($success) {
                same(array_search('commit', $trace, true) < array_search('delete:' . $oldFile, $trace, true), true, 'old media removed after commit');
            }
        }
        if (!$success) {
            same($db->saved, [], $case . ' unchanged persistent references');
        } else {
            same($db->saved[5], $case === 'replace-success' ? 'new.mp3' : '', $case . ' persisted audio reference');
            same($db->saved[6], $case === 'replace-success' ? 'new.png' : '', $case . ' persisted artwork reference');
        }
        if ($case === 'invalid-date') {
            same($trace, [], 'invalid date rejected before uploads');
        }
        if (in_array($case, ['replace-invalid-image', 'db-failure'], true)) {
            same(in_array('delete:new.mp3', $trace, true), true, $case . ' cleans staged audio');
        }
        if ($case === 'db-failure') {
            same(in_array('delete:new.png', $trace, true), true, 'failed save cleans staged artwork');
        }
    }

    foreach ([false, true] as $fail) {
        $db = new SaveDb();
        $db->fail = $fail;
        $GLOBALS['testDb'] = $db;
        $GLOBALS['trace'] = [];
        $_POST = ['show_id' => '1', 'title' => 'New pending', 'slug' => 'new-pending', 'article_status' => 'pending'];
        $_FILES = [];
        same(runFile('admin/podcast_save.php')->kind, 'redirect', 'new podcast PRG');
        $trace = operationTrace();
        same(in_array('notify', $trace, true), !$fail, 'pending notification only on successful insert');
        if (!$fail) {
            same(array_search('commit', $trace, true) < array_search('notify', $trace, true), true, 'pending notification after commit');
        }
    }

    $GLOBALS['testDb'] = $pdo;
    foreach (['news', 'event', 'faq', 'podcast', 'podcast_show'] as $editor) {
        $_SESSION = [];
        $_FILES = [];
        $_POST = ['title' => '', 'question' => '', 'slug' => 'retained-slug', 'content' => '<p>Retained body & text</p>',
            'answer' => 'Retained answer', 'description' => 'Retained description', 'excerpt' => 'Retained intro',
            'transcript' => 'Retained transcript', 'admin_note' => 'Retained note', 'article_status' => 'draft',
            'show_id' => '1', 'event_date' => '2026-10-11', 'event_time' => '13:14',
            'event_end_date' => '2026-10-12', 'event_end_time' => '15:16',
            'recurrence_frequency' => 'weekly', 'recurrence_interval' => '3', 'recurrence_count' => '5',
            'owner_email' => 'owner@example.test', 'feed_complete' => '1', 'block_from_feed' => '1',
            'csrf_token' => 'must-not-persist', 'redirect' => 'https://untrusted.test/', 'preview_token' => 'must-not-persist'];
        $post = $_POST;
        $result = runFile('admin/' . $editor . '_save.php');
        same($result->kind, 'redirect', $editor . ' rejected PRG');
        $scope = $editor === 'podcast' ? 1 : null;
        same(adminEditorFormFlashTake($editor, 999, $scope), [], 'rejected draft stays entity scoped');
        if ($scope !== null) {
            same(adminEditorFormFlashTake($editor, null, 2), [], 'episode draft stays show scoped');
        }
        $flash = adminEditorFormFlashTake($editor, null, $scope);
        foreach (['csrf_token', 'redirect', 'preview_token', 'id', 'show_id'] as $forbidden) {
            same(array_key_exists($forbidden, $flash), false, $editor . ' excludes ' . $forbidden);
        }
        foreach (adminEditorFormFields()[$editor] as $field) {
            if (array_key_exists($field, $post)) {
                same($flash[$field === 'article_status' ? 'status' : $field], $post[$field], $editor . ' retains ' . $field);
            }
        }
        adminEditorFormFlashStore($editor, null, $post, $scope);
        $_GET = ['err' => 'required', 'show_id' => '1'];
        $render = runFile('admin/' . $editor . '_form.php');
        same($render->kind, 'html', $editor . ' renders rejected form');
        [$fields, $doc] = formFields($render->data['html']);
        same($fields['slug'], 'retained-slug', $editor . ' renders rejected slug');
        same(isset($fields['id']), false, $editor . ' rejected new form remains new');
        foreach (['content', 'answer', 'description', 'excerpt', 'transcript', 'admin_note'] as $field) {
            if (in_array($field, adminEditorFormFields()[$editor], true)) {
                same($fields[$field], $post[$field], $editor . ' renders ' . $field);
            }
        }
        if (in_array($editor, ['faq', 'event', 'podcast_show'], true)) {
            same($fields['is_published'], false, $editor . ' unchecked publication retained');
        }
        if ($editor === 'event') {
            foreach (['event_date', 'event_time', 'event_end_date', 'event_end_time', 'recurrence_frequency', 'recurrence_interval', 'recurrence_count'] as $field) {
                same($fields[$field], $post[$field], 'event rendered ' . $field);
            }
        }
        same(adminEditorFormFlashTake($editor, null, $scope), [], $editor . ' flash consumed once');
    }

    $_POST = ['title' => 'Retained', 'content' => 'Retained body', 'publish_at' => '2026-02-30T12:00'];
    same(runFile('admin/news_save.php')->kind, 'redirect', 'news invalid calendar date rejected');
    $_GET = ['err' => 'publish_at'];
    [$fields, $doc] = formFields(runFile('admin/news_form.php')->data['html']);
    same($fields['title'], 'Retained', 'news date rejection retains title');
    same($doc->getElementById('form-error')?->getAttribute('role'), 'alert', 'news date error announced');
    same($doc->getElementById('publish_at')?->getAttribute('aria-invalid'), 'true', 'news invalid date field');
    same($doc->getElementById('publish_at-error') !== null, true, 'news date error has real target');

    $node = getenv('KORA_RC_NODE') ?: 'node';
    $process = proc_open(
        [$node, __DIR__ . '/rc_editor_autosave_selftest.js', PHP_BINARY],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start Node autosave regression');
    }
    fclose($pipes[0]);
    $jsOutput = stream_get_contents($pipes[1]);
    $jsError = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    same(proc_close($process), 0, 'production autosave JS: ' . $jsError);
    echo 'PASS rc_editor_publication_selftest: ' . $GLOBALS['checks'] . " checks\n" . $jsOutput;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL rc_editor_publication_selftest: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
