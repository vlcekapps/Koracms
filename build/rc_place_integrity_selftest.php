<?php

declare(strict_types=1);

namespace KoraRcPlacesTest;

use PDO;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = sys_get_temp_dir() . '/kora-places-' . bin2hex(random_bytes(12));
$checks = $exitCode = 0;
$files = [];
$link = '';

function same(mixed $actual, mixed $expected, string $label): void
{
    $GLOBALS['checks']++;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function loadFunction(string $path, string $name): void
{
    $source = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (!is_string($source) || preg_match('/^function ' . preg_quote($name, '/') . '\b.*?^\}/ms', $source, $match) !== 1) {
        throw new RuntimeException('Cannot extract production function ' . $name);
    }
    $code = str_replace("dirname(__DIR__) . '/uploads/places'", '$GLOBALS[\'root\']', $match[0]);
    eval('namespace ' . __NAMESPACE__ . '; use \\PDO; use \\PDOException; ' . $code);
}

function presentationLogFileDeleteFailure(string $kind, string $path): void
{
    throw new RuntimeException('Owned image deletion failed: ' . $kind);
}

function koraLog(string $level, string $message, array $context = []): void
{
    throw new RuntimeException('Unexpected Places cleanup error: ' . $message);
}

function writeFixture(string $filename): void
{
    $path = $GLOBALS['root'] . '/' . $filename;
    $stream = fopen($path, 'xb');
    if (!is_resource($stream)) {
        throw new RuntimeException('Cannot create owned Places fixture');
    }
    $GLOBALS['files'][] = $path;
    fwrite($stream, 'owned fixture ' . $filename);
    fclose($stream);
}

try {
    if (!mkdir($root, 0700)) {
        throw new RuntimeException('Cannot create owned Places directory');
    }
    foreach (['placeStoredImageFilename', 'placeStoredImagePath', 'deletePlaceImageFile', 'deleteUnusedPlaceImageFile'] as $name) {
        loadFunction('lib/presentation.php', $name);
    }
    foreach (['adminEditorFormFields', 'adminEditorFormFlashStore', 'adminEditorFormFlashTake'] as $name) {
        loadFunction('admin/layout.php', $name);
    }
    $_SESSION = [];
    adminEditorFormFlashStore('place', 12, ['name' => 'České místo', 'description' => 'Rozepsaný popis',
        'article_status' => 'draft', 'confirm_place_image_delete' => '1', 'place_image_delete' => '1',
        'image_file' => 'injected.png', 'csrf_token' => 'old-token']);
    same(adminEditorFormFlashTake('place', 13), [], 'Flash belongs to one editor');
    $flash = adminEditorFormFlashTake('place', 12);
    same($flash, ['name' => 'České místo', 'description' => 'Rozepsaný popis', 'status' => 'draft', 'is_published' => '0'], 'Draft preserves ordinary fields, not consent/file paths/CSRF');
    same(adminEditorFormFlashTake('place', 12), [], 'Flash consumed once');
    adminEditorFormFlashStore('place', null, ['is_published' => '1']);
    same(adminEditorFormFlashTake('place', null), ['is_published' => '1'], 'Checked publication is preserved');

    foreach (['test.png', 'Historické místo.JPG', 'name.with.dots.webp'] as $filename) {
        same(placeStoredImageFilename($filename), $filename, 'Valid legacy filename');
    }
    foreach (['', '../test.png', '..\\test.png', '/test.png', 'x/test.png', 'x\\test.png', '.htaccess', 'test.php', 'test.svg', 'test.png ', ' test.png', "test\n.png", 'test:one.png'] as $filename) {
        same(placeStoredImageFilename($filename), '', 'Invalid filename rejected');
        same(placeStoredImagePath($filename), '', 'Invalid read path rejected');
    }
    foreach (['original.png', 'original.webp', '.htaccess', 'script.php', 'script.webp'] as $filename) {
        writeFixture($filename);
    }
    same(placeStoredImagePath('original.png'), realpath($root . '/original.png'), 'Owned image resolves');
    deletePlaceImageFile('../original.png');
    deletePlaceImageFile('script.php');
    foreach (['original.png', 'original.webp', '.htaccess', 'script.php', 'script.webp'] as $filename) {
        same(is_file($root . '/' . $filename), true, 'Invalid delete preserves sentinel');
    }

    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STRINGIFY_FETCHES => getenv('KORA_RC_STRINGIFY_FETCHES') === '1']);
    $pdo->exec('CREATE TABLE cms_places (id INTEGER PRIMARY KEY, image_file TEXT, deleted_at TEXT)');
    $pdo->exec("INSERT INTO cms_places VALUES (1, 'original.png', '2026-10-01')");
    deleteUnusedPlaceImageFile($pdo, 'original.png');
    same(is_file($root . '/original.png'), true, 'Trash retains image');
    $pdo->exec("UPDATE cms_places SET image_file = 'original.jpg'");
    deleteUnusedPlaceImageFile($pdo, 'original.png');
    same(is_file($root . '/original.webp'), true, 'Legacy shared stem retains derived WebP');
    $pdo->exec('DELETE FROM cms_places');
    deleteUnusedPlaceImageFile($pdo, 'original.png');
    clearstatcache();
    same(is_file($root . '/original.png'), false, 'Last reference permits original cleanup');
    same(is_file($root . '/original.webp'), false, 'Last reference permits WebP cleanup');
    writeFixture('shared_%=.png');
    writeFixture('shared_%=.webp');
    $pdo->exec("INSERT INTO cms_places VALUES (2, 'shared_%=.jpg', NULL)");
    deleteUnusedPlaceImageFile($pdo, 'shared_%=.png');
    same(is_file($root . '/shared_%=.webp'), true, 'LIKE metacharacters in legacy names preserve shared variant');
    $pdo->exec("UPDATE cms_places SET image_file = 'shared_OTHER.jpg' WHERE id = 2");
    deleteUnusedPlaceImageFile($pdo, 'shared_%=.png');
    clearstatcache();
    same(is_file($root . '/shared_%=.webp'), false, 'Escaped LIKE does not treat a different stem as a reference');
    $pdo->exec('DELETE FROM cms_places');
    writeFixture('missing-original.webp');
    deleteUnusedPlaceImageFile($pdo, 'missing-original.png');
    same(is_file($root . '/missing-original.webp'), false, 'Missing original does not leak WebP');

    // Native filesystem links are mandatory on Linux; Windows requires privileges.
    if (PHP_OS_FAMILY !== 'Windows') {
        $outside = $root . '-outside.png';
        $stream = fopen($outside, 'xb');
        if (!is_resource($stream)) {
            throw new RuntimeException('Cannot create owned outside sentinel');
        }
        $files[] = $outside;
        fwrite($stream, 'owned outside sentinel');
        fclose($stream);
        $link = $root . '/outside.png';
        if (!symlink($outside, $link)) {
            throw new RuntimeException('Cannot create native symlink fixture');
        }
        same(placeStoredImagePath('outside.png'), '', 'Symlink read rejected');
        deletePlaceImageFile('outside.png');
        same(is_link($link), true, 'Symlink delete rejected');
        same(file_get_contents($outside), 'owned outside sentinel', 'Outside sentinel remains');
    }
    echo 'Places file/flash integrity OK (' . $checks . ' checks)' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Places integrity FAILED: ' . $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if ($link !== '' && is_link($link)) {
        unlink($link);
    }
    foreach ($files as $path) {
        clearstatcache(true, $path);
        if (is_file($path)) {
            unlink($path);
        }
    }
    if (is_dir($root)) {
        rmdir($root);
    }
}
exit($exitCode);
