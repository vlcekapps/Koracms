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
    $base = BASE_URL . '/uploads/gallery/thumbs/';

    $stmt = $pdo->prepare("SELECT cover_photo_id FROM cms_gallery_albums WHERE id = ?");
    $stmt->execute([$albumId]);
    $album = $stmt->fetch();
    if ($album && $album['cover_photo_id']) {
        $s = $pdo->prepare("SELECT filename FROM cms_gallery_photos WHERE id = ?");
        $s->execute([$album['cover_photo_id']]);
        $p = $s->fetch();
        if ($p) return $base . rawurlencode($p['filename']);
    }

    $stmt = $pdo->prepare(
        "SELECT filename FROM cms_gallery_photos WHERE album_id = ? ORDER BY sort_order, id LIMIT 1"
    );
    $stmt->execute([$albumId]);
    $photo = $stmt->fetch();
    if ($photo) return $base . rawurlencode($photo['filename']);

    $stmt = $pdo->prepare(
        "SELECT id FROM cms_gallery_albums WHERE parent_id = ? ORDER BY name LIMIT 1"
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
