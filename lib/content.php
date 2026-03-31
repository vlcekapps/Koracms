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

function contentEmbedPdfMimeType(string $url, string $preferredMimeType = ''): string
{
    $preferredMimeType = strtolower(trim($preferredMimeType));
    if ($preferredMimeType === 'application/pdf') {
        return $preferredMimeType;
    }

    $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    return $extension === 'pdf' ? 'application/pdf' : '';
}

function contentEmbedPdfTitle(string $url, string $preferredTitle = ''): string
{
    $preferredTitle = trim($preferredTitle);
    if ($preferredTitle !== '') {
        return $preferredTitle;
    }

    $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
    $filename = rawurldecode((string)basename($path));
    if ($filename === '') {
        return 'PDF dokument';
    }

    $filenameWithoutExtension = preg_replace('/\.pdf$/i', '', $filename);
    $normalized = trim((string)preg_replace('/[\s_-]+/u', ' ', (string)$filenameWithoutExtension));

    return $normalized !== '' ? $normalized : 'PDF dokument';
}

function contentCodeShortcodeBody(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    if (str_starts_with($body, "\n")) {
        $body = substr($body, 1);
    }
    if (str_ends_with($body, "\n")) {
        $body = substr($body, 0, -1);
    }

    return $body;
}

function renderContentCodeShortcode(string $body): ?string
{
    $code = contentCodeShortcodeBody($body);
    if ($code === '') {
        return null;
    }

    static $contentCodeShortcodeIndex = 0;
    $contentCodeShortcodeIndex++;

    $blockId = 'content-code-block-' . $contentCodeShortcodeIndex;
    $titleId = $blockId . '-title';
    $codeId = $blockId . '-code';

    return "\n\n"
        . '<section class="content-code-block" aria-labelledby="' . h($titleId) . '">'
        . '<div class="content-code-block__header">'
        . '<p id="' . h($titleId) . '" class="content-code-block__eyebrow">Ukázka kódu</p>'
        . '<button type="button" class="button-secondary content-code-block__copy js-copy-content" data-copy-target="' . h($codeId) . '" data-copy-label="Zkopírovat obsah">Zkopírovat obsah</button>'
        . '</div>'
        . '<pre class="content-code-block__pre"><code id="' . h($codeId) . '">' . h($code) . '</code></pre>'
        . '</section>'
        . "\n\n";
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

function renderContentPdfShortcode(string $url, string $title = '', string $preferredMimeType = ''): ?string
{
    $normalizedUrl = normalizeContentEmbedUrl($url);
    $mimeType = contentEmbedPdfMimeType($normalizedUrl, $preferredMimeType);

    if ($normalizedUrl === '' || $mimeType !== 'application/pdf') {
        return null;
    }

    $resolvedTitle = contentEmbedPdfTitle($normalizedUrl, $title);
    $escapedUrl = h($normalizedUrl);
    $escapedTitle = h($resolvedTitle);

    return "\n\n"
        . '<section class="content-embed-card content-embed-card--pdf" aria-label="PDF dokument: ' . $escapedTitle . '">'
        . '<div class="content-embed-card__content">'
        . '<p class="content-embed-card__eyebrow">PDF dokument</p>'
        . '<p class="content-embed-card__title"><a href="' . $escapedUrl . '">' . $escapedTitle . '</a></p>'
        . '<p class="content-embed-card__excerpt">Náhled PDF se může lišit podle prohlížeče a asistivní technologie. Pokud se nezobrazí správně, otevřete dokument samostatně.</p>'
        . '<div class="content-embed-frame content-embed-frame--pdf">'
        . '<iframe src="' . $escapedUrl . '" title="PDF dokument: ' . $escapedTitle . '" loading="lazy"></iframe>'
        . '</div>'
        . '<div class="content-embed-card__actions">'
        . '<a class="button-secondary" href="' . $escapedUrl . '">Otevřít PDF samostatně</a>'
        . '</div>'
        . '</div>'
        . '</section>'
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
               AND " . galleryAlbumPublicVisibilitySql() . "
             LIMIT 1"
        );
        $albumStmt->execute([$normalizedSlug]);
        $album = $albumStmt->fetch();
        if (!$album) {
            return '';
        }

        $album = hydrateGalleryAlbumPresentation($album);

        $photoStmt = $pdo->prepare(
            "SELECT p.id, p.album_id, p.filename, p.title, p.slug, p.sort_order, p.created_at
             FROM cms_gallery_photos p
             INNER JOIN cms_gallery_albums a ON a.id = p.album_id
             WHERE p.album_id = ?
               AND " . galleryPhotoPublicVisibilitySql('p', 'a') . "
             ORDER BY p.sort_order, p.id
             LIMIT 4"
        );
        $photoStmt->execute([(int)$album['id']]);
        $photos = [];
        foreach ($photoStmt->fetchAll() as $photo) {
            $photos[] = hydrateGalleryPhotoPresentation($photo);
        }
    } catch (\PDOException) {
        return '';
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

function contentShortcodeResolvedValue(array $attributes, string $body, array $attributeKeys = ['slug']): string
{
    foreach ($attributeKeys as $attributeKey) {
        $attributeValue = trim((string)($attributes[$attributeKey] ?? ''));
        if ($attributeValue !== '') {
            return $attributeValue;
        }
    }

    return trim($body);
}

function contentShortcodePodcastEpisodeParts(array $attributes, string $body): ?array
{
    $resolved = contentShortcodeResolvedValue($attributes, $body, ['slug', 'path']);
    if ($resolved === '' && !empty($attributes['show']) && !empty($attributes['episode'])) {
        $resolved = trim((string)$attributes['show']) . '/' . trim((string)$attributes['episode']);
    }

    if ($resolved === '' || !str_contains($resolved, '/')) {
        return null;
    }

    [$showSlug, $episodeSlug] = array_map('trim', explode('/', $resolved, 2));
    $showSlug = podcastShowSlug($showSlug);
    $episodeSlug = podcastEpisodeSlug($episodeSlug);

    if ($showSlug === '' || $episodeSlug === '') {
        return null;
    }

    return [
        'show_slug' => $showSlug,
        'episode_slug' => $episodeSlug,
    ];
}

function contentEmbedMetaHtml(array $items): string
{
    $items = array_values(array_filter(array_map(
        static fn($item): string => trim((string)$item),
        $items
    ), static fn(string $item): bool => $item !== ''));

    if ($items === []) {
        return '';
    }

    $html = '<p class="meta-row meta-row--tight content-embed-card__meta">';
    foreach ($items as $item) {
        $html .= '<span>' . h($item) . '</span>';
    }
    $html .= '</p>';

    return $html;
}

function renderContentEmbedCard(array $config): string
{
    $title = trim((string)($config['title'] ?? ''));
    $url = trim((string)($config['url'] ?? ''));
    if ($title === '' || $url === '') {
        return '';
    }

    $eyebrow = trim((string)($config['eyebrow'] ?? 'Obsah webu'));
    $excerpt = trim((string)($config['excerpt'] ?? ''));
    $ctaLabel = trim((string)($config['cta_label'] ?? 'Zobrazit detail'));
    $modifier = trim((string)($config['modifier'] ?? ''));
    $mediaUrl = trim((string)($config['media_url'] ?? ''));
    $mediaAlt = trim((string)($config['media_alt'] ?? ''));
    $extraHtml = trim((string)($config['extra_html'] ?? ''));
    $metaHtml = contentEmbedMetaHtml(is_array($config['meta_items'] ?? null) ? $config['meta_items'] : []);

    $classes = 'content-embed-card';
    if ($modifier !== '') {
        $classes .= ' content-embed-card--' . preg_replace('/[^a-z0-9_-]+/i', '-', $modifier);
    }
    if ($mediaUrl !== '') {
        $classes .= ' content-embed-card--with-media';
    }

    $html = "\n\n" . '<section class="' . h($classes) . '" aria-label="' . h($eyebrow . ': ' . $title) . '">';

    if ($mediaUrl !== '') {
        $html .= '<div class="content-embed-card__media">'
            . '<a href="' . h($url) . '">';
        $html .= '<img src="' . h($mediaUrl) . '" alt="' . h($mediaAlt !== '' ? $mediaAlt : $title) . '" loading="lazy">';
        $html .= '</a></div>';
    }

    $html .= '<div class="content-embed-card__content">'
        . '<p class="content-embed-card__eyebrow">' . h($eyebrow) . '</p>'
        . '<p class="content-embed-card__title"><a href="' . h($url) . '">' . h($title) . '</a></p>'
        . $metaHtml;

    if ($excerpt !== '') {
        $html .= '<p class="content-embed-card__excerpt">' . h($excerpt) . '</p>';
    }

    if ($extraHtml !== '') {
        $html .= '<div class="content-embed-card__extra">' . $extraHtml . '</div>';
    }

    $html .= '<div class="content-embed-card__actions">'
        . '<a class="button-secondary" href="' . h($url) . '">' . h($ctaLabel) . '</a>'
        . '</div>'
        . '</div>'
        . '</section>'
        . "\n\n";

    return $html;
}

function renderContentInteractiveEmbed(array $config): string
{
    $title = trim((string)($config['title'] ?? ''));
    $url = trim((string)($config['url'] ?? ''));
    $embedUrl = trim((string)($config['embed_url'] ?? ''));
    if ($title === '' || $url === '' || $embedUrl === '') {
        return '';
    }

    $eyebrow = trim((string)($config['eyebrow'] ?? 'Interaktivní obsah'));
    $excerpt = trim((string)($config['excerpt'] ?? ''));
    $openLabel = trim((string)($config['open_label'] ?? 'Otevřít samostatně'));
    $modifier = trim((string)($config['modifier'] ?? 'interactive'));
    $frameModifier = trim((string)($config['frame_modifier'] ?? $modifier));

    $classes = 'content-embed-card content-embed-card--interactive';
    if ($modifier !== '') {
        $classes .= ' content-embed-card--' . preg_replace('/[^a-z0-9_-]+/i', '-', $modifier);
    }

    $html = "\n\n"
        . '<section class="' . h($classes) . '" aria-label="' . h($eyebrow . ': ' . $title) . '">'
        . '<div class="content-embed-card__content">'
        . '<p class="content-embed-card__eyebrow">' . h($eyebrow) . '</p>'
        . '<p class="content-embed-card__title"><a href="' . h($url) . '">' . h($title) . '</a></p>';

    if ($excerpt !== '') {
        $html .= '<p class="content-embed-card__excerpt">' . h($excerpt) . '</p>';
    }

    $html .= '<div class="content-embed-frame content-embed-frame--' . h(preg_replace('/[^a-z0-9_-]+/i', '-', $frameModifier)) . '">'
        . '<iframe src="' . h($embedUrl) . '" title="' . h($eyebrow . ': ' . $title) . '" loading="lazy"></iframe>'
        . '</div>'
        . '<div class="content-embed-card__actions">'
        . '<a class="button-secondary" href="' . h($url) . '">' . h($openLabel) . '</a>'
        . '</div>'
        . '</div>'
        . '</section>'
        . "\n\n";

    return $html;
}

function renderContentFormShortcode(string $slug): string
{
    $normalizedSlug = formSlug($slug);
    if ($normalizedSlug === '') {
        return '';
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT id, title, slug, description
             FROM cms_forms
             WHERE slug = ?
               AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$normalizedSlug]);
        $form = $stmt->fetch() ?: null;
    } catch (\PDOException) {
        return '';
    }

    if (!$form) {
        return '';
    }

    return renderContentInteractiveEmbed([
        'eyebrow' => 'Formulář',
        'title' => (string)$form['title'],
        'excerpt' => trim((string)($form['description'] ?? '')),
        'url' => formPublicPath($form),
        'embed_url' => formPublicPath($form, ['embed' => '1']),
        'open_label' => 'Otevřít formulář samostatně',
        'modifier' => 'form',
        'frame_modifier' => 'form',
    ]);
}

