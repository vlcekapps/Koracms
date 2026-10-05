<?php

declare(strict_types=1);

namespace KoraRcEventIntegrityTest;

use PDO;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Real handlers and helpers, in-memory SQL and virtual upload files only.
define('BASE_URL', '');
date_default_timezone_set('Europe/Prague');
$GLOBALS['checks'] = 0;

final class Response extends RuntimeException
{
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
    $code = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (!is_string($code)) {
        throw new RuntimeException('Cannot read production source: ' . $path);
    }
    return $code;
}

function evaluate(string $code): mixed
{
    return eval('namespace ' . __NAMESPACE__ . '; use \\PDO; use \\PDOException; use \\Throwable; use \\DateTimeImmutable; ' . $code);
}

function loadFunction(string $path, string $name): void
{
    if (preg_match('/^function ' . preg_quote($name, '/') . '\b.*?^\}/ms', source($path), $match) !== 1) {
        throw new RuntimeException('Cannot extract production helper: ' . $name);
    }
    // A string callback must follow the relocated production function's namespace.
    $code = str_replace("'deleteEventImageFile'", "'" . __NAMESPACE__ . "\\\\deleteEventImageFile'", $match[0]);
    evaluate($code);
}

function runHandler(string $path): string
{
    $code = preg_replace('/^require_once [^\r\n]+;\R/m', '', source($path));
    ob_start();
    try {
        evaluate('?>' . $code);
        return (string)ob_get_contents();
    } catch (Response $response) {
        return $response->getMessage();
    } finally {
        ob_end_clean();
    }
}

function header(string $value): void
{
    if (str_starts_with($value, 'Location: ')) {
        throw new Response(substr($value, 10));
    }
}
function db_connect(): PDO
{
    return $GLOBALS['fixtureDb'];
}
function requireCapability(string $capability, string $message = ''): void
{
}
function requireModuleEnabled(string $module): void
{
    if (!isModuleEnabled($module)) {
        throw new Response('module-disabled');
    }
}
function isModuleEnabled(string $module): bool
{
    return $GLOBALS['modules'][$module] ?? true;
}
function verifyCsrf(): void
{
    if (($_POST['csrf_token'] ?? '') !== 'fixture-token') {
        throw new Response('csrf-rejected');
    }
}
function currentUserHasCapability(string $capability): bool
{
    return $GLOBALS['approver'];
}
function normalizeDownloadExternalUrl(string $value): string
{
    return $value;
}
function saveRevision(PDO $pdo, string $type, int $id, array $old, array $new): void
{
}
function upsertPathRedirect(PDO $pdo, string $old, string $new): void
{
}
function logAction(string $action, string $details): void
{
    $GLOBALS['actions'][] = $action;
}
function koraLog(string $level, string $message, array $context = []): void
{
    $GLOBALS['errors'][] = $message;
    if (($context['exception'] ?? null) instanceof Throwable) {
        $GLOBALS['exceptions'][] = $context['exception']->getMessage();
    }
}
function notifyPendingContent(string $type, string $title, string $path): void
{
    same(db_connect()->inTransaction(), false, 'Notification happens after commit');
    $GLOBALS['notifications'][] = $title;
}
function releaseContentLock(string $type, int $id): void
{
    $GLOBALS['released'][] = $id;
}
function acquireContentLock(string $type, ?int $id): mixed
{
    return null;
}
function getSetting(string $key, string $default = ''): string
{
    return $default;
}
function siteUrl(string $path): string
{
    return 'https://example.test' . $path;
}
function adminHeader(string $title): void
{
    echo '<!doctype html><html lang="cs"><body><main>';
}
function adminFooter(): void
{
    echo '</main></body></html>';
}
function csrfToken(): string
{
    return 'fixture-token';
}
function cspNonce(): string
{
    return 'fixture-nonce';
}
function adminHtmlSnippetSupportMarkup(): string
{
    return '';
}
function renderAdminContentReferencePicker(string $field): void
{
}
function adminRenderContentLockRefreshScript(string $type, ?int $id): void
{
}
function newWindowLinkSrOnlySuffix(): string
{
    return '';
}
function bulkActions(string $module, string $redirect, string $label, string $item): string
{
    return '<form id="bulk-form"></form>';
}
function bulkCheckboxJs(): string
{
    return '';
}
function checkMaintenanceMode(): void
{
}
function renderPublicNotFoundPage(array $options): void
{
    throw new Response('not-found');
}
function renderPublicPage(array $options): void
{
    $GLOBALS['publicPage'] = $options;
    throw new Response('public-page');
}
function trackPageView(string $type, int $id): void
{
}
function eventStructuredData(array $event): string
{
    return '';
}
function sendNoStoreNoIndexHeaders(): void
{
    $GLOBALS['noStore'] = true;
}
function requireReadOnlyHttpMethod(): bool
{
    return true;
}
function sendReadOnlyNotFoundResponse(string $message, bool $isHead): void
{
    throw new Response('not-found');
}
function sendReadOnlyContentHeaders(string $type, bool $isHead, string $disposition): void
{
    throw new Response('calendar-headers');
}
function is_file(string $path): bool
{
    return isset($GLOBALS['images'][basename($path)]);
}
function unlink(string $path): bool
{
    $filename = basename($path);
    $GLOBALS['removed'][] = $filename;
    unset($GLOBALS['images'][$filename]);
    return true;
}
function koraUploadHasFile(array $file): bool
{
    return ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}
