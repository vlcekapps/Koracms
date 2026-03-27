<?php
// Zpracování obsahu – shortcodes, renderContent – extrahováno z db.php

/**
 * Zpracuje obsah přes Parsedown (Markdown + HTML).
 * Markdown syntaxe se převede na HTML, existující HTML projde beze změny.
 */
function parseContentShortcodeAttributes(string $rawAttributes): array
{
    $attributes = [];
    if ($rawAttributes === '') {
        return $attributes;
    }

    preg_match_all(
        '/([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"|([a-zA-Z0-9_-]+)\s*=\s*\'([^\']*)\'|([a-zA-Z0-9_-]+)\s*=\s*([^\s"\']+)/',
        $rawAttributes,
        $matches,
        PREG_SET_ORDER
    );

    foreach ($matches as $match) {
        $key = strtolower((string)($match[1] ?: $match[3] ?: $match[5] ?: ''));
        $value = (string)($match[2] ?: $match[4] ?: $match[6] ?: '');
        if ($key !== '') {
            $attributes[$key] = trim($value);
        }
    }

    return $attributes;
}

function normalizeContentEmbedUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/[\r\n<>"\']/', $value)) {
        return '';
    }

    if (preg_match('#^https?://#i', $value)) {
        $validated = filter_var($value, FILTER_VALIDATE_URL);
        return is_string($validated) ? $validated : '';
    }

    if (str_starts_with($value, '/')) {
        return $value;
    }

    return '';
}

function normalizeContentEmbedMimeType(string $value, string $type): string
{
    $value = strtolower(trim($value));
    if ($value === '' || !preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $value)) {
        return '';
    }

    return str_starts_with($value, strtolower($type) . '/') ? $value : '';
}

function contentEmbedMediaMimeType(string $url, string $type, string $preferredMimeType = ''): string
{
    $normalizedPreferredMimeType = normalizeContentEmbedMimeType($preferredMimeType, $type);
    if ($normalizedPreferredMimeType !== '') {
        return $normalizedPreferredMimeType;
    }

    $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($type === 'audio') {
        return match ($extension) {
            'mp3' => 'audio/mpeg',
            'ogg', 'oga' => 'audio/ogg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            default => '',
        };
    }

    return match ($extension) {
        'mp4', 'm4v' => 'video/mp4',
        'webm' => 'video/webm',
        'ogv' => 'video/ogg',
        'mov' => 'video/quicktime',
        default => '',
    };
}

function renderContentAudioShortcode(string $url, string $preferredMimeType = ''): ?string
{
    $normalizedUrl = normalizeContentEmbedUrl($url);
    $mimeType = contentEmbedMediaMimeType($normalizedUrl, 'audio', $preferredMimeType);

    if ($normalizedUrl === '' || $mimeType === '') {
        return null;
    }

    $escapedUrl = h($normalizedUrl);

    return "\n\n"
        . '<div class="embedded-media embedded-media--audio">'
        . '<audio class="audio-player" controls preload="metadata">'
        . '<source src="' . $escapedUrl . '" type="' . h($mimeType) . '">'
        . 'Váš prohlížeč nepodporuje přehrávání audia. <a href="' . $escapedUrl . '">Otevřít audio soubor</a>.'
        . '</audio>'
        . '</div>'
        . "\n\n";
}

function renderContentVideoShortcode(string $url, string $preferredMimeType = ''): ?string
{
    $normalizedUrl = normalizeContentEmbedUrl($url);
    $mimeType = contentEmbedMediaMimeType($normalizedUrl, 'video', $preferredMimeType);

    if ($normalizedUrl === '' || $mimeType === '') {
        return null;
    }

    $escapedUrl = h($normalizedUrl);

    return "\n\n"
        . '<div class="embedded-media embedded-media--video">'
        . '<video class="video-player" controls preload="metadata">'
        . '<source src="' . $escapedUrl . '" type="' . h($mimeType) . '">'
        . 'Váš prohlížeč nepodporuje přehrávání videa. <a href="' . $escapedUrl . '">Otevřít video soubor</a>.'
        . '</video>'
        . '</div>'
        . "\n\n";
}

