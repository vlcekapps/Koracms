<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

requireLogin(BASE_URL . '/admin/login.php');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function contentReferenceAllowedTypes(): array
{
    return [
        'all',
        'blog',
        'page',
        'news',
        'event',
        'faq',
        'gallery',
        'podcast',
        'download',
        'media',
        'forms',
        'place',
        'board',
        'poll',
    ];
}

function contentReferenceTypeLabel(string $type): string
{
    return match ($type) {
        'blog' => 'Článek blogu',
        'page' => 'Statická stránka',
        'news' => 'Novinka',
        'event' => 'Událost',
        'faq' => 'FAQ',
        'gallery_album' => 'Fotogalerie',
        'gallery_photo' => 'Fotografie',
        'podcast_show' => 'Podcast',
        'podcast_episode' => 'Epizoda podcastu',
        'download' => 'Položka ke stažení',
        'form' => 'Formulář',
        'media_image' => 'Obrázek z knihovny médií',
        'media_audio' => 'Audio z knihovny médií',
        'media_video' => 'Video z knihovny médií',
        'media_file' => 'Soubor z knihovny médií',
        'place' => 'Zajímavé místo',
        'board' => boardModulePublicLabel(),
        'poll' => 'Anketa',
        default => 'Obsah webu',
    };
}

function contentReferenceTitle(array $row): string
{
    $type = (string)($row['type'] ?? '');

    return match ($type) {
        'blog',
        'page',
        'news',
        'event',
        'podcast_show',
        'podcast_episode',
        'download',
        'form',
        'place',
        'board',
        'faq',
        'poll',
        'gallery_album' => trim((string)($row['title'] ?? '')),
        'gallery_photo' => galleryPhotoLabel($row),
        default => trim((string)($row['title'] ?? '')),
    };
}

function contentReferenceExcerpt(array $row, int $limit = 180): string
{
    $type = (string)($row['type'] ?? '');

    return match ($type) {
        'blog' => trim((string)($row['perex'] ?? '')) !== ''
            ? mb_strimwidth(normalizePlainText((string)$row['perex']), 0, $limit, '…', 'UTF-8')
            : mb_strimwidth(normalizePlainText((string)($row['content'] ?? '')), 0, $limit, '…', 'UTF-8'),
        'page' => mb_strimwidth(normalizePlainText((string)($row['content'] ?? '')), 0, $limit, '…', 'UTF-8'),
        'news' => newsExcerpt((string)($row['content'] ?? ''), $limit),
        'event' => mb_strimwidth(normalizePlainText((string)($row['description'] ?? '')), 0, $limit, '…', 'UTF-8'),
        'faq' => faqExcerpt($row, $limit),
        'gallery_album' => galleryAlbumExcerpt($row, $limit),
        'gallery_photo' => trim((string)($row['album_title'] ?? '')) !== ''
            ? 'Album: ' . trim((string)$row['album_title'])
            : '',
        'podcast_show' => mb_strimwidth(normalizePlainText((string)($row['description'] ?? '')), 0, $limit, '…', 'UTF-8'),
        'podcast_episode' => podcastEpisodeExcerpt($row, $limit),
        'download' => downloadExcerpt($row, $limit),
        'form' => mb_strimwidth(normalizePlainText((string)($row['description'] ?? '')), 0, $limit, '…', 'UTF-8'),
        'media_image', 'media_audio', 'media_video', 'media_file' => mediaReferenceExcerpt($row, $limit),
        'place' => placeExcerpt($row, $limit),
        'board' => boardExcerpt($row, $limit),
        'poll' => pollExcerpt($row, $limit),
        default => '',
    };
}

function mediaReferenceKind(array $row): string
{
    $mimeType = strtolower(trim((string)($row['mime_type'] ?? '')));
    if (str_starts_with($mimeType, 'image/')) {
        return 'media_image';
    }
    if (str_starts_with($mimeType, 'audio/')) {
        return 'media_audio';
    }
    if (str_starts_with($mimeType, 'video/')) {
        return 'media_video';
    }

    return 'media_file';
}