function koraInspectUploadedFile(array $file, array $options): array
{
    return ['ok' => true, 'extension' => 'png', 'tmp_path' => 'virtual-upload'];
}
function koraStoreInspectedUpload(array $upload, string $directory, string $filename, array $options): array
{
    $GLOBALS['images'][$filename] = 'new image';
    return ['ok' => true, 'path' => $directory . $filename];
}
function generateWebp(string $path): void
{
}
function presentationLogFileDeleteFailure(string $type, string $path): void
{
    throw new RuntimeException('Unexpected virtual file deletion failure');
}

function fixture(bool $stringify): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => $stringify,
    ]);
    $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-10-05 12:00:00');
    $pdo->exec("CREATE TABLE cms_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, slug TEXT UNIQUE, event_kind TEXT,
        event_type_id INTEGER, place_id INTEGER, recurrence_group_id TEXT DEFAULT '',
        excerpt TEXT, description TEXT, program_note TEXT, location TEXT, organizer_name TEXT,
        organizer_email TEXT, registration_url TEXT, price_note TEXT, accessibility_note TEXT,
        image_file TEXT, event_date TEXT, event_end TEXT, is_published INTEGER, publish_at TEXT,
        unpublish_at TEXT, admin_note TEXT, status TEXT, preview_token TEXT,
        created_at TEXT DEFAULT '2026-10-01 12:00:00', updated_at TEXT, deleted_at TEXT);
        CREATE TABLE cms_event_types (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, legacy_key TEXT,
            description TEXT DEFAULT '', meta_title TEXT DEFAULT '', meta_description TEXT DEFAULT '',
            is_active INTEGER DEFAULT 1, sort_order INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT);
        INSERT INTO cms_event_types (id, title, slug, legacy_key) VALUES (3, 'Koncert', 'koncert', 'concert');
        CREATE TABLE cms_places (id INTEGER PRIMARY KEY, name TEXT DEFAULT 'Místo', slug TEXT,
            address TEXT, locality TEXT, latitude TEXT, longitude TEXT, status TEXT DEFAULT 'published',
            is_published INTEGER DEFAULT 1, deleted_at TEXT);
        INSERT INTO cms_places (id, deleted_at) VALUES (7, NULL), (8, '2026-10-01 12:00:00');
        INSERT INTO cms_events (id, title, slug, event_kind, event_type_id, place_id, excerpt,
            description, program_note, image_file, event_date, event_end, is_published, status, preview_token)
        VALUES (1, 'Koncert', 'koncert', 'concert', 3, 7, '', '', '', 'shared.png',
            '2027-01-31 20:00:00', '2027-02-01 01:00:00', 1, 'published', '0123456789abcdef0123456789abcdef')");
    $GLOBALS['fixtureDb'] = $pdo;
    $GLOBALS['images'] = ['shared.png' => 'original image'];
    foreach (['removed', 'actions', 'notifications', 'errors', 'exceptions', 'released'] as $key) {
        $GLOBALS[$key] = [];
    }
    $GLOBALS['approver'] = true;
    $GLOBALS['modules'] = ['events' => true, 'places' => true];
    $_GET = [];
    $_SESSION = [];
    $_FILES = [];
    $_POST = ['csrf_token' => 'fixture-token', 'id' => '1', 'title' => 'Koncert', 'slug' => 'koncert',
        'event_type_id' => '3', 'place_id' => '7', 'event_date' => '2027-01-31', 'event_time' => '20:00',
        'event_end_date' => '2027-02-01', 'event_end_time' => '01:00',
        'article_status' => 'published', 'is_published' => '1'];
    return $pdo;
}

function rows(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM cms_events ORDER BY id')->fetchAll();
}

function newSeries(): void
{
    unset($_POST['id']);
    $_POST['slug'] = 'serie';
    $_POST['recurrence_frequency'] = 'monthly';
    $_POST['recurrence_interval'] = '1';
    $_POST['recurrence_count'] = '3';
}

