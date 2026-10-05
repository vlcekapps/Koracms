<?php

declare(strict_types=1);

namespace KoraRcDownloadsTest;

use PDO;
use RuntimeException;
use Throwable;

// Only transport/auth/session seams are replaced; handlers, SQL and file mutations run unchanged.
final class EndpointExit extends RuntimeException
{
}

final class FixtureDatabase extends PDO
{
    public bool $failNextCommit = false;

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $query = str_replace(
            'ON DUPLICATE KEY UPDATE new_path = VALUES(new_path), status_code = VALUES(status_code)',
            'ON CONFLICT(old_path) DO UPDATE SET new_path = excluded.new_path, status_code = excluded.status_code',
            $query
        );
        return parent::prepare($query, $options);
    }

    public function commit(): bool
    {
        if ($this->failNextCommit) {
            $this->failNextCommit = false;
            throw new \PDOException('Forced fixture commit failure.');
        }
        return parent::commit();
    }
}

function same(mixed $actual, mixed $expected, string $label): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    $GLOBALS['rcChecks']++;
}

function db_connect(): PDO
{
    return $GLOBALS['rcDb'];
}
function currentUserHasCapability(string $capability): bool
{
    return $GLOBALS['rcStaff'];
}
function currentUserId(): int
{
    return 1;
}
function requireCapability(string $capability, string $message): void
{
    if (!$GLOBALS['rcStaff']) {
        throw new RuntimeException('Fixture admin access denied.');
    }
}
function requireModuleEnabled(string $module): void
{
}
function isModuleEnabled(string $module): bool
{
    return $GLOBALS['rcModule'];
}
function checkMaintenanceMode(): void
{
}
function verifyCsrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['csrf_token'] ?? '') !== 'fixture-csrf') {
        throw new RuntimeException('Fixture CSRF/method rejected.');
    }
}
function csrfToken(): string
{
    return 'fixture-csrf';
}
function cspNonce(): string
{
    return 'fixture-nonce';
}
function getSetting(string $key, string $fallback = ''): string
{
    return $fallback;
}
function siteUrl(string $path = ''): string
{
    return 'https://fixture.example' . $path;
}
function adminHeader(string $title): void
{
}
function adminFooter(): void
{
}
function adminHtmlSnippetSupportMarkup(): string
{
    return '';
}
function renderAdminContentReferencePicker(string $field): void
{
}
function bulkActions(mixed ...$args): string
{
    return '';
}
function bulkCheckboxJs(): string
{
    return '';
}
function koraLog(mixed ...$args): void
{
}
function notifyPendingContent(mixed ...$args): void
{
    throw new RuntimeException('Mail is forbidden in this fixture.');
}
function sendNoStoreNoIndexHeaders(): void
{
}
function sendNoSniffHeader(): void
{
}
function header(string $value, bool $replace = true, int $status = 0): void
{
    $GLOBALS['rcHeaders'][] = $value;
    if ($status !== 0) {
        $GLOBALS['rcStatus'] = $status;
    }
}
function sendFileDownloadNotFound(string $message = '', bool $head = false): void
{
    $GLOBALS['rcStatus'] = 404;
    throw new EndpointExit();
}
function sendStoredFileDownload(string $path, string $name): void
{
    $GLOBALS['rcAttachment'] = [$path, $name];
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        echo file_get_contents($path);
    }
    throw new EndpointExit();
}
function renderPublicNotFoundPage(array $page): void
{
    sendFileDownloadNotFound();
}
function renderPublicPage(array $page): void
{
    $GLOBALS['rcPage'] = $page;
}
function trackPageView(mixed ...$args): void
{
}
function logAction(string $action, string $details): void
{
    db_connect()->prepare('INSERT INTO fixture_logs (action, details) VALUES (?, ?)')->execute([$action, $details]);
}
function saveRevision(PDO $pdo, string $type, int $id, array $old, array $new): void
{
    $pdo->prepare('INSERT INTO fixture_revisions (entity_id) VALUES (?)')->execute([$id]);
}
function koraStoragePath(string $relative): string
{
    return $GLOBALS['rcRoot'] . '/private/' . $relative;
}
function koraEnsureDirectory(string $path, int $permissions = 0755): bool
{
    $normalized = str_replace('\\', '/', $path);
    if ($normalized !== $GLOBALS['rcRoot'] && !str_starts_with($normalized, $GLOBALS['rcRoot'] . '/')) {
        throw new RuntimeException('Write outside the owned fixture root.');
    }
    return is_dir($path) || mkdir($path, $permissions, true);
}
function is_uploaded_file(string $path): bool
{
    return isset($GLOBALS['rcUploads'][$path]) && \is_file($path);
}
function move_uploaded_file(string $source, string $target): bool
{
    return is_uploaded_file($source) && \rename($source, $target);
}