function renderContentPollShortcode(string $slug): string
{
    $normalizedSlug = pollSlug($slug);
    if ($normalizedSlug === '') {
        return '';
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
             FROM cms_polls p
             WHERE p.slug = ?
               AND " . pollPublicVisibilitySql('p') . "
             LIMIT 1"
        );
        $stmt->execute([$normalizedSlug]);
        $poll = $stmt->fetch() ?: null;
    } catch (\PDOException) {
        return '';
    }

    if (!$poll) {
        return '';
    }

    $poll = hydratePollPresentation($poll);

    return renderContentInteractiveEmbed([
        'eyebrow' => 'Anketa',
        'title' => (string)$poll['question'],
        'excerpt' => (string)($poll['excerpt'] ?? ''),
        'url' => pollPublicPath($poll),
        'embed_url' => pollPublicPath($poll, ['embed' => '1']),
        'open_label' => 'Otevřít anketu samostatně',
        'modifier' => 'poll',
        'frame_modifier' => 'poll',
    ]);
}

function renderContentDownloadShortcode(string $slug): string
{
    $normalizedSlug = downloadSlug($slug);
    if ($normalizedSlug === '') {
        return '';
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT *
             FROM cms_downloads
             WHERE slug = ?
               AND status = 'published'
               AND is_published = 1
             LIMIT 1"
        );
        $stmt->execute([$normalizedSlug]);
        $download = $stmt->fetch() ?: null;
    } catch (\PDOException) {
        return '';
    }

    if (!$download) {
        return '';
    }

    $download = hydrateDownloadPresentation($download);

    return renderContentEmbedCard([
        'eyebrow' => 'Ke stažení',
        'title' => (string)$download['title'],
        'excerpt' => (string)($download['excerpt_plain'] ?? ''),
        'url' => downloadPublicPath($download),
        'media_url' => (string)($download['image_url'] ?? ''),
        'media_alt' => (string)$download['title'],
        'meta_items' => [
            (string)($download['download_type_label'] ?? ''),
            (string)($download['version_label'] ?? ''),
            (string)($download['platform_label'] ?? ''),
        ],
        'cta_label' => 'Zobrazit detail',
        'modifier' => 'download',
    ]);
}

