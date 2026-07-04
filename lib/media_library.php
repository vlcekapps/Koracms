<?php

// Sdílené helpery pro knihovnu médií.

/**
 * @return array<string,string>
 */
function mediaVisibilityOptions(): array
{
    return [
        'public' => 'Veřejné',
        'private' => 'Soukromé',
    ];
}

function normalizeMediaVisibility(string $value): string
{
    $value = strtolower(trim($value));
    return array_key_exists($value, mediaVisibilityOptions()) ? $value : 'public';
}

function normalizeMediaLicenseUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return normalizeHttpExternalUrl($value, false);
}

function normalizeMediaCollectionSlug(string $value): string
{
    $slug = slugify($value);
    return $slug !== '' ? $slug : 'kolekce';
}

function mediaCollectionSlugExists(PDO $pdo, string $slug, int $ignoreId = 0): bool
{
    $slug = normalizeMediaCollectionSlug($slug);
    $sql = 'SELECT COUNT(*) FROM cms_media_collections WHERE slug = ?';
    $params = [$slug];
    if ($ignoreId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function uniqueMediaCollectionSlug(PDO $pdo, string $base, int $ignoreId = 0): string
{
    $baseSlug = normalizeMediaCollectionSlug($base);
    $slug = $baseSlug;
    $suffix = 2;
    while (mediaCollectionSlugExists($pdo, $slug, $ignoreId)) {
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }

    return $slug;
}

/**
 * @return list<array<string,mixed>>
 */
function mediaCollectionOptions(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, name, slug, description, default_visibility, default_credit,
                default_license_label, default_license_url, sort_order, created_at, updated_at
         FROM cms_media_collections
         ORDER BY sort_order ASC, name ASC, id ASC"
    );

    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * @return array<string,mixed>|null
 */
function mediaCollectionById(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, name, slug, description, default_visibility, default_credit,
                default_license_label, default_license_url, sort_order, created_at, updated_at
         FROM cms_media_collections
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

/**
 * @param array<string,mixed> $media
 */
function mediaMetadataStatus(array $media): string
{
    $isImage = mediaIsImageMime((string)($media['mime_type'] ?? '')) && !mediaIsSvgMime((string)($media['mime_type'] ?? ''));
    $missingAlt = $isImage && trim((string)($media['alt_text'] ?? '')) === '';
    $missingCreditOrLicense = trim((string)($media['credit'] ?? '')) === ''
        || trim((string)($media['license_label'] ?? '')) === '';

    if ($missingAlt && $missingCreditOrLicense) {
        return 'incomplete';
    }
    if ($missingAlt) {
        return 'missing_alt';
    }
    if ($missingCreditOrLicense) {
        return 'missing_credit_license';
    }

    return 'complete';
}

/**
 * @param array<string,mixed> $media
 */
function mediaMetadataStatusLabel(array $media): string
{
    return match (mediaMetadataStatus($media)) {
        'missing_alt' => 'Chybí alt text',
        'missing_credit_license' => 'Chybí kredit nebo licence',
        'incomplete' => 'Neúplná metadata',
        default => 'Metadata vyplněna',
    };
}

/**
 * @return array<string,string>
 */
function mediaAllowedMimeMap(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'audio/flac' => 'flac',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv',
        'video/quicktime' => 'mov',
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
        'text/vtt' => 'vtt',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];
}

function mediaMaxFileSizeBytes(): int
{
    return 10 * 1024 * 1024;
}

function mediaMimeFamily(string $mimeType): string
{
    $mimeType = strtolower(trim($mimeType));
    if ($mimeType === '') {
        return 'file';
    }

    if (str_starts_with($mimeType, 'image/')) {
        return 'image';
    }
    if (str_starts_with($mimeType, 'audio/')) {
        return 'audio';
    }
    if (str_starts_with($mimeType, 'video/')) {
        return 'video';
    }
    if (str_starts_with($mimeType, 'text/')) {
        return 'text';
    }
    if (str_starts_with($mimeType, 'application/')) {
        return 'application';
    }

    return 'file';
}

function mediaIsSvgMime(string $mimeType): bool
{
    return strtolower(trim($mimeType)) === 'image/svg+xml';
}

function mediaIsPdfMime(string $mimeType): bool
{
    return strtolower(trim($mimeType)) === 'application/pdf';
}

function mediaIsImageMime(string $mimeType): bool
{
    return str_starts_with(strtolower(trim($mimeType)), 'image/');
}

function mediaIsPreviewableImageMime(string $mimeType): bool
{
    return mediaIsImageMime($mimeType) && !mediaIsSvgMime($mimeType);
}

function mediaExtensionForMime(string $mimeType): string
{
    $map = mediaAllowedMimeMap();
    $mimeType = strtolower(trim($mimeType));
    return $map[$mimeType] ?? 'bin';
}

function mediaSanitizeExtension(string $originalName, string $mimeType): string
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension !== '' && preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1) {
        return $extension;
    }

    return mediaExtensionForMime($mimeType);
}