try {
    foreach (['slugify', 'eventSlug', 'eventTypeSlug', 'uniqueEventSlug', 'eventRevisionSnapshot',
        'eventPublicRequestPath', 'eventPublicPath', 'eventPublicUrl', 'eventPreviewPath', 'appendUrlQuery',
        'eventTypeRequestPath', 'eventTypePath', 'eventTypeUrl', 'loadEventTypes', 'eventIcsFilename',
        'normalizePlainText', 'eventExcerpt', 'eventImageUrl', 'hydrateEventPresentation', 'isValidArticlePreviewToken',
        'normalizeEventRecurrenceFrequency',
        'eventRecurrenceShift', 'eventRecurrenceGroupId', 'eventPublicVisibilitySql', 'eventCurrentStatus',
        'eventEffectiveEndSql', 'eventScopeVisibilitySql', 'formatCzechDate',
        'deleteEventImageFile', 'uploadEventImage', 'storePresentationUploadedFile', 'presentationImageMimeMap'] as $name) {
        loadFunction('lib/presentation.php', $name);
    }
    foreach (['eventKindDefinitions', 'normalizeEventKind', 'eventKindHelp'] as $name) {
        loadFunction('lib/definitions.php', $name);
    }
    loadFunction('db.php', 'inputInt');
    loadFunction('db.php', 'h');
    loadFunction('auth.php', 'internalRedirectTarget');
    loadFunction('lib/ui.php', 'validateDateTimeLocal');
    foreach (['adminEditorFormFields', 'adminEditorFormFlashStore', 'adminEditorFormFlashTake',
        'adminEditorDateTimeValue', 'adminFieldHasError', 'adminFieldErrorId', 'adminFieldAttributes', 'adminRenderFieldError'] as $name) {
        loadFunction('admin/layout.php', $name);
    }

    $scenarios = [
        'media-trash' => static function (bool $stringify): void {
            $pdo = fixture($stringify);
            same(runHandler('admin/event_clone.php'), '/admin/event_form.php?id=2', 'Production clone succeeds');
            same($pdo->query('SELECT image_file FROM cms_events WHERE id=2')->fetchColumn(), 'shared.png', 'Clone shares image');
            same($pdo->query('SELECT status FROM cms_events WHERE id=2')->fetchColumn(), 'draft', 'Clone stays draft');
            same((int)$pdo->query('SELECT is_published FROM cms_events WHERE id=2')->fetchColumn(), 0, 'Clone is hidden');
            same($pdo->query('SELECT recurrence_group_id FROM cms_events WHERE id=2')->fetchColumn(), '', 'Clone is not silently linked to series');
            $_POST['confirm_event_delete_1'] = '1';
            same(runHandler('admin/event_delete.php'), '/admin/events.php', 'Soft delete succeeds');
            same(isset($GLOBALS['images']['shared.png']), true, 'Soft delete must retain the shared image and allow full restore');
            same($pdo->query('SELECT deleted_at FROM cms_events WHERE id=1')->fetchColumn(), '2026-10-05 12:00:00', 'Event moves to trash');
            same($GLOBALS['removed'], [], 'Soft delete never unlinks images');
            $pdo->exec('UPDATE cms_events SET deleted_at = NULL WHERE id=1');
            same(isset($GLOBALS['images'][$pdo->query('SELECT image_file FROM cms_events WHERE id=1')->fetchColumn()]), true, 'Restore retains image bytes');
            $before = rows($pdo);
            $_POST['csrf_token'] = 'bad-token';
            same(runHandler('admin/event_delete.php'), 'csrf-rejected', 'Delete checks CSRF before mutation');
            same(rows($pdo), $before, 'Rejected delete leaves data unchanged');
        },
        'media-edit' => static function (bool $stringify): void {
            foreach (['active', 'trashed', 'last-reference'] as $reference) {
                $pdo = fixture($stringify);
                if ($reference !== 'last-reference') {
                    runHandler('admin/event_clone.php');
                    if ($reference === 'trashed') {
                        $pdo->exec("UPDATE cms_events SET deleted_at = '2026-10-01' WHERE id=2");
                    }
                }
                $_FILES['event_image'] = ['error' => UPLOAD_ERR_OK];
                same(runHandler('admin/event_save.php'), '/admin/events.php', 'Image replacement succeeds');
                $newImage = $pdo->query('SELECT image_file FROM cms_events WHERE id=1')->fetchColumn();
                same($newImage !== 'shared.png' && isset($GLOBALS['images'][$newImage]), true, 'New upload is attached');
                same(isset($GLOBALS['images']['shared.png']), $reference !== 'last-reference', 'Replacement retains every remaining reference, including trash');
                same($pdo->inTransaction(), false, 'Successful save releases transaction');
            }
            $pdo = fixture($stringify);
            runHandler('admin/event_clone.php');
            $_POST['confirm_event_image_delete'] = '1';
            same(runHandler('admin/event_save.php'), '/admin/events.php', 'Freshly confirmed image detach succeeds');
            same($pdo->query('SELECT image_file FROM cms_events WHERE id=1')->fetchColumn(), '', 'Image detached from just the edited event');
            same(isset($GLOBALS['images']['shared.png']), true, 'Detach retains clone image');

            $pdo = fixture($stringify);
            $_POST['confirm_event_image_delete'] = '1';
            runHandler('admin/event_save.php');
            same($GLOBALS['images'], [], 'Confirmed detach removes the last unreferenced image');
            foreach (['0', '', ['1']] as $invalidConsent) {
                $pdo = fixture($stringify);
                $_POST['confirm_event_image_delete'] = $invalidConsent;
                runHandler('admin/event_save.php');
                same($pdo->query('SELECT image_file FROM cms_events WHERE id=1')->fetchColumn(), 'shared.png', 'Server requires exact fresh confirmation value');
            }

            $pdo = fixture($stringify);
            runHandler('admin/event_clone.php');
            $_POST['confirm_event_image_delete'] = '1';
            $_FILES['event_image'] = ['error' => UPLOAD_ERR_OK];
            runHandler('admin/event_save.php');
            same($GLOBALS['images'], ['shared.png' => 'original image'], 'Simultaneous detach and upload preserves clone, discards unused upload');

            $pdo = fixture($stringify);
            $_POST['event_image_delete'] = '1';
            same(runHandler('admin/event_save.php'), '/admin/event_form.php?id=1&err=image_confirm', 'Legacy image removal reports an explicit confirmation error');
            same($pdo->query('SELECT image_file FROM cms_events WHERE id=1')->fetchColumn(), 'shared.png', 'Legacy restored checkbox is not fresh confirmation');

            $pdo = fixture($stringify);
            $_POST['confirm_event_image_delete'] = '1';
            $_POST['title'] = '';
            same(runHandler('admin/event_save.php'), '/admin/event_form.php?id=1&err=required', 'Validation error returns to editor');
            $flash = adminEditorFormFlashTake('event', 1);
            same(isset($flash['confirm_event_image_delete']), false, 'Flash does not store deletion consent');
            same($flash['is_published'], '1', 'Ordinary checkbox survives validation error');
            same(isset($GLOBALS['images']['shared.png']), true, 'Validation error deletes no image');
        },
        'duration' => static function (bool $stringify): void {
            foreach (['monthly', 'weekly', 'daily'] as $frequency) {
                $pdo = fixture($stringify);
                newSeries();
                $_POST['recurrence_frequency'] = $frequency;
                same(runHandler('admin/event_save.php'), '/admin/events.php', 'Recurring creation succeeds');
                $series = $pdo->query("SELECT * FROM cms_events WHERE slug LIKE 'serie%' ORDER BY id")->fetchAll();
                same(count($series), 3, 'All occurrences created');
                same(count(array_unique(array_column($series, 'recurrence_group_id'))), 1, 'One shared series group');
                same(count(array_unique(array_column($series, 'preview_token'))), 3, 'Distinct preview tokens');
                foreach ($series as $event) {
                    $start = new \DateTimeImmutable($event['event_date']);
                    $end = new \DateTimeImmutable($event['event_end']);
                    same($end >= $start, true, 'Repeated end cannot precede repeated start: ' . $event['event_date'] . ' -> ' . $event['event_end']);
                    same($start->diff($end)->format('%a %H:%I'), '0 05:00', 'Repeated occurrence retains original five-hour duration');
                    same((int)$event['event_type_id'], 3, 'Occurrence retains type ID with numeric-string fetches');
                    same((int)$event['place_id'], 7, 'Occurrence retains place ID');
                }
            }
            $pdo = fixture($stringify);
            newSeries();
            $_POST['event_end_date'] = '';
            runHandler('admin/event_save.php');
            same((int)$pdo->query("SELECT COUNT(*) FROM cms_events WHERE slug LIKE 'serie%' AND event_end IS NULL")->fetchColumn(), 3, 'Absent ends stay NULL');
            $_POST['id'] = '3';
            $_POST['slug'] = 'serie-edit';
            $_POST['title'] = 'Jeden upravený termín';
            runHandler('admin/event_save.php');
            same((int)$pdo->query("SELECT COUNT(*) FROM cms_events WHERE title = 'Jeden upravený termín'")->fetchColumn(), 1, 'Editing a term does not rewrite the series');
            same((int)$pdo->query('SELECT COUNT(*) FROM cms_events')->fetchColumn(), 4, 'Edit does not regenerate recurrence');
        },
        'atomic' => static function (bool $stringify): void {
            $pdo = fixture($stringify);
            newSeries();
            $_POST['article_status'] = 'pending';
            $_FILES['event_image'] = ['error' => UPLOAD_ERR_OK];
            $pdo->exec("CREATE TRIGGER fail_third_term BEFORE INSERT ON cms_events
                WHEN NEW.slug = 'serie-2027-03-31' BEGIN SELECT RAISE(ABORT, 'fixture third term failure'); END");
            $before = rows($pdo);
            try {
                $response = runHandler('admin/event_save.php');
            } catch (\PDOException $exception) {
                same(str_contains($exception->getMessage(), 'fixture third term failure'), true, 'Failure injection reaches real INSERT');
                $response = 'uncaught-storage-error';
            }
            same(rows($pdo), $before, 'A failed third INSERT must roll back the entire recurring series');
            same(count($GLOBALS['exceptions']), 1, 'Failed series records its storage exception');
            same(str_contains($GLOBALS['exceptions'][0], 'fixture third term failure'), true, 'Rollback follows the injected third INSERT failure');
            same($pdo->inTransaction(), false, 'Failed save releases transaction');
            same($response, '/admin/event_form.php?err=save', 'Storage error returns to new editor');
            same($GLOBALS['images'], ['shared.png' => 'original image'], 'Failed creation removes only the unattached new upload');
            same($GLOBALS['notifications'], [], 'Failed series sends no pending notification');
            $flash = adminEditorFormFlashTake('event', null);
            same($flash['title'], 'Koncert', 'Failed save retains title');
            same($flash['recurrence_count'], '3', 'Failed save retains recurrence count');
            $pdo->exec('DROP TRIGGER fail_third_term');
            same(runHandler('admin/event_save.php'), '/admin/events.php', 'Series can be retried normally');
            same((int)$pdo->query('SELECT COUNT(*) FROM cms_events')->fetchColumn(), 4, 'Retry creates precisely one complete series');
            same(count($GLOBALS['notifications']), 1, 'One notification after successful retry');

            $pdo = fixture($stringify);
            $_FILES['event_image'] = ['error' => UPLOAD_ERR_OK];
            $pdo->exec("CREATE TRIGGER fail_edit BEFORE UPDATE ON cms_events BEGIN SELECT RAISE(ABORT, 'fixture edit failure'); END");
            $before = rows($pdo);
            same(runHandler('admin/event_save.php'), '/admin/event_form.php?id=1&err=save', 'Failed edit returns to existing editor');
            same(rows($pdo), $before, 'Failed edit changes no row');
            same($GLOBALS['images'], ['shared.png' => 'original image'], 'Failed edit retains old image and discards new upload');
        },
        'relations-visibility' => static function (bool $stringify): void {
            $pdo = fixture($stringify);
            foreach (['event_type_id' => ['999', 'event_type'], 'place_id' => ['999', 'place']] as $field => [$value, $error]) {
                $original = $_POST[$field];
                $_POST[$field] = $value;
                $before = rows($pdo);
                same(runHandler('admin/event_save.php'), '/admin/event_form.php?id=1&err=' . $error, 'Nonexistent relation is rejected');
                same(rows($pdo), $before, 'Invalid relation writes no data');
                $_POST[$field] = $original;
            }
            $_POST['place_id'] = '8';
            same(runHandler('admin/event_save.php'), '/admin/event_form.php?id=1&err=place', 'New link to trashed place is rejected');
            $pdo->exec('UPDATE cms_events SET place_id=8 WHERE id=1');
            same(runHandler('admin/event_save.php'), '/admin/events.php', 'Existing trashed-place link can be retained');
            same((int)$pdo->query('SELECT place_id FROM cms_events WHERE id=1')->fetchColumn(), 8, 'Trashed-place ID retained');
            // Execute both public handlers as well as the shared SQL visibility helper.
            $pdo->exec('UPDATE cms_events SET place_id=NULL WHERE id=1');
            $_GET = ['slug' => 'koncert'];
            $visibilityCases = [
                "status='published', is_published=1, deleted_at=NULL, publish_at=NULL, unpublish_at=NULL" => 1,
                "status='draft'" => 0,
                "status='pending'" => 0,
                "status='published', is_published=0" => 0,
                "is_published=1, deleted_at='2026-10-01'" => 0,
                "deleted_at=NULL, publish_at='2026-10-06 00:00:00'" => 0,
                "publish_at='2026-10-05 12:00:00', unpublish_at=NULL" => 1,
                "unpublish_at='2026-10-05 12:00:00'" => 0,
                "unpublish_at='2026-10-06 00:00:00'" => 1,
            ];
            foreach ($visibilityCases as $assignments => $expected) {
                $pdo->exec('UPDATE cms_events SET ' . $assignments . ' WHERE id=1');
                same((int)$pdo->query('SELECT COUNT(*) FROM cms_events e WHERE ' . eventPublicVisibilitySql('e'))->fetchColumn(), $expected, 'Production public visibility: ' . $assignments);
                same(runHandler('events/event.php'), $expected === 1 ? 'public-page' : 'not-found', 'Detail applies full visibility rule');
                same(runHandler('events/ics.php'), $expected === 1 ? 'calendar-headers' : 'not-found', 'Calendar applies the same visibility rule');
            }
            $pdo->exec("UPDATE cms_events SET status='draft', is_published=0, deleted_at=NULL WHERE id=1");
            $_GET['preview'] = '0123456789abcdef0123456789abcdef';
            same(runHandler('events/event.php'), 'public-page', 'Valid preview opens draft');
            same($GLOBALS['noStore'], true, 'Draft preview sends no-store/no-index headers');
            same(runHandler('events/ics.php'), 'not-found', 'Preview cannot expose an unpublished calendar');
            $_GET['preview'] = 'ffffffffffffffffffffffffffffffff';
            same(runHandler('events/event.php'), 'not-found', 'Wrong preview token cannot open draft');
            $pdo = fixture($stringify);
            newSeries();
            $GLOBALS['approver'] = false;
            same(runHandler('admin/event_save.php'), '/admin/events.php', 'Author creates series without approval capability');
            same((int)$pdo->query("SELECT COUNT(*) FROM cms_events WHERE slug LIKE 'serie%' AND status='pending' AND is_published=0")->fetchColumn(), 3, 'Every unapproved occurrence stays pending and hidden');

            $pdo = fixture($stringify);
            newSeries();
            runHandler('admin/event_save.php');
            $pdo->exec("UPDATE cms_events SET place_id=NULL;
                UPDATE cms_events SET status='draft' WHERE id=3;
                UPDATE cms_events SET unpublish_at='2026-10-01' WHERE id=4");
            $_GET = ['slug' => 'serie'];
            same(runHandler('events/event.php'), 'public-page', 'Visible series detail opens');
            same(count($GLOBALS['publicPage']['view_data']['recurrenceEvents']), 1, 'Public recurrence list excludes draft and expired siblings');
        },
        'form-confirmation' => static function (bool $stringify): void {
            fixture($stringify);
            $_GET = ['id' => '1', 'err' => 'save'];
            $_SESSION['cms_editor_form_flash']['event:1:']['values'] = [
                'title' => 'Rozepsaný koncert', 'event_image_delete' => '1',
                'confirm_event_image_delete' => '1', 'is_published' => '0',
            ];
            $html = runHandler('admin/event_form.php');
            $dom = new \DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($dom);
            $confirmation = $xpath->query('//input[@name="confirm_event_image_delete"]')->item(0);
            same($confirmation instanceof \DOMElement, true, 'Rendered form has an autosave-excluded confirmation checkbox');
            same($confirmation->hasAttribute('checked'), false, 'Neither old nor forged flash consent prechecks deletion');
            same($xpath->query('//label[input[@name="confirm_event_image_delete"]]')->length, 1, 'Deletion confirmation has a real label');
            same($xpath->query('//input[@name="event_image_delete"]')->length, 0, 'Legacy autosave checkbox is not rendered');
            same($xpath->query('//input[@name="title"]')->item(0)->getAttribute('value'), 'Rozepsaný koncert', 'Storage-error form preserves entered title');
            same($xpath->query('//input[@name="is_published"]')->item(0)->hasAttribute('checked'), false, 'Regular data checkbox retains its false value');
            same($xpath->query('//*[@id="form-error" and @role="alert"]')->length, 1, 'Storage failure is announced accessibly');
            foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby]') as $element) {
                foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
                    foreach (preg_split('/\s+/', trim($element->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                        same($xpath->query('//*[@id="' . $id . '"]')->length, 1, 'ARIA reference resolves uniquely: ' . $id);
                    }
                }
            }
        },
        'delete-consent' => static function (bool $stringify): void {
            $rejectedConsent = [[], ['confirm_event_delete_2' => '1']];
            foreach (['0', 'true', 1, true, ['1']] as $value) {
                $rejectedConsent[] = ['confirm_event_delete_1' => $value];
            }
            foreach ($rejectedConsent as $input) {
                $pdo = fixture($stringify);
                $_POST = array_replace($_POST, $input);
                $before = rows($pdo);
                same(runHandler('admin/event_delete.php'), '/admin/events.php?delete_error=confirm_required&delete_error_id=1', 'Delete requires exact consent for this event ID');
                same(rows($pdo), $before, 'Rejected consent changes no rows');
                same($GLOBALS['actions'], [], 'Rejected consent creates no deletion audit entry');
                same($GLOBALS['images'], ['shared.png' => 'original image'], 'Rejected consent changes no files');
            }
            foreach ([null, '0', '999'] as $invalidId) {
                $pdo = fixture($stringify);
                $_POST['id'] = $invalidId;
                $_POST['confirm_event_delete_' . $invalidId] = '1';
                $before = rows($pdo);
                $suffix = $invalidId === '999' ? '&delete_error_id=999' : '';
                same(runHandler('admin/event_delete.php'), '/admin/events.php?delete_error=invalid' . $suffix, 'Invalid or missing event cannot be deleted');
                same(rows($pdo), $before, 'Invalid delete changes no rows');
                same($GLOBALS['actions'], [], 'No-op delete creates no audit entry');
            }
            $pdo = fixture($stringify);
            $_POST['confirm_event_delete_1'] = '1';
            same(runHandler('admin/event_delete.php'), '/admin/events.php', 'Exact freshly confirmed delete succeeds');
            same($pdo->query('SELECT deleted_at FROM cms_events WHERE id=1')->fetchColumn(), '2026-10-05 12:00:00', 'Confirmed event moves to Trash');
            same($GLOBALS['actions'], ['event_delete'], 'Successful delete records exactly one audit entry');
            same($GLOBALS['images'], ['shared.png' => 'original image'], 'Confirmed soft delete retains image for restore');
            $before = rows($pdo);
            same(runHandler('admin/event_delete.php'), '/admin/events.php?delete_error=invalid&delete_error_id=1', 'Repeated delete is rejected as a no-op');
            same(rows($pdo), $before, 'Repeated delete leaves Trash record unchanged');
            same($GLOBALS['actions'], ['event_delete'], 'Repeated delete does not log another deletion');
            $_POST['csrf_token'] = 'invalid-token';
            same(runHandler('admin/event_delete.php'), 'csrf-rejected', 'Fresh confirmation does not bypass CSRF');
            same($GLOBALS['actions'], ['event_delete'], 'CSRF rejection creates no audit entry');
        },
        'delete-review-form' => static function (bool $stringify): void {
            $pdo = fixture($stringify);
            runHandler('admin/event_clone.php');
            $pdo->exec('UPDATE cms_events SET place_id=NULL');
            $_GET = ['delete_error' => 'confirm_required', 'delete_error_id' => '1', 'confirm_event_delete_1' => '1'];
            $dom = new \DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8">' . runHandler('admin/events.php'), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($dom);
            same($xpath->query('//*[@id="event-delete-error" and @role="alert"]')->length, 1, 'Delete rejection is announced accessibly');
            same($xpath->query('//form[@action="event_delete.php"]/fieldset/legend')->length, 2, 'Every row has a deletion fieldset and legend');
            foreach ([1, 2] as $eventId) {
                $name = 'confirm_event_delete_' . $eventId;
                $confirmation = $xpath->query('//input[@name="' . $name . '"]')->item(0);
                same($confirmation instanceof \DOMElement, true, 'Row-specific confirmation checkbox exists');
                same($confirmation->getAttribute('value'), '1', 'Rendered checkbox submits exact server consent value');
                same($confirmation->hasAttribute('checked'), false, 'Request values cannot recover deletion consent');
                same($confirmation->hasAttribute('required'), true, 'Confirmation is required without JavaScript');
                same($confirmation->getAttribute('aria-required'), 'true', 'Required confirmation is announced');
                same($xpath->query('//label[@for="' . $confirmation->getAttribute('id') . '"]')->length, 1, 'Confirmation has an explicit label');
                same($confirmation->getAttribute('aria-invalid'), $eventId === 1 ? 'true' : '', 'Only the rejected row gets a field error');
                $reviewId = 'event-delete-review-' . $eventId;
                same(str_contains($confirmation->getAttribute('aria-describedby'), $reviewId), true, 'Confirmation references its own review');
                $review = $xpath->query('//*[@id="' . $reviewId . '"]')->item(0);
                same($review instanceof \DOMElement, true, 'Row review exists');
                same(str_contains($review->textContent, $eventId === 1 ? 'Koncert' : 'Koncert (kopie)'), true, 'Review identifies the exact event');
                same(str_contains($review->textContent, '2027, 20:00'), true, 'Review includes the occurrence date and start time');
                same(str_contains($review->textContent, 'Ostatní termíny'), true, 'Review explains series isolation');
            }
            foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby]') as $element) {
                foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
                    foreach (preg_split('/\s+/', trim($element->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                        same($xpath->query('//*[@id="' . $id . '"]')->length, 1, 'List ARIA reference resolves uniquely: ' . $id);
                    }
                }
            }
        },
        'legacy-image-consent' => static function (bool $stringify): void {
            foreach (['1', '0', ['1'], null] as $legacyValue) {
                $pdo = fixture($stringify);
                $_POST['event_image_delete'] = $legacyValue;
                $_POST['title'] = 'Zachovaný rozepsaný termín';
                $_POST['event_date'] = '2027-05-13';
                $_POST['event_time'] = '09:27';
                $_POST['event_end_date'] = '2027-05-14';
                $_POST['event_end_time'] = '10:21';
                unset($_POST['is_published']);
                $before = rows($pdo);
                same(runHandler('admin/event_save.php'), '/admin/event_form.php?id=1&err=image_confirm', 'Legacy image-delete field is explicitly rejected');
                same(rows($pdo), $before, 'Legacy consent changes no persisted data');
                same($GLOBALS['images'], ['shared.png' => 'original image'], 'Legacy consent changes no image files');
                same($GLOBALS['actions'], [], 'Legacy consent does not log a save');
                same($_SESSION['cms_editor_form_flash']['event:1:']['values']['event_image_delete'], '0', 'Recovery clears legacy critical consent');
                $_GET = ['id' => '1', 'err' => 'image_confirm'];
                $dom = new \DOMDocument();
                $dom->loadHTML('<?xml encoding="UTF-8">' . runHandler('admin/event_form.php'), LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new \DOMXPath($dom);
                same($xpath->query('//*[@id="form-error" and @role="alert"]')->length, 1, 'Legacy consent error is announced');
                $confirmation = $xpath->query('//input[@name="confirm_event_image_delete"]')->item(0);
                same($confirmation->hasAttribute('checked'), false, 'Legacy error recovery requires fresh consent');
                same($confirmation->getAttribute('aria-invalid'), 'true', 'Legacy error is attached to current confirmation');
                same($xpath->query('//input[@name="event_date"]')->item(0)->getAttribute('value'), '2027-05-13', 'Legacy error preserves submitted date');
                same($xpath->query('//input[@name="event_time"]')->item(0)->getAttribute('value'), '09:27', 'Legacy error preserves submitted time');
                same($xpath->query('//input[@name="is_published"]')->item(0)->hasAttribute('checked'), false, 'Legacy error preserves ordinary checkbox');
                same(str_contains($confirmation->getAttribute('aria-describedby'), 'event-image-delete-help'), true, 'Image confirmation references detachment help');
                same($xpath->query('//*[@id="event-image-delete-help"]')->length, 1, 'Image detachment help exists');
                same(str_contains($xpath->query('//*[@id="event-image-delete-help"]')->item(0)->textContent, 'ostatní termíny a kopie včetně Koše'), true, 'Image help explains preserved shared references');
                same(str_contains($xpath->query('//label[@for="confirm_event_image_delete"]')->item(0)->textContent, 'Odebrat obrázek z této události'), true, 'Image label describes detachment rather than unconditional destruction');
                foreach (preg_split('/\s+/', $confirmation->getAttribute('aria-describedby'), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                    same($xpath->query('//*[@id="' . $id . '"]')->length, 1, 'Image confirmation ARIA reference resolves uniquely');
                }
            }
        },
        'exact-editor-recovery' => static function (bool $stringify): void {
            foreach (['existing', 'new'] as $editor) {
                foreach ([false, true] as $published) {
                    $pdo = fixture($stringify);
                    $editorId = $editor === 'existing' ? 1 : null;
                    if ($editorId !== null) {
                        runHandler('admin/event_clone.php');
                        $otherEventId = 2;
                        $pdo->exec("CREATE TRIGGER fail_editor BEFORE UPDATE ON cms_events
                            BEGIN SELECT RAISE(ABORT, 'fixture exact editor failure'); END");
                    } else {
                        newSeries();
                        $otherEventId = 1;
                        $pdo->exec("CREATE TRIGGER fail_editor BEFORE INSERT ON cms_events
                            WHEN NEW.slug LIKE 'serie%' AND
                                (SELECT COUNT(*) FROM cms_events WHERE slug LIKE 'serie%') = 2
                            BEGIN SELECT RAISE(ABORT, 'fixture exact editor failure'); END");
                    }
                    $otherInput = ['title' => 'Jiný rozepsaný termín',
                        'event_date' => '2030-10-21', 'event_time' => '08:11'];
                    if (!$published) {
                        $otherInput['is_published'] = '1';
                    }
                    adminEditorFormFlashStore('event', $otherEventId, $otherInput);
                    adminEditorFormFlashStore('news', 1, ['title' => 'Rozepsaná aktualita']);
                    $newsFlash = $_SESSION['cms_editor_form_flash']['news:1:'];
                    $expectedDates = [
                        'event_date' => '2027-04-17',
                        'event_time' => $published ? '09:13' : '',
                        'event_end_date' => $published ? '2027-04-18' : '',
                        'event_end_time' => $published ? '11:07' : '',
                        'publish_at' => $published ? '2026-11-14T10:23' : '',
                        'unpublish_at' => $published ? '2027-12-01T16:41' : '',
                    ];
                    $_POST = array_replace($_POST, $expectedDates, [
                        'title' => 'Přesně tento rozepsaný termín', 'confirm_event_image_delete' => '1',
                    ]);
                    if (!$published) {
                        unset($_POST['is_published']);
                    }
                    $before = rows($pdo);
                    $expectedRedirect = '/admin/event_form.php' . ($editorId !== null ? '?id=1&err=save' : '?err=save');
                    same(runHandler('admin/event_save.php'), $expectedRedirect, 'Failure returns to the exact editor, not a rolled-back INSERT ID');
                    same(rows($pdo), $before, 'Failed editor save preserves all persisted events');
                    $targetKey = 'event:' . ($editorId ?? 'new') . ':';
                    $targetFlash = $_SESSION['cms_editor_form_flash'][$targetKey];
                    foreach ($expectedDates as $field => $value) {
                        same($targetFlash['values'][$field], $value, 'Flash retains exact submitted date/time, including blanks: ' . $field);
                    }
                    same($targetFlash['values']['is_published'], $published ? '1' : '0', 'Flash preserves both data checkbox states');
                    same(isset($targetFlash['values']['confirm_event_image_delete']), false, 'Failed editor never stores critical consent');

                    // Visiting a different event must not consume or display this failed save.
                    $_GET = ['id' => (string)$otherEventId, 'err' => 'save'];
                    $otherDom = new \DOMDocument();
                    $otherDom->loadHTML('<?xml encoding="UTF-8">' . runHandler('admin/event_form.php'), LIBXML_NOERROR | LIBXML_NOWARNING);
                    $otherXpath = new \DOMXPath($otherDom);
                    same($otherXpath->query('//input[@name="title"]')->item(0)->getAttribute('value'), $otherInput['title'], 'Other editor renders only its own draft');
                    same($otherXpath->query('//input[@name="event_date"]')->item(0)->getAttribute('value'), $otherInput['event_date'], 'Other editor retains its own date');
                    same($otherXpath->query('//input[@name="is_published"]')->item(0)->hasAttribute('checked'), !$published, 'Other editor retains its own data checkbox');
                    same($_SESSION['cms_editor_form_flash'][$targetKey], $targetFlash, 'Visiting another editor leaves failed-save flash untouched');

                    $_GET = $editorId !== null ? ['id' => '1', 'err' => 'save'] : ['err' => 'save'];
                    $dom = new \DOMDocument();
                    $dom->loadHTML('<?xml encoding="UTF-8">' . runHandler('admin/event_form.php'), LIBXML_NOERROR | LIBXML_NOWARNING);
                    $xpath = new \DOMXPath($dom);
                    same($xpath->query('//input[@name="title"]')->item(0)->getAttribute('value'), $_POST['title'], 'Exact editor renders submitted title');
                    foreach ($expectedDates as $field => $value) {
                        same($xpath->query('//input[@name="' . $field . '"]')->item(0)->getAttribute('value'), $value, 'Exact editor renders submitted date/time, including blanks: ' . $field);
                    }
                    same($xpath->query('//input[@name="is_published"]')->item(0)->hasAttribute('checked'), $published, 'Exact editor renders submitted data checkbox');
                    same($xpath->query('//input[starts-with(@name,"confirm_") and @checked]')->length, 0, 'Recovered editor contains no checked critical confirmation');
                    same(isset($_SESSION['cms_editor_form_flash'][$targetKey]), false, 'Only the exact editor consumes its failed-save flash');
                    same($_SESSION['cms_editor_form_flash']['news:1:'], $newsFlash, 'Another module editor retains its independent draft');
                }
            }
        },
    ];

    $selected = $argv[1] ?? 'all';
    if ($selected !== 'all' && !isset($scenarios[$selected])) {
        throw new RuntimeException('Unknown scenario: ' . $selected);
    }
    $failed = 0;
    foreach ([false, true] as $stringify) {
        foreach ($scenarios as $name => $scenario) {
            if ($selected !== 'all' && $selected !== $name) {
                continue;
            }
            try {
                $scenario($stringify);
                echo 'PASS ' . $name . ' (stringify=' . (int)$stringify . ')' . PHP_EOL;
            } catch (Throwable $exception) {
                $failed++;
                fwrite(STDERR, 'FAIL ' . $name . ' (stringify=' . (int)$stringify . '): ' . $exception->getMessage() . PHP_EOL);
            }
        }
    }
    echo 'Event integrity self-test: ' . $GLOBALS['checks'] . ' checks, ' . $failed . ' failed scenarios.' . PHP_EOL;
    exit($failed === 0 ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL);
    exit(1);
}