function renderContentPodcastShowShortcode(string $slug): string
{
    $normalizedSlug = podcastShowSlug($slug);
    if ($normalizedSlug === '') {
        return '';
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT *
             FROM cms_podcast_shows
             WHERE slug = ?
               AND " . podcastShowPublicVisibilitySql() . "
             LIMIT 1"
        );
        $stmt->execute([$normalizedSlug]);
        $show = $stmt->fetch() ?: null;
    } catch (\PDOException) {
        return '';
    }

    if (!$show) {
        return '';
    }

    $show = hydratePodcastShowPresentation($show);
    $excerpt = $show['subtitle'] !== ''
        ? (string)$show['subtitle']
        : (string)($show['description_plain'] ?? '');

    return renderContentEmbedCard([
        'eyebrow' => 'Podcast',
        'title' => (string)$show['title'],
        'excerpt' => $excerpt,
        'url' => podcastShowPublicPath($show),
        'media_url' => (string)($show['cover_url'] ?? ''),
        'media_alt' => (string)$show['title'],
        'cta_label' => 'Otevřít podcast',
        'modifier' => 'podcast',
    ]);
}

function renderContentPodcastEpisodeShortcode(string $showSlug, string $episodeSlug): string
{
    try {
        $stmt = db_connect()->prepare(
            "SELECT
                p.*,
                s.slug AS show_slug,
                s.title AS show_title,
                s.cover_image AS show_cover_image,
                s.status AS show_status,
                s.is_published AS show_is_published
             FROM cms_podcasts p
             INNER JOIN cms_podcast_shows s ON s.id = p.show_id
             WHERE s.slug = ?
               AND p.slug = ?
               AND " . podcastEpisodePublicVisibilitySql('p', 's') . "
             LIMIT 1"
        );
        $stmt->execute([$showSlug, $episodeSlug]);
        $episode = $stmt->fetch() ?: null;
    } catch (\PDOException) {
        return '';
    }

    if (!$episode) {
        return '';
    }

    $episode = hydratePodcastEpisodePresentation($episode);
    $extraHtml = '';
    if (!empty($episode['audio_src'])) {
        $extraHtml = trim((string)(renderContentAudioShortcode((string)$episode['audio_src']) ?? ''));
    }

    return renderContentEmbedCard([
        'eyebrow' => 'Epizoda podcastu',
        'title' => (string)$episode['title'],
        'excerpt' => (string)($episode['excerpt'] ?? ''),
        'url' => podcastEpisodePublicPath($episode),
        'media_url' => (string)($episode['display_image_url'] ?? ''),
        'media_alt' => (string)$episode['title'],
        'meta_items' => [
            (string)($episode['show_title'] ?? ''),
            trim((string)($episode['display_date'] ?? '')) !== '' ? formatCzechDate((string)$episode['display_date']) : '',
        ],
        'extra_html' => $extraHtml,
        'cta_label' => 'Otevřít epizodu',
        'modifier' => 'podcast-episode',
    ]);
}

