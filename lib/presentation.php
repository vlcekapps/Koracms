<?php
// Prezentační funkce – slugy, URL, excerpty, hydratace, autoři – extrahováno z db.php

function formatCzechDate(string $datetime): string
{
    static $months = [
        '', 'ledna', 'února', 'března', 'dubna', 'května', 'června',
        'července', 'srpna', 'září', 'října', 'listopadu', 'prosince',
    ];
    try { $dt = new \DateTime($datetime); } catch (\Exception $e) { return h($datetime); }
    return $dt->format('j') . '. ' . $months[(int)$dt->format('n')]
         . ' ' . $dt->format('Y, H:i');
}

/**
 * Odhadne dobu čtení textu v minutách (průměr 200 slov/min pro češtinu).
 */
function readingTime(string $text): int
{
    $plain = strip_tags($text);
    $words = preg_match_all('/\S+/u', $plain);
    return max(1, (int)round($words / 200));
}

function articleReadingMeta(string $text, int $viewCount): string
{
    return readingTime($text) . ' min čtení, přečteno ' . max(0, $viewCount) . ' krát';
}

// ─────────────────────────────── Statické stránky ────────────────────────

/**
 * Převede text na URL slug (podporuje českou diakritiku).
 */
function slugify(string $text): string
{
    $map = [
        'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n',
        'ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
        'Á'=>'a','Č'=>'c','Ď'=>'d','É'=>'e','Ě'=>'e','Í'=>'i','Ň'=>'n',
        'Ó'=>'o','Ř'=>'r','Š'=>'s','Ť'=>'t','Ú'=>'u','Ů'=>'u','Ý'=>'y','Ž'=>'z',
    ];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function articleSlug(string $value): string
{
    return slugify(trim($value));
}

function pageSlug(string $value): string
{
    return slugify(trim($value));
}

function newsSlug(string $value): string
{
    return slugify(trim($value));
}

function eventSlug(string $value): string
{
    return slugify(trim($value));
}

function placeSlug(string $value): string
{
    return slugify(trim($value));
}

function foodCardSlug(string $value): string
{
    return slugify(trim($value));
}

function reservationResourceSlug(string $value): string
{
    return slugify(trim($value));
}

function downloadSlug(string $value): string
{
    return slugify(trim($value));
}

function boardSlug(string $value): string
{
    return slugify(trim($value));
}

function galleryAlbumSlug(string $value): string
{
    return slugify(trim($value));
}

function galleryPhotoSlug(string $value): string
{
    return slugify(trim($value));
}

function pollSlug(string $value): string
{
    return slugify(trim($value));
}

function faqSlug(string $value): string
{
    return slugify(trim($value));
}

function podcastShowSlug(string $value): string
{
    return slugify(trim($value));
}

function podcastEpisodeSlug(string $value): string
{
    return slugify(trim($value));
}

function authorSlug(string $value): string
{
    return slugify(trim($value));
}

function normalizePlainText(string $text): string
{
    $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plain = preg_replace('/\s+/u', ' ', $plain);
    return trim((string)$plain);
}

function newsTitleCandidate(string $title, string $content = ''): string
{
    $normalizedTitle = trim($title);
    if ($normalizedTitle !== '') {
        return mb_substr($normalizedTitle, 0, 255);
    }

    $plain = normalizePlainText($content);
    if ($plain === '') {
        return 'Novinka';
    }

    return mb_strimwidth($plain, 0, 120, '…', 'UTF-8');
}

function newsExcerpt(string $content, int $limit = 220): string
{
    $plain = normalizePlainText($content);
    if ($plain === '') {
        return '';
    }

    return mb_strimwidth($plain, 0, $limit, '…', 'UTF-8');
}

function boardTypeLabel(string $type): string
{
    $definitions = boardTypeDefinitions();
    return $definitions[normalizeBoardType($type)]['label'];
}

function boardExcerpt(array $document, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($document['excerpt'] ?? ''));
    if ($explicitExcerpt !== '') {
        return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
    }

    $descriptionExcerpt = normalizePlainText((string)($document['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

function pollExcerpt(array $poll, int $limit = 220): string
{
    $descriptionExcerpt = normalizePlainText((string)($poll['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

function faqExcerpt(array $faq, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($faq['excerpt'] ?? ''));
    if ($explicitExcerpt !== '') {
        return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
    }

    $answerExcerpt = normalizePlainText((string)($faq['answer'] ?? ''));
    if ($answerExcerpt === '') {
        return '';
    }

    return mb_strimwidth($answerExcerpt, 0, $limit, '...', 'UTF-8');
}

function placeKindLabel(string $kind): string
{
    $definitions = placeKindDefinitions();
    return $definitions[normalizePlaceKind($kind)]['label'];
}

function placeExcerpt(array $place, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($place['excerpt'] ?? ''));
    if ($explicitExcerpt !== '') {
        return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
    }

    $descriptionExcerpt = normalizePlainText((string)($place['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

function downloadTypeLabel(string $type): string
{
    $definitions = downloadTypeDefinitions();
    return $definitions[normalizeDownloadType($type)]['label'];
}

function downloadExcerpt(array $download, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($download['excerpt'] ?? ''));
    if ($explicitExcerpt !== '') {
        return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
    }

    $descriptionExcerpt = normalizePlainText((string)($download['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

function podcastEpisodeExcerpt(array $episode, int $limit = 220): string
{
    $descriptionExcerpt = normalizePlainText((string)($episode['description'] ?? ''));
    if ($descriptionExcerpt === '') {
        return '';
    }

    return mb_strimwidth($descriptionExcerpt, 0, $limit, '...', 'UTF-8');
}

function downloadImageUrl(array $download): string
{
    $filename = trim((string)($download['image_file'] ?? ''));
    if ($filename === '') {
        return '';
    }

    return BASE_URL . '/uploads/downloads/images/' . rawurlencode($filename);
}

function podcastCoverUrl(array $show): string
{
    $filename = trim((string)($show['cover_image'] ?? ''));
    if ($filename === '') {
        return '';
    }

    return BASE_URL . '/uploads/podcasts/covers/' . rawurlencode($filename);
}

function podcastEpisodeAudioUrl(array $episode): string
{
    $audioFile = trim((string)($episode['audio_file'] ?? ''));
    if ($audioFile !== '') {
        return BASE_URL . '/uploads/podcasts/' . rawurlencode($audioFile);
    }

    return trim((string)($episode['audio_url'] ?? ''));
}

function normalizePodcastWebsiteUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    $validated = filter_var($value, FILTER_VALIDATE_URL);
    if (!is_string($validated) || !preg_match('#^https?://#i', $validated)) {
        return '';
    }

    return $validated;
}

function normalizePodcastEpisodeAudioUrl(string $value): string
{
    return normalizePodcastWebsiteUrl($value);
}

function deletePodcastCoverFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/podcasts/covers/' . $filename;
    if (is_file($path)) {
        if (!unlink($path)) {
            error_log('presentation: nelze smazat soubor ' . $path);
        }
    }
}

function deletePodcastAudioFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/podcasts/' . $filename;
    if (is_file($path)) {
        if (!unlink($path)) {
            error_log('presentation: nelze smazat soubor ' . $path);
        }
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadPodcastCoverImage(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek coveru se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek coveru se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Cover musí být ve formátu JPEG, PNG, GIF, WebP nebo SVG.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/podcasts/covers/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro cover obrázky se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('podcast_cover_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Cover obrázek se nepodařilo uložit.',
        ];
    }

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deletePodcastCoverFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadPodcastAudioFile(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Audio soubor se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Audio soubor se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/aac' => 'aac',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Audio musí být ve formátu MP3, OGG, WAV, M4A nebo AAC.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/podcasts/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro podcastová audia se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('podcast_episode_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Audio soubor se nepodařilo uložit.',
        ];
    }

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deletePodcastAudioFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

function deleteDownloadImageFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/downloads/images/' . $filename;
    if (is_file($path)) {
        if (!unlink($path)) {
            error_log('presentation: nelze smazat soubor ' . $path);
        }
    }
}

function deleteDownloadStoredFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/downloads/' . $filename;
    if (is_file($path)) {
        if (!unlink($path)) {
            error_log('presentation: nelze smazat soubor ' . $path);
        }
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadDownloadImage(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek musí být ve formátu JPEG, PNG, GIF, WebP nebo SVG.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/downloads/images/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro obrázky ke stažení se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('download_image_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo uložit.',
        ];
    }

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deleteDownloadImageFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

function normalizeDownloadExternalUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    $validated = filter_var($value, FILTER_VALIDATE_URL);
    if (!is_string($validated) || !preg_match('#^https?://#i', $validated)) {
        return '';
    }

    return $validated;
}

function hydrateDownloadPresentation(array $download): array
{
    $download['slug'] = downloadSlug((string)($download['slug'] ?? ''));
    $download['download_type'] = normalizeDownloadType((string)($download['download_type'] ?? 'document'));
    $download['download_type_label'] = downloadTypeLabel((string)$download['download_type']);
    $download['excerpt_plain'] = downloadExcerpt($download);
    $download['image_url'] = downloadImageUrl($download);
    $download['version_label'] = trim((string)($download['version_label'] ?? ''));
    $download['platform_label'] = trim((string)($download['platform_label'] ?? ''));
    $download['license_label'] = trim((string)($download['license_label'] ?? ''));
    $download['external_url'] = normalizeDownloadExternalUrl((string)($download['external_url'] ?? ''));
    $download['has_external_url'] = $download['external_url'] !== '';
    $download['filename'] = trim((string)($download['filename'] ?? ''));
    $download['original_name'] = trim((string)($download['original_name'] ?? ''));
    $download['has_file'] = $download['filename'] !== '';

    return $download;
}

function foodCardTypeLabel(string $type): string
{
    return $type === 'beverage' ? 'Nápojový lístek' : 'Jídelní lístek';
}

function foodCardValidityLabel(array $card): string
{
    $from = !empty($card['valid_from']) ? formatCzechDate((string)$card['valid_from']) : null;
    $to = !empty($card['valid_to']) ? formatCzechDate((string)$card['valid_to']) : null;

    if ($from && $to) {
        return 'Platnost: ' . $from . ' – ' . $to;
    }
    if ($from) {
        return 'Platnost od ' . $from;
    }
    if ($to) {
        return 'Platnost do ' . $to;
    }

    return '';
}

function foodCardMetaLabel(array $card): string
{
    $parts = [];
    $validityLabel = foodCardValidityLabel($card);
    if ($validityLabel !== '') {
        $parts[] = $validityLabel;
    }

    $description = trim((string)($card['description'] ?? ''));
    if ($description !== '') {
        $parts[] = $description;
    }

    return implode(' | ', $parts);
}

function hydrateFoodCardPresentation(array $card): array
{
    $card['slug'] = foodCardSlug((string)($card['slug'] ?? ''));
    $card['type'] = in_array((string)($card['type'] ?? 'food'), ['food', 'beverage'], true)
        ? (string)$card['type']
        : 'food';
    $card['type_label'] = foodCardTypeLabel((string)$card['type']);
    $card['validity_label'] = foodCardValidityLabel($card);
    $card['meta_label'] = foodCardMetaLabel($card);
    $card['public_path'] = foodCardPublicPath($card);
    $card['is_publicly_visible'] = ((string)($card['status'] ?? 'published') === 'published')
        && (int)($card['is_published'] ?? 1) === 1;

    return $card;
}

function placeImageUrl(array $place): string
{
    $filename = trim((string)($place['image_file'] ?? ''));
    if ($filename === '') {
        return '';
    }

    return BASE_URL . '/uploads/places/' . rawurlencode($filename);
}

function deletePlaceImageFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/places/' . $filename;
    if (is_file($path)) {
        if (!unlink($path)) {
            error_log('presentation: nelze smazat soubor ' . $path);
        }
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadPlaceImage(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek musí být ve formátu JPEG, PNG, GIF, WebP nebo SVG.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/places/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro obrázky míst se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('place_image_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo uložit.',
        ];
    }

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deletePlaceImageFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

function normalizePlaceUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    $validated = filter_var($value, FILTER_VALIDATE_URL);
    if (!is_string($validated) || !preg_match('#^https?://#i', $validated)) {
        return '';
    }

    return $validated;
}

function hydratePlacePresentation(array $place): array
{
    $place['slug'] = placeSlug((string)($place['slug'] ?? ''));
    $place['place_kind'] = normalizePlaceKind((string)($place['place_kind'] ?? 'sight'));
    $place['place_kind_label'] = placeKindLabel((string)$place['place_kind']);
    $place['excerpt_plain'] = placeExcerpt($place);
    $place['image_url'] = placeImageUrl($place);
    $place['url'] = normalizePlaceUrl((string)($place['url'] ?? ''));
    $place['address'] = trim((string)($place['address'] ?? ''));
    $place['locality'] = trim((string)($place['locality'] ?? ''));
    $place['contact_phone'] = trim((string)($place['contact_phone'] ?? ''));
    $place['contact_email'] = trim((string)($place['contact_email'] ?? ''));
    $place['opening_hours'] = trim((string)($place['opening_hours'] ?? ''));
    $place['has_contact'] = $place['contact_phone'] !== '' || $place['contact_email'] !== '';
    $place['full_address'] = trim(
        implode(', ', array_filter([
            $place['address'],
            $place['locality'],
        ], static fn(string $value): bool => $value !== ''))
    );

    $latitude = trim((string)($place['latitude'] ?? ''));
    $longitude = trim((string)($place['longitude'] ?? ''));
    $place['latitude'] = $latitude;
    $place['longitude'] = $longitude;
    $place['has_coordinates'] = $latitude !== '' && $longitude !== '';
    $place['map_url'] = $place['has_coordinates']
        ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($latitude . ',' . $longitude)
        : '';

    return $place;
}

function boardImageUrl(array $document): string
{
    $filename = trim((string)($document['image_file'] ?? ''));
    if ($filename === '') {
        return '';
    }

    return BASE_URL . '/uploads/board/images/' . rawurlencode($filename);
}

function deleteBoardImageFile(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/board/images/' . $filename;
    if (is_file($path)) {
        if (!unlink($path)) {
            error_log('presentation: nelze smazat soubor ' . $path);
        }
    }
}

/**
 * @return array{filename:string,uploaded:bool,error:string}
 */
function uploadBoardImage(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if (($file['name'] ?? '') === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek musí být ve formátu JPEG, PNG, GIF, WebP nebo SVG.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/board/images/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro obrázky vývěsky se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('board_image_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Obrázek se nepodařilo uložit.',
        ];
    }

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deleteBoardImageFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

function hydrateBoardPresentation(array $document): array
{
    $document['board_type'] = normalizeBoardType((string)($document['board_type'] ?? 'document'));
    $document['board_type_label'] = boardTypeLabel((string)$document['board_type']);
    $document['excerpt_plain'] = boardExcerpt($document);
    $document['image_url'] = boardImageUrl($document);
    $document['contact_name'] = trim((string)($document['contact_name'] ?? ''));
    $document['contact_phone'] = trim((string)($document['contact_phone'] ?? ''));
    $document['contact_email'] = trim((string)($document['contact_email'] ?? ''));
    $document['has_contact'] = $document['contact_name'] !== ''
        || $document['contact_phone'] !== ''
        || $document['contact_email'] !== '';
    $document['is_pinned'] = (int)($document['is_pinned'] ?? 0);

    return $document;
}

function authorSlugCandidate(array $account): string
{
    $nickname = trim((string)($account['nickname'] ?? ''));
    if ($nickname !== '') {
        return $nickname;
    }

    $fullName = trim(
        trim((string)($account['first_name'] ?? '')) . ' ' . trim((string)($account['last_name'] ?? ''))
    );
    if ($fullName !== '') {
        return $fullName;
    }

    $email = trim((string)($account['email'] ?? ''));
    if ($email !== '') {
        $localPart = strstr($email, '@', true);
        return $localPart !== false && $localPart !== '' ? $localPart : $email;
    }

    return 'autor';
}

function appendUrlQuery(string $path, array $params): string
{
    $query = http_build_query(array_filter(
        $params,
        static fn($value): bool => $value !== null && $value !== ''
    ));

    if ($query === '') {
        return $path;
    }

    return $path . (str_contains($path, '?') ? '&' : '?') . $query;
}

function articlePublicRequestPath(array $article): string
{
    $slug = articleSlug((string)($article['slug'] ?? ''));
    if ($slug !== '') {
        return '/blog/' . rawurlencode($slug);
    }

    return '/blog/article.php?id=' . (int)($article['id'] ?? 0);
}

function articlePublicPath(array $article, array $query = []): string
{
    return BASE_URL . appendUrlQuery(articlePublicRequestPath($article), $query);
}

function articlePublicUrl(array $article, array $query = []): string
{
    return siteUrl(appendUrlQuery(articlePublicRequestPath($article), $query));
}

function pagePublicRequestPath(array $page): string
{
    $slug = pageSlug((string)($page['slug'] ?? ''));
    if ($slug !== '') {
        return '/page.php?slug=' . rawurlencode($slug);
    }

    return '/';
}

function pagePublicPath(array $page, array $query = []): string
{
    return BASE_URL . appendUrlQuery(pagePublicRequestPath($page), $query);
}

function pagePublicUrl(array $page, array $query = []): string
{
    return siteUrl(appendUrlQuery(pagePublicRequestPath($page), $query));
}

function articlePreviewPath(array $article): string
{
    $previewToken = trim((string)($article['preview_token'] ?? ''));
    return articlePublicPath($article, $previewToken !== '' ? ['preview' => $previewToken] : []);
}

function newsPublicRequestPath(array $news): string
{
    $slug = newsSlug((string)($news['slug'] ?? ''));
    if ($slug !== '') {
        return '/news/' . rawurlencode($slug);
    }

    return '/news/article.php?id=' . (int)($news['id'] ?? 0);
}

function podcastShowPublicRequestPath(array $show): string
{
    $slug = podcastShowSlug((string)($show['slug'] ?? ''));
    if ($slug !== '') {
        return '/podcast/' . rawurlencode($slug);
    }

    return '/podcast/index.php';
}

function podcastShowPublicPath(array $show, array $query = []): string
{
    return BASE_URL . appendUrlQuery(podcastShowPublicRequestPath($show), $query);
}

function podcastShowPublicUrl(array $show, array $query = []): string
{
    return siteUrl(appendUrlQuery(podcastShowPublicRequestPath($show), $query));
}

function podcastEpisodePublicRequestPath(array $episode): string
{
    $showSlug = podcastShowSlug((string)($episode['show_slug'] ?? ''));
    $episodeSlug = podcastEpisodeSlug((string)($episode['slug'] ?? ''));
    if ($showSlug !== '' && $episodeSlug !== '') {
        return '/podcast/' . rawurlencode($showSlug) . '/' . rawurlencode($episodeSlug);
    }

    return '/podcast/episode.php?id=' . (int)($episode['id'] ?? 0);
}

function podcastEpisodePublicPath(array $episode, array $query = []): string
{
    return BASE_URL . appendUrlQuery(podcastEpisodePublicRequestPath($episode), $query);
}

function podcastEpisodePublicUrl(array $episode, array $query = []): string
{
    return siteUrl(appendUrlQuery(podcastEpisodePublicRequestPath($episode), $query));
}

function faqPublicRequestPath(array $faq): string
{
    $slug = faqSlug((string)($faq['slug'] ?? ''));
    if ($slug !== '') {
        return '/faq/' . rawurlencode($slug);
    }

    return '/faq/item.php?id=' . (int)($faq['id'] ?? 0);
}

function faqPublicPath(array $faq, array $query = []): string
{
    return BASE_URL . appendUrlQuery(faqPublicRequestPath($faq), $query);
}

function faqPublicUrl(array $faq, array $query = []): string
{
    return siteUrl(appendUrlQuery(faqPublicRequestPath($faq), $query));
}

function pollPublicRequestPath(array $poll): string
{
    $slug = pollSlug((string)($poll['slug'] ?? ''));
    if ($slug !== '') {
        return '/polls/' . rawurlencode($slug);
    }

    return '/polls/index.php?id=' . (int)($poll['id'] ?? 0);
}

function pollPublicPath(array $poll, array $query = []): string
{
    return BASE_URL . appendUrlQuery(pollPublicRequestPath($poll), $query);
}

function pollPublicUrl(array $poll, array $query = []): string
{
    return siteUrl(appendUrlQuery(pollPublicRequestPath($poll), $query));
}

function foodCardPublicRequestPath(array $card): string
{
    $slug = foodCardSlug((string)($card['slug'] ?? ''));
    if ($slug !== '') {
        return '/food/card/' . rawurlencode($slug);
    }

    return '/food/card.php?id=' . (int)($card['id'] ?? 0);
}

function foodCardPublicPath(array $card, array $query = []): string
{
    return BASE_URL . appendUrlQuery(foodCardPublicRequestPath($card), $query);
}

function foodCardPublicUrl(array $card, array $query = []): string
{
    return siteUrl(appendUrlQuery(foodCardPublicRequestPath($card), $query));
}

function reservationResourcePublicRequestPath(array $resource): string
{
    $slug = reservationResourceSlug((string)($resource['slug'] ?? ''));
    if ($slug !== '') {
        return '/reservations/resource.php?slug=' . rawurlencode($slug);
    }

    return '/reservations/index.php';
}

function reservationResourcePublicPath(array $resource, array $query = []): string
{
    return BASE_URL . appendUrlQuery(reservationResourcePublicRequestPath($resource), $query);
}

function reservationResourcePublicUrl(array $resource, array $query = []): string
{
    return siteUrl(appendUrlQuery(reservationResourcePublicRequestPath($resource), $query));
}

function galleryAlbumPublicRequestPath(array $album): string
{
    $slug = galleryAlbumSlug((string)($album['slug'] ?? ''));
    if ($slug !== '') {
        return '/gallery/album/' . rawurlencode($slug);
    }

    return '/gallery/album.php?id=' . (int)($album['id'] ?? 0);
}

function galleryAlbumPublicPath(array $album, array $query = []): string
{
    return BASE_URL . appendUrlQuery(galleryAlbumPublicRequestPath($album), $query);
}

function galleryAlbumPublicUrl(array $album, array $query = []): string
{
    return siteUrl(appendUrlQuery(galleryAlbumPublicRequestPath($album), $query));
}

function galleryPhotoPublicRequestPath(array $photo): string
{
    $slug = galleryPhotoSlug((string)($photo['slug'] ?? ''));
    if ($slug !== '') {
        return '/gallery/photo/' . rawurlencode($slug);
    }

    return '/gallery/photo.php?id=' . (int)($photo['id'] ?? 0);
}

function galleryPhotoPublicPath(array $photo, array $query = []): string
{
    return BASE_URL . appendUrlQuery(galleryPhotoPublicRequestPath($photo), $query);
}

function galleryPhotoPublicUrl(array $photo, array $query = []): string
{
    return siteUrl(appendUrlQuery(galleryPhotoPublicRequestPath($photo), $query));
}

function newsPublicPath(array $news, array $query = []): string
{
    return BASE_URL . appendUrlQuery(newsPublicRequestPath($news), $query);
}

function newsPublicUrl(array $news, array $query = []): string
{
    return siteUrl(appendUrlQuery(newsPublicRequestPath($news), $query));
}

function downloadPublicRequestPath(array $download): string
{
    $slug = downloadSlug((string)($download['slug'] ?? ''));
    if ($slug !== '') {
        return '/downloads/' . rawurlencode($slug);
    }

    return '/downloads/item.php?id=' . (int)($download['id'] ?? 0);
}

function downloadPublicPath(array $download, array $query = []): string
{
    return BASE_URL . appendUrlQuery(downloadPublicRequestPath($download), $query);
}

function downloadPublicUrl(array $download, array $query = []): string
{
    return siteUrl(appendUrlQuery(downloadPublicRequestPath($download), $query));
}

function boardPublicRequestPath(array $document): string
{
    $slug = boardSlug((string)($document['slug'] ?? ''));
    if ($slug !== '') {
        return '/board/' . rawurlencode($slug);
    }

    return '/board/document.php?id=' . (int)($document['id'] ?? 0);
}

function boardPublicPath(array $document, array $query = []): string
{
    return BASE_URL . appendUrlQuery(boardPublicRequestPath($document), $query);
}

function boardPublicUrl(array $document, array $query = []): string
{
    return siteUrl(appendUrlQuery(boardPublicRequestPath($document), $query));
}

function eventPublicRequestPath(array $event): string
{
    $slug = eventSlug((string)($event['slug'] ?? ''));
    if ($slug !== '') {
        return '/events/' . rawurlencode($slug);
    }

    return '/events/event.php?id=' . (int)($event['id'] ?? 0);
}

function placePublicRequestPath(array $place): string
{
    $slug = placeSlug((string)($place['slug'] ?? ''));
    if ($slug !== '') {
        return '/places/' . rawurlencode($slug);
    }

    return '/places/place.php?id=' . (int)($place['id'] ?? 0);
}

function placePublicPath(array $place, array $query = []): string
{
    return BASE_URL . appendUrlQuery(placePublicRequestPath($place), $query);
}

function placePublicUrl(array $place, array $query = []): string
{
    return siteUrl(appendUrlQuery(placePublicRequestPath($place), $query));
}

function eventPublicPath(array $event, array $query = []): string
{
    return BASE_URL . appendUrlQuery(eventPublicRequestPath($event), $query);
}

function eventPublicUrl(array $event, array $query = []): string
{
    return siteUrl(appendUrlQuery(eventPublicRequestPath($event), $query));
}

function uniqueArticleSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = articleSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'clanek';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_articles WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniquePageSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = pageSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'stranka';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_pages WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function normalizePageNavigationOrder(PDO $pdo): void
{
    $pages = $pdo->query(
        "SELECT id, nav_order, title
         FROM cms_pages
         ORDER BY nav_order, title, id"
    )->fetchAll();

    if ($pages === []) {
        return;
    }

    $update = $pdo->prepare("UPDATE cms_pages SET nav_order = ? WHERE id = ?");
    $position = 1;
    foreach ($pages as $page) {
        if ((int)($page['nav_order'] ?? 0) !== $position) {
            $update->execute([$position, (int)$page['id']]);
        }
        $position++;
    }
}

function nextPageNavigationOrder(PDO $pdo): int
{
    normalizePageNavigationOrder($pdo);

    $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(nav_order), 0) FROM cms_pages")->fetchColumn();
    return $maxOrder + 1;
}

function movePageNavigationOrder(PDO $pdo, int $pageId, string $direction): bool
{
    if (!in_array($direction, ['up', 'down'], true)) {
        return false;
    }

    normalizePageNavigationOrder($pdo);

    $pages = $pdo->query(
        "SELECT id
         FROM cms_pages
         ORDER BY nav_order, title, id"
    )->fetchAll();

    $orderedIds = array_map(static fn(array $row): int => (int)$row['id'], $pages);
    $currentIndex = array_search($pageId, $orderedIds, true);
    if ($currentIndex === false) {
        return false;
    }

    $swapIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
    if (!isset($orderedIds[$swapIndex])) {
        return false;
    }

    [$orderedIds[$currentIndex], $orderedIds[$swapIndex]] = [$orderedIds[$swapIndex], $orderedIds[$currentIndex]];

    $update = $pdo->prepare("UPDATE cms_pages SET nav_order = ? WHERE id = ?");
    foreach ($orderedIds as $index => $orderedId) {
        $update->execute([$index + 1, $orderedId]);
    }

    return true;
}

function uniqueEventSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = eventSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'udalost';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_events WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniquePlaceSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = placeSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'misto';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_places WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueDownloadSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = downloadSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'soubor';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_downloads WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueFoodCardSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = foodCardSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'listek';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_food_cards WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function reservationBookingStatusLabels(): array
{
    return [
        'pending' => 'Čeká na schválení',
        'confirmed' => 'Potvrzená',
        'cancelled' => 'Zrušená',
        'rejected' => 'Zamítnutá',
        'completed' => 'Dokončená',
        'no_show' => 'Nedostavil se',
    ];
}

function reservationBookingStatusColors(): array
{
    return [
        'pending' => '#8a4b00',
        'confirmed' => '#1b5e20',
        'cancelled' => '#666666',
        'rejected' => '#b71c1c',
        'completed' => '#005fcc',
        'no_show' => '#6d0000',
    ];
}

function uniqueBoardSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = boardSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'dokument';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_board WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueGalleryAlbumSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = galleryAlbumSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'album';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_gallery_albums WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueGalleryPhotoSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = galleryPhotoSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'fotografie';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_gallery_photos WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniquePodcastShowSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = podcastShowSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'podcast';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_podcast_shows WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniquePodcastEpisodeSlug(PDO $pdo, int $showId, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = podcastEpisodeSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'epizoda';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_podcasts WHERE show_id = ? AND slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$showId, $slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueFaqSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = faqSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'otazka';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_faqs WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniquePollSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = pollSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'anketa';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_polls WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueNewsSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = newsSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'novinka';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_news WHERE slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function uniqueAuthorSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $baseSlug = authorSlug($candidate);
    if ($baseSlug === '') {
        $baseSlug = 'autor';
    }

    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare("SELECT id FROM cms_users WHERE author_slug = ? AND id != ?");

    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function authorRoleValue(array $author): string
{
    return trim((string)($author['author_role'] ?? $author['role'] ?? ''));
}

function authorPublicSlugValue(array $author): string
{
    return authorSlug((string)($author['author_slug'] ?? $author['slug'] ?? ''));
}

function authorPublicEnabled(array $author): bool
{
    return (int)($author['author_public_enabled'] ?? 0) === 1
        && authorRoleValue($author) !== 'public'
        && authorPublicSlugValue($author) !== '';
}

function authorDisplayName(array $author): string
{
    $preferred = trim((string)($author['author_name'] ?? ''));
    if ($preferred !== '') {
        return $preferred;
    }

    $nickname = trim((string)($author['nickname'] ?? ''));
    if ($nickname !== '') {
        return $nickname;
    }

    $fullName = trim(
        trim((string)($author['first_name'] ?? '')) . ' ' . trim((string)($author['last_name'] ?? ''))
    );
    if ($fullName !== '') {
        return $fullName;
    }

    return trim((string)($author['email'] ?? ''));
}

function normalizeAuthorWebsite(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    $validated = filter_var($value, FILTER_VALIDATE_URL);
    if (!is_string($validated) || !preg_match('#^https?://#i', $validated)) {
        return '';
    }

    return $validated;
}

function authorPublicRequestPath(array $author): string
{
    if (!authorPublicEnabled($author)) {
        return '';
    }

    return '/author/' . rawurlencode(authorPublicSlugValue($author));
}

function authorIndexRequestPath(): string
{
    return '/authors/';
}

function authorPublicPath(array $author): string
{
    $path = authorPublicRequestPath($author);
    return $path !== '' ? BASE_URL . $path : '';
}

function authorIndexPath(): string
{
    return BASE_URL . authorIndexRequestPath();
}

function authorPublicUrl(array $author): string
{
    $path = authorPublicRequestPath($author);
    return $path !== '' ? siteUrl($path) : '';
}

function authorIndexUrl(): string
{
    return siteUrl(authorIndexRequestPath());
}

function authorAvatarUrl(array $author): string
{
    $avatarFile = trim((string)($author['author_avatar'] ?? ''));
    if ($avatarFile === '') {
        return '';
    }

    return BASE_URL . '/uploads/authors/' . rawurlencode($avatarFile);
}

function hydrateAuthorPresentation(array $author): array
{
    $author['author_display_name'] = authorDisplayName($author);
    $author['author_public_path'] = authorPublicPath($author);
    $author['author_public_url'] = authorPublicUrl($author);
    $author['author_avatar_url'] = authorAvatarUrl($author);
    $author['author_website_url'] = normalizeAuthorWebsite((string)($author['author_website'] ?? ''));
    return $author;
}

function hydrateNewsPresentation(array $news): array
{
    $news['title'] = newsTitleCandidate((string)($news['title'] ?? ''), (string)($news['content'] ?? ''));
    $news['slug'] = newsSlug((string)($news['slug'] ?? ''));
    $news['excerpt'] = newsExcerpt((string)($news['content'] ?? ''));
    $news['public_path'] = newsPublicPath($news);
    $news['public_url'] = newsPublicUrl($news);

    if (array_key_exists('author_public_enabled', $news) || array_key_exists('author_slug', $news) || array_key_exists('author_name', $news)) {
        $news = hydrateAuthorPresentation($news);
    }

    return $news;
}

function hydratePodcastShowPresentation(array $show): array
{
    $show['slug'] = podcastShowSlug((string)($show['slug'] ?? ''));
    $show['website_url'] = normalizePodcastWebsiteUrl((string)($show['website_url'] ?? ''));
    $show['cover_url'] = podcastCoverUrl($show);
    $show['public_path'] = podcastShowPublicPath($show);
    $show['public_url'] = podcastShowPublicUrl($show);
    $show['description_plain'] = normalizePlainText((string)($show['description'] ?? ''));
    return $show;
}

function hydratePodcastEpisodePresentation(array $episode): array
{
    $episode['slug'] = podcastEpisodeSlug((string)($episode['slug'] ?? ''));
    $episode['audio_url'] = normalizePodcastEpisodeAudioUrl((string)($episode['audio_url'] ?? ''));
    $episode['excerpt'] = podcastEpisodeExcerpt($episode);
    $episode['public_path'] = podcastEpisodePublicPath($episode);
    $episode['public_url'] = podcastEpisodePublicUrl($episode);
    $episode['audio_src'] = podcastEpisodeAudioUrl($episode);
    $displayDate = trim((string)($episode['publish_at'] ?? ''));
    if ($displayDate === '') {
        $displayDate = trim((string)($episode['created_at'] ?? ''));
    }
    $episode['display_date'] = $displayDate;
    $episode['is_scheduled'] = trim((string)($episode['publish_at'] ?? '')) !== ''
        && strtotime((string)$episode['publish_at']) > time();
    return $episode;
}

function hydrateFaqPresentation(array $faq): array
{
    $faq['question'] = trim((string)($faq['question'] ?? ''));
    $faq['slug'] = faqSlug((string)($faq['slug'] ?? ''));
    $faq['excerpt'] = faqExcerpt($faq);
    $faq['public_path'] = faqPublicPath($faq);
    $faq['public_url'] = faqPublicUrl($faq);
    $faq['status'] = (string)($faq['status'] ?? ((int)($faq['is_published'] ?? 1) === 1 ? 'published' : 'pending'));
    $faq['is_publicly_visible'] = $faq['status'] === 'published' && (int)($faq['is_published'] ?? 1) === 1;
    return $faq;
}

function hydratePollPresentation(array $poll): array
{
    $poll['question'] = trim((string)($poll['question'] ?? ''));
    $poll['slug'] = pollSlug((string)($poll['slug'] ?? ''));
    $poll['excerpt'] = pollExcerpt($poll);
    $poll['public_path'] = pollPublicPath($poll);
    $poll['public_url'] = pollPublicUrl($poll);

    $status = (string)($poll['status'] ?? 'active');
    $nowTimestamp = time();
    $startAt = trim((string)($poll['start_date'] ?? ''));
    $endAt = trim((string)($poll['end_date'] ?? ''));
    $startTimestamp = $startAt !== '' ? strtotime($startAt) : false;
    $endTimestamp = $endAt !== '' ? strtotime($endAt) : false;

    if ($status === 'closed' || ($endTimestamp !== false && $endTimestamp <= $nowTimestamp)) {
        $poll['state'] = 'closed';
        $poll['state_label'] = 'Uzavřená';
    } elseif ($startTimestamp !== false && $startTimestamp > $nowTimestamp) {
        $poll['state'] = 'scheduled';
        $poll['state_label'] = 'Naplánovaná';
    } else {
        $poll['state'] = 'active';
        $poll['state_label'] = 'Aktivní';
    }

    return $poll;
}

function galleryAlbumExcerpt(array $album, int $limit = 220): string
{
    $explicitExcerpt = normalizePlainText((string)($album['description'] ?? ''));
    if ($explicitExcerpt === '') {
        return '';
    }

    return mb_strimwidth($explicitExcerpt, 0, $limit, '...', 'UTF-8');
}

function galleryPhotoLabel(array $photo): string
{
    $title = trim((string)($photo['title'] ?? ''));
    if ($title !== '') {
        return $title;
    }

    $filename = pathinfo((string)($photo['filename'] ?? ''), PATHINFO_FILENAME);
    $filename = preg_replace('/[_-]+/u', ' ', $filename);
    $filename = trim((string)$filename);
    if ($filename !== '') {
        return $filename;
    }

    return 'Fotografie';
}

function hydrateGalleryAlbumPresentation(array $album): array
{
    $album['name'] = trim((string)($album['name'] ?? ''));
    if ($album['name'] === '') {
        $album['name'] = 'Album';
    }
    $album['slug'] = galleryAlbumSlug((string)($album['slug'] ?? ''));
    $album['excerpt'] = galleryAlbumExcerpt($album);
    $album['public_path'] = galleryAlbumPublicPath($album);
    $album['public_url'] = galleryAlbumPublicUrl($album);
    if (!isset($album['cover_url']) && !empty($album['id'])) {
        $album['cover_url'] = gallery_cover_url((int)$album['id']);
    }
    return $album;
}

function hydrateGalleryPhotoPresentation(array $photo): array
{
    $photo['slug'] = galleryPhotoSlug((string)($photo['slug'] ?? ''));
    $photo['label'] = galleryPhotoLabel($photo);
    $photo['public_path'] = galleryPhotoPublicPath($photo);
    $photo['public_url'] = galleryPhotoPublicUrl($photo);
    if (!isset($photo['image_url'])) {
        $photo['image_url'] = BASE_URL . '/uploads/gallery/' . rawurlencode((string)($photo['filename'] ?? ''));
    }
    if (!isset($photo['thumb_url'])) {
        $photo['thumb_url'] = BASE_URL . '/uploads/gallery/thumbs/' . rawurlencode((string)($photo['filename'] ?? ''));
    }
    return $photo;
}

function fetchPublicAuthorBySlug(PDO $pdo, string $slug): ?array
{
    $normalizedSlug = authorSlug($slug);
    if ($normalizedSlug === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, email, first_name, last_name, nickname, role,
                author_public_enabled, author_slug, author_bio, author_avatar, author_website
         FROM cms_users
         WHERE author_slug = ? AND author_public_enabled = 1 AND role != 'public'
         LIMIT 1"
    );
    $stmt->execute([$normalizedSlug]);
    $author = $stmt->fetch();

    return $author ? hydrateAuthorPresentation($author) : null;
}

function fetchPublicAuthorById(PDO $pdo, int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, email, first_name, last_name, nickname, role,
                author_public_enabled, author_slug, author_bio, author_avatar, author_website
         FROM cms_users
         WHERE id = ? AND author_public_enabled = 1 AND role != 'public'
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $author = $stmt->fetch();

    return $author ? hydrateAuthorPresentation($author) : null;
}

function fetchPublicAuthors(PDO $pdo): array
{
    $authors = $pdo->query(
        "SELECT u.id, u.email, u.first_name, u.last_name, u.nickname, u.role, u.is_superadmin,
                u.author_public_enabled, u.author_slug, u.author_bio, u.author_avatar, u.author_website,
                COUNT(a.id) AS article_count,
                MAX(COALESCE(a.publish_at, a.created_at)) AS latest_article_at
         FROM cms_users u
         LEFT JOIN cms_articles a
           ON a.author_id = u.id
          AND a.status = 'published'
          AND (a.publish_at IS NULL OR a.publish_at <= NOW())
         WHERE u.author_public_enabled = 1
           AND u.role != 'public'
         GROUP BY u.id, u.email, u.first_name, u.last_name, u.nickname, u.role, u.is_superadmin,
                  u.author_public_enabled, u.author_slug, u.author_bio, u.author_avatar, u.author_website
         ORDER BY COUNT(a.id) DESC, latest_article_at DESC, u.is_superadmin DESC, u.id ASC"
    )->fetchAll();

    return array_map(
        static function (array $author): array {
            $author['article_count'] = (int)($author['article_count'] ?? 0);
            return hydrateAuthorPresentation($author);
        },
        $authors
    );
}

function resolveHomeAuthor(PDO $pdo): ?array
{
    $selectedAuthorId = (int)getSetting('home_author_user_id', '0');
    if ($selectedAuthorId > 0) {
        return fetchPublicAuthorById($pdo, $selectedAuthorId);
    }

    $authors = $pdo->query(
        "SELECT id, email, first_name, last_name, nickname, role,
                author_public_enabled, author_slug, author_bio, author_avatar, author_website
         FROM cms_users
         WHERE author_public_enabled = 1 AND role != 'public'
         ORDER BY is_superadmin DESC, id ASC
         LIMIT 2"
    )->fetchAll();

    if (count($authors) !== 1) {
        return null;
    }

    return hydrateAuthorPresentation($authors[0]);
}

function articleCountLabel(int $count): string
{
    $count = max(0, $count);
    if ($count === 1) {
        return '1 článek';
    }

    $mod100 = $count % 100;
    $mod10 = $count % 10;
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
        return $count . ' články';
    }

    return $count . ' článků';
}

function deleteAuthorAvatarFile(string $filename): void
{
    $safeFilename = basename(trim($filename));
    if ($safeFilename === '') {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/authors/' . $safeFilename;
    if (is_file($path)) {
        unlink($path);
    }
}

function storeUploadedAuthorAvatar(array $file, string $existingFilename = ''): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE || trim((string)($file['name'] ?? '')) === '') {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Avatar se nepodařilo nahrát.',
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Avatar se nepodařilo zpracovat.',
        ];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    if (!isset($allowedTypes[$mimeType])) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Avatar musí být ve formátu JPEG, PNG, GIF, WebP nebo SVG.',
        ];
    }

    $directory = dirname(__DIR__) . '/uploads/authors/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Adresář pro avatary se nepodařilo vytvořit.',
        ];
    }

    $filename = uniqid('author_', true) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($tmpPath, $directory . $filename)) {
        return [
            'filename' => $existingFilename,
            'uploaded' => false,
            'error' => 'Avatar se nepodařilo uložit.',
        ];
    }

    if ($existingFilename !== '' && $existingFilename !== $filename) {
        deleteAuthorAvatarFile($existingFilename);
    }

    return [
        'filename' => $filename,
        'uploaded' => true,
        'error' => '',
    ];
}

// ─────────────────────────────── Galerie ──────────────────────────────────