function mediaReferencePublicPath(array $row): string
{
    $folder = trim((string)($row['folder'] ?? 'media'));
    $filename = trim((string)($row['filename'] ?? ''));
    if ($filename === '') {
        return BASE_URL . '/';
    }

    return BASE_URL . '/uploads/' . rawurlencode($folder) . '/' . rawurlencode($filename);
}

function mediaReferenceExcerpt(array $row, int $limit = 180): string
{
    $parts = [];
    $altText = trim((string)($row['alt_text'] ?? ''));
    $mimeType = trim((string)($row['mime_type'] ?? ''));
    $fileSize = (int)($row['file_size'] ?? 0);

    if ($altText !== '') {
        $parts[] = 'Popis: ' . $altText;
    }
    if ($mimeType !== '') {
        $parts[] = strtoupper($mimeType);
    }
    if ($fileSize > 0) {
        $parts[] = number_format($fileSize / 1024, 0, ',', ' ') . ' KB';
    }

    return mb_strimwidth(implode(' · ', $parts), 0, $limit, '…', 'UTF-8');
}

function contentReferencePublicPath(array $row): string
{
    return match ((string)($row['type'] ?? '')) {
        'blog' => articlePublicPath($row),
        'page' => pagePublicPath($row),
        'news' => newsPublicPath($row),
        'event' => eventPublicPath($row),
        'faq' => faqPublicPath($row),
        'gallery_album' => galleryAlbumPublicPath($row),
        'gallery_photo' => galleryPhotoPublicPath($row),
        'podcast_show' => podcastShowPublicPath($row),
        'podcast_episode' => podcastEpisodePublicPath($row),
        'download' => downloadPublicPath($row),
        'form' => formPublicPath($row),
        'media_image', 'media_audio', 'media_video', 'media_file' => mediaReferencePublicPath($row),
        'place' => placePublicPath($row),
        'board' => boardPublicPath($row),
        'poll' => pollPublicPath($row),
        default => BASE_URL . '/',
    };
}