function renderContentPlaceShortcode(string $slug): string
{
    $normalizedSlug = placeSlug($slug);
    if ($normalizedSlug === '') {
        return '';
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT *
             FROM cms_places
             WHERE slug = ?
               AND " . placePublicVisibilitySql() . "
             LIMIT 1"
        );
        $stmt->execute([$normalizedSlug]);
        $place = $stmt->fetch() ?: null;
    } catch (\PDOException) {
        return '';
    }

    if (!$place) {
        return '';
    }

    $place = hydratePlacePresentation($place);

    return renderContentEmbedCard([
        'eyebrow' => 'Zajímavé místo',
        'title' => (string)$place['name'],
        'excerpt' => (string)($place['excerpt_plain'] ?? ''),
        'url' => placePublicPath($place),
        'media_url' => (string)($place['image_url'] ?? ''),
        'media_alt' => (string)$place['name'],
        'meta_items' => [
            (string)($place['place_kind_label'] ?? ''),
            (string)($place['locality'] ?? ''),
        ],
        'cta_label' => 'Zobrazit místo',
        'modifier' => 'place',
    ]);
}

function renderContentEventShortcode(string $slug): string
{
    $normalizedSlug = eventSlug($slug);
    if ($normalizedSlug === '') {
        return '';
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT *
             FROM cms_events
             WHERE slug = ?
               AND " . eventPublicVisibilitySql() . "
             LIMIT 1"
        );
        $stmt->execute([$normalizedSlug]);
        $event = $stmt->fetch() ?: null;
    } catch (\PDOException) {
        return '';
    }

    if (!$event) {
        return '';
    }

    $event = hydrateEventPresentation($event);

    return renderContentEmbedCard([
        'eyebrow' => 'Událost',
        'title' => (string)$event['title'],
        'excerpt' => (string)($event['excerpt_plain'] ?? ''),
        'url' => eventPublicPath($event),
        'media_url' => (string)($event['image_url'] ?? ''),
        'media_alt' => (string)$event['title'],
        'meta_items' => [
            trim((string)($event['event_date'] ?? '')) !== '' ? formatCzechDate((string)$event['event_date']) : '',
            (string)($event['location'] ?? ''),
        ],
        'cta_label' => 'Zobrazit událost',
        'modifier' => 'event',
    ]);
}