function writeFixture(string $path, string $bytes): void
{
    koraEnsureDirectory(dirname($path));
    if (file_put_contents($path, $bytes) !== strlen($bytes)) {
        throw new RuntimeException('Cannot write fixture.');
    }
}

function productionFunction(string $file, string $name): string
{
    $source = (string)file_get_contents($file);
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) {
        throw new RuntimeException('Missing production function ' . $name);
    }
    $body = '';
    $depth = 0;
    $opened = false;
    foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
        if (is_array($token)) {
            if ($token[0] !== T_OPEN_TAG) {
                $body .= $token[1];
            }
            continue;
        }
        $body .= $token;
        if ($token === '{') {
            $depth++;
            $opened = true;
        } elseif ($token === '}' && --$depth === 0 && $opened) {
            return $body;
        }
    }
    throw new RuntimeException('Unterminated production function ' . $name);
}

function copyProduction(string $relative): void
{
    $source = (string)file_get_contents($GLOBALS['rcProject'] . '/' . $relative);
    $copy = '';
    foreach (token_get_all($source) as $token) {
        $copy .= is_array($token) && $token[0] === T_EXIT ? 'throw new EndpointExit()' : (is_array($token) ? $token[1] : $token);
    }
    $copy = '<?php ' . $GLOBALS['rcPrefix'] . substr($copy, 5);
    writeFixture($GLOBALS['rcRoot'] . '/' . $relative, $copy);
}

function request(string $relative, array $post = [], array $get = [], string $method = 'POST', array $files = []): array
{
    $_POST = $post;
    $_GET = $get;
    $_FILES = $files;
    $_SERVER = ['REQUEST_METHOD' => $method, 'QUERY_STRING' => http_build_query($get)];
    $GLOBALS['rcHeaders'] = [];
    $GLOBALS['rcStatus'] = 200;
    $GLOBALS['rcPage'] = null;
    $GLOBALS['rcAttachment'] = null;
    ob_start();
    try {
        require $GLOBALS['rcRoot'] . '/' . $relative;
    } catch (EndpointExit $e) {
    } finally {
        $body = (string)ob_get_clean();
    }
    return ['headers' => $GLOBALS['rcHeaders'], 'status' => $GLOBALS['rcStatus'], 'body' => $body, 'page' => $GLOBALS['rcPage']];
}

function formPost(?int $id = null): array
{
    return [
        'csrf_token' => 'fixture-csrf', 'id' => $id ?? '', 'title' => 'Rozepsaná příručka',
        'slug' => 'rozepsana-prirucka', 'download_type' => 'document', 'dl_category_id' => '1',
        'excerpt' => 'Nový perex', 'description' => '<p>Nový český popis</p>',
        'version_label' => '2.0', 'platform_label' => 'Windows', 'license_label' => 'MIT',
        'project_url' => 'example.com/project', 'release_date' => '2026-09-01',
        'requirements' => 'České požadavky', 'checksum_sha256' => '', 'download_series_id' => '1',
        'external_url' => 'example.com/release', 'is_current_version' => '1',
        'is_featured' => '1', 'is_published' => '1', 'article_status' => 'published',
    ];
}

function upload(string $name, string $bytes): array
{
    $path = $GLOBALS['rcRoot'] . '/incoming/' . bin2hex(random_bytes(8));
    writeFixture($path, $bytes);
    $GLOBALS['rcUploads'][$path] = true;
    return ['name' => $name, 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
}

function element(string $html, string $tag, string $attribute, string $value): \DOMElement
{
    $dom = new \DOMDocument();
    $old = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($old);
    $found = (new \DOMXPath($dom))->query('//' . $tag . '[@' . $attribute . '="' . $value . '"]')->item(0);
    if (!$found instanceof \DOMElement) {
        throw new RuntimeException('Missing rendered ' . $tag . ' ' . $value);
    }
    return $found;
}

function validDescriptions(string $html): void
{
    $dom = new \DOMDocument();
    $old = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($old);
    $xpath = new \DOMXPath($dom);
    foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby]') as $node) {
        foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
            foreach (preg_split('/\s+/', trim($node->getAttribute($attribute))) ?: [] as $id) {
                if ($id !== '') {
                    same($xpath->query('//*[@id="' . $id . '"]')->length, 1, 'Existing unique ARIA reference ' . $id);
                }
            }
        }
    }
}