function renderContentGalleryShortcode(string $slug): ?string
{
    $normalizedSlug = galleryAlbumSlug($slug);
    if ($normalizedSlug === '') {
        return null;
    }

    try {
        $pdo = db_connect();
        $albumStmt = $pdo->prepare(
            "SELECT id, name, slug, description, COALESCE(updated_at, created_at) AS updated_at
             FROM cms_gallery_albums
             WHERE slug = ?
             LIMIT 1"
        );
        $albumStmt->execute([$normalizedSlug]);
        $album = $albumStmt->fetch();
        if (!$album) {
            return null;
        }

        $album = hydrateGalleryAlbumPresentation($album);

        $photoStmt = $pdo->prepare(
            "SELECT id, album_id, filename, title, slug, sort_order, created_at
             FROM cms_gallery_photos
             WHERE album_id = ?
             ORDER BY sort_order, id
             LIMIT 4"
        );
        $photoStmt->execute([(int)$album['id']]);
        $photos = [];
        foreach ($photoStmt->fetchAll() as $photo) {
            $photos[] = hydrateGalleryPhotoPresentation($photo);
        }
    } catch (\PDOException $e) {
        return null;
    }

    $html = "\n\n"
        . '<section class="content-gallery-embed" aria-label="Fotogalerie: ' . h((string)$album['name']) . '">'
        . '<div class="content-gallery-embed__header">'
        . '<p class="content-gallery-embed__eyebrow">Fotogalerie</p>'
        . '<p class="content-gallery-embed__title"><a href="' . h((string)$album['public_path']) . '">' . h((string)$album['name']) . '</a></p>';

    if (!empty($album['excerpt'])) {
        $html .= '<p class="content-gallery-embed__excerpt">' . h((string)$album['excerpt']) . '</p>';
    }

    $html .= '</div>';

    if ($photos !== []) {
        $html .= '<div class="content-gallery-embed__grid">';
        foreach ($photos as $photo) {
            $html .= '<a class="content-gallery-embed__item" href="' . h((string)$photo['public_path']) . '">'
                . '<img src="' . h((string)$photo['thumb_url']) . '" alt="' . h((string)$photo['label']) . '">'
                . '</a>';
        }
        $html .= '</div>';
    }

    $html .= '<p class="content-gallery-embed__footer"><a href="' . h((string)$album['public_path']) . '">Zobrazit celé album</a></p>'
        . '</section>'
        . "\n\n";

    return $html;
}

function renderContentShortcodes(string $text): string
{
    if (!str_contains($text, '[')) {
        return $text;
    }

    $text = preg_replace_callback(
        '/\[audio(?:\s+([^\]]*))?\](.*?)\[\/audio\]/is',
        static function (array $matches): string {
            $attributes = parseContentShortcodeAttributes(trim((string)($matches[1] ?? '')));
            $source = '';
            foreach (['src', 'url', 'mp3', 'ogg', 'wav', 'm4a', 'aac', 'flac'] as $key) {
                if (!empty($attributes[$key])) {
                    $source = (string)$attributes[$key];
                    break;
                }
            }
            if ($source === '') {
                $source = trim((string)($matches[2] ?? ''));
            }

            $mimeType = normalizeContentEmbedMimeType((string)($attributes['mime'] ?? $attributes['type'] ?? ''), 'audio');

            return renderContentAudioShortcode($source, $mimeType) ?? $matches[0];
        },
        $text
    ) ?? $text;

    $text = preg_replace_callback(
        '/\[video(?:\s+([^\]]*))?\](.*?)\[\/video\]/is',
        static function (array $matches): string {
            $attributes = parseContentShortcodeAttributes(trim((string)($matches[1] ?? '')));
            $source = '';
            foreach (['src', 'url', 'mp4', 'webm', 'ogv', 'm4v', 'mov'] as $key) {
                if (!empty($attributes[$key])) {
                    $source = (string)$attributes[$key];
                    break;
                }
            }
            if ($source === '') {
                $source = trim((string)($matches[2] ?? ''));
            }

            $mimeType = normalizeContentEmbedMimeType((string)($attributes['mime'] ?? $attributes['type'] ?? ''), 'video');

            return renderContentVideoShortcode($source, $mimeType) ?? $matches[0];
        },
        $text
    ) ?? $text;

    $text = preg_replace_callback(
        '/\[gallery(?:\s+([^\]]*))?\](.*?)\[\/gallery\]/is',
        static function (array $matches): string {
            $attributes = parseContentShortcodeAttributes(trim((string)($matches[1] ?? '')));
            $slug = trim((string)($attributes['slug'] ?? $attributes['album'] ?? ($matches[2] ?? '')));

            return renderContentGalleryShortcode($slug) ?? $matches[0];
        },
        $text
    ) ?? $text;

    return $text;
}

function renderContent(string $text): string
{
    static $parsedown = null;
    if ($parsedown === null) {
        require_once __DIR__ . '/Parsedown.php';
        $parsedown = new Parsedown();
        $parsedown->setSafeMode(false);
    }
    return $parsedown->text(renderContentShortcodes($text));
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 0) . ' kB';
    return $bytes . ' B';
}