/**
 * @param array<string,mixed> $media
 */
function mediaIsPublic(array $media): bool
{
    return normalizeMediaVisibility((string)($media['visibility'] ?? 'public')) === 'public';
}

/**
 * @param array<string,mixed> $media
 */
function mediaUsesProtectedFileEndpoint(array $media): bool
{
    return !mediaIsPublic($media) || mediaIsSvgMime((string)($media['mime_type'] ?? ''));
}

/**
 * @param array<string,mixed> $media
 */
function mediaCanPreviewImage(array $media): bool
{
    return mediaIsPreviewableImageMime((string)($media['mime_type'] ?? ''));
}

/**
 * @param array<string,mixed> $media
 */
function mediaCanPreviewPdf(array $media): bool
{
    return mediaIsPublic($media) && mediaIsPdfMime((string)($media['mime_type'] ?? ''));
}

/**
 * @param array<string,mixed> $media
 */
function mediaStoredFilename(array $media): string
{
    return basename(trim((string)($media['filename'] ?? '')));
}

/**
 * @param array<string,mixed> $media
 */
function mediaOriginalName(array $media): string
{
    $originalName = trim((string)($media['original_name'] ?? ''));
    if ($originalName !== '') {
        return $originalName;
    }

    return mediaStoredFilename($media);
}

function mediaPublicDirectoryPath(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'media';
}

function mediaPublicThumbDirectoryPath(): string
{
    return mediaPublicDirectoryPath() . DIRECTORY_SEPARATOR . 'thumbs';
}

function mediaPrivateDirectoryPath(): string
{
    return koraStoragePath('media' . DIRECTORY_SEPARATOR . 'files');
}

function mediaPrivateThumbDirectoryPath(): string
{
    return koraStoragePath('media' . DIRECTORY_SEPARATOR . 'thumbs');
}

function mediaEnsureDirectories(string $visibility): bool
{
    if (!koraEnsureDirectory(mediaPublicDirectoryPath())) {
        return false;
    }

    if (!koraEnsureDirectory(mediaPublicThumbDirectoryPath())) {
        return false;
    }

    if (normalizeMediaVisibility($visibility) === 'private') {
        if (!koraEnsureDirectory(mediaPrivateDirectoryPath())) {
            return false;
        }
        if (!koraEnsureDirectory(mediaPrivateThumbDirectoryPath())) {
            return false;
        }
    }

    return true;
}

/**
 * @param array<string,mixed> $media
 */
function mediaOriginalPath(array $media, ?string $visibilityOverride = null, ?string $filenameOverride = null): string
{
    $visibility = normalizeMediaVisibility($visibilityOverride ?? (string)($media['visibility'] ?? 'public'));
    $filename = basename($filenameOverride ?? mediaStoredFilename($media));
    if ($filename === '') {
        return '';
    }

    $directory = $visibility === 'private' ? mediaPrivateDirectoryPath() : mediaPublicDirectoryPath();
    return $directory . DIRECTORY_SEPARATOR . $filename;
}

/**
 * @param array<string,mixed> $media
 */
function mediaThumbPath(array $media, ?string $visibilityOverride = null, ?string $filenameOverride = null): string
{
    if (!mediaCanPreviewImage($media)) {
        return '';
    }

    $visibility = normalizeMediaVisibility($visibilityOverride ?? (string)($media['visibility'] ?? 'public'));
    $filename = basename($filenameOverride ?? mediaStoredFilename($media));
    if ($filename === '') {
        return '';
    }

    $directory = $visibility === 'private' ? mediaPrivateThumbDirectoryPath() : mediaPublicThumbDirectoryPath();
    return $directory . DIRECTORY_SEPARATOR . $filename;
}