function renderContentBoardShortcode(string $slug): string
{
    $normalizedSlug = boardSlug($slug);
    if ($normalizedSlug === '') {
        return '';
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT b.*, COALESCE(c.name, '') AS category_name
             FROM cms_board b
             LEFT JOIN cms_board_categories c ON c.id = b.category_id
             WHERE b.slug = ?
               AND " . boardPublicVisibilitySql('b') . "
             LIMIT 1"
        );
        $stmt->execute([$normalizedSlug]);
        $document = $stmt->fetch() ?: null;
    } catch (\PDOException) {
        return '';
    }

    if (!$document) {
        return '';
    }

    $document = hydrateBoardPresentation($document);

    return renderContentEmbedCard([
        'eyebrow' => boardModulePublicLabel(),
        'title' => (string)$document['title'],
        'excerpt' => (string)($document['excerpt_plain'] ?? ''),
        'url' => boardPublicPath($document),
        'media_url' => (string)($document['image_url'] ?? ''),
        'media_alt' => (string)$document['title'],
        'meta_items' => [
            (string)($document['board_type_label'] ?? ''),
            trim((string)($document['posted_date'] ?? '')) !== '' ? formatCzechDate((string)$document['posted_date']) : '',
        ],
        'cta_label' => 'Zobrazit oznámení',
        'modifier' => 'board',
    ]);
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
            $slug = contentShortcodeResolvedValue($attributes, (string)($matches[2] ?? ''), ['slug', 'album']);
            if (galleryAlbumSlug($slug) === '') {
                return $matches[0];
            }

            return renderContentGalleryShortcode($slug);
        },
        $text
    ) ?? $text;

    $text = preg_replace_callback(
        '/\[pdf(?:\s+([^\]]*))?\](.*?)\[\/pdf\]/is',
        static function (array $matches): string {
            $attributes = parseContentShortcodeAttributes(trim((string)($matches[1] ?? '')));
            $source = contentShortcodeResolvedValue($attributes, (string)($matches[2] ?? ''), ['src', 'url']);
            $title = trim((string)($attributes['title'] ?? $attributes['label'] ?? ''));
            $mimeType = strtolower(trim((string)($attributes['mime'] ?? $attributes['type'] ?? '')));

            return renderContentPdfShortcode($source, $title, $mimeType) ?? $matches[0];
        },
        $text
    ) ?? $text;

    $text = preg_replace_callback(
        '/\[code(?:\s+([^\]]*))?\](.*?)\[\/code\]/is',
        static function (array $matches): string {
            return renderContentCodeShortcode((string)($matches[2] ?? '')) ?? $matches[0];
        },
        $text
    ) ?? $text;

    $text = preg_replace_callback(
        '/\[form(?:\s+([^\]]*))?\](.*?)\[\/form\]/is',
        static function (array $matches): string {
            $attributes = parseContentShortcodeAttributes(trim((string)($matches[1] ?? '')));
            $slug = contentShortcodeResolvedValue($attributes, (string)($matches[2] ?? ''), ['slug', 'form']);
            if (formSlug($slug) === '') {
                return $matches[0];
            }

            return renderContentFormShortcode($slug);
        },
        $text
    ) ?? $text;

    $text = preg_replace_callback(
        '/\[poll(?:\s+([^\]]*))?\](.*?)\[\/poll\]/is',
        static function (array $matches): string {
            $attributes = parseContentShortcodeAttributes(trim((string)($matches[1] ?? '')));
            $slug = contentShortcodeResolvedValue($attributes, (string)($matches[2] ?? ''), ['slug']);
            if (pollSlug($slug) === '') {
                return $matches[0];
            }

            return renderContentPollShortcode($slug);
        },
        $text
    ) ?? $text;

    $text = preg_replace_callback(
        '/\[download(?:\s+([^\]]*))?\](.*?)\[\/download\]/is',
        static function (array $matches): string {
            $attributes = parseContentShortcodeAttributes(trim((string)($matches[1] ?? '')));
            $slug = contentShortcodeResolvedValue($attributes, (string)($matches[2] ?? ''), ['slug']);
            if (downloadSlug($slug) === '') {
                return $matches[0];
            }

            return renderContentDownloadShortcode($slug);
        },
        $text
    ) ?? $text;

    $text = preg_replace_callback(
        '/\[podcast(?:\s+([^\]]*))?\](.*?)\[\/podcast\]/is',
        static function (array $matches): string {
            $attributes = parseContentShortcodeAttributes(trim((string)($matches[1] ?? '')));
            $slug = contentShortcodeResolvedValue($attributes, (string)($matches[2] ?? ''), ['slug', 'show']);
            if (podcastShowSlug($slug) === '') {
                return $matches[0];
            }

            return renderContentPodcastShowShortcode($slug);
        },
        $text
    ) ?? $text;

    $text = preg_replace_callback(
        '/\[podcast_episode(?:\s+([^\]]*))?\](.*?)\[\/podcast_episode\]/is',
        static function (array $matches): string {
            $attributes = parseContentShortcodeAttributes(trim((string)($matches[1] ?? '')));
            $parts = contentShortcodePodcastEpisodeParts($attributes, (string)($matches[2] ?? ''));
            if ($parts === null) {
                return $matches[0];
            }

            return renderContentPodcastEpisodeShortcode($parts['show_slug'], $parts['episode_slug']);
        },
        $text
    ) ?? $text;

    $text = preg_replace_callback(
        '/\[place(?:\s+([^\]]*))?\](.*?)\[\/place\]/is',
        static function (array $matches): string {
            $attributes = parseContentShortcodeAttributes(trim((string)($matches[1] ?? '')));
            $slug = contentShortcodeResolvedValue($attributes, (string)($matches[2] ?? ''), ['slug']);
            if (placeSlug($slug) === '') {
                return $matches[0];
            }

            return renderContentPlaceShortcode($slug);
        },
        $text
    ) ?? $text;

    $text = preg_replace_callback(
        '/\[event(?:\s+([^\]]*))?\](.*?)\[\/event\]/is',
        static function (array $matches): string {
            $attributes = parseContentShortcodeAttributes(trim((string)($matches[1] ?? '')));
            $slug = contentShortcodeResolvedValue($attributes, (string)($matches[2] ?? ''), ['slug']);
            if (eventSlug($slug) === '') {
                return $matches[0];
            }

            return renderContentEventShortcode($slug);
        },
        $text
    ) ?? $text;

    $text = preg_replace_callback(
        '/\[board(?:\s+([^\]]*))?\](.*?)\[\/board\]/is',
        static function (array $matches): string {
            $attributes = parseContentShortcodeAttributes(trim((string)($matches[1] ?? '')));
            $slug = contentShortcodeResolvedValue($attributes, (string)($matches[2] ?? ''), ['slug']);
            if (boardSlug($slug) === '') {
                return $matches[0];
            }

            return renderContentBoardShortcode($slug);
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
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 0) . ' kB';
    }
    return $bytes . ' B';
}
