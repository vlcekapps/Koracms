<?php

declare(strict_types=1);

namespace KoraRcBoardNewsletterTest;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_URL', '');
date_default_timezone_set('Europe/Prague');
$GLOBALS['checks'] = 0;

// Production handlers/SQL/helpers run unchanged, except transport and virtual upload IO.
final class Response extends RuntimeException
{
}
final class FixturePdo extends PDO
{
    public bool $failCommit = false;
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $query = str_replace(' FOR UPDATE', '', $query);
        $query = str_replace(
            'ON DUPLICATE KEY UPDATE new_path = VALUES(new_path), status_code = VALUES(status_code)',
            'ON CONFLICT(old_path) DO UPDATE SET new_path = excluded.new_path, status_code = excluded.status_code',
            $query
        );
        return parent::prepare($query, $options);
    }
    public function commit(): bool
    {
        if ($this->failCommit) {
            $this->failCommit = false;
            throw new PDOException('Owned commit failure');
        }
        return parent::commit();
    }
}
function same(mixed $actual, mixed $expected, string $label): void
{
    $GLOBALS['checks']++;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function production(string $path): string
{
    $code = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (!is_string($code)) {
        throw new RuntimeException('Cannot read production source ' . $path);
    }
    return $code;
}
function evaluate(string $code): mixed
{
    return eval('namespace ' . __NAMESPACE__ . '; use \\PDO; use \\PDOException; use \\Throwable; use \\RuntimeException; use \\DateTimeImmutable; ' . $code);
}
function loadFunction(string $path, string $name): void
{
    if (preg_match('/^function ' . preg_quote($name, '/') . '\b.*?^\}/ms', production($path), $match) !== 1) {
        throw new RuntimeException('Cannot extract production helper ' . $name);
    }
    $code = $match[0];
    foreach (['deleteBoardImageFile', 'deleteBoardStoredFile'] as $callback) {
        $code = str_replace("'" . $callback . "'", "'" . __NAMESPACE__ . '\\' . $callback . "'", $code);
    }
    evaluate($code);
}
function runHandler(string $path): array
{
    $source = preg_replace('/^require_once [^\r\n]+;\R/m', '', production($path));
    $code = '';
    foreach (token_get_all($source) as $token) {
        $code .= is_array($token) && $token[0] === T_EXIT ? 'throw new Response()' : (is_array($token) ? $token[1] : $token);
    }
    $GLOBALS['location'] = '';
    ob_start();
    try {
        evaluate('?>' . $code);
    } catch (Response $response) {
    } finally {
        $html = (string)ob_get_clean();
    }
    return ['location' => $GLOBALS['location'], 'html' => $html];
}
function header(string $value): void
{
    if (str_starts_with($value, 'Location: ')) {
        $GLOBALS['location'] = substr($value, 10);
    }
}
function db_connect(): PDO
{
    return $GLOBALS['db'];
}
function requireCapability(string $capability, string $message = ''): void
{
}
function requireModuleEnabled(string $module): void
{
}
function currentUserHasCapability(string $capability): bool
{
    return true;
}
function currentUserId(): int
{
    return 1;
}
function verifyCsrf(): void
{
    same($_SERVER['REQUEST_METHOD'], 'POST', 'Mutation requires POST');
    same($_POST['csrf_token'] ?? '', 'owned-token', 'Mutation requires CSRF');
}
function csrfToken(): string
{
    return 'owned-token';
}
function cspNonce(): string
{
    return 'owned-nonce';
}
function getSetting(string $key, string $default = ''): string
{
    return $key === 'board_public_label' ? 'Vývěska' : $default;
}
function acquireContentLock(string $type, ?int $id): mixed
{
    return null;
}
function releaseContentLock(string $type, int $id): void
{
}
function adminRenderContentLockRefreshScript(string $type, ?int $id): void
{
}
function adminHeader(string $title): void
{
    echo '<!doctype html><html lang="cs"><body><main>';
}
function adminFooter(): void
{
    echo '</main></body></html>';
}
function adminHtmlSnippetSupportMarkup(): string
{
    return '';
}
function renderAdminContentReferencePicker(string $field): void
{
}
function newWindowLinkSrOnlySuffix(): string
{
    return '';
}
function siteUrl(string $path): string
{
    return 'https://example.test' . $path;
}
function revisionLogError(string $operation, string $type, int $id, PDOException $exception): void
{
}
function koraLog(string $level, string $message, array $context = []): void
{
    $GLOBALS['errors'][] = $message;
}
function logAction(string $action, string $details): void
{
    db_connect()->prepare('INSERT INTO owned_actions (action) VALUES (?)')->execute([$action]);
}
function notifyBoardSubscribers(PDO $pdo, array $document): int
{
    same($pdo->inTransaction(), false, 'Publication notification runs after commit');
    $GLOBALS['notices'][] = $document['id'];
    return 0;
}
function notifyPendingContent(string $type, string $title, string $path): void
{
    same(db_connect()->inTransaction(), false, 'Pending notification runs after commit');
}
function sendMail(string $recipient, string $subject, string $body): bool
{
    $GLOBALS['mail'][] = [$recipient, $subject, $body];
    return true;
}
function sendNewsletterSubscriptionConfirmation(string $email, string $token): bool
{
    $GLOBALS['mail'][] = [$email, $token];
    return $GLOBALS['mailResult'];
}
function is_file(string $path): bool
{
    return isset($GLOBALS['files'][basename($path)]);
}
function unlink(string $path): bool
{
    same(db_connect()->inTransaction(), false, 'File deletion never precedes commit/rollback');
    unset($GLOBALS['files'][basename($path)]);
    return true;
}
function hash_file(string $algorithm, string $path): string|false
{
    return isset($GLOBALS['files'][basename($path)]) ? hash($algorithm, $GLOBALS['files'][basename($path)]) : false;
}
function presentationLogFileDeleteFailure(string $kind, string $path): void
{
    throw new RuntimeException('Unexpected virtual file delete failure');
}
function koraUploadHasFile(?array $file): bool
{
    return ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}
function koraInspectUploadedFile(array $file, array $options): array
{
    return ($file['name'] ?? '') === 'invalid.exe'
        ? ['ok' => false, 'error' => 'Owned upload rejection']
        : ['ok' => true, 'extension' => pathinfo((string)$file['name'], PATHINFO_EXTENSION), 'file_size' => 9, 'original_name' => $file['name']];
}
function koraStoreInspectedUpload(array $upload, string $directory, string $name, array $options): array
{
    $GLOBALS['files'][$name] = 'new bytes';
    return ['ok' => true, 'path' => $directory . $name];
}
function generateWebp(string $path): void
{
    $GLOBALS['files'][pathinfo(basename($path), PATHINFO_FILENAME) . '.webp'] = 'generated webp';
}
function fixture(): FixturePdo
{
    $pdo = new FixturePdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => getenv('KORA_RC_STRINGIFY_FETCHES') === '1']);
    $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));
    $pdo->sqliteCreateFunction('CURDATE', static fn (): string => date('Y-m-d'));
    $pdo->exec("CREATE TABLE cms_board (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
        board_type TEXT DEFAULT 'document', excerpt TEXT DEFAULT '', description TEXT DEFAULT '', category_id INTEGER,
        posted_date TEXT NOT NULL, removal_date TEXT, image_file TEXT DEFAULT '', contact_name TEXT DEFAULT '',
        contact_phone TEXT DEFAULT '', contact_email TEXT DEFAULT '', filename TEXT DEFAULT '', original_name TEXT DEFAULT '',
        file_size INTEGER DEFAULT 0, is_pinned INTEGER DEFAULT 0, is_published INTEGER DEFAULT 1,
        publish_at TEXT, unpublish_at TEXT, status TEXT DEFAULT 'published', author_id INTEGER, preview_token TEXT DEFAULT '',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, deleted_at TEXT);
        CREATE TABLE cms_board_categories (id INTEGER PRIMARY KEY, name TEXT, sort_order INTEGER DEFAULT 0);
        CREATE TABLE cms_board_publication_events (id INTEGER PRIMARY KEY, board_id INTEGER NOT NULL, event_type TEXT,
        event_date TEXT, actor_user_id INTEGER, public_path TEXT, attachment_name TEXT, attachment_size INTEGER, attachment_checksum TEXT);
        CREATE TABLE cms_revisions (id INTEGER PRIMARY KEY, entity_type TEXT, entity_id INTEGER, field_name TEXT, old_value TEXT, new_value TEXT, user_id INTEGER, created_at TEXT);
        CREATE TABLE cms_redirects (old_path TEXT PRIMARY KEY, new_path TEXT, status_code INTEGER);
        CREATE TABLE owned_actions (id INTEGER PRIMARY KEY, action TEXT);
        CREATE TABLE cms_subscribers (id INTEGER PRIMARY KEY, email TEXT UNIQUE, token TEXT, confirmed INTEGER DEFAULT 0);
        CREATE TABLE cms_newsletters (id INTEGER PRIMARY KEY, subject TEXT, body TEXT, recipient_count INTEGER, sent_at TEXT);
        INSERT INTO cms_subscribers VALUES (1, 'owned@example.test', 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789', 0);
        INSERT INTO cms_board (id,title,slug,posted_date,image_file,filename,original_name,file_size,preview_token)
        VALUES (1,'Původní dokument','puvodni','2025-01-01','shared.png','original.txt','Původní.txt',8,'0123456789abcdef0123456789abcdef');");
    $GLOBALS['db'] = $pdo;
    $GLOBALS['files'] = ['shared.png' => 'old image', 'original.txt' => 'old text'];
    $GLOBALS['errors'] = $GLOBALS['notices'] = $GLOBALS['mail'] = [];
    $GLOBALS['mailResult'] = true;
    $_SESSION = $_GET = $_FILES = [];
    $_SERVER = ['REQUEST_METHOD' => 'POST'];
    $_POST = ['csrf_token' => 'owned-token', 'id' => '1', 'title' => 'Nový český nadpis', 'slug' => 'puvodni',
        'description' => '<p>Rozepsaný český text</p>', 'posted_date' => '2025-01-01', 'article_status' => 'published'];
    return $pdo;
}
function rows(string $table): array
{
    if (!in_array($table, ['cms_board', 'cms_board_publication_events', 'cms_revisions', 'owned_actions', 'cms_subscribers'], true)) {
        throw new RuntimeException('Invalid fixture table');
    }
    return db_connect()->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll();
}
function snapshot(): array
{
    return [rows('cms_board'), rows('cms_board_publication_events'), rows('cms_revisions'), rows('owned_actions')];
}
function aria(string $html): \DOMXPath
{
    $dom = new \DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new \DOMXPath($dom);
    foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby]') as $element) {
        foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
            foreach (preg_split('/\s+/', trim($element->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                same($xpath->query('//*[@id="' . $id . '"]')->length, 1, 'Existing unique ARIA target ' . $id);
            }
        }
    }
    return $xpath;
}

