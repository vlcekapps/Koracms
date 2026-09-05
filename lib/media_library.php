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
    return koraDefaultUploadMaxSizeBytes();
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
    $aliases = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'text/plain' => ['txt', 'csv', 'vtt'],
        'audio/mp4' => ['m4a', 'mp4'],
    ];
    if (in_array($extension, $aliases[strtolower(trim($mimeType))] ?? [], true)) {
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
    $mimeType = (string)($media['mime_type'] ?? '');
    $extension = strtolower(pathinfo(mediaStoredFilename($media), PATHINFO_EXTENSION));
    return !mediaIsPublic($media) || mediaIsSvgMime($mimeType)
        || $extension === 'txt'
        || $extension !== mediaSanitizeExtension(mediaStoredFilename($media), $mimeType);
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
        if (mediaDeleteFile($sourcePath, 'move_source_cleanup', [
            'target_path_hash' => hash('sha256', str_replace('\\', '/', $targetPath)),
            'target_file_extension' => strtolower((string)pathinfo($targetPath, PATHINFO_EXTENSION)),
        ])) {
            return true;
        }
        mediaDeleteFile($targetPath, 'failed_move_target_cleanup');
        return false;
    }

    mediaLogFilesystemFailure('move', $targetPath, [
        'source_path_hash' => hash('sha256', str_replace('\\', '/', $sourcePath)),
        'source_file_extension' => strtolower((string)pathinfo($sourcePath, PATHINFO_EXTENSION)),
    ]);

    return false;
}

/**
 * @param array<string,mixed> $media
 * @return bool True, pokud všechny fyzické soubory chybí nebo se je podařilo odstranit.
 */
function mediaDeleteDerivedFiles(array $media, ?string $visibilityOverride = null, ?string $filenameOverride = null): bool
{
    return mediaApplyFileChanges([], array_values(array_diff(
        mediaPhysicalPaths($media, $visibilityOverride, $filenameOverride),
        [mediaOriginalPath($media, $visibilityOverride, $filenameOverride)]
    )));
}

/**
 * @param array<string,mixed> $media
 * @return list<string>
 */
function mediaPhysicalPaths(array $media, ?string $visibilityOverride = null, ?string $filenameOverride = null): array
{
    $original = mediaOriginalPath($media, $visibilityOverride, $filenameOverride);
    $paths = [$original];
    if (mediaCanPreviewImage($media)) {
        $thumb = mediaThumbPath($media, $visibilityOverride, $filenameOverride);
        $paths = array_merge($paths, [$thumb, mediaWebpPath($original), mediaWebpPath($thumb)]);
    }
    return array_values(array_unique(array_filter($paths, static fn (string $path): bool => $path !== '')));
}

function mediaWorkDirectory(): string
{
    $path = koraStoragePath('media/work/' . bin2hex(random_bytes(16)));
    if (!koraEnsureDirectory($path, 0700)) {
        throw new RuntimeException('Cannot prepare private media staging directory.');
    }
    return $path;
}

function mediaRemoveWorkDirectory(string $directory): void
{
    foreach (scandir($directory) ?: [] as $name) {
        if ($name !== '.' && $name !== '..') {
            mediaDeleteFile($directory . DIRECTORY_SEPARATOR . $name, 'work_cleanup');
        }
    }
    mediaRunFilesystemOperation(static fn (): bool => rmdir($directory));
}

function mediaCopyFile(string $source, string $target): bool
{
    if (!mediaRunFilesystemOperation(static fn (): bool => copy($source, $target))) {
        return false;
    }
    clearstatcache(true, $target);
    return is_file($target) && filesize($source) === filesize($target)
        && hash_file('sha256', $source) === hash_file('sha256', $target);
}

/**
 * Prepared files and rollback copies stay outside the web root. The persistence
 * callback runs only after every file operation succeeds; a DB error restores files.
 * @param array<string,string> $replacements Target path => prepared source path.
 * @param list<string> $removals
 */