function mediaWebpPath(string $path): string
{
    return preg_replace('/\.[a-z0-9]+$/i', '.webp', $path) ?: ($path . '.webp');
}

/**
 * @param array<string,mixed> $context
 */
function mediaLogFilesystemFailure(string $operation, string $path, array $context = []): void
{
    koraLog('warning', 'media filesystem operation failed', array_merge([
        'operation' => $operation,
        'path_hash' => hash('sha256', str_replace('\\', '/', $path)),
        'file_extension' => strtolower((string)pathinfo($path, PATHINFO_EXTENSION)),
    ], $context));
}

/**
 * @param callable(): bool $operation
 */
function mediaRunFilesystemOperation(callable $operation): bool
{
    set_error_handler(static fn (): bool => true);
    try {
        return (bool)$operation();
    } finally {
        restore_error_handler();
    }
}

/**
 * @param array<string,mixed> $context
 */
function mediaDeleteFile(string $path, string $operation = 'delete', array $context = []): bool
{
    if ($path === '' || !is_file($path)) {
        return true;
    }

    if (mediaRunFilesystemOperation(static fn (): bool => unlink($path))) {
        return true;
    }

    mediaLogFilesystemFailure($operation, $path, $context);
    return false;
}

function mediaMoveFile(string $sourcePath, string $targetPath): bool
{
    if ($sourcePath === '' || $targetPath === '' || !is_file($sourcePath)) {
        return false;
    }

    if ($sourcePath === $targetPath) {
        return true;
    }

    $targetDirectory = dirname($targetPath);
    if (!koraEnsureDirectory($targetDirectory)) {
        return false;
    }

    if (mediaRunFilesystemOperation(static fn (): bool => rename($sourcePath, $targetPath))) {
        return true;
    }

    if (mediaRunFilesystemOperation(static fn (): bool => copy($sourcePath, $targetPath))) {
        mediaDeleteFile($sourcePath, 'move_source_cleanup', [
            'target_path_hash' => hash('sha256', str_replace('\\', '/', $targetPath)),
            'target_file_extension' => strtolower((string)pathinfo($targetPath, PATHINFO_EXTENSION)),
        ]);
        return true;
    }

    mediaLogFilesystemFailure('move', $targetPath, [
        'source_path_hash' => hash('sha256', str_replace('\\', '/', $sourcePath)),
        'source_file_extension' => strtolower((string)pathinfo($sourcePath, PATHINFO_EXTENSION)),
    ]);

    return false;
}

/**
 * @param array<string,mixed> $media
 */
function mediaDeleteDerivedFiles(array $media, ?string $visibilityOverride = null, ?string $filenameOverride = null): void
{
    $originalPath = mediaOriginalPath($media, $visibilityOverride, $filenameOverride);
    if ($originalPath !== '') {
        mediaDeleteFile(mediaWebpPath($originalPath), 'delete_derived_webp');
    }

    $thumbPath = mediaThumbPath($media, $visibilityOverride, $filenameOverride);
    if ($thumbPath !== '') {
        mediaDeleteFile($thumbPath, 'delete_thumb');
        mediaDeleteFile(mediaWebpPath($thumbPath), 'delete_thumb_webp');
    }
}

/**
 * @param array<string,mixed> $media
 */
function mediaDeletePhysicalFiles(array $media, ?string $visibilityOverride = null, ?string $filenameOverride = null): void
{
    mediaDeleteDerivedFiles($media, $visibilityOverride, $filenameOverride);

    $originalPath = mediaOriginalPath($media, $visibilityOverride, $filenameOverride);
    if ($originalPath !== '') {
        mediaDeleteFile($originalPath, 'delete_original');
    }
}

/**
 * @param array<string,mixed> $media
 */
function mediaRebuildDerivedFiles(array $media): bool
{
    mediaDeleteDerivedFiles($media);

    if (!mediaCanPreviewImage($media)) {
        return true;
    }

    $originalPath = mediaOriginalPath($media);
    $thumbPath = mediaThumbPath($media);
    if ($originalPath === '' || $thumbPath === '' || !is_file($originalPath)) {
        return false;
    }

    if (!koraEnsureDirectory(dirname($thumbPath))) {
        return false;
    }

    if (!gallery_make_thumb($originalPath, $thumbPath, 300)) {
        return false;
    }

    if (mediaIsPublic($media)) {
        generateWebp($originalPath);
        generateWebp($thumbPath);
    }

    return true;
}

