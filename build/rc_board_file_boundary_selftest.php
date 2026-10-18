<?php

declare(strict_types=1);

namespace KoraRcBoardFileTest;

use PDO;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_URL', '');
$root = sys_get_temp_dir() . '/kora-board-boundary-' . bin2hex(random_bytes(12));
$GLOBALS['boardRoot'] = $root . '/board';
$checks = 0;
$exitCode = 0;
$ownedFiles = $ownedDirectories = [];

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
        throw new RuntimeException('Cannot extract production helper ' . $name);
    }
    $code = str_replace("dirname(__DIR__) . '/uploads/board'", '$GLOBALS[\'boardRoot\']', $match[0]);
    foreach (['deleteBoardImageFile', 'deleteBoardStoredFile'] as $callback) {
        $code = str_replace("'" . $callback . "'", "'" . __NAMESPACE__ . '\\' . $callback . "'", $code);
    }
    eval('namespace ' . __NAMESPACE__ . '; use \\PDO; use \\PDOException; ' . $code);
}

function presentationLogFileDeleteFailure(string $kind, string $path): void
{
    throw new RuntimeException('Cannot delete owned fixture ' . $kind);
}

function koraLog(string $level, string $message, array $context = []): void
{
    throw new RuntimeException('Unexpected cleanup error ' . $message);
}

function writeFixture(string $path, string $contents): void
{
    $stream = fopen($path, 'xb');
    if (!is_resource($stream)) {
        throw new RuntimeException('Cannot create owned fixture');
    }
    $GLOBALS['ownedFiles'][] = $path;
    fwrite($stream, $contents);
    fclose($stream);
}