function mediaApplyFileChanges(array $replacements, array $removals = [], ?callable $persist = null): bool
{
    $directory = '';
    $backups = [];
    $changed = [];
    $recoveryNeeded = false;
    $lock = false;
    try {
        $directory = mediaWorkDirectory();
        $lock = fopen(koraStoragePath('media/.mutation.lock'), 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock media mutation.');
        }
        $paths = array_values(array_unique(array_merge(array_keys($replacements), $removals)));
        foreach ($paths as $index => $path) {
            if ($path === '' || is_link($path) || (file_exists($path) && !is_file($path))) {
                throw new RuntimeException('Invalid media mutation target.');
            }
            if (isset($replacements[$path]) && !koraEnsureDirectory(dirname($path))) {
                throw new RuntimeException('Cannot prepare media target directory.');
            }
            if (is_file($path)) {
                $backup = $directory . DIRECTORY_SEPARATOR . $index . '.bak';
                if (!mediaCopyFile($path, $backup)) {
                    throw new RuntimeException('Cannot preserve original media file.');
                }
                $backups[$path] = $backup;
            }
        }
        foreach ($replacements as $target => $source) {
            $changed[$target] = true;
            if (!mediaMoveFile($source, $target)) {
                throw new RuntimeException('Cannot install prepared media file.');
            }
        }
        foreach (array_diff($removals, array_keys($replacements)) as $path) {
            $changed[$path] = true;
            if (!mediaDeleteFile($path, 'mutation_remove')) {
                throw new RuntimeException('Cannot remove superseded media file.');
            }
        }
        if ($persist !== null && $persist() === false) {
            throw new RuntimeException('Cannot persist media mutation.');
        }
        return true;
    } catch (Throwable $e) {
        foreach (array_reverse(array_keys($changed)) as $path) {
            $restored = isset($backups[$path])
                ? mediaCopyFile($backups[$path], $path)
                : mediaDeleteFile($path, 'mutation_rollback');
            if (!$restored) {
                $recoveryNeeded = true;
                mediaLogFilesystemFailure('mutation_recovery_required', $path);
            }
        }
        koraLog('error', 'media mutation failed', ['exception' => $e, 'recovery_required' => $recoveryNeeded]);
        return false;
    } finally {
        if ($directory !== '' && !$recoveryNeeded) {
            mediaRemoveWorkDirectory($directory);
        }
        if (is_resource($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

/**
 * @param array<string,mixed> $media
 * @return array{directory:string,files:array<string,string>}|null
 */
function mediaPrepareFileSet(array $media, string $sourcePath): ?array
{
    $directory = mediaWorkDirectory();
    try {
        $extension = mediaSanitizeExtension(mediaStoredFilename($media), (string)$media['mime_type']);
        $original = $directory . DIRECTORY_SEPARATOR . 'original.' . $extension;
        if (!mediaCopyFile($sourcePath, $original)) {
            throw new RuntimeException('Cannot stage original media file.');
        }
        $files = [mediaOriginalPath($media) => $original];
        if (mediaCanPreviewImage($media)) {
            $thumb = $directory . DIRECTORY_SEPARATOR . 'thumb.' . $extension;
            if (!gallery_make_thumb($original, $thumb, 300)) {
                throw new RuntimeException('Cannot decode media image.');
            }
            $files[mediaThumbPath($media)] = $thumb;
            if (mediaIsPublic($media)) {
                foreach ($files as $target => $source) {
                    $webp = generateWebp($source);
                    if ($webp !== '' && $webp !== $source && mediaWebpPath($target) !== $target) {
                        $files[mediaWebpPath($target)] = $webp;
                    }
                }
            }
        }
        return ['directory' => $directory, 'files' => $files];
    } catch (Throwable $e) {
        mediaRemoveWorkDirectory($directory);
        koraLog('warning', 'media preparation failed', ['exception' => $e]);
        return null;
    }
}

/**
 * @param array<string,mixed> $media
 * @return bool True, pokud všechny fyzické soubory chybí nebo se je podařilo odstranit.
 */
function mediaDeletePhysicalFiles(array $media, ?string $visibilityOverride = null, ?string $filenameOverride = null, ?callable $persist = null): bool
{
    return mediaApplyFileChanges([], mediaPhysicalPaths($media, $visibilityOverride, $filenameOverride), $persist);
}

/**
 * @param array<string,mixed> $media
 */
function mediaRebuildDerivedFiles(array $media): bool
{
    if (!mediaCanPreviewImage($media)) {
        return true;
    }
    $originalPath = mediaOriginalPath($media);
    if ($originalPath === '' || !is_file($originalPath)) {
        return false;
    }
    $prepared = mediaPrepareFileSet($media, $originalPath);
    if ($prepared === null) {
        return false;
    }
    try {
        unset($prepared['files'][$originalPath]);
        return mediaApplyFileChanges($prepared['files'], array_values(array_diff(mediaPhysicalPaths($media), [$originalPath])));
    } finally {
        mediaRemoveWorkDirectory($prepared['directory']);
    }
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
function mediaStoreUploadedFile(array $file, string $visibility = 'public', ?array $existingMedia = null, ?callable $persist = null): array
{
    $upload = koraInspectUploadedFile($file, [
        'no_file_error' => 'Nebyl vybrán žádný soubor.',
        'upload_error' => mediaUploadErrorMessage((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)),
        'invalid_upload_error' => 'Soubor se nepodařilo zpracovat.',
        'too_large_error' => 'Soubor překračuje maximální velikost ' . koraUploadMaxSizeLabel() . '.',
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
        if ($existingFilename !== '' && $existingVisibility === 'public' && !mediaUsesProtectedFileEndpoint($existingMedia) && $oldExtension !== '' && $oldExtension !== $newExtension) {
            return [
                'ok' => false,
                'error' => 'Veřejný soubor lze nahradit jen variantou se stejnou příponou, aby zůstaly funkční stávající odkazy.',
            ];
        }
    }

    $filename = mediaCanonicalStoredFilename($originalName, $mimeType);
    if ($existingFilename !== '') {
        $existingMimeType = (string)($existingMedia['mime_type'] ?? '');
        $existingDirectPublic = $existingVisibility === 'public' && !mediaUsesProtectedFileEndpoint($existingMedia);
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

    $record = [
        'id' => (int)($existingMedia['id'] ?? 0),
        'filename' => $filename,
        'mime_type' => $mimeType,
        'visibility' => $visibility,
    ];

    $prepared = mediaPrepareFileSet($record, (string)$upload['tmp_path']);
    if ($prepared === null) {
        return [
            'ok' => false,
            'error' => 'Náhled souboru se nepodařilo připravit.',
        ];
    }

    $result = [
        'ok' => true,
        'filename' => $filename,
        'original_name' => $originalName,
        'mime_type' => $mimeType,
        'file_size' => $fileSize,
    ];
    try {
        $saved = mediaApplyFileChanges(
            $prepared['files'],
            $existingMedia !== null ? mediaPhysicalPaths($existingMedia) : [],
            $persist !== null ? static fn () => $persist($result) : null
        );
        return $saved ? $result : ['ok' => false, 'error' => 'Soubor se nepodařilo bezpečně uložit.'];
    } finally {
        mediaRemoveWorkDirectory($prepared['directory']);
    }
}

/**
 * @param array<string,mixed> $media
 * @return array{ok:bool,error?:string}
 */
function mediaSwitchVisibility(array $media, string $newVisibility, ?callable $persist = null): array
{
    $currentVisibility = normalizeMediaVisibility((string)($media['visibility'] ?? 'public'));
    $newVisibility = normalizeMediaVisibility($newVisibility);
    if ($currentVisibility === $newVisibility) {
        return ['ok' => mediaApplyFileChanges([], [], $persist)];
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

    $updatedMedia = $media;
    $updatedMedia['visibility'] = $newVisibility;
    $prepared = mediaPrepareFileSet($updatedMedia, $sourcePath);
    if ($prepared === null) {
        return [
            'ok' => false,
            'error' => 'Soubor nelze přesunout, protože se nepodařilo připravit jeho náhled.',
        ];
    }

    try {
        $saved = mediaApplyFileChanges($prepared['files'], mediaPhysicalPaths($media), $persist);
        return $saved ? ['ok' => true] : ['ok' => false, 'error' => 'Soubor se nepodařilo bezpečně přesunout.'];
    } finally {
        mediaRemoveWorkDirectory($prepared['directory']);
    }
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

    if (mediaUsesProtectedFileEndpoint($media)) {
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
 *   reference_column?:string,
 *   label:string,
 *   admin_path:callable(array<string,mixed>):string
 * }>
 */
function mediaUsageSearchDefinitions(): array
{
    return [
        [
            'table' => 'cms_appmarket_apps',
            'id_column' => 'id',
            'title_sql' => 'name',
            'columns' => [],
            'reference_column' => 'icon_media_id',
            'label' => 'Ikona aplikace',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/appmarket_form.php?id=' . (int)$row['id'],
        ],
        [
            'table' => 'cms_appmarket_screenshots',
            'id_column' => 'app_id',
            'title_sql' => "CONCAT('Appmarket #', app_id)",
            'columns' => [],
            'reference_column' => 'media_id',
            'label' => 'Snímek aplikace',
            'admin_path' => static fn (array $row): string => BASE_URL . '/admin/appmarket_form.php?id=' . (int)$row['id'],
        ],
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
        $needles[] = '/media/preview.php?id=' . $id;
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

/** @param array<string,mixed> $media */
function mediaContentReferences(array $media, string $content): bool
{
    foreach (mediaUsageNeedles($media) as $needle) {
        if (str_contains($content, $needle)) {
            return true;
        }
    }
    preg_match_all('/\[pdf\s+([^\]]*)\]/i', $content, $matches);
    foreach ($matches[1] as $attributes) {
        $parsed = parseContentShortcodeAttributes($attributes);
        $id = filter_var($parsed['media_id'] ?? $parsed['media'] ?? '', FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0 && $id === (int)($media['id'] ?? 0)) {
            return true;
        }
    }
    return false;
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
        $referenceColumn = (string)($definition['reference_column'] ?? '');
        if ($referenceColumn !== '' && !mediaColumnExists($tableName, $referenceColumn)) {
            continue;
        }
        if ($columns === [] && $referenceColumn === '') {
            continue;
        }

        $whereParts = [];
        $params = [];
        if ($referenceColumn !== '') {
            $whereParts[] = $referenceColumn . ' = ?';
            $params[] = $mediaId;
        }
        foreach ($columns as $columnName) {
            $columnParts = [];
            foreach ($needles as $needle) {
                $columnParts[] = "{$columnName} LIKE ?";
                $params[] = '%' . $needle . '%';
            }
            $columnParts[] = "{$columnName} LIKE ?";
            $params[] = '%[pdf%';
            $whereParts[] = '(' . implode(' OR ', $columnParts) . ')';
        }

        $sql = sprintf(
            "SELECT %s AS id, %s AS title%s
             FROM %s
             WHERE %s",
            $definition['id_column'],
            $definition['title_sql'],
            $columns !== [] ? ', ' . implode(', ', $columns) : '',
            $tableName,
            implode(' OR ', $whereParts)
        );

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $found = 0;
            while ($row = $stmt->fetch()) {
                if ($referenceColumn === '' && !array_filter(
                    $columns,
                    static fn (string $column): bool => mediaContentReferences($media, (string)($row[$column] ?? ''))
                )) {
                    continue;
                }
                $usage = [
                    'label' => (string)$definition['label'],
                    'title' => trim((string)($row['title'] ?? '')) !== ''
                        ? trim((string)$row['title'])
                        : ((string)$definition['label'] . ' #' . (int)$row['id']),
                    'admin_url' => (string)$definition['admin_path']($row),
                ];
                $usages[] = $usage;
                if (++$found >= ($limit > 0 ? $limit : 200)) {
                    break;
                }
            }
            $stmt->closeCursor();
        } catch (\PDOException $e) {
            koraLog('warning', 'media usage scan failed', [
                'media_id' => $mediaId,
                'source_table' => $tableName,
                'exception' => $e,
            ]);
            $usages[] = ['label' => 'Použití nelze ověřit', 'title' => $tableName, 'admin_url' => BASE_URL . '/admin/media.php'];
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
