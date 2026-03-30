<?php
// Galerie – breadcrumb, cover, thumbnail – extrahováno z db.php

// ─────────────────────────────── Galerie ──────────────────────────────────

/**
 * Sestaví drobečkový trail od kořene po dané album.
 * Vrací pole [ ['id'=>…, 'name'=>…], … ] od nejstaršího k aktuálnímu.
 */
function gallery_breadcrumb(int $albumId): array
{
    $pdo   = db_connect();
    $trail = [];
    $id    = $albumId;
    $seen  = [];
    while ($id !== null && !in_array($id, $seen, true)) {
        $seen[] = $id;
        $stmt   = $pdo->prepare("SELECT id, name, slug, parent_id FROM cms_gallery_albums WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) break;
        array_unshift($trail, [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'slug' => galleryAlbumSlug((string)($row['slug'] ?? '')),
            'public_path' => galleryAlbumPublicPath($row),
        ]);
        $id = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
    }
    return $trail;
}

/**
 * Vrátí URL náhledové miniatury alba.
 * Priorita: cover_photo_id → první fotka v albu → první podalbum (rekurze max. 4×).
 */
function gallery_cover_url(int $albumId, int $depth = 0): string
{
    if ($depth > 4) return '';
    $pdo  = db_connect();

    $stmt = $pdo->prepare("SELECT cover_photo_id FROM cms_gallery_albums WHERE id = ?");
    $stmt->execute([$albumId]);
    $album = $stmt->fetch();
    if ($album && $album['cover_photo_id']) {
        $s = $pdo->prepare(
            "SELECT id
             FROM cms_gallery_photos
             WHERE id = ?
               AND " . galleryPhotoPublicVisibilitySql()
        );
        $s->execute([$album['cover_photo_id']]);
        $p = $s->fetch();
        if ($p) {
            return galleryPhotoMediaPath($p, 'thumb');
        }
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM cms_gallery_photos
         WHERE album_id = ?
           AND " . galleryPhotoPublicVisibilitySql() . "
         ORDER BY sort_order, id
         LIMIT 1"
    );
    $stmt->execute([$albumId]);
    $photo = $stmt->fetch();
    if ($photo) {
        return galleryPhotoMediaPath($photo, 'thumb');
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM cms_gallery_albums
         WHERE parent_id = ?
           AND " . galleryAlbumPublicVisibilitySql() . "
         ORDER BY name
         LIMIT 1"
    );
    $stmt->execute([$albumId]);
    $sub = $stmt->fetch();
    if ($sub) return gallery_cover_url((int)$sub['id'], $depth + 1);

    return '';
}

/**
 * Vytvoří miniaturu obrázku (max. $maxDim px na delší straně).
 * Vrátí true při úspěchu, false při selhání.
 */
function gallery_make_thumb(string $src, string $dst, int $maxDim = 300): bool
{
    $info = @getimagesize($src);
    if (!$info) return false;
    [$w, $h, $type] = $info;

    $image = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_GIF  => @imagecreatefromgif($src),
        IMAGETYPE_WEBP => @imagecreatefromwebp($src),
        default        => false,
    };
    if (!$image) return false;

    if ($w <= $maxDim && $h <= $maxDim) {
        $newW = $w;
        $newH = $h;
    } elseif ($w >= $h) {
        $newW = $maxDim;
        $newH = (int)round($h * $maxDim / $w);
    } else {
        $newH = $maxDim;
        $newW = (int)round($w * $maxDim / $h);
    }

    $thumb = imagecreatetruecolor($newW, $newH);
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }
    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);

    $ok = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($thumb, $dst, 85),
        IMAGETYPE_PNG  => imagepng($thumb, $dst, 6),
        IMAGETYPE_GIF  => imagegif($thumb, $dst),
        IMAGETYPE_WEBP => imagewebp($thumb, $dst, 85),
        default        => false,
    };
    imagedestroy($image);
    imagedestroy($thumb);
    return (bool)$ok;
}

/**
 * Vygeneruje WebP verzi obrázku vedle originálu.
 * Vrátí cestu k WebP souboru nebo '' při selhání.
 */
function generateWebp(string $srcPath, int $quality = 80): string
{
    if (!function_exists('imagewebp')) {
        return '';
    }
    $info = @getimagesize($srcPath);
    if (!$info) {
        return '';
    }
    [$w, $h, $type] = $info;
    if ($type === IMAGETYPE_WEBP) {
        return $srcPath;
    }

    $image = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($srcPath),
        IMAGETYPE_PNG  => @imagecreatefrompng($srcPath),
        IMAGETYPE_GIF  => @imagecreatefromgif($srcPath),
        default        => false,
    };
    if (!$image) {
        return '';
    }

    $webpPath = preg_replace('/\.[a-z]+$/i', '.webp', $srcPath);
    if ($webpPath === $srcPath) {
        $webpPath .= '.webp';
    }

    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagealphablending($image, true);
        imagesavealpha($image, true);
    }

    $ok = @imagewebp($image, $webpPath, $quality);
    imagedestroy($image);
    return $ok ? $webpPath : '';
}

