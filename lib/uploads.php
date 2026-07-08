<?php

/**
 * Shared upload helpers for modules that need to validate and store files.
 *
 * The helpers intentionally keep business rules in callers. They only centralize
 * low-level upload checks: PHP upload status, temporary file validation, size,
 * MIME allowlist, safe extensions and final move into a prepared directory.
 */

function koraUploadHasFile(mixed $file): bool
{
    if (!is_array($file)) {
        return false;
    }

    return trim((string)($file['name'] ?? '')) !== ''
        && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function koraUploadErrorMessage(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Soubor je příliš velký.',
        UPLOAD_ERR_PARTIAL => 'Soubor se nepodařilo nahrát celý.',
        UPLOAD_ERR_NO_FILE => 'Nebyl vybrán žádný soubor.',
        default => 'Soubor se nepodařilo nahrát.',
    };
}

function koraDefaultUploadMaxSizeMb(): int
{
    $rawValue = function_exists('getSetting') ? getSetting('upload_max_size_mb', '10') : '10';
    return max(1, min(500, (int)$rawValue));
}

function koraDefaultUploadMaxSizeBytes(): int
{
    return koraDefaultUploadMaxSizeMb() * 1024 * 1024;
}

function koraUploadMaxSizeLabel(?int $megabytes = null): string
{
    $megabytes ??= koraDefaultUploadMaxSizeMb();
    return number_format(max(1, $megabytes), 0, ',', "\u{00a0}") . "\u{00a0}MB";
}

function koraUploadSanitizeExtension(string $originalName): string
{
    $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '') {
        return '';
    }

    return preg_match('/\A[a-z0-9]{1,12}\z/', $extension) === 1 ? $extension : '';
}

function koraUploadMimeType(string $tmpPath): string
{
    if ($tmpPath === '' || !is_file($tmpPath)) {
        return '';
    }

    $mimeType = (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    return trim($mimeType);
}

function koraUploadMimeIsSvg(string $mimeType): bool
{
    $normalized = strtolower(trim($mimeType));
    return $normalized === 'image/svg+xml' || $normalized === 'image/svg';
}

/**
 * @param array<string,mixed> $context
 */
function koraUploadLogFilesystemFailure(string $operation, string $path, array $context = []): void
{
    koraLog('warning', 'upload filesystem operation failed', array_merge([
        'operation' => $operation,
        'path_hash' => hash('sha256', str_replace('\\', '/', $path)),
        'file_extension' => strtolower((string)pathinfo($path, PATHINFO_EXTENSION)),
    ], $context));
}

/**
 * @param callable(): bool $operation
 */
function koraUploadRunFilesystemOperation(callable $operation): bool
{
    set_error_handler(static fn (): bool => true);
    try {
        return (bool)$operation();
    } finally {
        restore_error_handler();
    }
}

function koraUploadDeleteExistingTarget(string $targetPath): void
{
    if ($targetPath === '' || !is_file($targetPath)) {
        return;
    }

    if (koraUploadRunFilesystemOperation(static fn (): bool => unlink($targetPath))) {
        return;
    }

    koraUploadLogFilesystemFailure('replace_existing', $targetPath);
}

/**
 * @param array<string,mixed> $file
 * @param array<string,mixed> $options
 * @return array<string,mixed>
 */
function koraInspectUploadedFile(array $file, array $options = []): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $originalName = trim((string)($file['name'] ?? ''));

    if ($originalName === '' || $uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'ok' => false,
            'no_file' => true,
            'error' => (string)($options['no_file_error'] ?? koraUploadErrorMessage(UPLOAD_ERR_NO_FILE)),
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'ok' => false,
            'error' => (string)($options['upload_error'] ?? koraUploadErrorMessage($uploadError)),
        ];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    $requireUploadedFile = (bool)($options['require_uploaded_file'] ?? true);
    if ($tmpPath === '' || ($requireUploadedFile && !is_uploaded_file($tmpPath)) || (!$requireUploadedFile && !is_file($tmpPath))) {
        return [
            'ok' => false,
            'error' => (string)($options['invalid_upload_error'] ?? 'Nahraný soubor se nepodařilo ověřit.'),
        ];
    }

    $fileSize = (int)($file['size'] ?? 0);
    if ($fileSize <= 0) {
        return [
            'ok' => false,
            'error' => (string)($options['empty_file_error'] ?? 'Vybraný soubor je prázdný.'),
        ];
    }

    $maxBytes = (int)($options['max_bytes'] ?? 0);
    if ($maxBytes > 0 && $fileSize > $maxBytes) {
        return [
            'ok' => false,
            'error' => (string)($options['too_large_error'] ?? 'Vybraný soubor je příliš velký.'),
        ];
    }

    $mimeType = koraUploadMimeType($tmpPath);
    if ($mimeType === '') {
        return [
            'ok' => false,
            'error' => (string)($options['invalid_upload_error'] ?? 'Nahraný soubor se nepodařilo ověřit.'),
        ];
    }

    if (!empty($options['reject_svg']) && koraUploadMimeIsSvg($mimeType)) {
        return [
            'ok' => false,
            'error' => (string)($options['svg_error'] ?? 'SVG soubory nejsou povolené.'),
        ];
    }

    $allowedMimeMap = is_array($options['allowed_mime_map'] ?? null) ? $options['allowed_mime_map'] : [];
    if ($allowedMimeMap !== [] && !array_key_exists($mimeType, $allowedMimeMap)) {
        return [
            'ok' => false,
            'error' => (string)($options['unsupported_type_error'] ?? 'Tento typ souboru není povolený.'),
        ];
    }

    $extension = koraUploadSanitizeExtension($originalName);
    if ($allowedMimeMap !== [] && isset($allowedMimeMap[$mimeType])) {
        $mappedExtension = strtolower(trim((string)$allowedMimeMap[$mimeType]));
        if ($mappedExtension !== '') {
            $extension = preg_match('/\A[a-z0-9]{1,12}\z/', $mappedExtension) === 1 ? $mappedExtension : '';
        }
    }

    return [
        'ok' => true,
        'tmp_path' => $tmpPath,
        'original_name' => $originalName,
        'file_size' => $fileSize,
        'mime_type' => $mimeType,
        'extension' => $extension,
    ];
}