function mediaCanonicalStoredFilename(string $originalName, string $mimeType): string
{
    return uniqid('m_', true) . '.' . mediaSanitizeExtension($originalName, $mimeType);
}

function mediaUploadErrorMessage(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Soubor je příliš velký.',
        UPLOAD_ERR_PARTIAL => 'Soubor se nepodařilo nahrát celý.',
        UPLOAD_ERR_NO_FILE => 'Nebyl vybrán žádný soubor.',
        default => 'Soubor se nepodařilo nahrát.',
    };
}

/**
 * @param array<string,mixed> $file
 * @param array<string,mixed>|null $existingMedia
 * @return array{ok:bool,error?:string,filename?:string,original_name?:string,mime_type?:string,file_size?:int}
 */
function mediaStoreUploadedFile(array $file, string $visibility = 'public', ?array $existingMedia = null): array
{
    $upload = koraInspectUploadedFile($file, [
        'no_file_error' => 'Nebyl vybrán žádný soubor.',
        'upload_error' => mediaUploadErrorMessage((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)),
        'invalid_upload_error' => 'Soubor se nepodařilo zpracovat.',
        'too_large_error' => 'Soubor překračuje maximální velikost 10 MB.',
        'max_bytes' => mediaMaxFileSizeBytes(),
        'reject_svg' => true,
        'svg_error' => 'SVG soubory už knihovna médií nepřijímá. Nahrajte prosím PNG, JPG, WebP nebo jiný podporovaný formát.',
        'allowed_mime_map' => mediaAllowedMimeMap(),
        'unsupported_type_error' => 'Tento typ souboru není v knihovně médií podporovaný.',
    ]);
    if (empty($upload['ok'])) {
        return [
            'ok' => false,
            'error' => (string)($upload['error'] ?? 'Soubor se nepodařilo nahrát.'),
        ];
    }

    $mimeType = (string)$upload['mime_type'];
    $originalName = (string)$upload['original_name'];
    $fileSize = (int)$upload['file_size'];
    $visibility = normalizeMediaVisibility($visibility);
    if (!mediaEnsureDirectories($visibility)) {
        return [
            'ok' => false,
            'error' => 'Adresáře pro knihovnu médií se nepodařilo připravit.',
        ];
    }

    $existingFilename = '';
    $existingVisibility = $visibility;
    if (is_array($existingMedia)) {
        $existingFilename = mediaStoredFilename($existingMedia);
        $existingVisibility = normalizeMediaVisibility((string)($existingMedia['visibility'] ?? 'public'));
        $existingMimeType = (string)($existingMedia['mime_type'] ?? '');
        if ($existingMimeType !== '' && mediaMimeFamily($existingMimeType) !== mediaMimeFamily($mimeType)) {
            return [
                'ok' => false,
                'error' => 'Náhradní soubor musí zůstat ve stejné rodině typu jako původní médium.',
            ];
        }

        $oldExtension = strtolower(pathinfo($existingFilename, PATHINFO_EXTENSION));
        $newExtension = mediaSanitizeExtension($originalName, $mimeType);
        if ($existingFilename !== '' && $existingVisibility === 'public' && !mediaIsSvgMime($existingMimeType) && $oldExtension !== '' && $oldExtension !== $newExtension) {
            return [
                'ok' => false,
                'error' => 'Veřejný soubor lze nahradit jen variantou se stejnou příponou, aby zůstaly funkční stávající odkazy.',
            ];
        }
    }

    $filename = mediaCanonicalStoredFilename($originalName, $mimeType);
    if ($existingFilename !== '') {
        $existingMimeType = (string)($existingMedia['mime_type'] ?? '');
        $existingDirectPublic = $existingVisibility === 'public' && !mediaIsSvgMime($existingMimeType);
        if ($existingDirectPublic) {
            $filename = $existingFilename;
        } else {
            $newExtension = mediaSanitizeExtension($originalName, $mimeType);
            $oldExtension = strtolower(pathinfo($existingFilename, PATHINFO_EXTENSION));
            if ($oldExtension !== '' && $oldExtension === $newExtension) {
                $filename = $existingFilename;
            }
        }
    }

    $targetPath = mediaOriginalPath(
        [
            'filename' => $filename,
            'visibility' => $visibility,
            'mime_type' => $mimeType,
        ]
    );
    if ($targetPath === '') {
        return [
            'ok' => false,
            'error' => 'Soubor se nepodařilo uložit.',
        ];
    }

    mediaDeleteFile($targetPath, 'replace_existing');

    $storedUpload = koraStoreInspectedUpload($upload, dirname($targetPath), basename($targetPath), [
        'move_error' => 'Soubor se nepodařilo uložit.',
    ]);
    if (empty($storedUpload['ok'])) {
        return [
            'ok' => false,
            'error' => (string)($storedUpload['error'] ?? 'Soubor se nepodařilo uložit.'),
        ];
    }

    $record = [
        'id' => (int)($existingMedia['id'] ?? 0),
        'filename' => $filename,
        'mime_type' => $mimeType,
        'visibility' => $visibility,
    ];

    if (!mediaRebuildDerivedFiles($record)) {
        mediaDeletePhysicalFiles($record);
        return [
            'ok' => false,
            'error' => 'Náhled souboru se nepodařilo připravit.',
        ];
    }

    if (is_array($existingMedia)) {
        $oldFilename = mediaStoredFilename($existingMedia);
        $oldVisibility = normalizeMediaVisibility((string)($existingMedia['visibility'] ?? 'public'));
        if ($oldFilename !== '' && ($oldFilename !== $filename || $oldVisibility !== $visibility)) {
            mediaDeletePhysicalFiles($existingMedia, $oldVisibility, $oldFilename);
        }
    }

    return [
        'ok' => true,
        'filename' => $filename,
        'original_name' => $originalName,
        'mime_type' => $mimeType,
        'file_size' => $fileSize,
    ];
}