function removeFixtureTree(): void
{
    $root = realpath($GLOBALS['rcRoot']);
    if ($root === false || str_replace('\\', '/', $root) !== $GLOBALS['rcRoot']) {
        throw new RuntimeException('Refusing cleanup outside owned fixture root.');
    }
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($root);
}

$GLOBALS['rcProject'] = dirname(__DIR__);
$GLOBALS['rcRoot'] = str_replace('\\', '/', sys_get_temp_dir()) . '/kora_rc_downloads_' . bin2hex(random_bytes(12));
$GLOBALS['rcPrefix'] = 'namespace KoraRcDownloadsTest; use \\PDO; use \\RuntimeException; use \\Throwable; use \\DateTimeImmutable; use \\finfo;';
$GLOBALS['rcChecks'] = 0;
$GLOBALS['rcStaff'] = true;
$GLOBALS['rcModule'] = true;
$GLOBALS['rcUploads'] = [];
$_SESSION = [];
mkdir($GLOBALS['rcRoot'], 0700);
$exitCode = 0;

try {
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true) || !class_exists('DOMDocument')) {
        throw new RuntimeException('Standalone test requires pdo_sqlite and DOM.');
    }
    define('BASE_URL', '');
    foreach (['lib/presentation.php', 'lib/definitions.php', 'lib/uploads.php', 'lib/media_library.php',
        'admin/download_save.php', 'admin/download_form.php', 'admin/download_delete.php', 'admin/downloads.php',
        'downloads/file.php', 'downloads/external.php', 'downloads/item.php', 'downloads/series.php'] as $relative) {
        copyProduction($relative);
    }
    foreach (['db.php', 'admin/layout.php', 'admin/content_reference_picker.php'] as $relative) {
        writeFixture($GLOBALS['rcRoot'] . '/' . $relative, '<?php');
    }
    foreach (['presentation', 'definitions', 'uploads', 'media_library'] as $library) {
        require $GLOBALS['rcRoot'] . '/lib/' . $library . '.php';
    }
    foreach (['db.php' => ['inputInt', 'h'], 'auth.php' => ['normalizeHttpExternalUrl', 'normalizeHttpMethods', 'requireHttpMethods', 'internalRedirectTarget', 'storedRedirectTarget'],
        'lib/filedownloads.php' => ['safeDownloadName', 'requireReadOnlyHttpMethod', 'moduleFileUrl'],
        'admin/layout.php' => ['adminFieldHasError', 'adminFieldErrorId', 'adminFieldAttributes', 'adminRenderFieldError']] as $file => $names) {
        foreach ($names as $name) {
            eval($GLOBALS['rcPrefix'] . productionFunction($GLOBALS['rcProject'] . '/' . $file, $name));
        }
    }
    $pdo = new FixtureDatabase('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => getenv('KORA_RC_STRINGIFY_FETCHES') === '1',
    ]);
    $GLOBALS['rcDb'] = $pdo;
    $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-10-05 12:00:00');
    $pdo->sqliteCreateFunction('CONCAT', static fn (mixed ...$values): string => implode('', $values));
    $pdo->exec("CREATE TABLE cms_downloads (
        id INTEGER PRIMARY KEY, title TEXT DEFAULT '', slug TEXT UNIQUE, download_type TEXT DEFAULT 'document',
        dl_category_id INTEGER, excerpt TEXT DEFAULT '', description TEXT DEFAULT '', image_file TEXT DEFAULT '',
        version_label TEXT DEFAULT '', platform_label TEXT DEFAULT '', license_label TEXT DEFAULT '', project_url TEXT DEFAULT '',
        release_date TEXT, requirements TEXT DEFAULT '', checksum_sha256 TEXT DEFAULT '', series_key TEXT DEFAULT '',
        external_url TEXT DEFAULT '', download_series_id INTEGER, is_current_version INTEGER DEFAULT 0,
        filename TEXT DEFAULT '', original_name TEXT DEFAULT '', file_size INTEGER DEFAULT 0, is_featured INTEGER DEFAULT 0,
        is_published INTEGER DEFAULT 1, status TEXT DEFAULT 'published', author_id INTEGER,
        download_count INTEGER DEFAULT 0, external_click_count INTEGER DEFAULT 0, deleted_at TEXT,
        created_at TEXT DEFAULT '2026-09-01 12:00:00', updated_at TEXT DEFAULT '2026-09-01 12:00:00'
    );
    CREATE TABLE cms_download_series (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, description TEXT DEFAULT '', is_active INTEGER DEFAULT 1, sort_order INTEGER DEFAULT 0);
    CREATE TABLE cms_dl_categories (id INTEGER PRIMARY KEY, name TEXT, slug TEXT);
    CREATE TABLE cms_users (id INTEGER PRIMARY KEY, nickname TEXT, first_name TEXT, last_name TEXT, email TEXT);
    CREATE TABLE fixture_logs (action TEXT, details TEXT);
    CREATE TABLE fixture_revisions (entity_id INTEGER);
    CREATE TABLE cms_redirects (old_path TEXT UNIQUE, new_path TEXT, status_code INTEGER);
    INSERT INTO cms_download_series (id, title, slug) VALUES (1, 'Příručky', 'prirucky');
    INSERT INTO cms_dl_categories VALUES (1, 'Dokumenty', 'dokumenty');
    INSERT INTO cms_downloads (id, title, slug, filename, original_name, image_file, download_series_id, is_current_version)
    VALUES (1, 'Původní příručka', 'puvodni-prirucka', 'owned.txt', 'Příručka.txt', 'owned.png', 1, 1);");
    $filePath = $GLOBALS['rcRoot'] . '/uploads/downloads/owned.txt';
    $imagePath = $GLOBALS['rcRoot'] . '/uploads/downloads/images/owned.png';
    writeFixture($filePath, 'original bytes');
    writeFixture($imagePath, 'original image bytes');

    $post = formPost(1);
    $post['slug'] = 'neulozeny-slug';
    $post['project_url'] = 'javascript:bad';
    $post['checksum_sha256'] = 'unfinished checksum';
    $post['confirm_download_file_delete'] = '1';
    $response = request('admin/download_save.php', $post);
    same(str_contains(implode('\n', $response['headers']), 'err=checksum'), true, 'Invalid checksum rejected');
    $response = request('admin/download_form.php', [], ['id' => '1', 'err' => 'checksum'], 'GET');
    same(element($response['body'], 'input', 'id', 'title')->getAttribute('value'), $post['title'], 'Validation preserves edited title');
    same(element($response['body'], 'input', 'id', 'project_url')->getAttribute('value'), $post['project_url'], 'Invalid raw URL remains editable');
    same(element($response['body'], 'input', 'id', 'checksum_sha256')->getAttribute('value'), $post['checksum_sha256'], 'Invalid raw checksum remains editable');
    same(element($response['body'], 'textarea', 'id', 'description')->textContent, $post['description'], 'Description preserved');
    same(str_contains($response['body'], 'href="/downloads/puvodni-prirucka"'), true, 'Public preview still targets persisted slug');
    foreach (['is_featured', 'is_published', 'is_current_version'] as $name) {
        same(element($response['body'], 'input', 'name', $name)->hasAttribute('checked'), true, 'Data checkbox preserved: ' . $name);
    }
    foreach (['confirm_download_file_delete', 'confirm_download_image_delete'] as $name) {
        same(element($response['body'], 'input', 'name', $name)->hasAttribute('checked'), false, 'Confirmation is fresh: ' . $name);
    }
    validDescriptions($response['body']);
    same(isset($_SESSION['download_form_flash']), false, 'Flash consumed once');

    $sourcePost = array_merge(formPost(1), ['external_url' => '', 'confirm_download_file_delete' => '1']);
    $response = request('admin/download_save.php', $sourcePost);
    same(str_contains(implode('\n', $response['headers']), 'err=source'), true, 'Confirmed removal cannot delete the only source');
    same(file_get_contents($filePath), 'original bytes', 'Only source retained after rejection');
    $response = request('admin/download_form.php', [], ['id' => '1', 'err' => 'source'], 'GET');
    same(element($response['body'], 'input', 'name', 'confirm_download_file_delete')->hasAttribute('checked'), false, 'Source error requires renewed deletion consent');
    same(element($response['body'], 'input', 'id', 'title')->getAttribute('value'), $sourcePost['title'], 'Source error retains edited title');
    validDescriptions($response['body']);

    $newPost = formPost();
    $newPost['external_url'] = 'javascript:bad';
    $newPost['article_status'] = 'draft';
    unset($newPost['is_featured'], $newPost['is_published'], $newPost['is_current_version']);
    request('admin/download_save.php', $newPost);
    $response = request('admin/download_form.php', [], ['id' => '1'], 'GET');
    same(element($response['body'], 'input', 'id', 'title')->getAttribute('value'), 'Původní příručka', 'Other editor does not consume new-item flash');
    $response = request('admin/download_form.php', [], ['err' => 'url'], 'GET');
    same(element($response['body'], 'input', 'id', 'external_url')->getAttribute('value'), 'javascript:bad', 'New item retains invalid external URL');
    same(element($response['body'], 'option', 'value', 'draft')->hasAttribute('selected'), true, 'Draft status retained on error');
    foreach (['is_featured', 'is_published', 'is_current_version'] as $name) {
        same(element($response['body'], 'input', 'name', $name)->hasAttribute('checked'), false, 'Unchecked data preserved: ' . $name);
    }

    // Legacy autosave fields or forged checkbox values must not remove attachments.
    foreach ([['file_delete' => '1'], ['download_image_delete' => '1'],
        ['confirm_download_file_delete' => '0'], ['confirm_download_image_delete' => '0']] as $fields) {
        $response = request('admin/download_save.php', array_merge(formPost(1), $fields));
        same(str_contains(implode('\n', $response['headers']), 'confirmation'), true, 'Unconfirmed removal rejected');
        same(file_get_contents($filePath), 'original bytes', 'Unconfirmed file removal preserves bytes');
        same(file_get_contents($imagePath), 'original image bytes', 'Unconfirmed image removal preserves bytes');
    }
    $response = request('admin/download_form.php', [], ['id' => '1', 'err' => 'image_delete_confirmation'], 'GET');
    same(element($response['body'], 'input', 'name', 'confirm_download_image_delete')->getAttribute('aria-invalid'), 'true', 'Attachment confirmation error rendered');
    validDescriptions($response['body']);
    same((int)$pdo->query('SELECT COUNT(*) FROM fixture_logs')->fetchColumn(), 0, 'Rejected saves do not audit');
    same((int)$pdo->query('SELECT COUNT(*) FROM fixture_revisions')->fetchColumn(), 0, 'Rejected saves do not revise');

    request('admin/download_save.php', array_merge(formPost(), ['external_url' => 'javascript:bad']));
    $response = request('admin/download_save.php', formPost(1));
    same($response['headers'], ['Location: /admin/downloads.php'], 'Successful metadata edit');
    same(array_key_exists('download_id', $_SESSION['download_form_flash'] ?? []) && $_SESSION['download_form_flash']['download_id'] === null, true, 'Saving another ID preserves waiting new-item flash');
    same($pdo->query('SELECT external_url FROM cms_downloads WHERE id = 1')->fetchColumn(), 'https://example.com/release', 'External URL normalized by production helper');
    same(file_get_contents($filePath), 'original bytes', 'Metadata edit retains local file');
    $newPost = formPost();
    $newPost['slug'] = 'nova-verze';
    $response = request('admin/download_save.php', $newPost);
    same($response['headers'], ['Location: /admin/downloads.php'], 'New external version created');
    same(isset($_SESSION['download_form_flash']), false, 'Successful matching save clears its flash');
    same((int)$pdo->query('SELECT COUNT(*) FROM cms_downloads WHERE download_series_id = 1 AND is_current_version = 1')->fetchColumn(), 1, 'At most one current version, numeric COUNT');
    same((int)$pdo->query('SELECT is_current_version FROM cms_downloads WHERE id = 1')->fetchColumn(), 0, 'Previous current version demoted');

    $GLOBALS['rcStaff'] = false;
    $response = request('downloads/item.php', [], ['slug' => 'rozepsana-prirucka'], 'GET');
    same((int)$response['page']['view_data']['currentVersion']['id'], 2, 'Old detail points to current public version');
    $response = request('downloads/series.php', [], ['slug' => 'prirucky'], 'GET');
    same((int)$response['page']['view_data']['items'][0]['id'], 2, 'Current version first on series page');
    $response = request('downloads/file.php', [], ['id' => '1'], 'HEAD');
    same($response['body'], '', 'HEAD file has no body');
    same((int)$pdo->query('SELECT download_count FROM cms_downloads WHERE id = 1')->fetchColumn(), 0, 'HEAD does not count downloads');
    $response = request('downloads/file.php', [], ['id' => '1'], 'GET');
    same($response['body'], 'original bytes', 'Public local download uses owned file');
    same((int)$pdo->query('SELECT download_count FROM cms_downloads WHERE id = 1')->fetchColumn(), 1, 'GET counts once');
    $response = request('downloads/external.php', [], ['id' => '1'], 'HEAD');
    same($response['headers'], ['Location: https://example.com/release'], 'HEAD external redirect');
    same((int)$pdo->query('SELECT external_click_count FROM cms_downloads WHERE id = 1')->fetchColumn(), 0, 'HEAD external not counted');
    $response = request('downloads/external.php', [], ['id' => '1'], 'GET');
    same($response['status'], 302, 'GET external redirect status');
    same((int)$pdo->query('SELECT external_click_count FROM cms_downloads WHERE id = 1')->fetchColumn(), 1, 'GET external counted once');
    $GLOBALS['rcModule'] = false;
    foreach (['downloads/file.php', 'downloads/external.php'] as $endpoint) {
        same(request($endpoint, [], ['id' => '1'], 'GET')['status'], 404, 'Disabled module denies public source: ' . $endpoint);
    }
    $GLOBALS['rcModule'] = true;
    $GLOBALS['rcStaff'] = true;

    foreach ([[], ['confirm_download_delete_2' => '1'], ['confirm_download_delete_1' => '0']] as $fields) {
        request('admin/download_delete.php', array_merge(['csrf_token' => 'fixture-csrf', 'id' => '1'], $fields));
        same($pdo->query('SELECT deleted_at FROM cms_downloads WHERE id = 1')->fetchColumn(), null, 'Missing, foreign or false item confirmation rejected');
    }
    same((int)$pdo->query("SELECT COUNT(*) FROM fixture_logs WHERE action = 'download_delete'")->fetchColumn(), 0, 'Unconfirmed delete not audited');
    $response = request('admin/downloads.php', [], ['delete_error' => 'confirm_required', 'delete_error_id' => '1'], 'GET');
    $confirm = element($response['body'], 'input', 'name', 'confirm_download_delete_1');
    same($confirm->getAttribute('aria-invalid'), 'true', 'Delete error attached to confirmation');
    same($confirm->hasAttribute('checked'), false, 'Delete confirmation fresh');
    validDescriptions($response['body']);

    $before = $pdo->query('SELECT * FROM cms_downloads WHERE id = 1')->fetch();
    $logsBefore = (int)$pdo->query('SELECT COUNT(*) FROM fixture_logs')->fetchColumn();
    $revisionsBefore = (int)$pdo->query('SELECT COUNT(*) FROM fixture_revisions')->fetchColumn();
    $failedPost = array_merge(formPost(1), ['title' => 'Neuložená změna', 'confirm_download_image_delete' => '1']);
    $pdo->failNextCommit = true;
    $response = request('admin/download_save.php', $failedPost, [], 'POST', ['file' => upload('replacement.txt', 'failed replacement bytes')]);
    same(str_contains(implode('\n', $response['headers']), 'err=file'), true, 'Commit failure returns form error');
    same($pdo->query('SELECT * FROM cms_downloads WHERE id = 1')->fetch(), $before, 'Commit failure rolls row back');
    same(file_get_contents($filePath), 'original bytes', 'Commit failure restores file bytes');
    same(file_get_contents($imagePath), 'original image bytes', 'Commit failure restores image bytes');
    same((int)$pdo->query('SELECT COUNT(*) FROM fixture_logs')->fetchColumn(), $logsBefore, 'Commit failure rolls audit back');
    same((int)$pdo->query('SELECT COUNT(*) FROM fixture_revisions')->fetchColumn(), $revisionsBefore, 'Commit failure rolls revision back');
    same((int)$pdo->query('SELECT COUNT(*) FROM cms_downloads WHERE download_series_id = 1 AND is_current_version = 1')->fetchColumn(), 1, 'Commit failure restores current-version invariant');
    $response = request('admin/download_form.php', [], ['id' => '1', 'err' => 'file'], 'GET');
    same(element($response['body'], 'input', 'id', 'title')->getAttribute('value'), 'Neuložená změna', 'Commit error retains submitted title');
    same(element($response['body'], 'input', 'name', 'confirm_download_image_delete')->hasAttribute('checked'), false, 'Commit error cannot restore deletion consent');
    same(str_contains($response['body'], 'nutné vybrat znovu'), true, 'Upload re-selection explained');
    validDescriptions($response['body']);

    $response = request('admin/download_save.php', formPost(1), [], 'POST', ['file' => upload('replacement.txt', 'new release bytes')]);
    same($response['headers'], ['Location: /admin/downloads.php'], 'Replacement upload saved');
    $row = $pdo->query('SELECT * FROM cms_downloads WHERE id = 1')->fetch();
    $replacementPath = $GLOBALS['rcRoot'] . '/uploads/downloads/' . $row['filename'];
    same(file_get_contents($replacementPath), 'new release bytes', 'New upload installed');
    same($row['checksum_sha256'], hash('sha256', 'new release bytes'), 'Upload checksum describes installed bytes');
    same(is_file($filePath), false, 'Old local file removed after commit');
    $response = request('admin/download_save.php', array_merge(formPost(1), ['confirm_download_file_delete' => '1', 'confirm_download_image_delete' => '1']));
    same($response['headers'], ['Location: /admin/downloads.php'], 'Fresh attachment removal saved');
    same(is_file($replacementPath), false, 'Confirmed file removed');
    same(is_file($imagePath), false, 'Confirmed image removed');
    same($pdo->query('SELECT filename FROM cms_downloads WHERE id = 1')->fetchColumn(), '', 'External-only row has no local filename');

    $response = request('admin/download_delete.php', ['csrf_token' => 'fixture-csrf', 'id' => '1', 'confirm_download_delete_1' => '1']);
    same($response['headers'], ['Location: /admin/downloads.php?msg=deleted'], 'Confirmed soft deletion redirects');
    same((int)$pdo->query('SELECT COUNT(*) FROM cms_downloads WHERE id = 1 AND deleted_at IS NOT NULL')->fetchColumn(), 1, 'Confirmed soft deletion, numeric COUNT');
    same((int)$pdo->query("SELECT COUNT(*) FROM fixture_logs WHERE action = 'download_delete'")->fetchColumn(), 1, 'One delete audit entry');
    request('admin/download_delete.php', ['csrf_token' => 'fixture-csrf', 'id' => '1', 'confirm_download_delete_1' => '1']);
    same((int)$pdo->query("SELECT COUNT(*) FROM fixture_logs WHERE action = 'download_delete'")->fetchColumn(), 1, 'Repeated delete cannot audit a nonexistent transition');
    $GLOBALS['rcStaff'] = false;
    foreach (['downloads/file.php', 'downloads/external.php'] as $endpoint) {
        same(request($endpoint, [], ['id' => '1'], 'GET')['status'], 404, 'Deleted source denied: ' . $endpoint);
    }
    foreach (["status = 'draft'", 'is_published = 0', "external_url = 'javascript:alert(1)'", "external_url = 'https://example.com/' || char(13) || char(10) || 'X-Test: bad'"] as $state) {
        $pdo->exec("UPDATE cms_downloads SET deleted_at = NULL, status = 'published', is_published = 1, external_url = 'https://example.com/release'");
        $pdo->exec('UPDATE cms_downloads SET ' . $state . ' WHERE id = 1');
        same(request('downloads/external.php', [], ['id' => '1'], 'GET')['status'], 404, 'Nonpublic or unsafe external source denied');
    }
    echo 'PASS rc_download_integrity_selftest: ' . $GLOBALS['rcChecks'] . ' checks; SQLite stringify=' . (getenv('KORA_RC_STRINGIFY_FETCHES') === '1' ? 'on' : 'off') . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL rc_download_integrity_selftest: ' . $e->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    removeFixtureTree();
}
exit($exitCode);