/**
 * Vrátí URL webp verze obrázku, pokud soubor existuje.
 */
function webpUrl(string $originalUrl): string
{
    $webpUrl = preg_replace('/\.[a-z]+$/i', '.webp', $originalUrl);
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), '/\\');
    $basePath = rtrim(BASE_URL, '/');
    $relativePath = $basePath !== '' ? str_replace($basePath, '', parse_url($webpUrl, PHP_URL_PATH) ?? '') : (parse_url($webpUrl, PHP_URL_PATH) ?? '');
    $webpPath = $docRoot . $relativePath;
    if ($webpUrl !== $originalUrl && is_file($webpPath)) {
        return $webpUrl;
    }
    return '';
}

/**
 * Vrátí <picture> element s WebP source pokud existuje, jinak prostý <img>.
 */
function pictureTag(string $src, string $alt, string $class = '', string $attrs = '', bool $lazy = true): string
{
    $webp = webpUrl($src);
    $classAttr = $class !== '' ? ' class="' . h($class) . '"' : '';
    $lazyAttr = $lazy ? ' loading="lazy"' : '';
    $img = '<img src="' . h($src) . '" alt="' . h($alt) . '"' . $classAttr . $lazyAttr . ($attrs !== '' ? ' ' . $attrs : '') . '>';
    if ($webp !== '') {
        return '<picture><source srcset="' . h($webp) . '" type="image/webp">' . $img . '</picture>';
    }
    return $img;
}

/**
 * Vygeneruje responsive velikosti obrázku při uploadu.
 * Vrátí pole vytvořených cest [šířka => cesta].
 */
function generateResponsiveSizes(string $srcPath, string $destDir, string $baseName): array
{
    $sizes = [400, 800, 1200];
    $generated = [];
    $info = @getimagesize($srcPath);
    if (!$info) {
        return [];
    }
    [$origW, $origH, $type] = $info;

    foreach ($sizes as $maxW) {
        if ($origW <= $maxW) {
            continue;
        }
        $ext = pathinfo($baseName, PATHINFO_EXTENSION);
        $name = pathinfo($baseName, PATHINFO_FILENAME);
        $resizedName = $name . '-' . $maxW . 'w.' . $ext;
        $destPath = $destDir . $resizedName;

        if (is_file($destPath)) {
            $generated[$maxW] = $resizedName;
            continue;
        }

        if (gallery_make_thumb($srcPath, $destPath, $maxW)) {
            generateWebp($destPath);
            $generated[$maxW] = $resizedName;
        }
    }
    return $generated;
}

/**
 * Sestaví cestu ke složce alba z hierarchie parent_id.
 * Např. album "MyDva Kafé" pod "2024" pod "Výlety" → "Výlety/2024/MyDva Kafé"
 */
function buildAlbumPath(PDO $pdo, int $albumId): string
{
    $parts = [];
    $id = $albumId;
    $seen = [];
    while ($id !== null && !in_array($id, $seen, true)) {
        $seen[] = $id;
        $stmt = $pdo->prepare("SELECT id, name, parent_id FROM cms_gallery_albums WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) break;
        $name = str_replace(['/', '\\', "\0"], '_', (string)$row['name']);
        array_unshift($parts, $name);
        $id = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
    }
    return implode('/', $parts);
}

/**
 * Rekurzivně sesbírá všechny fotky z alba a jeho podalb.
 * Vrací pole [['zip_path' => 'Album/Sub/photo.jpg', 'disk_path' => '/abs/path/photo.jpg'], ...]
 * Prázdná alba vrací záznam s 'empty_dir' => true.
 */
function collectAlbumTree(PDO $pdo, int $albumId, string $basePath, string $galleryDir): array
{
    $entries = [];
    $seen = [];

    $collect = static function (int $id, string $path) use ($pdo, $galleryDir, &$entries, &$seen, &$collect): void {
        if (in_array($id, $seen, true)) return;
        $seen[] = $id;

        // Fotky tohoto alba
        $stmt = $pdo->prepare("SELECT filename FROM cms_gallery_photos WHERE album_id = ? ORDER BY sort_order, id");
        $stmt->execute([$id]);
        $photos = $stmt->fetchAll();

        if (empty($photos)) {
            $entries[] = ['zip_path' => $path . '/', 'disk_path' => '', 'empty_dir' => true];
        }

        foreach ($photos as $photo) {
            $filename = (string)$photo['filename'];
            if ($filename === '') continue;
            $diskPath = $galleryDir . $filename;
            $entries[] = [
                'zip_path' => $path . '/' . $filename,
                'disk_path' => $diskPath,
                'empty_dir' => false,
            ];
        }

        // Podřazená alba
        $subStmt = $pdo->prepare("SELECT id, name FROM cms_gallery_albums WHERE parent_id = ? ORDER BY name");
        $subStmt->execute([$id]);
        foreach ($subStmt->fetchAll() as $sub) {
            $subName = str_replace(['/', '\\', "\0"], '_', (string)$sub['name']);
            $collect((int)$sub['id'], $path . '/' . $subName);
        }
    };

    $collect($albumId, $basePath);
    return $entries;
}