/**
 * @param array<string,mixed> $media
 * @return array{ok:bool,error?:string}
 */
function mediaSwitchVisibility(array $media, string $newVisibility): array
{
    $currentVisibility = normalizeMediaVisibility((string)($media['visibility'] ?? 'public'));
    $newVisibility = normalizeMediaVisibility($newVisibility);
    if ($currentVisibility === $newVisibility) {
        return ['ok' => true];
    }

    if (!mediaEnsureDirectories($newVisibility)) {
        return [
            'ok' => false,
            'error' => 'Adresáře pro cílovou viditelnost se nepodařilo připravit.',
        ];
    }

    $sourcePath = mediaOriginalPath($media, $currentVisibility);
    $targetPath = mediaOriginalPath($media, $newVisibility);
    if ($sourcePath === '' || $targetPath === '' || !is_file($sourcePath)) {
        return [
            'ok' => false,
            'error' => 'Původní soubor knihovny médií chybí, takže ho nelze přesunout.',
        ];
    }

    if (!mediaMoveFile($sourcePath, $targetPath)) {
        return [
            'ok' => false,
            'error' => 'Soubor se nepodařilo přesunout do nového úložiště.',
        ];
    }

    if (mediaCanPreviewImage($media)) {
        $sourceThumb = mediaThumbPath($media, $currentVisibility);
        $targetThumb = mediaThumbPath($media, $newVisibility);
        if ($sourceThumb !== '' && is_file($sourceThumb) && $targetThumb !== '') {
            mediaMoveFile($sourceThumb, $targetThumb);
        }
    }

    mediaDeleteDerivedFiles($media, $currentVisibility);

    $updatedMedia = $media;
    $updatedMedia['visibility'] = $newVisibility;
    if (!mediaRebuildDerivedFiles($updatedMedia)) {
        return [
            'ok' => false,
            'error' => 'Soubor se přesunul, ale nepodařilo se znovu připravit jeho náhled.',
        ];
    }

    return ['ok' => true];
}

/**
 * @param array<string,mixed> $media
 */
function mediaFileUrl(array $media): string
{
    $id = (int)($media['id'] ?? 0);
    if ($id <= 0) {
        return BASE_URL . '/';
    }

    if (mediaUsesProtectedFileEndpoint($media)) {
        return BASE_URL . '/media/file.php?id=' . $id;
    }

    return BASE_URL . '/uploads/media/' . rawurlencode(mediaStoredFilename($media));
}

