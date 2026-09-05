<?php

declare(strict_types=1);

namespace KoraRcMediaTest;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

/** Production helpers run from a private temporary tree, never against the CMS DB. */
final class FixtureDatabase extends PDO
{
    /** @param array<int,mixed> $options */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'INFORMATION_SCHEMA.TABLES')) {
            $query = "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?";
        } elseif (str_contains($query, 'INFORMATION_SCHEMA.COLUMNS')) {
            $query = 'SELECT COUNT(*) FROM pragma_table_info(?) WHERE name = ?';
        }
        return parent::prepare($query, $options);
    }
}

final class Response extends RuntimeException
{
    public function __construct(public int $status, public string $body = '', public bool $public = false)
    {
        parent::__construct('Fixture response ' . $status);
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

function koraStoragePath(string $relative): string
{
    return $GLOBALS['rcRoot'] . '/private/' . str_replace('\\', '/', $relative);
}

function koraEnsureDirectory(string $path, int $permissions = 0755): bool
{
    $normalized = str_replace('\\', '/', $path);
    if ($normalized !== $GLOBALS['rcRoot'] && !str_starts_with($normalized, $GLOBALS['rcRoot'] . '/')) {
        throw new RuntimeException('Fixture attempted to write outside its private tree.');
    }
    return is_dir($path) || mkdir($path, $permissions, true);
}

/** @param array<string,mixed> $context */
function koraLog(string $level, string $message, array $context = []): void
{
    $GLOBALS['rcLogs'][] = [$level, $message, $context];
}

function is_uploaded_file(string $path): bool
{
    return isset($GLOBALS['rcUploads'][$path]) && \is_file($path);
}

function move_uploaded_file(string $source, string $target): bool
{
    return is_uploaded_file($source) && \rename($source, $target);
}

function rename(string $source, string $target): bool
{
    return empty($GLOBALS['rcFailRename']) && \rename($source, $target);
}

function unlink(string $path): bool
{
    if (($GLOBALS['rcFailUnlink'][$path] ?? 0) > 0) {
        $GLOBALS['rcFailUnlink'][$path]--;
        return false;
    }
    return \unlink($path);
}

/** @return list<array<string,string>>|false */
function dns_get_record(string $host, int $type): array|false
{
    return $GLOBALS['rcDns'][$host] ?? [];
}

/** @return false */
function gethostbynamel(string $host): bool
{
    return false;
}

function checkMaintenanceMode(): void
{
}

function requireReadOnlyHttpMethod(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';
}

function isModuleEnabled(string $module): bool
{
    return true;
}

function currentUserHasCapability(string $capability): bool
{
    return (bool)($GLOBALS['rcStaff'] ?? false);
}

function inputInt(string $source, string $name): ?int
{
    $value = ($source === 'get' ? $_GET : $_POST)[$name] ?? null;
    return $value !== null ? (int)$value : null;
}

function sendFileDownloadNotFound(string $message = '', bool $head = false): void
{
    throw new Response(404);
}

function sendInlineStoredFileResponse(string $path, string $name, bool $public, bool $head): void
{
    throw new Response(200, $head ? '' : (string)file_get_contents($path), $public);
}

function sendStoredFileDownload(string $path, string $name): void
{
    if (!is_file($path)) {
        throw new Response(404);
    }
    throw new Response(200, requireReadOnlyHttpMethod() ? '' : (string)file_get_contents($path));
}

function mediaAdminRedirectWithFlash(string $target): void
{
    throw new Response(302);
}

function writeFixture(string $path, string $contents): void
{
    koraEnsureDirectory(dirname($path));
    if (file_put_contents($path, $contents) !== strlen($contents)) {
        throw new RuntimeException('Cannot write fixture.');
    }
}

function productionFunction(string $file, string $name): string
{
    $source = (string)file_get_contents($file);
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) {
        throw new RuntimeException('Missing production function: ' . $name);
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
    throw new RuntimeException('Unterminated production function: ' . $name);
}

/** @return array<string,mixed> */
function uploaded(string $name, string $bytes): array
{
    $path = $GLOBALS['rcRoot'] . '/incoming/' . bin2hex(random_bytes(8));
    writeFixture($path, $bytes);
    $GLOBALS['rcUploads'][$path] = true;
    return ['name' => $name, 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
}

function imageBytes(string $format): string
{
    $image = imagecreatetruecolor(20, 20);
    if ($image === false) {
        throw new RuntimeException('GD fixture unavailable.');
    }
    ob_start();
    if ($format === 'webp') {
        imagewebp($image);
    } elseif ($format === 'jpg') {
        imagejpeg($image);
    } else {
        imagepng($image);
    }
    $bytes = (string)ob_get_clean();
    imagedestroy($image);
    return $bytes;
}

/**
 * @param array<string,mixed> $media
 * @return array<string,string>
 */
function hashes(array $media): array
{
    $result = [];
    foreach (mediaPhysicalPaths($media) as $path) {
        if (is_file($path)) {
            $result[$path] = (string)hash_file('sha256', $path);
        }
    }
    return $result;
}

function removeFixtureTree(string $root): void
{
    $resolved = realpath($root);
    if ($resolved === false || str_replace('\\', '/', $resolved) !== $GLOBALS['rcRoot']) {
        throw new RuntimeException('Refusing cleanup outside the resolved fixture root.');
    }
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($resolved, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        if (!$item instanceof \SplFileInfo) {
            continue;
        }
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            \unlink($item->getPathname());
        }
    }
    rmdir($resolved);
}

function redirectServer(): void
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
    if ($socket === false) {
        throw new RuntimeException('Cannot start redirect fixture.');
    }
    $address = (string)stream_socket_get_name($socket, false);
    echo $address . PHP_EOL;
    flush();
    $requests = 0;
    while ($requests < 2 && ($client = @stream_socket_accept($socket, $requests === 0 ? 5 : 1)) !== false) {
        $requests++;
        $length = 0;
        while (($line = fgets($client)) !== false && trim($line) !== '') {
            if (stripos($line, 'Content-Length:') === 0) {
                $length = (int)substr($line, 15);
            }
        }
        if ($length > 0) {
            fread($client, $length);
        }
        fwrite($client, "HTTP/1.1 307 Temporary Redirect\r\nLocation: http://" . $address . "/internal\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
        fclose($client);
    }
    fclose($socket);
    echo 'REQUESTS=' . $requests;
    exit(0);
}

if (($argv[1] ?? '') === '--redirect-server') {
    redirectServer();
}

$projectRoot = dirname(__DIR__);
$GLOBALS['rcRoot'] = str_replace('\\', '/', sys_get_temp_dir()) . '/kora_rc_media_' . bin2hex(random_bytes(12));
$GLOBALS['rcChecks'] = 0;
$GLOBALS['rcUploads'] = [];
$GLOBALS['rcLogs'] = [];
$GLOBALS['rcFailUnlink'] = [];
$GLOBALS['rcDns'] = ['public.example' => [['ip' => '8.8.8.8']], 'mixed.example' => [['ip' => '8.8.8.8'], ['ip' => '127.0.0.1']]];
$exitCode = 0;
mkdir($GLOBALS['rcRoot'], 0700);

try {
    if (!extension_loaded('gd') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('This standalone self-test requires GD and pdo_sqlite.');
    }
    define('BASE_URL', '');
    $prefix = 'namespace KoraRcMediaTest; use \\PDO; use \\PDOException; use \\RuntimeException; use \\Throwable; use \\finfo; use \\DateTimeImmutable;';
    foreach (['lib/uploads.php', 'lib/gallery.php', 'lib/media_library.php', 'lib/presentation.php', 'lib/content.php', 'lib/webhooks.php', 'gallery/image.php', 'downloads/file.php', 'media/file.php'] as $relative) {
        $source = (string)file_get_contents($projectRoot . '/' . $relative);
        writeFixture($GLOBALS['rcRoot'] . '/' . $relative, '<?php ' . $prefix . substr($source, 5));
    }
    writeFixture($GLOBALS['rcRoot'] . '/db.php', '<?php');
    foreach (['normalizeHttpExternalUrl', 'serverFetchMappedIpv4Address', 'serverFetchIpAllowed', 'serverFetchResolvedAddresses'] as $name) {
        eval($prefix . productionFunction($projectRoot . '/auth.php', $name));
    }
    foreach (['uploads', 'gallery', 'media_library', 'presentation', 'content', 'webhooks'] as $library) {
        require $GLOBALS['rcRoot'] . '/lib/' . $library . '.php';
    }
    foreach (['safeDownloadName', 'safeDownloadAsciiFallback', 'storedFileContentDisposition', 'storedFileMimeType'] as $name) {
        eval($prefix . productionFunction($projectRoot . '/lib/filedownloads.php', $name));
    }
    eval($prefix . productionFunction($projectRoot . '/build/http_server_router.php', 'isProtectedRequest'));

    $pdo = new FixtureDatabase('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => getenv('KORA_RC_STRINGIFY_FETCHES') === '1',
    ]);
    $GLOBALS['rcDb'] = $pdo;
    $pdo->sqliteCreateFunction('CONCAT', static fn (mixed ...$values): string => implode('', $values));
    $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-09-05 12:00:00');
    $pdo->exec('CREATE TABLE cms_media (id INTEGER PRIMARY KEY, filename TEXT, original_name TEXT, mime_type TEXT, visibility TEXT, file_size INT)');
    $pdo->exec('CREATE TABLE cms_media_collections (id INT, name TEXT, slug TEXT, description TEXT, sort_order INT, default_visibility TEXT, default_credit TEXT, default_license_label TEXT, default_license_url TEXT, created_at TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE cms_pages (id INT, title TEXT, content TEXT)');
    $pdo->exec('CREATE TABLE cms_appmarket_apps (id INT, name TEXT, icon_media_id INT)');
    $pdo->exec('CREATE TABLE cms_appmarket_screenshots (id INT, app_id INT, media_id INT)');
    $pdo->exec('CREATE TABLE cms_gallery_albums (id INT, status TEXT, is_published INT, deleted_at TEXT)');
    $pdo->exec('CREATE TABLE cms_gallery_photos (id INT, album_id INT, filename TEXT, status TEXT, is_published INT, deleted_at TEXT)');
    $pdo->exec('CREATE TABLE cms_podcast_shows (id INT, status TEXT, is_published INT, deleted_at TEXT)');
    $pdo->exec('CREATE TABLE cms_downloads (id INT, title TEXT, filename TEXT, original_name TEXT, is_published INT, deleted_at TEXT, status TEXT, download_count INT)');

    $payload = str_repeat('plain text ', 500) . "\n<script>alert(document.domain)</script>";
    $htmlUpload = uploaded('payload.html', $payload);
    same(koraUploadMimeType($htmlUpload['tmp_name']), 'text/plain', 'Regression payload is detected as text/plain');
    $stored = mediaStoreUploadedFile($htmlUpload);
    same($stored['ok'], true, 'Safe text content is retained, not broadly rejected');
    same(pathinfo($stored['filename'], PATHINFO_EXTENSION), 'txt', 'MIME-derived safe extension');
    $textMedia = array_merge($stored, ['id' => 20, 'visibility' => 'public']);
    same(mediaFileUrl($textMedia), '/media/file.php?id=20', 'Public TXT uses accessible attachment endpoint');
    foreach (mediaAllowedMimeMap() as $mime => $extension) {
        same(mediaSanitizeExtension('unexpected.html', $mime), $extension, 'Canonical extension for ' . $mime);
    }
    same(mediaSanitizeExtension('captions.vtt', 'text/plain'), 'vtt', 'WebVTT remains supported');
    same(mediaSanitizeExtension('table.csv', 'text/plain'), 'csv', 'CSV remains supported');
    same(mediaUsesProtectedFileEndpoint(['filename' => 'legacy.html', 'mime_type' => 'text/plain', 'visibility' => 'public']), true, 'Historical HTML routes through attachment endpoint');
    same(str_starts_with(storedFileContentDisposition('attachment', 'legacy.html'), 'attachment;'), true, 'Historical filename uses attachment disposition');

    writeFixture(mediaPublicDirectoryPath() . '/legacy.html', $payload);
    foreach ([20 => $stored['filename'], 21 => 'legacy.html'] as $id => $filename) {
        $pdo->prepare('INSERT INTO cms_media VALUES (?,?,?,?,?,?)')->execute([$id, $filename, 'legacy.html', 'text/plain', 'public', strlen($payload)]);
        same(storedFileMimeType(mediaPublicDirectoryPath() . '/' . $filename), 'text/plain', 'Attachment MIME is detected from bytes, not historical extension');
        foreach (['GET', 'HEAD'] as $method) {
            $_SERVER['REQUEST_METHOD'] = $method;
            $_GET = ['id' => $id];
            try {
                require $GLOBALS['rcRoot'] . '/media/file.php';
                throw new RuntimeException('Media endpoint must send a response.');
            } catch (Response $response) {
                same($response->status, 200, 'Actual media attachment endpoint: ' . $filename . '/' . $method);
                same($response->body, $method === 'HEAD' ? '' : $payload, 'Attachment endpoint preserves bytes and HEAD semantics');
            }
        }
    }

    foreach (['public', 'private'] as $visibility) {
        $bytes = imageBytes('webp');
        $result = mediaStoreUploadedFile(uploaded('photo.webp', $bytes), $visibility);
        same($result['ok'], true, 'WebP upload succeeds: ' . $visibility);
        $webpMedia = array_merge($result, ['id' => 30, 'visibility' => $visibility]);
        same(hash_file('sha256', mediaOriginalPath($webpMedia)), hash('sha256', $bytes), 'WebP upload retains original bytes');
        same(mediaRebuildDerivedFiles($webpMedia), true, 'WebP thumbnail rebuild succeeds');
        same(hash_file('sha256', mediaOriginalPath($webpMedia)), hash('sha256', $bytes), 'WebP rebuild preserves original');
        same(mediaSwitchVisibility($webpMedia, $visibility === 'public' ? 'private' : 'public')['ok'], true, 'WebP visibility switch succeeds');
    }

    $jpeg = mediaStoreUploadedFile(uploaded('photo.jpg', imageBytes('jpg')));
    $jpegMedia = array_merge($jpeg, ['id' => 31, 'visibility' => 'public']);
    $before = hashes($jpegMedia);
    $broken = hex2bin('ffd8ffe000104a46494600010100000100010000');
    same(is_string($broken), true, 'Corrupt JPEG fixture decoded');
    $replacement = mediaStoreUploadedFile(uploaded('photo.jpg', (string)$broken), 'public', $jpegMedia);
    same($replacement['ok'], false, 'Corrupt JPEG replacement is rejected');
    same(hashes($jpegMedia), $before, 'Rejected JPEG retains original and derivatives');
    $GLOBALS['rcRejectPersistence'] = true;
    $replacement = mediaStoreUploadedFile(uploaded('photo.jpg', imageBytes('jpg')), 'public', $jpegMedia, static function (): bool {
        if (!empty($GLOBALS['rcRejectPersistence'])) {
            throw new PDOException('Injected DB failure');
        }
        return true;
    });
    same($replacement['ok'], false, 'DB failure rejects replacement');
    same(hashes($jpegMedia), $before, 'DB failure restores original and derivatives');
    same(mediaDeletePhysicalFiles($jpegMedia, null, null, static fn (): bool => false), false, 'Failed delete persistence is rejected');
    same(hashes($jpegMedia), $before, 'Failed deletion restores every original file');

    $png = mediaStoreUploadedFile(uploaded('photo.png', imageBytes('png')));
    $pngMedia = array_merge($png, ['id' => 32, 'visibility' => 'public']);
    $before = hashes($pngMedia);
    $GLOBALS['rcFailUnlink'][mediaWebpPath(mediaOriginalPath($pngMedia))] = 1;
    $persisted = false;
    $switch = mediaSwitchVisibility($pngMedia, 'private', static function () use (&$persisted): bool {
        $persisted = true;
        return true;
    });
    same($switch['ok'], false, 'Failed public derivative cleanup rejects privatization');
    same($persisted, false, 'Failed cleanup never updates DB visibility');
    same(hashes($pngMedia), $before, 'Failed cleanup restores complete public file set');
    same(is_file(mediaOriginalPath($pngMedia, 'private')), false, 'Failed switch removes target copy');
    $switch = mediaSwitchVisibility($pngMedia, 'private', static fn (): bool => false);
    same($switch['ok'], false, 'Failed visibility persistence rolls back');
    same(hashes($pngMedia), $before, 'Failed visibility persistence preserves original set');
    same(mediaSwitchVisibility($pngMedia, 'private')['ok'], true, 'Successful privatization');
    same(hashes($pngMedia), [], 'Successful privatization removes every public original and derivative');

    $source = $GLOBALS['rcRoot'] . '/move/source.pdf';
    $target = $GLOBALS['rcRoot'] . '/move/target.pdf';
    writeFixture($source, 'private bytes');
    $GLOBALS['rcFailRename'] = true;
    $GLOBALS['rcFailUnlink'][$source] = 1;
    same(mediaMoveFile($source, $target), false, 'Copy fallback does not hide source cleanup failure');
    same(file_get_contents($source), 'private bytes', 'Failed copy fallback retains source');
    same(is_file($target), false, 'Failed copy fallback removes duplicate');
    $GLOBALS['rcFailRename'] = false;

    $pdo->exec("INSERT INTO cms_appmarket_apps VALUES (1, 'App', 70)");
    $pdo->exec('INSERT INTO cms_appmarket_screenshots VALUES (1, 1, 71)');
    $pdo->exec("INSERT INTO cms_pages VALUES (1, 'PDF', '[pdf media_id=72][/pdf]'), (2, 'Other PDF', '[pdf media_id=720][/pdf]')");
    foreach ([70, 71, 72] as $id) {
        same(mediaHasUsage(['id' => $id, 'filename' => 'fixture-' . $id . '.pdf']), true, 'Real production scanner finds reference ' . $id);
    }
    foreach (['[pdf media_id="73"][/pdf]', "[pdf MEDIA = '73'][/pdf]", '[pdf src="/media/preview.php?id=73"][/pdf]'] as $content) {
        same(mediaContentReferences(['id' => 73, 'filename' => '73.pdf'], $content), true, 'PDF reference syntax: ' . $content);
    }
    same(mediaContentReferences(['id' => 73, 'filename' => '73.pdf'], '[pdf media_id=730][/pdf]'), false, 'PDF ID is matched exactly');

    $pdf = mediaStoreUploadedFile(uploaded('private.pdf', "%PDF-1.4\nfixture\n%%EOF"), 'private');
    $privateMedia = array_merge($pdf, ['id' => 80, 'visibility' => 'private']);
    $pdo->prepare('INSERT INTO cms_media VALUES (?,?,?,?,?,?)')->execute([80, $pdf['filename'], 'private.pdf', $pdf['mime_type'], 'private', $pdf['file_size']]);
    $adminSource = (string)file_get_contents($projectRoot . '/admin/media.php');
    $start = strpos($adminSource, "if (\$action === 'update_meta') {");
    $end = strpos($adminSource, "if (\$action === 'replace') {", $start ?: 0);
    if ($start === false || $end === false) {
        throw new RuntimeException('Cannot locate actual media metadata controller block.');
    }
    foreach (['license', 'collection'] as $invalid) {
        $_POST = ['media_id' => 80, 'visibility' => 'public', 'collection_id' => $invalid === 'collection' ? 999 : 0, 'license_url' => $invalid === 'license' ? 'javascript:alert(1)' : ''];
        $action = 'update_meta';
        $target = '/admin/media.php';
        try {
            eval($prefix . substr($adminSource, $start, $end - $start));
            throw new RuntimeException('Invalid metadata should have redirected.');
        } catch (Response $response) {
            same($response->status, 302, 'Invalid metadata rejection: ' . $invalid);
        }
        same(is_file(mediaOriginalPath($privateMedia)), true, 'Invalid metadata retains private file');
        same(is_file(mediaOriginalPath($privateMedia, 'public')), false, 'Invalid metadata never creates public copy');
        same($pdo->query('SELECT visibility FROM cms_media WHERE id=80')->fetchColumn(), 'private', 'Invalid metadata retains DB visibility');
    }

    $oldDownload = ['filename' => 'old.pdf', 'original_name' => 'old.pdf', 'file_size' => 3, 'image_file' => 'cover.jpg', 'checksum_sha256' => ''];
    $downloadRoot = $GLOBALS['rcRoot'] . '/uploads/downloads/';
    writeFixture($downloadRoot . 'old.pdf', 'OLD');
    writeFixture($downloadRoot . 'images/cover.jpg', 'JPG');
    writeFixture($downloadRoot . 'images/cover.webp', 'WEBP');
    same(downloadPrepareFileMutation($oldDownload, [], [], true, false, ''), ['ok' => false, 'error' => 'source'], 'Deleting the only source is rejected before side effects');
    same(file_get_contents($downloadRoot . 'old.pdf'), 'OLD', 'Rejected source deletion preserves download');
    $prepared = downloadPrepareFileMutation($oldDownload, uploaded('new.pdf', "%PDF-1.4\nnew\n%%EOF"), [], false, true, '');
    same($prepared['ok'], true, 'Download replacement prepared');
    same(file_get_contents($downloadRoot . 'old.pdf'), 'OLD', 'Preparing replacement does not delete download');
    same(mediaApplyFileChanges($prepared['files'], $prepared['removals'], static fn (): bool => false), false, 'Failed download DB commit rolls back files');
    same(file_get_contents($downloadRoot . 'old.pdf'), 'OLD', 'Failed download commit preserves old bytes');
    same(file_get_contents($downloadRoot . 'images/cover.webp'), 'WEBP', 'Failed download commit preserves old derivative');
    mediaRemoveWorkDirectory($prepared['directory']);
    deleteDownloadImageFile('cover.jpg');
    same(is_file($downloadRoot . 'images/cover.jpg'), false, 'Image deletion removes original');
    same(is_file($downloadRoot . 'images/cover.webp'), false, 'Image deletion removes WebP derivative');

    $pdo->exec("INSERT INTO cms_gallery_albums VALUES (1,'published',1,NULL)");
    $pdo->exec("INSERT INTO cms_gallery_photos VALUES (1,1,'fixture.png','published',1,NULL)");
    writeFixture($GLOBALS['rcRoot'] . '/uploads/gallery/fixture.png', 'PHOTO');
    writeFixture($GLOBALS['rcRoot'] . '/uploads/gallery/thumbs/fixture.png', 'THUMB');
    foreach (['GET', 'HEAD'] as $method) {
        foreach (['full', 'thumb'] as $size) {
            foreach (['public', 'photo_deleted', 'album_deleted'] as $state) {
                $pdo->exec('UPDATE cms_gallery_photos SET deleted_at=NULL');
                $pdo->exec('UPDATE cms_gallery_albums SET deleted_at=NULL');
                if ($state === 'photo_deleted') {
                    $pdo->exec("UPDATE cms_gallery_photos SET deleted_at='2026-09-05'");
                } elseif ($state === 'album_deleted') {
                    $pdo->exec("UPDATE cms_gallery_albums SET deleted_at='2026-09-05'");
                }
                $_SERVER['REQUEST_METHOD'] = $method;
                $_GET = ['id' => 1, 'size' => $size];
                try {
                    require $GLOBALS['rcRoot'] . '/gallery/image.php';
                } catch (Response $response) {
                    same($response->status, $state === 'public' ? 200 : 404, 'Gallery actual endpoint ' . $method . '/' . $size . '/' . $state);
                }
            }
        }
    }
    $pdo->exec("INSERT INTO cms_podcast_shows VALUES (1,'published',1,NULL)");
    $episode = ['show_id' => 1, 'status' => 'published', 'publish_at' => null, 'deleted_at' => null, 'show_status' => 'published', 'show_is_published' => 1];
    same(podcastEpisodeIsPublic($episode), true, 'Published episode and show');
    same(podcastEpisodeIsPublic(array_merge($episode, ['deleted_at' => '2026-09-05'])), false, 'Trashed episode is not public');
    $pdo->exec("UPDATE cms_podcast_shows SET deleted_at='2026-09-05'");
    same(podcastEpisodeIsPublic($episode), false, 'Legacy endpoint shape looks up parent deletion');
    same(podcastEpisodeIsPublic(array_merge($episode, ['show_deleted_at' => '2026-09-05'])), false, 'Joined parent deletion is respected');
    same(podcastShowIsPublic(['status' => 'published', 'is_published' => 1, 'deleted_at' => '2026-09-05']), false, 'Trashed show artwork is not public');

    $pdo->exec("INSERT INTO cms_downloads VALUES (1,'PDF','old.pdf','old.pdf',1,NULL,'published',0)");
    foreach (['published', 'draft', 'deleted'] as $state) {
        $pdo->prepare('UPDATE cms_downloads SET status=?, deleted_at=?')->execute([$state === 'draft' ? 'draft' : 'published', $state === 'deleted' ? '2026-09-05' : null]);
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_GET = ['id' => 1];
        try {
            require $GLOBALS['rcRoot'] . '/downloads/file.php';
        } catch (Response $response) {
            same($response->status, $state === 'published' ? 200 : 404, 'Download endpoint remains usable with record visibility: ' . $state);
        }
    }
    $rootApache = (string)file_get_contents($projectRoot . '/.htaccess');
    $uploadApache = (string)file_get_contents($projectRoot . '/uploads/.htaccess');
    $routes = ['/uploads/forms/private.png' => true, '/uploads/backups/private.png' => true, '/uploads/gallery/thumbs/private.png' => true, '/uploads/places/private.png' => true, '/uploads/podcasts/private.mp3' => true, '/uploads/downloads/old.pdf' => true, '/uploads/downloads/images/cover.png' => false, '/uploads/media/legacy.html' => true, '/uploads/media/legacy.svgz' => true, '/uploads/media/legacy.xhtml' => true, '/uploads/media/file.txt' => true, '/uploads/media/photo.png' => false, '/uploads/media/captions.vtt' => false];
    foreach ($routes as $path => $blocked) {
        same(isProtectedRequest($path), $blocked, 'Production router: ' . $path);
        foreach ([$rootApache, $uploadApache] as $index => $config) {
            preg_match_all('/RewriteRule\s+(\^\S+)\s+-\s+\[F,L,NC\]/', $config, $rules);
            $apacheBlocked = false;
            foreach ($rules[1] as $pattern) {
                $apachePath = $index === 0 ? ltrim($path, '/') : substr($path, strlen('/uploads/'));
                $apacheBlocked = $apacheBlocked || preg_match('~' . $pattern . '~i', $apachePath) === 1;
            }
            same($apacheBlocked, $blocked, 'Apache configuration ' . $index . ': ' . $path);
        }
    }

    foreach (['https://[::ffff:127.0.0.1]/hook', 'https://[::ffff:192.168.1.1]/hook', 'https://127.0.0.1/hook', 'https://unresolved.example/hook', 'https://mixed.example/hook'] as $url) {
        same(normalizeFormWebhookUrl($url), '', 'Webhook unsafe address rejected: ' . $url);
    }
    same(normalizeFormWebhookUrl('https://public.example/hook'), 'https://public.example/hook', 'Public HTTPS webhook preserved');
    $options = formWebhookRequestOptions('public.example', ['Host: public.example', 'Content-Type: application/json'], '{"synthetic":true}');
    same($options['ssl']['peer_name'], 'public.example', 'Pinned connection keeps original certificate identity');
    same($options['ssl']['verify_peer'], true, 'TLS certificate verification enabled');
    same($options['http']['follow_location'], 0, 'Redirect following disabled');
    $process = proc_open([PHP_BINARY, __FILE__, '--redirect-server'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start isolated redirect transport test.');
    }
    fclose($pipes[0]);
    try {
        $address = trim((string)fgets($pipes[1]));
        file_get_contents('http://' . $address . '/start', false, stream_context_create($options));
        same(trim((string)stream_get_contents($pipes[1])), 'REQUESTS=1', 'Native transport does not POST to redirected internal target');
    } finally {
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
    echo 'PASS rc_media_security_selftest: ' . $GLOBALS['rcChecks'] . ' checks' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL rc_media_security_selftest: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
    $exitCode = 1;
} finally {
    removeFixtureTree($GLOBALS['rcRoot']);
}
exit($exitCode);
