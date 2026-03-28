<?php
/**
 * Export fotoalba do ZIP – rekurzivně včetně podalb.
 * Hierarchická struktura složek odpovídá albům v admin galerii.
 */
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');
verifyCsrf();

set_time_limit(0);

$albumId = inputInt('post', 'id');
if ($albumId === null) {
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}

$pdo = db_connect();

// Ověříme, že album existuje
$albumStmt = $pdo->prepare("SELECT id, name, slug FROM cms_gallery_albums WHERE id = ?");
$albumStmt->execute([$albumId]);
$album = $albumStmt->fetch();
if (!$album) {
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}

$galleryDir = dirname(__DIR__) . '/uploads/gallery/';
$albumName = str_replace(['/', '\\', "\0"], '_', (string)$album['name']);

// Sesbíráme všechny soubory rekurzivně
$entries = collectAlbumTree($pdo, $albumId, $albumName, $galleryDir);

// Filtrujeme jen existující soubory + prázdné složky
$filesToZip = [];
$emptyDirs = [];
foreach ($entries as $entry) {
    if (!empty($entry['empty_dir'])) {
        $emptyDirs[] = $entry['zip_path'];
        continue;
    }
    if ($entry['disk_path'] !== '' && is_file($entry['disk_path'])) {
        $filesToZip[] = $entry;
    } else {
        error_log('gallery_export_zip: soubor nenalezen: ' . ($entry['disk_path'] ?? ''));
    }
}

if (empty($filesToZip) && empty($emptyDirs)) {
    $_SESSION['import_log'] = ['⚠ Album neobsahuje žádné fotografie k exportu.'];
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}

$zipFilename = 'galerie-' . slugify($albumName) . '-' . date('Y-m-d') . '.zip';

// ── ZipArchive (preferovaný) ──
if (class_exists('ZipArchive')) {
    $tmpDir = dirname(__DIR__) . '/uploads/tmp';
    if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
    $tmpFile = $tmpDir . '/export_' . bin2hex(random_bytes(8)) . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE) !== true) {
        $_SESSION['import_log'] = ['✗ Nepodařilo se vytvořit ZIP soubor.'];
        header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
        exit;
    }

    foreach ($emptyDirs as $dir) {
        $zip->addEmptyDir($dir);
    }

    foreach ($filesToZip as $entry) {
        $zip->addFile($entry['disk_path'], $entry['zip_path']);
    }

    $zip->close();

    // Odešleme
    $fileSize = filesize($tmpFile);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache');

    readfile($tmpFile);
    @unlink($tmpFile);

    logAction('gallery_export_zip', 'album_id=' . $albumId . ' files=' . count($filesToZip));
    exit;
}

// ── Fallback: streaming ZIP bez ZipArchive ──
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'wb');
$centralDir = '';
$offset = 0;
$entryCount = 0;

$writeEntry = function (string $zipPath, string $diskPath) use ($out, &$centralDir, &$offset, &$entryCount): void {
    $data = file_get_contents($diskPath);
    if ($data === false) return;

    $crc = crc32($data);
    $sizeUncompressed = strlen($data);
    $compressed = gzdeflate($data, 6);
    $sizeCompressed = strlen($compressed);

    $nameBytes = $zipPath;
    $nameLen = strlen($nameBytes);
    $time = filemtime($diskPath) ?: time();
    $dosTime = localtime($time, true);
    $dosTimeInt = ($dosTime['tm_sec'] >> 1) | ($dosTime['tm_min'] << 5) | ($dosTime['tm_hour'] << 11);
    $dosDateInt = $dosTime['tm_mday'] | (($dosTime['tm_mon'] + 1) << 5) | (($dosTime['tm_year'] - 80) << 9);

    // Local file header
    $local = pack('V', 0x04034b50)          // signature
           . pack('v', 20)                   // version needed
           . pack('v', 0x0800)               // flags (UTF-8)
           . pack('v', 8)                    // compression (deflate)
           . pack('v', $dosTimeInt)
           . pack('v', $dosDateInt)
           . pack('V', $crc)
           . pack('V', $sizeCompressed)
           . pack('V', $sizeUncompressed)
           . pack('v', $nameLen)
           . pack('v', 0);                   // extra field length

    fwrite($out, $local . $nameBytes . $compressed);

    // Central directory entry
    $centralDir .= pack('V', 0x02014b50)    // signature
                 . pack('v', 20)              // version made by
                 . pack('v', 20)              // version needed
                 . pack('v', 0x0800)          // flags (UTF-8)
                 . pack('v', 8)               // compression
                 . pack('v', $dosTimeInt)
                 . pack('v', $dosDateInt)
                 . pack('V', $crc)
                 . pack('V', $sizeCompressed)
                 . pack('V', $sizeUncompressed)
                 . pack('v', $nameLen)
                 . pack('v', 0)               // extra length
                 . pack('v', 0)               // comment length
                 . pack('v', 0)               // disk number
                 . pack('v', 0)               // internal attrs
                 . pack('V', 0)               // external attrs
                 . pack('V', $offset)         // local header offset
                 . $nameBytes;

    $offset += 30 + $nameLen + $sizeCompressed;
    $entryCount++;
};

$writeDirEntry = function (string $zipPath) use ($out, &$centralDir, &$offset, &$entryCount): void {
    if (!str_ends_with($zipPath, '/')) $zipPath .= '/';

    $nameBytes = $zipPath;
    $nameLen = strlen($nameBytes);

    $local = pack('V', 0x04034b50)
           . pack('v', 20)
           . pack('v', 0x0800)
           . pack('v', 0)      // no compression
           . pack('v', 0)
           . pack('v', 0)
           . pack('V', 0)      // crc
           . pack('V', 0)      // compressed
           . pack('V', 0)      // uncompressed
           . pack('v', $nameLen)
           . pack('v', 0);

    fwrite($out, $local . $nameBytes);

    $centralDir .= pack('V', 0x02014b50)
                 . pack('v', 20)
                 . pack('v', 20)
                 . pack('v', 0x0800)
                 . pack('v', 0)
                 . pack('v', 0)
                 . pack('v', 0)
                 . pack('V', 0)
                 . pack('V', 0)
                 . pack('V', 0)
                 . pack('v', $nameLen)
                 . pack('v', 0)
                 . pack('v', 0)
                 . pack('v', 0)
                 . pack('v', 0)
                 . pack('V', 0x10)    // external attrs: directory
                 . pack('V', $offset)
                 . $nameBytes;

    $offset += 30 + $nameLen;
    $entryCount++;
};

foreach ($emptyDirs as $dir) {
    $writeDirEntry($dir);
}

foreach ($filesToZip as $entry) {
    $writeEntry($entry['zip_path'], $entry['disk_path']);
}

// End of central directory
$centralDirSize = strlen($centralDir);
fwrite($out, $centralDir);
fwrite($out, pack('V', 0x06054b50)   // signature
             . pack('v', 0)            // disk number
             . pack('v', 0)            // disk with central dir
             . pack('v', $entryCount)
             . pack('v', $entryCount)
             . pack('V', $centralDirSize)
             . pack('V', $offset)
             . pack('v', 0));          // comment length

fclose($out);

logAction('gallery_export_zip', 'album_id=' . $albumId . ' files=' . count($filesToZip) . ' fallback=1');
exit;