/**
 * @param array<string,mixed> $media
 */
function mediaPreviewUrl(array $media): string
{
    $id = (int)($media['id'] ?? 0);
    if ($id <= 0 || !mediaCanPreviewPdf($media)) {
        return mediaFileUrl($media);
    }

    return BASE_URL . '/media/preview.php?id=' . $id;
}

/**
 * @param array<string,mixed> $media
 */
function mediaThumbUrl(array $media): string
{
    if (!mediaCanPreviewImage($media)) {
        return '';
    }

    $id = (int)($media['id'] ?? 0);
    if ($id <= 0) {
        return '';
    }

    if (!mediaIsPublic($media)) {
        return BASE_URL . '/media/thumb.php?id=' . $id;
    }

    return BASE_URL . '/uploads/media/thumbs/' . rawurlencode(mediaStoredFilename($media));
}

/**
 * @param array<string,mixed> $media
 */
function mediaDisplayKind(array $media): string
{
    return match (mediaMimeFamily((string)($media['mime_type'] ?? ''))) {
        'image' => mediaIsSvgMime((string)($media['mime_type'] ?? '')) ? 'file' : 'image',
        'audio' => 'audio',
        'video' => 'video',
        default => 'file',
    };
}

/**
 * @return list<array{
 *   table:string,
 *   id_column:string,
 *   title_sql:string,
 *   columns:list<string>,
 *   label:string,
 *   admin_path:callable(array<string,mixed>):string
 * }>
 */
function mediaUsageSearchDefinitions(): array
{
    return [
        [
            'table' => 'cms_pages',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(title,''), CONCAT('Stránka #', id))",
            'columns' => ['content'],
            'label' => 'Stránka',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/page_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_articles',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(title,''), CONCAT('Článek #', id))",
            'columns' => ['perex', 'content'],
            'label' => 'Článek blogu',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/blog_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_news',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(title,''), CONCAT('Novinka #', id))",
            'columns' => ['content', 'meta_description'],
            'label' => 'Novinka',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/news_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_events',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(title,''), CONCAT('Událost #', id))",
            'columns' => ['excerpt', 'description', 'program_note', 'accessibility_note'],
            'label' => 'Událost',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/event_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_faqs',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(question,''), CONCAT('FAQ #', id))",
            'columns' => ['excerpt', 'answer', 'meta_description'],
            'label' => 'FAQ',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/faq_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_downloads',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(title,''), CONCAT('Download #', id))",
            'columns' => ['excerpt', 'description', 'requirements'],
            'label' => 'Ke stažení',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/download_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_places',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(name,''), CONCAT('Místo #', id))",
            'columns' => ['excerpt', 'description', 'meta_description'],
            'label' => 'Zajímavé místo',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/place_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_board',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(title,''), CONCAT('Oznámení #', id))",
            'columns' => ['excerpt', 'description'],
            'label' => 'Vývěska',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/board_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_food_cards',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(title,''), CONCAT('Lístek #', id))",
            'columns' => ['description', 'content'],
            'label' => 'Jídelní lístek',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/food_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_forms',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(title,''), CONCAT('Formulář #', id))",
            'columns' => ['description', 'success_message', 'submitter_confirmation_message'],
            'label' => 'Formulář',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/form_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_podcast_shows',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(title,''), CONCAT('Podcast #', id))",
            'columns' => ['description', 'subtitle'],
            'label' => 'Podcast',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/podcast_show_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_podcasts',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(title,''), CONCAT('Epizoda podcastu #', id))",
            'columns' => ['description', 'subtitle'],
            'label' => 'Epizoda podcastu',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/podcast_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_widgets',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(title,''), CONCAT(widget_type, ' #', id))",
            'columns' => ['settings'],
            'label' => 'Widget',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/widgets.php',
        ],
        [
            'table' => 'cms_settings',
            'id_column' => 'id',
            'title_sql' => "`key`",
            'columns' => ['value'],
            'label' => 'Nastavení',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/settings.php',
        ],
        [
            'table' => 'cms_blogs',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(name,''), CONCAT('Blog #', id))",
            'columns' => ['description', 'intro_content', 'meta_description'],
            'label' => 'Blog',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/blogs.php?edit=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_gallery_albums',
            'id_column' => 'id',
            'title_sql' => "COALESCE(NULLIF(name,''), CONCAT('Album #', id))",
            'columns' => ['description'],
            'label' => 'Fotogalerie',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/gallery_album_form.php?id=' . (int)$row['id'],
        ],
    ];
}