try {
    foreach ([$root, $GLOBALS['boardRoot'], $GLOBALS['boardRoot'] . '/images'] as $directory) {
        if (!mkdir($directory)) {
            throw new RuntimeException('Cannot create private fixture directory');
        }
        $ownedDirectories[] = $directory;
    }
    foreach (['boardAttachmentMimeMap', 'boardStoredFilename', 'boardStoredFilePath', 'boardImageUrl',
        'boardAttachmentChecksum', 'deleteBoardStoredFile', 'deleteBoardImageFile', 'deleteUnusedBoardFiles',
        'slugify', 'boardSlug', 'uniqueBoardSlug'] as $name) {
        loadFunction('lib/presentation.php', $name);
    }
    foreach (['boardTypeDefinitions', 'normalizeBoardType'] as $name) {
        loadFunction('lib/definitions.php', $name);
    }
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => getenv('KORA_RC_STRINGIFY_FETCHES') === '1']);
    $pdo->exec('CREATE TABLE cms_board (id INTEGER PRIMARY KEY, filename TEXT, image_file TEXT, slug TEXT,
        title TEXT, board_type TEXT, excerpt TEXT, description TEXT, category_id INTEGER, posted_date TEXT,
        removal_date TEXT, contact_name TEXT, contact_phone TEXT, contact_email TEXT, original_name TEXT,
        file_size INTEGER, sort_order INTEGER, is_pinned INTEGER, is_published INTEGER, status TEXT,
        publish_at TEXT, unpublish_at TEXT, preview_token TEXT, deleted_at TEXT, created_at TEXT)');

    writeFixture($GLOBALS['boardRoot'] . '/.htaccess', 'PROTECTED-CONFIG');
    writeFixture($GLOBALS['boardRoot'] . '/unsafe.php', 'PROTECTED-SCRIPT');
    writeFixture($GLOBALS['boardRoot'] . '/legacy.pdf', 'OWNED-PDF');
    writeFixture($GLOBALS['boardRoot'] . '/images/legacy.png', 'OWNED-IMAGE');
    writeFixture($GLOBALS['boardRoot'] . '/images/legacy.webp', 'OWNED-WEBP');
    foreach (['', '../legacy.pdf', '..\\legacy.pdf', '/legacy.pdf', 'C:\\legacy.pdf', '.htaccess',
        '.hidden.pdf', 'unsafe.php', 'CON.pdf', 'LPT1.txt', 'file.pdf:stream', "file\0.pdf", "file\n.pdf",
        'file.pdf ', 'file.pdf.', 'file?.pdf', str_repeat('a', 256) . '.pdf'] as $filename) {
        same(boardStoredFilename($filename), '', 'Invalid filename rejected ' . bin2hex($filename));
        same(boardStoredFilePath($filename), '', 'Invalid reference has no stored path');
        same(boardAttachmentChecksum(['filename' => $filename]), '', 'Invalid reference has no checksum');
        deleteBoardStoredFile($filename);
        deleteUnusedBoardFiles($pdo, ['filename' => $filename]);
    }
    foreach (array_unique(array_values(boardAttachmentMimeMap())) as $extension) {
        same(boardStoredFilename('starší dokument.' . $extension), 'starší dokument.' . $extension, 'Legacy attachment extension preserved');
    }
    same(boardStoredFilename('legacy.PDF'), 'legacy.PDF', 'Case-insensitive extension');
    same(boardStoredFilename('legacy.jpeg', true), 'legacy.jpeg', 'Legacy JPEG preserved');
    same(boardStoredFilename('legacy.pdf', true), '', 'An attachment is not an image');
    same(boardStoredFilename('legacy.png'), '', 'An image is not an attachment');
    same(boardStoredFilePath('legacy.pdf'), realpath($GLOBALS['boardRoot'] . '/legacy.pdf'), 'Native real file resolved');
    same(boardStoredFilePath('missing.pdf'), '', 'Missing attachment is unavailable');
    same(boardAttachmentChecksum(['filename' => 'legacy.pdf']), hash('sha256', 'OWNED-PDF'), 'Checksum uses validated native bytes');
    same(boardImageUrl(['image_file' => '../legacy.png']), '', 'Invalid image reference produces no URL');
    same(boardImageUrl(['image_file' => 'starší obrázek.jpeg']), '/uploads/board/images/' . rawurlencode('starší obrázek.jpeg'), 'Image URL preserves valid legacy name');
    deleteBoardImageFile('../legacy.png');
    deleteBoardImageFile('..\\legacy.png');
    same(file_get_contents($GLOBALS['boardRoot'] . '/images/legacy.png'), 'OWNED-IMAGE', 'Invalid image reference cannot delete basename');
    same(file_get_contents($GLOBALS['boardRoot'] . '/images/legacy.webp'), 'OWNED-WEBP', 'Invalid image reference cannot delete variant');
    same(file_get_contents($GLOBALS['boardRoot'] . '/legacy.pdf'), 'OWNED-PDF', 'Malformed paths never alias a valid basename');
    same(file_get_contents($GLOBALS['boardRoot'] . '/.htaccess'), 'PROTECTED-CONFIG', 'Cleanup preserves protection file');
    same(file_get_contents($GLOBALS['boardRoot'] . '/unsafe.php'), 'PROTECTED-SCRIPT', 'Cleanup preserves unsupported file');

    // Native symlink tests are mandatory on Linux CI; Windows often lacks the privilege.
    if (PHP_OS_FAMILY !== 'Windows') {
        writeFixture($root . '/outside.pdf', 'OUTSIDE-SENTINEL');
        $link = $GLOBALS['boardRoot'] . '/linked.pdf';
        same(symlink($root . '/outside.pdf', $link), true, 'Create owned outside symlink');
        $ownedFiles[] = $link;
        same(boardStoredFilePath('linked.pdf'), '', 'Native symlink is not a download');
        same(boardAttachmentChecksum(['filename' => 'linked.pdf']), '', 'Native symlink has no checksum');
        deleteBoardStoredFile('linked.pdf');
        same(is_link($link), true, 'Refused symlink is not removed as a managed attachment');
        same(file_get_contents($root . '/outside.pdf'), 'OUTSIDE-SENTINEL', 'Outside target preserved');
        unlink($link);
        // Resolve a linked image directory without changing any real upload directory.
        $linkedRoot = $root . '/linked-board';
        same(mkdir($linkedRoot), true, 'Create secondary owned root');
        $ownedDirectories[] = $linkedRoot;
        same(symlink($root, $linkedRoot . '/images'), true, 'Create outside image directory');
        $ownedFiles[] = $linkedRoot . '/images';
        same(boardStoredFilePath('outside.pdf', true, $linkedRoot), '', 'Image extension policy is retained');
        writeFixture($root . '/outside.png', 'OUTSIDE-IMAGE');
        same(boardStoredFilePath('outside.png', true, $linkedRoot), '', 'Outside image directory is rejected');
    }

    // Execute the actual Board JSON-import block, not a second implementation.
    $source = file_get_contents(dirname(__DIR__) . '/admin/import.php');
    $start = strpos($source, '                // Úřední deska');
    $end = strpos($source, '                // Evidence zveřejnění vývěsky', $start);
    if ($start === false || $end === false) {
        throw new RuntimeException('Cannot find production import block');
    }
    $importCode = str_replace('INSERT IGNORE', 'INSERT OR IGNORE', substr($source, $start, $end - $start));
    $base = ['title' => 'Importovaný dokument', 'category_id' => null, 'posted_date' => '2026-01-01', 'removal_date' => null];
    $data = ['board' => [
        ['id' => 1, 'slug' => 'unsafe', 'filename' => '.htaccess', 'image_file' => '../legacy.png'] + $base,
        ['id' => 2, 'slug' => 'legacy', 'filename' => 'legacy.pdf', 'image_file' => 'legacy.png'] + $base,
    ]];
    $summary = [];
    eval('namespace ' . __NAMESPACE__ . '; ' . $importCode);
    $imported = $pdo->query('SELECT filename,image_file FROM cms_board ORDER BY id')->fetchAll();
    same($imported, [['filename' => '', 'image_file' => ''], ['filename' => 'legacy.pdf', 'image_file' => 'legacy.png']], 'Actual import drops malformed references and preserves valid ones');
    same(str_contains(implode(' ', $summary), 'byly vynechány: 2.'), true, 'Import reports dropped references without reflecting their values');
    deleteUnusedBoardFiles($pdo, ['image_file' => 'legacy.png']);
    same(is_file($GLOBALS['boardRoot'] . '/images/legacy.png'), true, 'Referenced legacy image preserved');
    $pdo->exec('DELETE FROM cms_board');
    deleteUnusedBoardFiles($pdo, ['image_file' => 'legacy.png', 'filename' => 'legacy.pdf']);
    clearstatcache();
    same(is_file($GLOBALS['boardRoot'] . '/legacy.pdf'), false, 'Unreferenced validated attachment removed');
    same(is_file($GLOBALS['boardRoot'] . '/images/legacy.png'), false, 'Unreferenced validated image removed');
    same(is_file($GLOBALS['boardRoot'] . '/images/legacy.webp'), false, 'Validated image variant removed');
    echo 'Board file boundary selftest: OK (' . $checks . ' checks; native symlinks: ' . (PHP_OS_FAMILY === 'Windows' ? 'Linux CI' : 'tested') . ')' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Board file boundary selftest: FAIL: ' . $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    foreach (array_reverse($ownedFiles) as $path) {
        clearstatcache(true, $path);
        if (is_link($path) || is_file($path)) {
            unlink($path);
        }
    }
    foreach (array_reverse($ownedDirectories) as $directory) {
        rmdir($directory);
    }
}

exit($exitCode);