function contentReferenceTimestamp(array $row): int
{
    $value = trim((string)($row['created_at'] ?? ''));
    if ($value === '') {
        return 0;
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? $timestamp : 0;
}

function contentReferenceResult(array $row): array
{
    $title = contentReferenceTitle($row);
    if ($title === '') {
        $title = contentReferenceTypeLabel((string)($row['type'] ?? ''));
    }

    $path = contentReferencePublicPath($row);

    return [
        'type' => (string)($row['type'] ?? ''),
        'kind_label' => contentReferenceTypeLabel((string)($row['type'] ?? '')),
        'title' => $title,
        'url' => $path,
        'path' => $path,
        'excerpt' => contentReferenceExcerpt($row),
        'media_alt' => trim((string)($row['alt_text'] ?? '')),
        'thumbnail_url' => contentReferenceThumbnailUrl($row),
        'insert_actions' => contentReferenceInsertActions($row),
    ];
}

function contentReferenceShortcodeAttribute(string $name, string $value): string
{
    return $name . '="' . addcslashes(trim($value), "\\\"") . '"';
}

function contentReferenceAudioShortcode(string $url, string $mimeType = ''): string
{
    $attributes = [contentReferenceShortcodeAttribute('src', $url)];
    if ($mimeType !== '') {
        $attributes[] = contentReferenceShortcodeAttribute('mime', $mimeType);
    }

    return '[audio ' . implode(' ', $attributes) . '][/audio]';
}

function contentReferenceVideoShortcode(string $url, string $mimeType = ''): string
{
    $attributes = [contentReferenceShortcodeAttribute('src', $url)];
    if ($mimeType !== '') {
        $attributes[] = contentReferenceShortcodeAttribute('mime', $mimeType);
    }

    return '[video ' . implode(' ', $attributes) . '][/video]';
}

function contentReferenceGalleryShortcode(string $slug): string
{
    return '[gallery]' . $slug . '[/gallery]';
}

function contentReferenceSimpleEntityShortcode(string $tag, string $slug): string
{
    return '[' . $tag . ']' . $slug . '[/' . $tag . ']';
}

function contentReferencePodcastEpisodeShortcode(string $showSlug, string $episodeSlug): string
{
    return '[podcast_episode]' . $showSlug . '/' . $episodeSlug . '[/podcast_episode]';
}

function contentReferenceBuildAction(
    string $kind,
    string $label,
    string $status,
    bool $block = false,
    string $snippet = '',
    array $extra = []
): array {
    $action = [
        'kind' => $kind,
        'label' => $label,
        'status' => $status,
        'block' => $block,
    ];

    if ($snippet !== '') {
        $action['snippet'] = $snippet;
    }

    return array_merge($action, $extra);
}

function contentReferenceThumbnailUrl(array $row): string
{
    $type = (string)($row['type'] ?? '');

    if ($type === 'gallery_album') {
        return (string)(hydrateGalleryAlbumPresentation($row)['cover_url'] ?? '');
    }
    if ($type === 'gallery_photo') {
        return (string)(hydrateGalleryPhotoPresentation($row)['thumb_url'] ?? '');
    }
    if ($type === 'podcast_show') {
        return podcastCoverUrl($row);
    }
    if ($type === 'download') {
        return downloadImageUrl($row);
    }
    if ($type === 'place') {
        return placeImageUrl($row);
    }
    if ($type === 'board') {
        return boardImageUrl($row);
    }
    if (str_starts_with($type, 'media_') && mediaReferenceKind($row) === 'media_image') {
        return mediaReferencePublicPath($row);
    }

    return '';
}

function contentReferenceDownloadMediaAction(array $row): ?array
{
    $externalUrl = normalizeDownloadExternalUrl((string)($row['external_url'] ?? ''));
    if ($externalUrl !== '') {
        $audioMime = contentEmbedMediaMimeType($externalUrl, 'audio');
        if ($audioMime !== '') {
            return contentReferenceBuildAction(
                'audio_shortcode',
                'Vložit audio přehrávač',
                'Do textu byl vložen audio přehrávač.',
                true,
                contentReferenceAudioShortcode($externalUrl, $audioMime)
            );
        }

        $videoMime = contentEmbedMediaMimeType($externalUrl, 'video');
        if ($videoMime !== '') {
            return contentReferenceBuildAction(
                'video_shortcode',
                'Vložit video přehrávač',
                'Do textu byl vložen video přehrávač.',
                true,
                contentReferenceVideoShortcode($externalUrl, $videoMime)
            );
        }
    }

    $sourceName = trim((string)($row['original_name'] ?? ''));
    if ($sourceName === '') {
        $sourceName = trim((string)($row['filename'] ?? ''));
    }
    if ($sourceName === '') {
        return null;
    }

    $mediaProbePath = '/downloads/' . rawurlencode($sourceName);
    $audioMime = contentEmbedMediaMimeType($mediaProbePath, 'audio');
    $videoMime = contentEmbedMediaMimeType($mediaProbePath, 'video');
    $downloadUrl = BASE_URL . '/downloads/file.php?id=' . (int)($row['id'] ?? 0);

    if ($audioMime !== '') {
        return contentReferenceBuildAction(
            'audio_shortcode',
            'Vložit audio přehrávač',
            'Do textu byl vložen audio přehrávač.',
            true,
            contentReferenceAudioShortcode($downloadUrl, $audioMime)
        );
    }

    if ($videoMime !== '') {
        return contentReferenceBuildAction(
            'video_shortcode',
            'Vložit video přehrávač',
            'Do textu byl vložen video přehrávač.',
            true,
            contentReferenceVideoShortcode($downloadUrl, $videoMime)
        );
    }

    return null;
}

function contentReferenceDownloadDirectLinkAction(array $row): ?array
{
    $linkUrl = '';
    if (normalizeDownloadExternalUrl((string)($row['external_url'] ?? '')) !== '') {
        $linkUrl = normalizeDownloadExternalUrl((string)($row['external_url'] ?? ''));
    } elseif ((int)($row['id'] ?? 0) > 0 && (trim((string)($row['filename'] ?? '')) !== '' || trim((string)($row['original_name'] ?? '')) !== '')) {
        $linkUrl = BASE_URL . '/downloads/file.php?id=' . (int)$row['id'];
    }

    if ($linkUrl === '') {
        return null;
    }

    $linkTitle = contentReferenceTitle($row);
    $snippet = '<a href="' . htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($linkTitle, ENT_QUOTES, 'UTF-8') . '</a>';

    return contentReferenceBuildAction(
        'snippet',
        'Vložit odkaz ke stažení',
        'Do textu byl vložen odkaz ke stažení.',
        false,
        $snippet
    );
}

function contentReferencePodcastEpisodeMediaAction(array $row): ?array
{
    $audioSrc = podcastEpisodeAudioUrl($row);
    if ($audioSrc === '') {
        return null;
    }

    $mimeType = contentEmbedMediaMimeType($audioSrc, 'audio');
    if ($mimeType === '') {
        return null;
    }

    return contentReferenceBuildAction(
        'audio_shortcode',
        'Vložit audio přehrávač',
        'Do textu byl vložen audio přehrávač.',
        true,
        contentReferenceAudioShortcode($audioSrc, $mimeType)
    );
}

function contentReferenceMediaLibraryAction(array $row): ?array
{
    $type = mediaReferenceKind($row);
    $url = mediaReferencePublicPath($row);
    $mimeType = trim((string)($row['mime_type'] ?? ''));

    if ($url === '') {
        return null;
    }

    if ($type === 'media_image') {
        return contentReferenceBuildAction(
            'image_html',
            'Vložit obrázek',
            'Do textu byl vložen obrázek.',
            true
        );
    }

    if ($type === 'media_audio') {
        $normalizedMimeType = contentEmbedMediaMimeType($url, 'audio', $mimeType);
        if ($normalizedMimeType === '') {
            return null;
        }

        return contentReferenceBuildAction(
            'audio_shortcode',
            'Vložit audio přehrávač',
            'Do textu byl vložen audio přehrávač.',
            true,
            contentReferenceAudioShortcode($url, $normalizedMimeType)
        );
    }

    if ($type === 'media_video') {
        $normalizedMimeType = contentEmbedMediaMimeType($url, 'video', $mimeType);
        if ($normalizedMimeType === '') {
            return null;
        }

        return contentReferenceBuildAction(
            'video_shortcode',
            'Vložit video přehrávač',
            'Do textu byl vložen video přehrávač.',
            true,
            contentReferenceVideoShortcode($url, $normalizedMimeType)
        );
    }

    return null;
}

function contentReferenceGalleryPhotoImageAction(array $row): ?array
{
    $photo = hydrateGalleryPhotoPresentation($row);
    $imageUrl = trim((string)($photo['image_url'] ?? ''));
    if ($imageUrl === '') {
        return null;
    }

    return contentReferenceBuildAction(
        'image_html',
        'Vložit fotografii',
        'Do textu byla vložena fotografie.',
        true,
        '',
        ['url' => $imageUrl]
    );
}

function contentReferenceInsertActions(array $row): array
{
    $actions = [
        contentReferenceBuildAction('link', 'Vložit jako odkaz', 'Do textu byl vložen odkaz.'),
        contentReferenceBuildAction('html_block', 'Vložit jako HTML blok', 'Do textu byl vložen HTML blok.', true),
    ];

    $type = (string)($row['type'] ?? '');
    if ($type === 'form') {
        $slug = formSlug((string)($row['slug'] ?? ''));
        if ($slug !== '') {
            $actions[] = contentReferenceBuildAction(
                'shortcode',
                'Vložit formulář',
                'Do textu byl vložen formulář.',
                true,
                contentReferenceSimpleEntityShortcode('form', $slug)
            );
        }
    } elseif ($type === 'poll') {
        $slug = pollSlug((string)($row['slug'] ?? ''));
        if ($slug !== '') {
            $actions[] = contentReferenceBuildAction(
                'shortcode',
                'Vložit anketu',
                'Do textu byla vložena anketa.',
                true,
                contentReferenceSimpleEntityShortcode('poll', $slug)
            );
        }
    } elseif ($type === 'gallery_album') {
        $slug = galleryAlbumSlug((string)($row['slug'] ?? ''));
        if ($slug !== '') {
            $actions[] = contentReferenceBuildAction(
                'gallery_shortcode',
                'Vložit fotogalerii',
                'Do textu byla vložena fotogalerie.',
                true,
                contentReferenceGalleryShortcode($slug)
            );
        }
    } elseif ($type === 'podcast_episode') {
        $showSlug = podcastShowSlug((string)($row['show_slug'] ?? ''));
        $episodeSlug = podcastEpisodeSlug((string)($row['slug'] ?? ''));
        if ($showSlug !== '' && $episodeSlug !== '') {
            $actions[] = contentReferenceBuildAction(
                'shortcode',
                'Vložit epizodu podcastu',
                'Do textu byla vložena epizoda podcastu.',
                true,
                contentReferencePodcastEpisodeShortcode($showSlug, $episodeSlug)
            );
        }
        $mediaAction = contentReferencePodcastEpisodeMediaAction($row);
        if ($mediaAction !== null) {
            $actions[] = $mediaAction;
        }
    } elseif ($type === 'podcast_show') {
        $slug = podcastShowSlug((string)($row['slug'] ?? ''));
        if ($slug !== '') {
            $actions[] = contentReferenceBuildAction(
                'shortcode',
                'Vložit podcast',
                'Do textu byl vložen podcast.',
                true,
                contentReferenceSimpleEntityShortcode('podcast', $slug)
            );
        }
    } elseif ($type === 'download') {
        $slug = downloadSlug((string)($row['slug'] ?? ''));
        if ($slug !== '') {
            $actions[] = contentReferenceBuildAction(
                'shortcode',
                'Vložit blok ke stažení',
                'Do textu byl vložen blok ke stažení.',
                true,
                contentReferenceSimpleEntityShortcode('download', $slug)
            );
        }
        $mediaAction = contentReferenceDownloadMediaAction($row);
        if ($mediaAction !== null) {
            $actions[] = $mediaAction;
        }
        $downloadLinkAction = contentReferenceDownloadDirectLinkAction($row);
        if ($downloadLinkAction !== null) {
            $actions[] = $downloadLinkAction;
        }
    } elseif ($type === 'place') {
        $slug = placeSlug((string)($row['slug'] ?? ''));
        if ($slug !== '') {
            $actions[] = contentReferenceBuildAction(
                'shortcode',
                'Vložit místo',
                'Do textu bylo vloženo místo.',
                true,
                contentReferenceSimpleEntityShortcode('place', $slug)
            );
        }
    } elseif ($type === 'event') {
        $slug = eventSlug((string)($row['slug'] ?? ''));
        if ($slug !== '') {
            $actions[] = contentReferenceBuildAction(
                'shortcode',
                'Vložit událost',
                'Do textu byla vložena událost.',
                true,
                contentReferenceSimpleEntityShortcode('event', $slug)
            );
        }
    } elseif ($type === 'board') {
        $slug = boardSlug((string)($row['slug'] ?? ''));
        if ($slug !== '') {
            $actions[] = contentReferenceBuildAction(
                'shortcode',
                'Vložit oznámení',
                'Do textu bylo vloženo oznámení.',
                true,
                contentReferenceSimpleEntityShortcode('board', $slug)
            );
        }
    } elseif ($type === 'gallery_photo') {
        $galleryPhotoAction = contentReferenceGalleryPhotoImageAction($row);
        if ($galleryPhotoAction !== null) {
            $actions[] = $galleryPhotoAction;
        }
    } elseif (str_starts_with($type, 'media_')) {
        $mediaAction = contentReferenceMediaLibraryAction($row);
        if ($mediaAction !== null) {
            $actions[] = $mediaAction;
        }
    }

    return $actions;
}
$query = trim((string)($_GET['q'] ?? ''));
$requestedType = strtolower(trim((string)($_GET['type'] ?? 'all')));

if (!in_array($requestedType, contentReferenceAllowedTypes(), true)) {
    $requestedType = 'all';
}

if (mb_strlen($query) < 2) {
    echo json_encode([
        'ok' => true,
        'count' => 0,
        'message' => 'Zadejte alespoň 2 znaky.',
        'results' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = db_connect();
$like = '%' . $query . '%';
$results = [];

if (($requestedType === 'all' || $requestedType === 'blog') && isModuleEnabled('blog')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT a.id, a.title, a.slug, a.perex, a.content, a.created_at, 'blog' AS type, b.slug AS blog_slug
             FROM cms_articles a
             LEFT JOIN cms_blogs b ON b.id = a.blog_id
             WHERE a.status = 'published'
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
               AND (a.title LIKE ? OR a.perex LIKE ? OR a.content LIKE ?)
             ORDER BY a.created_at DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

if ($requestedType === 'all' || $requestedType === 'page') {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, content, created_at, 'page' AS type
             FROM cms_pages
             WHERE is_published = 1
               AND (title LIKE ? OR content LIKE ? OR slug LIKE ?)
             ORDER BY title
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

if (($requestedType === 'all' || $requestedType === 'news') && isModuleEnabled('news')) {
    try {
        $stmt = $pdo->prepare(
             "SELECT id, title, slug, content, created_at, 'news' AS type
              FROM cms_news
              WHERE " . newsPublicVisibilitySql() . "
                AND (title LIKE ? OR content LIKE ? OR slug LIKE ?)
              ORDER BY created_at DESC
              LIMIT 10"
        );
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

if (($requestedType === 'all' || $requestedType === 'event') && isModuleEnabled('events')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, description, event_date AS created_at, 'event' AS type
             FROM cms_events
             WHERE " . eventPublicVisibilitySql() . "
               AND (title LIKE ? OR description LIKE ? OR location LIKE ? OR slug LIKE ?)
             ORDER BY event_date DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

if (($requestedType === 'all' || $requestedType === 'faq') && isModuleEnabled('faq')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, question AS title, slug, excerpt, answer, COALESCE(updated_at, created_at) AS created_at, 'faq' AS type
             FROM cms_faqs
             WHERE COALESCE(status, 'published') = 'published'
               AND is_published = 1
               AND (question LIKE ? OR excerpt LIKE ? OR answer LIKE ? OR slug LIKE ?)
             ORDER BY COALESCE(updated_at, created_at) DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

if (($requestedType === 'all' || $requestedType === 'gallery') && isModuleEnabled('gallery')) {
    try {
        $albumStmt = $pdo->prepare(
            "SELECT id, name AS title, slug, excerpt, COALESCE(updated_at, created_at) AS created_at, 'gallery_album' AS type
             FROM cms_gallery_albums
             WHERE status = 'published'
               AND is_published = 1
               AND (name LIKE ? OR excerpt LIKE ? OR slug LIKE ?)
             ORDER BY COALESCE(updated_at, created_at) DESC
             LIMIT 6"
        );
        $albumStmt->execute([$like, $like, $like]);
        foreach ($albumStmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }

        $photoStmt = $pdo->prepare(
            "SELECT p.id, p.slug, p.caption, p.alt_text, p.filename, p.created_at, a.name AS album_title,
                    COALESCE(NULLIF(p.caption, ''), NULLIF(p.alt_text, ''), a.name, CONCAT('Fotografie #', p.id)) AS title,
                    'gallery_photo' AS type
             FROM cms_gallery_photos p
             INNER JOIN cms_gallery_albums a ON a.id = p.album_id
             WHERE a.status = 'published'
               AND a.is_published = 1
               AND (p.caption LIKE ? OR p.alt_text LIKE ? OR p.slug LIKE ? OR a.name LIKE ?)
             ORDER BY p.created_at DESC
             LIMIT 6"
        );
        $photoStmt->execute([$like, $like, $like, $like]);
        foreach ($photoStmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

if (($requestedType === 'all' || $requestedType === 'podcast') && isModuleEnabled('podcast')) {
    try {
        $showStmt = $pdo->prepare(
            "SELECT id, title, slug, description, cover_image, created_at, 'podcast_show' AS type
             FROM cms_podcast_shows
             WHERE " . podcastShowPublicVisibilitySql() . "
               AND (title LIKE ? OR description LIKE ? OR slug LIKE ?)
             ORDER BY created_at DESC
             LIMIT 6"
        );
        $showStmt->execute([$like, $like, $like]);
        foreach ($showStmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }

        $episodeStmt = $pdo->prepare(
            "SELECT e.id, e.title, e.slug, e.description, e.audio_file, e.audio_url, e.created_at,
                    s.slug AS show_slug, s.title AS show_title, s.cover_image AS show_cover_image,
                    s.status AS show_status, s.is_published AS show_is_published,
                    'podcast_episode' AS type
             FROM cms_podcasts e
             INNER JOIN cms_podcast_shows s ON s.id = e.show_id
             WHERE " . podcastEpisodePublicVisibilitySql('e', 's') . "
               AND (e.title LIKE ? OR e.description LIKE ? OR e.slug LIKE ? OR s.title LIKE ?)
             ORDER BY e.created_at DESC
             LIMIT 6"
        );
        $episodeStmt->execute([$like, $like, $like, $like]);
        foreach ($episodeStmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

if (($requestedType === 'all' || $requestedType === 'download') && isModuleEnabled('downloads')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, excerpt, description, external_url, filename, original_name, image_file, created_at, 'download' AS type
             FROM cms_downloads
             WHERE status = 'published'
               AND is_published = 1
               AND (title LIKE ? OR excerpt LIKE ? OR description LIKE ? OR slug LIKE ?)
             ORDER BY created_at DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

if (($requestedType === 'all' || $requestedType === 'forms') && isModuleEnabled('forms')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, description, updated_at AS created_at, 'form' AS type
             FROM cms_forms
             WHERE is_active = 1
               AND (title LIKE ? OR description LIKE ? OR slug LIKE ?)
             ORDER BY updated_at DESC, id DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

if ($requestedType === 'all' || $requestedType === 'media') {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, filename, original_name, alt_text, mime_type, file_size, folder, created_at
             FROM cms_media
             WHERE original_name LIKE ? OR alt_text LIKE ? OR mime_type LIKE ?
             ORDER BY created_at DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $row['title'] = trim((string)($row['original_name'] ?? ''));
            $row['type'] = mediaReferenceKind($row);
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

if (($requestedType === 'all' || $requestedType === 'place') && isModuleEnabled('places')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, name AS title, slug, excerpt, description, image_file, locality, category, place_kind, created_at, 'place' AS type
             FROM cms_places
             WHERE " . placePublicVisibilitySql() . "
               AND (name LIKE ? OR excerpt LIKE ? OR description LIKE ? OR locality LIKE ? OR slug LIKE ?)
             ORDER BY COALESCE(updated_at, created_at) DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

if (($requestedType === 'all' || $requestedType === 'board') && isModuleEnabled('board')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, excerpt, description, image_file, posted_at AS created_at, 'board' AS type
             FROM cms_board
             WHERE status = 'published'
               AND is_published = 1
               AND (removed_at IS NULL OR removed_at > NOW())
               AND (title LIKE ? OR excerpt LIKE ? OR description LIKE ? OR slug LIKE ?)
             ORDER BY pinned DESC, posted_at DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

if (($requestedType === 'all' || $requestedType === 'poll') && isModuleEnabled('polls')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, question AS title, slug, description, created_at, 'poll' AS type
             FROM cms_polls
             WHERE status = 'published'
               AND is_published = 1
               AND (question LIKE ? OR description LIKE ? OR slug LIKE ?)
             ORDER BY created_at DESC
             LIMIT 10"
        );
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $row) {
            $results[] = contentReferenceResult($row);
        }
    } catch (\PDOException $e) {
        error_log('content_reference_search: ' . $e->getMessage());
    }
}

usort(
    $results,
    static fn(array $left, array $right): int => contentReferenceTimestamp($right) <=> contentReferenceTimestamp($left)
);

$results = array_slice($results, 0, 20);

echo json_encode([
    'ok' => true,
    'count' => count($results),
    'message' => $results === [] ? 'Žádný veřejný obsah neodpovídá hledání.' : '',
    'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