function mediaTableExists(string $tableName): bool
{
    static $cache = [];
    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$tableName]);
        $cache[$tableName] = (int)$stmt->fetchColumn() > 0;
    } catch (\PDOException) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

function mediaColumnExists(string $tableName, string $columnName): bool
{
    static $cache = [];
    $cacheKey = $tableName . '.' . $columnName;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$tableName, $columnName]);
        $cache[$cacheKey] = (int)$stmt->fetchColumn() > 0;
    } catch (\PDOException) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

/**
 * @param array<string,mixed> $media
 * @return list<string>
 */
function mediaUsageNeedles(array $media): array
{
    $needles = [];
    $id = (int)($media['id'] ?? 0);
    $filename = mediaStoredFilename($media);
    if ($id > 0) {
        $needles[] = '/media/file.php?id=' . $id;
        $needles[] = '/media/thumb.php?id=' . $id;
    }
    if ($filename !== '') {
        $encodedFilename = rawurlencode($filename);
        $needles[] = '/uploads/media/' . $filename;
        $needles[] = '/uploads/media/' . $encodedFilename;
        $needles[] = '/uploads/media/thumbs/' . $filename;
        $needles[] = '/uploads/media/thumbs/' . $encodedFilename;
    }

    return array_values(array_unique($needles));
}

/**
 * @param array<string,mixed> $media
 * @return list<array{label:string,title:string,admin_url:string}>
 */
function mediaFindUsages(array $media, int $limit = 25): array
{
    static $cache = [];

    $mediaId = (int)($media['id'] ?? 0);
    if ($mediaId <= 0) {
        return [];
    }

    if (isset($cache[$mediaId])) {
        $all = $cache[$mediaId];
        return $limit > 0 ? array_slice($all, 0, $limit) : $all;
    }

    $needles = mediaUsageNeedles($media);
    if ($needles === []) {
        $cache[$mediaId] = [];
        return [];
    }

    $usages = [];
    $pdo = db_connect();

    foreach (mediaUsageSearchDefinitions() as $definition) {
        $tableName = (string)$definition['table'];
        if (!mediaTableExists($tableName)) {
            continue;
        }

        $columns = array_values(array_filter(
            $definition['columns'],
            static fn (string $column): bool => mediaColumnExists($tableName, $column)
        ));
        if ($columns === []) {
            continue;
        }

        $whereParts = [];
        $params = [];
        foreach ($columns as $columnName) {
            $columnParts = [];
            foreach ($needles as $needle) {
                $columnParts[] = "{$columnName} LIKE ?";
                $params[] = '%' . $needle . '%';
            }
            $whereParts[] = '(' . implode(' OR ', $columnParts) . ')';
        }

        $sql = sprintf(
            "SELECT %s AS id, %s AS title
             FROM %s
             WHERE %s
             LIMIT %d",
            $definition['id_column'],
            $definition['title_sql'],
            $tableName,
            implode(' OR ', $whereParts),
            max($limit > 0 ? $limit : 200, 1)
        );

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $row) {
                $usage = [
                    'label' => (string)$definition['label'],
                    'title' => trim((string)($row['title'] ?? '')) !== ''
                        ? trim((string)$row['title'])
                        : ((string)$definition['label'] . ' #' . (int)$row['id']),
                    'admin_url' => (string)$definition['admin_path']($row),
                ];
                $usages[] = $usage;
            }
        } catch (\PDOException $e) {
            koraLog('warning', 'media usage scan failed', [
                'media_id' => $mediaId,
                'source_table' => $tableName,
                'exception' => $e,
            ]);
        }
    }

    $cache[$mediaId] = $usages;
    return $limit > 0 ? array_slice($usages, 0, $limit) : $usages;
}

/**
 * @param array<string,mixed> $media
 */
function mediaHasUsage(array $media): bool
{
    return mediaFindUsages($media, 1) !== [];
}