/**
 * @param array<string,mixed> $upload
 * @param array<string,mixed> $options
 * @return array<string,mixed>
 */
function koraStoreInspectedUpload(array $upload, string $directory, string $filename, array $options = []): array
{
    $safeFilename = basename($filename);
    if ($safeFilename === '' || $safeFilename !== $filename) {
        return [
            'ok' => false,
            'error' => (string)($options['invalid_filename_error'] ?? 'Název souboru se nepodařilo připravit.'),
        ];
    }

    $targetDirectory = rtrim($directory, "\\/") . DIRECTORY_SEPARATOR;
    $permissions = (int)($options['permissions'] ?? 0755);
    if (!koraEnsureDirectory($targetDirectory, $permissions)) {
        koraUploadLogFilesystemFailure('prepare_directory', $targetDirectory, [
            'permissions' => decoct($permissions),
        ]);

        return [
            'ok' => false,
            'error' => (string)($options['mkdir_error'] ?? 'Adresář pro uložení souboru se nepodařilo připravit.'),
        ];
    }

    $targetPath = $targetDirectory . $safeFilename;
    if (!empty($options['replace_existing'])) {
        koraUploadDeleteExistingTarget($targetPath);
    }

    $tmpPath = (string)($upload['tmp_path'] ?? '');
    if (!koraUploadRunFilesystemOperation(static fn (): bool => move_uploaded_file($tmpPath, $targetPath))) {
        koraUploadLogFilesystemFailure('move_uploaded_file', $targetPath, [
            'tmp_path_hash' => hash('sha256', str_replace('\\', '/', $tmpPath)),
        ]);

        return [
            'ok' => false,
            'error' => (string)($options['move_error'] ?? 'Soubor se nepodařilo uložit na server.'),
        ];
    }

    return [
        'ok' => true,
        'filename' => $safeFilename,
        'path' => $targetPath,
    ];
}