try {
    foreach (['slugify', 'boardSlug', 'uniqueBoardSlug', 'boardTypeLabel', 'boardPublicRequestPath', 'boardPublicPath',
        'boardPreviewPath', 'appendUrlQuery', 'boardIsPubliclyReachable', 'boardAttachmentChecksum',
        'boardPublicationEventLabels', 'recordBoardPublicationEvent', 'deleteUnusedBoardFiles',
        'boardAttachmentMimeMap', 'uploadBoardStoredFile', 'uploadBoardImage', 'deleteBoardStoredFile',
        'deleteBoardImageFile', 'boardImageUrl', 'storePresentationUploadedFile', 'presentationImageMimeMap',
        'deleteRedirectsTargetingPath', 'upsertPathRedirect', 'formatCzechDate'] as $name) {
        loadFunction('lib/presentation.php', $name);
    }
    foreach (['boardTypeDefinitions', 'normalizeBoardType', 'boardModulePublicLabel'] as $name) {
        loadFunction('lib/definitions.php', $name);
    }
    foreach (['inputInt', 'h'] as $name) {
        loadFunction('db.php', $name);
    }
    foreach (['internalRedirectTarget', 'storedRedirectTarget'] as $name) {
        loadFunction('auth.php', $name);
    }
    loadFunction('lib/ui.php', 'validateDateTimeLocal');
    loadFunction('lib/content.php', 'formatFileSize');
    foreach (['adminEditorFormFields', 'adminEditorFormFlashStore', 'adminEditorFormFlashTake', 'adminEditorDateTimeValue',
        'adminFieldHasError', 'adminFieldErrorId', 'adminFieldAttributes', 'adminRenderFieldError'] as $name) {
        loadFunction('admin/layout.php', $name);
    }
    loadFunction('lib/revisions.php', 'saveRevision');
    loadFunction('lib/messages.php', 'newsletterSubscriberCounts');

    // Validation preserves only editable data and respects the editor ID.
    fixture();
    $_POST['contact_email'] = 'not-an-email';
    $_POST['confirm_board_image_delete'] = '1';
    $before = snapshot();
    $response = runHandler('admin/board_save.php');
    same(str_contains($response['location'], 'err=contact_email'), true, 'Invalid contact rejected');
    same(snapshot(), $before, 'Validation is read-only');
    same(adminEditorFormFlashTake('board', 2), [], 'Another editor cannot consume this draft');
    $_GET = ['id' => '1', 'err' => 'contact_email'];
    $page = runHandler('admin/board_form.php');
    $xpath = aria($page['html']);
    same($xpath->query('//input[@id="title"]')->item(0)->getAttribute('value'), $_POST['title'], 'Title retained');
    same($xpath->query('//textarea[@name="description"]')->item(0)->textContent, $_POST['description'], 'Text retained');
    same($xpath->query('//input[@name="is_published" and @checked]')->length, 0, 'Unchecked data checkbox retained');
    same($xpath->query('//input[@name="confirm_board_image_delete" and @checked]')->length, 0, 'Consent not restored');
    same(adminEditorFormFlashTake('board', 1), [], 'Draft consumed once');

    foreach (['publish_at' => 'bad-date', 'unpublish_at' => 'bad-date'] as $field => $value) {
        fixture();
        $_POST[$field] = $value;
        $before = snapshot();
        same(str_contains(runHandler('admin/board_save.php')['location'], 'err=' . $field), true, 'Invalid schedule rejected');
        same(snapshot(), $before, 'Invalid schedule does not publish');
        $_GET = ['id' => '1', 'err' => $field];
        $xpath = aria(runHandler('admin/board_form.php')['html']);
        same($xpath->query('//input[@id="' . $field . '" and @aria-invalid="true"]')->length, 1, 'Schedule has field-level error');
    }
    fixture();
    $_POST['publish_at'] = '2027-04-02T10:00';
    $_POST['unpublish_at'] = '2027-04-01T10:00';
    same(str_contains(runHandler('admin/board_save.php')['location'], 'err=publish_range'), true, 'Inverted schedule rejected');

    foreach (['upload', 'evidence', 'revision', 'redirect', 'commit'] as $failure) {
        $pdo = fixture();
        $_POST['is_published'] = '1';
        $_FILES = ['board_image' => ['name' => 'new.png', 'error' => UPLOAD_ERR_OK],
            'file' => ['name' => $failure === 'upload' ? 'invalid.exe' : 'new.txt', 'error' => UPLOAD_ERR_OK]];
        if ($failure === 'evidence') {
            $pdo->exec("CREATE TRIGGER owned_failure BEFORE INSERT ON cms_board_publication_events BEGIN SELECT RAISE(ABORT, 'Owned evidence failure'); END");
        }
        if ($failure === 'revision') {
            $pdo->exec("CREATE TRIGGER owned_failure BEFORE INSERT ON cms_revisions BEGIN SELECT RAISE(ABORT, 'Owned revision failure'); END");
        }
        if ($failure === 'redirect') {
            $_POST['slug'] = 'changed-slug';
            $pdo->exec("CREATE TRIGGER owned_failure BEFORE INSERT ON cms_redirects BEGIN SELECT RAISE(ABORT, 'Owned redirect failure'); END");
        }
        $pdo->failCommit = $failure === 'commit';
        $before = snapshot();
        $files = $GLOBALS['files'];
        $response = runHandler('admin/board_save.php');
        same(str_contains($response['location'], 'err=' . ($failure === 'upload' ? 'file' : 'save')), true, 'Truthful save error ' . $failure);
        same(snapshot(), $before, 'Complete rollback ' . $failure);
        same($GLOBALS['files'], $files, 'Original bytes retained, new files cleaned ' . $failure);
        same($GLOBALS['notices'], [], 'No notification before successful commit');
        same($pdo->inTransaction(), false, 'No leaked transaction');
    }
    $pdo = fixture();
    unset($_POST['id']);
    $_POST['slug'] = 'new-document';
    $_POST['is_published'] = '1';
    $pdo->exec("CREATE TRIGGER owned_failure BEFORE INSERT ON cms_board_publication_events BEGIN SELECT RAISE(ABORT, 'Owned evidence failure'); END");
    $before = snapshot();
    same(str_contains(runHandler('admin/board_save.php')['location'], '?err=save'), true, 'New failed insert returns new editor');
    same(snapshot(), $before, 'Failed creation leaves no orphan document/evidence');
    same(adminEditorFormFlashTake('board', null)['title'] ?? '', $_POST['title'], 'Failed new insert keeps correct draft key');

    $pdo = fixture();
    $_POST['is_published'] = '1';
    $_FILES['file'] = ['name' => 'new.txt', 'error' => UPLOAD_ERR_OK];
    same(runHandler('admin/board_save.php')['location'], '/admin/board.php', 'Successful replacement');
    $document = rows('cms_board')[0];
    same($GLOBALS['files'][$document['filename']] ?? '', 'new bytes', 'Replacement bytes installed');
    same(isset($GLOBALS['files']['original.txt']), false, 'Unreferenced original cleaned after commit');
    same(rows('cms_board_publication_events')[0]['attachment_checksum'], hash('sha256', 'new bytes'), 'Evidence records new attachment checksum');

    // Clones share images, including while waiting in trash for restoration.
    $pdo = fixture();
    $pdo->failCommit = true;
    $before = snapshot();
    same(runHandler('admin/board_clone.php')['location'], '/admin/board.php?clone_error=failed', 'Failed clone has truthful error');
    same(snapshot(), $before, 'Clone and its log roll back together');
    $pdo = fixture();
    same(str_contains(runHandler('admin/board_clone.php')['location'], 'id=2'), true, 'Actual clone created');
    $pdo->exec("UPDATE cms_board SET deleted_at = '2026-01-01' WHERE id = 2");
    $_POST['confirm_board_image_delete'] = '1';
    same(runHandler('admin/board_save.php')['location'], '/admin/board.php', 'Image detached from original');
    same(isset($GLOBALS['files']['shared.png']), true, 'Trashed clone keeps shared image');
    $pdo->exec('DELETE FROM cms_board WHERE id = 2');
    deleteUnusedBoardFiles($pdo, ['image_file' => 'shared.png']);
    same(isset($GLOBALS['files']['shared.png']), false, 'Last reference cleanup');

    foreach (['board_image_delete', 'confirm_board_image_delete'] as $field) {
        fixture();
        $_POST[$field] = $field === 'board_image_delete' ? '1' : 'yes';
        $before = snapshot();
        same(str_contains(runHandler('admin/board_save.php')['location'], 'err=image_confirm'), true, 'Legacy/invalid consent rejected');
        same(snapshot(), $before, 'Rejected consent does not write');
        same(isset($GLOBALS['files']['shared.png']), true, 'Rejected consent retains file');
    }
    foreach ([false, true] as $confirmed) {
        $pdo = fixture();
        $_POST = ['csrf_token' => 'owned-token', 'id' => '1'];
        if ($confirmed) {
            $_POST['confirm_board_delete_1'] = '1';
        }
        $before = snapshot();
        if ($confirmed) {
            $pdo->exec("CREATE TRIGGER owned_failure BEFORE UPDATE OF deleted_at ON cms_board BEGIN SELECT RAISE(ABORT, 'Owned delete failure'); END");
        }
        same(str_contains(runHandler('admin/board_delete.php')['location'], $confirmed ? 'delete_error=failed' : 'delete_error=confirm_required'), true, 'Missing consent/failed deletion explained');
        same(snapshot(), $before, 'Deletion failure rolls back evidence and data');
    }
    fixture();
    $_POST = ['csrf_token' => 'owned-token', 'id' => '1', 'confirm_board_delete_1' => '1'];
    same(runHandler('admin/board_delete.php')['location'], '/admin/board.php?deleted=1', 'Confirmed trash succeeds');
    same(rows('cms_board')[0]['deleted_at'] !== null, true, 'Document now in trash');
    same(rows('cms_board_publication_events')[0]['event_type'], 'deleted', 'Delete evidence committed');
    $before = snapshot();
    same(str_contains(runHandler('admin/board_delete.php')['location'], 'delete_error=invalid'), true, 'Repeat delete is not a new success');
    same(snapshot(), $before, 'Repeat delete leaves evidence/log unchanged');
    same(isset($GLOBALS['files']['original.txt']), true, 'Soft deletion retains attachment');

    // A rejected mailing restores content, never consent, even from a legacy session.
    $pdo = fixture();
    $_POST = ['csrf_token' => 'owned-token', 'subject' => 'Český předmět', 'body' => '', 'confirm_newsletter_send' => '1'];
    same(runHandler('admin/newsletter_send.php')['location'], '/admin/newsletter_form.php', 'Invalid mailing returns editor');
    same(isset($_SESSION['newsletter_form_state']['confirm_newsletter_send']), false, 'Consent not stored in mailing flash');
    $pdo->exec('UPDATE cms_subscribers SET confirmed = 1');
    $_SESSION['newsletter_form_state']['confirm_newsletter_send'] = '1';
    $xpath = aria(runHandler('admin/newsletter_form.php')['html']);
    same($xpath->query('//input[@name="confirm_newsletter_send" and @checked]')->length, 0, 'Legacy stored consent ignored');
    same($xpath->query('//input[@name="subject"]')->item(0)->getAttribute('value'), 'Český předmět', 'Mail subject retained');
    same($GLOBALS['mail'], [], 'Invalid mailing sends no mail');

    fixture();
    $_SESSION['newsletter_form_state'] = ['confirm_newsletter_send' => '1'];
    $_POST = ['csrf_token' => 'owned-token', 'subject' => 'Předmět', 'body' => 'Úplný text'];
    runHandler('admin/newsletter_send.php');
    same($GLOBALS['mail'], [], 'Old session consent cannot authorize a complete mailing');
    same(in_array('confirm_newsletter_send', $_SESSION['newsletter_form_error_fields'] ?? [], true), true, 'New confirmation explicitly required');

    foreach ([false, true] as $mailResult) {
        fixture();
        $GLOBALS['mailResult'] = $mailResult;
        $_POST = ['csrf_token' => 'owned-token', 'id' => '1', 'action' => 'resend', 'redirect' => 'https://evil.example/'];
        $before = rows('cms_subscribers');
        $response = runHandler('admin/newsletter_subscriber_action.php');
        same(rows('cms_subscribers'), $before, 'Resend success/failure preserves persisted token and state');
        same($GLOBALS['mail'][0][1], $before[0]['token'], 'Resend uses original working link');
        same(str_starts_with($response['location'], '/admin/newsletter.php?'), true, 'External redirect rejected');
    }
    fixture();
    $pdo = db_connect();
    $pdo->exec('UPDATE cms_subscribers SET confirmed = 1');
    $_POST = ['csrf_token' => 'owned-token', 'id' => '1', 'action' => 'resend'];
    runHandler('admin/newsletter_subscriber_action.php');
    same($GLOBALS['mail'], [], 'Confirmed subscriber is not resent');

    foreach ([false, true] as $mailResult) {
        $pdo = fixture();
        $pdo->exec("INSERT INTO cms_subscribers VALUES (2, 'confirmed@example.test', 'confirmed-token', 1), (3, 'other@example.test', 'other-token', 0)");
        $GLOBALS['mailResult'] = $mailResult;
        $_POST = ['csrf_token' => 'owned-token', 'action' => 'resend', 'ids' => ['1', '2'], 'confirm_newsletter_bulk_action' => '1'];
        $before = rows('cms_subscribers');
        runHandler('admin/newsletter_bulk.php');
        same(rows('cms_subscribers'), $before, 'Bulk resend success/failure preserves all token/state snapshots');
        same(count($GLOBALS['mail']), 1, 'Bulk resend skips confirmed/unselected subscribers');
        same($GLOBALS['mail'][0][1], $before[0]['token'], 'Bulk resend reuses working link');
    }

    echo 'Board/newsletter integrity self-test OK: ' . $GLOBALS['checks'] . ' checks; production handlers, SQL, rollback and DOM; no application DB/mail.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL);
    exit(1);
}