function mediaFlashSet(string $type, string $message): void
{
    if ($message === '') {
        return;
    }

    if (!isset($_SESSION['media_library_flash']) || !is_array($_SESSION['media_library_flash'])) {
        $_SESSION['media_library_flash'] = [];
    }

    if (!isset($_SESSION['media_library_flash'][$type]) || !is_array($_SESSION['media_library_flash'][$type])) {
        $_SESSION['media_library_flash'][$type] = [];
    }

    $_SESSION['media_library_flash'][$type][] = $message;
}

function mediaFlashSetFieldError(string $fieldName, string $message): void
{
    $fieldName = trim($fieldName);
    if ($fieldName === '' || $message === '') {
        return;
    }

    if (!isset($_SESSION['media_library_field_errors']) || !is_array($_SESSION['media_library_field_errors'])) {
        $_SESSION['media_library_field_errors'] = [];
    }

    $_SESSION['media_library_field_errors'][$fieldName] = $message;
}

/**
 * @return array<string,string>
 */
function mediaFlashPullFieldErrors(): array
{
    $fieldErrors = $_SESSION['media_library_field_errors'] ?? [];
    unset($_SESSION['media_library_field_errors']);
    if (!is_array($fieldErrors)) {
        return [];
    }

    $result = [];
    foreach ($fieldErrors as $fieldName => $message) {
        $fieldName = trim((string)$fieldName);
        $message = trim((string)$message);
        if ($fieldName !== '' && $message !== '') {
            $result[$fieldName] = $message;
        }
    }

    return $result;
}

/**
 * @return array<string,list<string>>
 */
function mediaFlashPull(): array
{
    $flash = $_SESSION['media_library_flash'] ?? [];
    unset($_SESSION['media_library_flash']);
    return is_array($flash) ? $flash : [];
}

function mediaStaffCanAccessPrivate(): bool
{
    return currentUserHasCapability('content_manage_shared');
}

/**
 * @return array<string,mixed>|null
 */
function mediaGetById(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT *
             FROM cms_media
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $media = $stmt->fetch() ?: null;
        return $media ?: null;
    } catch (\PDOException) {
        return null;
    }
}

/**
 * @return array<string,mixed>|null
 */
function mediaGetPublicByStoredFilename(string $filename): ?array
{
    $filename = basename(trim($filename));
    if ($filename === '') {
        return null;
    }

    try {
        $stmt = db_connect()->prepare(
            "SELECT *
             FROM cms_media
             WHERE filename = ?
               AND visibility = 'public'
             LIMIT 1"
        );
        $stmt->execute([$filename]);
        $media = $stmt->fetch() ?: null;
        return $media ?: null;
    } catch (\PDOException) {
        return null;
    }
}

/**
 * @return array<string,mixed>|null
 */
function mediaGetPublicPdfByUrl(string $url): ?array
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    $host = strtolower(trim((string)(parse_url($url, PHP_URL_HOST) ?? '')));
    if ($host !== '') {
        $currentHost = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
        if ($currentHost === '' || $host !== $currentHost) {
            return null;
        }
    }

    $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
    $query = (string)(parse_url($url, PHP_URL_QUERY) ?? '');
    if ($path === '') {
        return null;
    }

    $basePath = trim((string)BASE_URL);
    if ($basePath !== '' && $path === $basePath) {
        $path = '/';
    } elseif ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
        $path = substr($path, strlen($basePath));
    }

    if ($path === '/media/file.php' || $path === '/media/preview.php') {
        parse_str($query, $params);
        $mediaId = (int)($params['id'] ?? 0);
        if ($mediaId <= 0) {
            return null;
        }

        $media = mediaGetById($mediaId);
        return ($media !== null && mediaCanPreviewPdf($media)) ? $media : null;
    }

    if (preg_match('~^/uploads/media/([^/]+\.pdf)$~i', $path, $matches) !== 1) {
        return null;
    }

    $filename = rawurldecode((string)$matches[1]);
    $media = mediaGetPublicByStoredFilename($filename);
    return ($media !== null && mediaCanPreviewPdf($media)) ? $media : null;
}

/**
 * @param array<string,mixed> $media
 */
function mediaDownloadName(array $media): string
{
    return safeDownloadName(mediaOriginalName($media), mediaStoredFilename($media));
}
