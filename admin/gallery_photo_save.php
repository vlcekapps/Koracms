<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo     = db_connect();
$mode    = $_POST['mode']    ?? '';
$albumId = inputInt('post', 'album_id');

// ── Úprava titulku / pořadí ────────────────────────────────────────────────
if ($mode === 'edit') {
    $id         = inputInt('post', 'id');
    $title      = trim($_POST['title']      ?? '');
    $sortOrder  = max(0, (int)($_POST['sort_order'] ?? 0));

    if ($id !== null) {
        $pdo->prepare(
            "UPDATE cms_gallery_photos SET title = ?, sort_order = ? WHERE id = ?"
        )->execute([$title, $sortOrder, $id]);
    }

    header('Location: ' . BASE_URL . '/admin/gallery_photos.php?album_id=' . $albumId);
    exit;
}

// ── Upload nových fotografií ───────────────────────────────────────────────
if ($mode !== 'upload' || $albumId === null) {
    header('Location: ' . BASE_URL . '/admin/gallery_albums.php');
    exit;
}

$uploadDir = __DIR__ . '/../uploads/gallery/';
$thumbDir  = $uploadDir . 'thumbs/';

$allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxBytes    = 10 * 1024 * 1024; // 10 MB

$files = $_FILES['photos'] ?? [];
$errors = [];

if (!empty($files['name'])) {
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $error    = $files['error'][$i];
        $tmpName  = $files['tmp_name'][$i];
        $origName = $files['name'][$i];
        $size     = $files['size'][$i];

        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = h($origName) . ': chyba při nahrávání (kód ' . $error . ').';
            continue;
        }
        if ($size > $maxBytes) {
            $errors[] = h($origName) . ': soubor je příliš velký (max. 10 MB).';
            continue;
        }

        // Ověření MIME přes finfo (spolehlivější než přípona)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpName);
        if (!in_array($mime, $allowedMime, true)) {
            $errors[] = h($origName) . ': nepodporovaný formát (povoleno JPEG, PNG, GIF, WebP).';
            continue;
        }

        // Bezpečný unikátní název souboru
        $ext      = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        };
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        if (!move_uploaded_file($tmpName, $uploadDir . $filename)) {
            $errors[] = h($origName) . ': nepodařilo se uložit soubor.';
            continue;
        }

        // Miniatura
        gallery_make_thumb($uploadDir . $filename, $thumbDir . $filename, 300);

        // Uložit do DB
        $pdo->prepare(
            "INSERT INTO cms_gallery_photos (album_id, filename, title) VALUES (?, ?, ?)"
        )->execute([$albumId, $filename, '']);
    }
}

// Přesměrování zpět (případné chyby zobrazíme přes session – zatím jen přejdeme)
header('Location: ' . BASE_URL . '/admin/gallery_photos.php?album_id=' . $albumId);
exit;
