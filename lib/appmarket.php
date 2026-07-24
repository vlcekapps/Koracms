<?php

/**
 * @return array<string,string>
 */
function appmarketAppStatusDefinitions(): array
{
    return [
        'draft' => 'Koncept',
        'published' => 'Zveřejněná',
        'archived' => 'Archivovaná',
    ];
}

/**
 * @return array<string,string>
 */
function appmarketReleaseStatusDefinitions(): array
{
    return [
        'draft' => 'Koncept',
        'published' => 'Zveřejněné',
        'withdrawn' => 'Stažené',
    ];
}

function appmarketNormalizeAppStatus(?string $status): string
{
    $normalized = trim((string)$status);
    return array_key_exists($normalized, appmarketAppStatusDefinitions()) ? $normalized : 'draft';
}

function appmarketNormalizeReleaseStatus(?string $status): string
{
    $normalized = trim((string)$status);
    return array_key_exists($normalized, appmarketReleaseStatusDefinitions()) ? $normalized : 'draft';
}

function appmarketMetadataMaxBytes(): int
{
    return 65536;
}

function appmarketReleaseNotesMaxLength(): int
{
    return 50000;
}

function appmarketMaxPermissions(): int
{
    return 256;
}

function appmarketMaxArchiveEntries(): int
{
    return 32;
}

function appmarketReleaseNotesValid(string $releaseNotes): bool
{
    return mb_strlen($releaseNotes) <= appmarketReleaseNotesMaxLength();
}

function appmarketNormalizePackageId(?string $packageId): string
{
    $normalized = trim((string)$packageId);
    if (mb_strlen($normalized) > 255
        || !preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)+$/D', $normalized)
    ) {
        return '';
    }

    return $normalized;
}

function appmarketNormalizeVersionName(?string $versionName): string
{
    $normalized = trim((string)$versionName);
    if ($normalized === '' || mb_strlen($normalized) > 100 || preg_match('/[\x00-\x1F\x7F]/u', $normalized)) {
        return '';
    }

    return $normalized;
}

function appmarketNormalizeVersionCode(mixed $versionCode): ?int
{
    $normalized = filter_var($versionCode, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
            'max_range' => PHP_INT_MAX,
        ],
    ]);

    return $normalized === false ? null : (int)$normalized;
}

function appmarketNormalizeSdkLevel(mixed $sdkLevel): ?int
{
    if ($sdkLevel === null || $sdkLevel === '') {
        return null;
    }

    $normalized = filter_var($sdkLevel, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
            'max_range' => 1000,
        ],
    ]);

    return $normalized === false ? null : (int)$normalized;
}

function appmarketNormalizeDateTime(?string $value): ?string
{
    $normalized = trim((string)$value);
    if ($normalized === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($normalized))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function appmarketNormalizeJsonMetadata(mixed $value): string
{
    if (is_array($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : '[]';
    }

    $normalized = trim((string)$value);
    if ($normalized === '') {
        return '[]';
    }

    try {
        $decoded = json_decode($normalized, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        return '[]';
    }

    if (!is_array($decoded)) {
        return '[]';
    }

    $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($encoded) ? $encoded : '[]';
}

function appmarketNormalizeSha256(?string $hash): string
{
    $normalized = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string)$hash) ?? '');
    return preg_match('/^[a-f0-9]{64}$/D', $normalized) ? $normalized : '';
}

function appmarketNormalizeCertificateFingerprint(?string $fingerprint): string
{
    return appmarketNormalizeSha256($fingerprint);
}

function appmarketAppSlug(?string $value): string
{
    return slugify((string)$value);
}

function uniqueAppmarketAppSlug(PDO $pdo, string $candidate, ?int $excludeId = null): string
{
    $base = appmarketAppSlug($candidate);
    if ($base === '') {
        $base = 'aplikace';
    }

    $slug = $base;
    $suffix = 2;
    while (true) {
        $sql = 'SELECT id FROM cms_appmarket_apps WHERE slug = ?';
        $params = [$slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}

function appmarketPrivateApkDirectory(): string
{
    return koraStoragePath('appmarket/apks');
}

function appmarketPrivateStorageIsSafe(): bool
{
    $webRoot = realpath(dirname(__DIR__));
    if (!is_string($webRoot) || !koraEnsureDirectory(koraStorageDirectory(), 0750)) {
        return false;
    }

    $storageRoot = realpath(koraStorageDirectory());
    if (!is_string($storageRoot)) {
        return false;
    }

    $normalize = static function (string $path): string {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');
        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    };
    $normalizedWebRoot = $normalize($webRoot);
    $normalizedStorageRoot = $normalize($storageRoot);

    return $normalizedStorageRoot !== $normalizedWebRoot
        && !str_starts_with($normalizedStorageRoot . '/', $normalizedWebRoot . '/');
}

function appmarketApkStorageName(?string $sha256): string
{
    $normalized = appmarketNormalizeSha256($sha256);
    return $normalized === '' ? '' : $normalized . '.apk';
}

function appmarketPrivateApkPath(?string $storageName): string
{
    $normalized = strtolower(trim((string)$storageName));
    if (!preg_match('/^[a-f0-9]{64}\.apk$/D', $normalized)) {
        return '';
    }

    return appmarketPrivateApkDirectory() . DIRECTORY_SEPARATOR . $normalized;
}

function appmarketHashPublishToken(string $token): string
{
    return hash('sha256', $token);
}

/**
 * @return array{token:string,prefix:string,hash:string}
 */
function appmarketGeneratePublishToken(): array
{
    $random = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $token = 'kam_' . $random;

    return [
        'token' => $token,
        'prefix' => substr($token, 0, 12),
        'hash' => appmarketHashPublishToken($token),
    ];
}

/**
 * @return list<string>
 */
function appmarketNormalizeTokenScopes(?string $scopes): array
{
    $allowed = ['release:create'];
    $normalized = [];
    foreach (preg_split('/[\s,]+/', trim((string)$scopes)) ?: [] as $scope) {
        if (in_array($scope, $allowed, true) && !in_array($scope, $normalized, true)) {
            $normalized[] = $scope;
        }
    }

    return $normalized;
}

function appmarketTokenScopesValue(?string $scopes): string
{
    return implode(',', appmarketNormalizeTokenScopes($scopes));
}

function appmarketReleaseVersionIsNewer(int $candidateVersionCode, ?int $latestVersionCode): bool
{
    return $candidateVersionCode > 0
        && ($latestVersionCode === null || $candidateVersionCode > $latestVersionCode);
}

function appmarketNormalizeHttpUrl(?string $value): string
{
    $normalized = trim((string)$value);
    if ($normalized === '') {
        return '';
    }

    if (!preg_match('~^https?://~i', $normalized)) {
        return '';
    }

    $parts = parse_url($normalized);
    if (!is_array($parts)
        || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
        || trim((string)($parts['host'] ?? '')) === ''
        || isset($parts['user'])
        || isset($parts['pass'])
    ) {
        return '';
    }

    return mb_substr($normalized, 0, 500);
}

/**
 * @param array<string,mixed>|string $app
 */
function appmarketAppPath(array|string $app): string
{
    $slug = is_array($app) ? (string)($app['slug'] ?? '') : $app;
    $normalized = appmarketAppSlug($slug);
    return BASE_URL . '/aplikace/' . rawurlencode($normalized);
}

/**
 * @param array<string,mixed>|string $app
 */
function appmarketAppUrl(array|string $app): string
{
    return siteUrl(str_replace(BASE_URL, '', appmarketAppPath($app)));
}

/**
 * @param array<string,mixed>|string $app
 */
function appmarketReleasePath(array|string $app, int $versionCode): string
{
    return appmarketAppPath($app) . '/verze/' . max(1, $versionCode);
}

/**
 * @param array<string,mixed>|string $app
 */
function appmarketDownloadPath(array|string $app, int $versionCode): string
{
    return appmarketAppPath($app) . '/stahnout/' . max(1, $versionCode);
}

function appmarketUpdateApiPath(): string
{
    return BASE_URL . '/api/appmarket/v1/update';
}

function appmarketPublishApiPath(): string
{
    return BASE_URL . '/api/appmarket/v1/releases';
}

function appmarketAppPublicVisibilitySql(string $alias = 'a'): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    return $prefix . "status = 'published'";
}

function appmarketReleasePublicVisibilitySql(string $alias = 'r'): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    return $prefix . "status = 'published'"
        . ' AND ' . $prefix . "apk_storage_name <> ''"
        . ' AND ' . $prefix . 'apk_size > 0'
        . ' AND ' . $prefix . "apk_sha256 <> ''"
        . ' AND EXISTS ('
        . 'SELECT 1 FROM cms_appmarket_certificates appmarket_certificate '
        . 'WHERE appmarket_certificate.id = ' . $prefix . 'certificate_id '
        . 'AND appmarket_certificate.app_id = ' . $prefix . 'app_id '
        . 'AND appmarket_certificate.is_active = 1 '
        . 'AND appmarket_certificate.fingerprint_sha256 = '
        . $prefix . 'certificate_fingerprint_sha256'
        . ')';
}

function appmarketDownloadCountLabel(int $count): string
{
    $count = max(0, $count);
    if ($count === 1) {
        return 'Staženo 1krát';
    }

    return 'Staženo ' . number_format($count, 0, ',', "\u{00a0}") . 'krát';
}

/**
 * @param array<string,mixed> $release
 * @return array<string,mixed>
 */
function appmarketHydrateReleasePresentation(array $release): array
{
    $release['version_code'] = max(0, (int)($release['version_code'] ?? 0));
    $release['version_name'] = appmarketNormalizeVersionName((string)($release['version_name'] ?? ''));
    $release['min_sdk'] = appmarketNormalizeSdkLevel($release['min_sdk'] ?? null);
    $release['target_sdk'] = appmarketNormalizeSdkLevel($release['target_sdk'] ?? null);
    $release['apk_size'] = max(0, (int)($release['apk_size'] ?? 0));
    $release['apk_sha256'] = appmarketNormalizeSha256((string)($release['apk_sha256'] ?? ''));
    $release['certificate_fingerprint_sha256'] = appmarketNormalizeCertificateFingerprint(
        (string)($release['certificate_fingerprint_sha256'] ?? '')
    );
    $release['download_count'] = max(0, (int)($release['download_count'] ?? 0));
    $release['download_count_label'] = appmarketDownloadCountLabel($release['download_count']);
    $release['status'] = appmarketNormalizeReleaseStatus((string)($release['status'] ?? ''));
    $release['has_apk'] = appmarketPrivateApkPath((string)($release['apk_storage_name'] ?? '')) !== '';
    $release['release_notes_plain'] = trim(strip_tags((string)($release['release_notes'] ?? '')));
    $release['published_at_label'] = '';
    if (trim((string)($release['published_at'] ?? '')) !== '') {
        $release['published_at_label'] = formatCzechDate((string)$release['published_at']);
    }

    return $release;
}

/**
 * @param array<string,mixed> $app
 * @return array<string,mixed>
 */
function appmarketHydrateAppPresentation(array $app): array
{
    $app['id'] = max(0, (int)($app['id'] ?? 0));
    $app['slug'] = appmarketAppSlug((string)($app['slug'] ?? ''));
    $app['package_id'] = appmarketNormalizePackageId((string)($app['package_id'] ?? ''));
    $app['status'] = appmarketNormalizeAppStatus((string)($app['status'] ?? ''));
    $app['is_featured'] = (int)($app['is_featured'] ?? 0) === 1 ? 1 : 0;
    $app['short_description'] = trim((string)($app['short_description'] ?? ''));
    $app['description'] = (string)($app['description'] ?? '');
    $app['website_url'] = appmarketNormalizeHttpUrl((string)($app['website_url'] ?? ''));
    $app['support_url'] = appmarketNormalizeHttpUrl((string)($app['support_url'] ?? ''));
    $app['privacy_url'] = appmarketNormalizeHttpUrl((string)($app['privacy_url'] ?? ''));
    $app['icon_url'] = '';
    $app['icon_alt'] = trim((string)($app['icon_alt_text'] ?? ''));

    if ((int)($app['icon_media_id'] ?? 0) > 0
        && trim((string)($app['icon_filename'] ?? '')) !== ''
        && trim((string)($app['icon_mime_type'] ?? '')) !== ''
    ) {
        $media = [
            'id' => (int)$app['icon_media_id'],
            'filename' => (string)$app['icon_filename'],
            'original_name' => (string)($app['icon_original_name'] ?? ''),
            'mime_type' => (string)$app['icon_mime_type'],
            'visibility' => (string)($app['icon_visibility'] ?? 'private'),
            'alt_text' => (string)($app['icon_alt_text'] ?? ''),
        ];
        if (mediaIsPublic($media) && mediaCanPreviewImage($media)) {
            $app['icon_url'] = mediaFileUrl($media);
            if ($app['icon_alt'] === '') {
                $app['icon_alt'] = 'Ikona aplikace ' . trim((string)($app['name'] ?? ''));
            }
        }
    }

    return $app;
}

/**
 * @return array<string,mixed>|null
 */
function appmarketFindApp(PDO $pdo, int $appId): ?array
{
    if ($appId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM cms_appmarket_apps WHERE id = ? LIMIT 1');
    $stmt->execute([$appId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/**
 * @return array<string,mixed>|null
 */
function appmarketFindPublicAppBySlug(PDO $pdo, string $slug): ?array
{
    $normalized = appmarketAppSlug($slug);
    if ($normalized === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT a.*,
                m.filename AS icon_filename,
                m.original_name AS icon_original_name,
                m.mime_type AS icon_mime_type,
                m.visibility AS icon_visibility,
                m.alt_text AS icon_alt_text
         FROM cms_appmarket_apps a
         LEFT JOIN cms_media m ON m.id = a.icon_media_id
         WHERE a.slug = ?
           AND " . appmarketAppPublicVisibilitySql('a') . "
           AND EXISTS (
             SELECT 1
             FROM cms_appmarket_releases r
             WHERE r.app_id = a.id
               AND " . appmarketReleasePublicVisibilitySql('r') . "
           )
         LIMIT 1"
    );
    $stmt->execute([$normalized]);
    $row = $stmt->fetch();
    return is_array($row) ? appmarketHydrateAppPresentation($row) : null;
}

/**
 * @return array<string,mixed>|null
 */
function appmarketLatestPublishedRelease(PDO $pdo, int $appId, ?int $greaterThanVersionCode = null): ?array
{
    if ($appId <= 0) {
        return null;
    }

    $sql = "SELECT r.*
            FROM cms_appmarket_releases r
            WHERE r.app_id = ?
              AND " . appmarketReleasePublicVisibilitySql('r');
    $params = [$appId];
    if ($greaterThanVersionCode !== null) {
        $sql .= ' AND r.version_code > ?';
        $params[] = max(0, $greaterThanVersionCode);
    }
    $sql .= ' ORDER BY r.version_code DESC, r.id DESC LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return is_array($row) ? appmarketHydrateReleasePresentation($row) : null;
}

function appmarketLatestVersionCode(PDO $pdo, int $appId, bool $publishedOnly = false): ?int
{
    if ($appId <= 0) {
        return null;
    }

    $sql = 'SELECT MAX(version_code) FROM cms_appmarket_releases WHERE app_id = ?';
    if ($publishedOnly) {
        $sql .= " AND status = 'published'";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$appId]);
    $value = $stmt->fetchColumn();
    return $value === null || $value === false ? null : (int)$value;
}

/**
 * @return array<string,mixed>|null
 */
function appmarketFindRelease(PDO $pdo, int $releaseId): ?array
{
    if ($releaseId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT r.*, a.name AS app_name, a.slug AS app_slug, a.package_id AS app_package_id,
                a.status AS app_status, c.is_active AS certificate_is_active
         FROM cms_appmarket_releases r
         INNER JOIN cms_appmarket_apps a ON a.id = r.app_id
         LEFT JOIN cms_appmarket_certificates c ON c.id = r.certificate_id
         WHERE r.id = ?
         LIMIT 1"
    );
    $stmt->execute([$releaseId]);
    $row = $stmt->fetch();
    return is_array($row) ? appmarketHydrateReleasePresentation($row) : null;
}

/**
 * @return array<string,mixed>|null
 */
function appmarketFindPublicRelease(PDO $pdo, int $appId, int $versionCode): ?array
{
    if ($appId <= 0 || $versionCode <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT r.*
         FROM cms_appmarket_releases r
         WHERE r.app_id = ?
           AND r.version_code = ?
           AND " . appmarketReleasePublicVisibilitySql('r') . "
         LIMIT 1"
    );
    $stmt->execute([$appId, $versionCode]);
    $row = $stmt->fetch();
    return is_array($row) ? appmarketHydrateReleasePresentation($row) : null;
}

/**
 * @param array<string,mixed> $release
 * @return list<string>
 */
function appmarketReleasePublicationIssues(array $release): array
{
    $issues = [];
    if (appmarketNormalizePackageId((string)($release['package_id_snapshot'] ?? '')) === ''
        || (string)($release['package_id_snapshot'] ?? '') !== (string)($release['app_package_id'] ?? '')
    ) {
        $issues[] = 'Balíček APK neodpovídá aplikaci.';
    }
    if (appmarketNormalizeVersionCode($release['version_code'] ?? null) === null) {
        $issues[] = 'Vydání nemá platný versionCode.';
    }
    $path = appmarketPrivateApkPath((string)($release['apk_storage_name'] ?? ''));
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        $issues[] = 'Soukromě uložený APK soubor není dostupný.';
    } elseif (filesize($path) !== (int)($release['apk_size'] ?? 0)) {
        $issues[] = 'Velikost uloženého APK nesouhlasí.';
    } elseif (appmarketNormalizeSha256((string)hash_file('sha256', $path))
        !== appmarketNormalizeSha256((string)($release['apk_sha256'] ?? ''))
    ) {
        $issues[] = 'Kontrolní součet uloženého APK nesouhlasí.';
    } else {
        $verifiedAnalysis = appmarketAnalyzeApk($path);
        if (!$verifiedAnalysis['ok']) {
            foreach ($verifiedAnalysis['errors'] as $analysisError) {
                $issues[] = 'Opakovaná kontrola APK: ' . $analysisError;
            }
        } else {
            $verifiedMetadata = $verifiedAnalysis['metadata'];
            if ((string)($verifiedMetadata['package_id'] ?? '')
                !== (string)($release['package_id_snapshot'] ?? '')
            ) {
                $issues[] = 'Opakovaná kontrola APK zjistila jiné applicationId.';
            }
            if ((int)($verifiedMetadata['version_code'] ?? 0)
                !== (int)($release['version_code'] ?? 0)
            ) {
                $issues[] = 'Opakovaná kontrola APK zjistila jiný versionCode.';
            }
            if ((string)($verifiedMetadata['version_name'] ?? '')
                !== (string)($release['version_name'] ?? '')
            ) {
                $issues[] = 'Opakovaná kontrola APK zjistila jiný versionName.';
            }
            if ((string)($verifiedMetadata['certificate_sha256'] ?? '')
                !== (string)($release['certificate_fingerprint_sha256'] ?? '')
            ) {
                $issues[] = 'Opakovaná kontrola APK zjistila jiný podpisový certifikát.';
            }
        }
    }
    $storedAnalysis = json_decode((string)($release['analysis_json'] ?? ''), true);
    if ((string)($release['metadata_source'] ?? '') !== 'apk'
        || !is_array($storedAnalysis)
        || empty($storedAnalysis['tool_verified'])
    ) {
        $issues[] = 'Vydání nemá úplný záznam nezávislého serverového ověření APK.';
    }
    if ((int)($release['certificate_id'] ?? 0) <= 0
        || (int)($release['certificate_is_active'] ?? 0) !== 1
        || appmarketNormalizeCertificateFingerprint((string)($release['certificate_fingerprint_sha256'] ?? '')) === ''
    ) {
        $issues[] = 'Podpisový certifikát vydání není schválený.';
    }

    return $issues;
}

function appmarketMetadataBool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'ano'], true);
}

/**
 * @param array<string,mixed> $metadata
 * @return array{
 *     package_id:string,
 *     version_name:string,
 *     version_code:?int,
 *     min_sdk:?int,
 *     target_sdk:?int,
 *     certificate_sha256:string,
 *     certificate_subject:string,
 *     certificate_serial:string,
 *     certificate_valid_from:?string,
 *     certificate_valid_to:?string,
 *     debuggable:bool,
 *     build_type:string,
 *     permissions:list<string>,
 *     apk_sha256:string
 * }
 */
function appmarketNormalizeReleaseMetadata(array $metadata): array
{
    $permissionSet = [];
    $rawPermissions = $metadata['permissions'] ?? [];
    if (is_string($rawPermissions)) {
        $rawPermissions = preg_split('/[\r\n,]+/', $rawPermissions) ?: [];
    }
    if (is_array($rawPermissions)) {
        foreach ($rawPermissions as $permission) {
            if (count($permissionSet) >= appmarketMaxPermissions()) {
                break;
            }
            $normalized = trim((string)$permission);
            if ($normalized !== ''
                && preg_match('/\A[a-zA-Z0-9._-]{1,255}\z/', $normalized) === 1
            ) {
                $permissionSet[$normalized] = true;
            }
        }
    }
    $permissions = array_keys($permissionSet);
    sort($permissions);

    $buildType = strtolower(trim((string)($metadata['build_type'] ?? 'unknown')));
    if (!in_array($buildType, ['release', 'debug', 'qa'], true)) {
        $buildType = 'unknown';
    }

    return [
        'package_id' => appmarketNormalizePackageId((string)($metadata['package_id'] ?? '')),
        'version_name' => appmarketNormalizeVersionName((string)($metadata['version_name'] ?? '')),
        'version_code' => appmarketNormalizeVersionCode($metadata['version_code'] ?? null),
        'min_sdk' => appmarketNormalizeSdkLevel($metadata['min_sdk'] ?? null),
        'target_sdk' => appmarketNormalizeSdkLevel($metadata['target_sdk'] ?? null),
        'certificate_sha256' => appmarketNormalizeCertificateFingerprint(
            (string)($metadata['certificate_sha256'] ?? $metadata['certificate_fingerprint_sha256'] ?? '')
        ),
        'certificate_subject' => mb_substr(trim((string)($metadata['certificate_subject'] ?? '')), 0, 4000),
        'certificate_serial' => mb_substr(trim((string)($metadata['certificate_serial'] ?? '')), 0, 255),
        'certificate_valid_from' => appmarketNormalizeDateTime(
            (string)($metadata['certificate_valid_from'] ?? '')
        ),
        'certificate_valid_to' => appmarketNormalizeDateTime(
            (string)($metadata['certificate_valid_to'] ?? '')
        ),
        'debuggable' => appmarketMetadataBool($metadata['debuggable'] ?? false),
        'build_type' => $buildType,
        'permissions' => $permissions,
        'apk_sha256' => appmarketNormalizeSha256((string)($metadata['apk_sha256'] ?? '')),
    ];
}

function appmarketFindAndroidTool(string $tool): string
{
    static $cache = [];
    $normalizedTool = strtolower(trim($tool));
    if (!in_array($normalizedTool, ['apkanalyzer', 'apksigner'], true)) {
        return '';
    }
    if (array_key_exists($normalizedTool, $cache)) {
        return $cache[$normalizedTool];
    }

    $fileNames = PHP_OS_FAMILY === 'Windows'
        ? [$normalizedTool . '.bat', $normalizedTool . '.cmd', $normalizedTool . '.exe']
        : [$normalizedTool];
    $sdkRoots = [];
    foreach (['ANDROID_SDK_ROOT', 'ANDROID_HOME'] as $environmentKey) {
        $root = trim((string)getenv($environmentKey));
        if ($root !== '' && is_dir($root)) {
            $sdkRoots[] = rtrim($root, "\\/");
        }
    }

    foreach ($sdkRoots as $root) {
        $directories = $normalizedTool === 'apkanalyzer'
            ? [
                $root . DIRECTORY_SEPARATOR . 'cmdline-tools' . DIRECTORY_SEPARATOR . 'latest'
                    . DIRECTORY_SEPARATOR . 'bin',
                $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'bin',
            ]
            : (glob($root . DIRECTORY_SEPARATOR . 'build-tools' . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: []);
        if ($normalizedTool === 'apksigner') {
            rsort($directories, SORT_NATURAL);
        }
        foreach ($directories as $directory) {
            foreach ($fileNames as $fileName) {
                $candidate = rtrim((string)$directory, "\\/") . DIRECTORY_SEPARATOR . $fileName;
                if (is_file($candidate)) {
                    $cache[$normalizedTool] = $candidate;
                    return $candidate;
                }
            }
        }
    }

    foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $directory) {
        $directory = trim($directory, " \t\n\r\0\x0B\"");
        if ($directory === '') {
            continue;
        }
        foreach ($fileNames as $fileName) {
            $candidate = rtrim($directory, "\\/") . DIRECTORY_SEPARATOR . $fileName;
            if (is_file($candidate)) {
                $cache[$normalizedTool] = $candidate;
                return $candidate;
            }
        }
    }

    $cache[$normalizedTool] = '';
    return '';
}

/**
 * @param list<string> $arguments
 * @return array{ok:bool,output:string,exit_code:int,timed_out:bool}
 */
function appmarketRunAndroidTool(string $toolPath, array $arguments): array
{
    if ($toolPath === '' || !is_file($toolPath)) {
        return ['ok' => false, 'output' => '', 'exit_code' => 127, 'timed_out' => false];
    }

    $command = array_merge([$toolPath], array_map('strval', $arguments));
    $pipes = [];
    $process = proc_open(
        $command,
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        null,
        null,
        PHP_OS_FAMILY === 'Windows' ? [] : ['bypass_shell' => true]
    );
    if (!is_resource($process)) {
        return ['ok' => false, 'output' => '', 'exit_code' => 127, 'timed_out' => false];
    }

    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            stream_set_blocking($pipe, false);
        }
    }

    $startedAt = microtime(true);
    $output = '';
    $exitCode = -1;
    $timedOut = false;
    $outputLimit = 200000;
    while (true) {
        foreach ($pipes as $pipe) {
            if (!is_resource($pipe)) {
                continue;
            }
            $chunk = stream_get_contents($pipe);
            if (is_string($chunk) && $chunk !== '' && strlen($output) < $outputLimit) {
                $output .= substr($chunk, 0, $outputLimit - strlen($output));
            }
        }

        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = $status['exitcode'];
            break;
        }
        if ((microtime(true) - $startedAt) >= 30.0) {
            $timedOut = true;
            proc_terminate($process);
            usleep(100000);
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            $exitCode = 124;
            break;
        }

        usleep(10000);
    }

    foreach ($pipes as $pipe) {
        if (!is_resource($pipe)) {
            continue;
        }
        $chunk = stream_get_contents($pipe);
        if (is_string($chunk) && $chunk !== '' && strlen($output) < $outputLimit) {
            $output .= substr($chunk, 0, $outputLimit - strlen($output));
        }
        fclose($pipe);
    }
    $closeCode = proc_close($process);
    if ($exitCode < 0 && $closeCode >= 0) {
        $exitCode = $closeCode;
    }
    $output = trim($output);

    return [
        'ok' => !$timedOut && $exitCode === 0,
        'output' => $output,
        'exit_code' => $exitCode,
        'timed_out' => $timedOut,
    ];
}

/**
 * @param array<string,mixed> $fallbackMetadata
 * @return array{
 *     ok:bool,
 *     errors:list<string>,
 *     metadata:array<string,mixed>,
 *     metadata_source:string,
 *     tool_verified:bool,
 *     analysis_json:string
 * }
 */
function appmarketAnalyzeApk(string $apkPath, array $fallbackMetadata = []): array
{
    $errors = [];
    if (!is_file($apkPath) || !is_readable($apkPath)) {
        return [
            'ok' => false,
            'errors' => ['APK soubor není čitelný.'],
            'metadata' => [],
            'metadata_source' => 'apk',
            'tool_verified' => false,
            'analysis_json' => '[]',
        ];
    }

    $fileSize = filesize($apkPath);
    $sha256 = hash_file('sha256', $apkPath);
    if (!is_int($fileSize) || $fileSize <= 0 || !is_string($sha256)) {
        return [
            'ok' => false,
            'errors' => ['APK soubor se nepodařilo bezpečně načíst.'],
            'metadata' => [],
            'metadata_source' => 'apk',
            'tool_verified' => false,
            'analysis_json' => '[]',
        ];
    }

    $apkanalyzerPath = appmarketFindAndroidTool('apkanalyzer');
    $apksignerPath = appmarketFindAndroidTool('apksigner');
    $toolsAvailable = $apkanalyzerPath !== '' && $apksignerPath !== '';
    $toolDetails = [
        'apkanalyzer_available' => $apkanalyzerPath !== '',
        'apksigner_available' => $apksignerPath !== '',
    ];
    if (!$toolsAvailable) {
        $errors[] = 'Server nemá dostupné nástroje apkanalyzer a apksigner; APK nelze nezávisle ověřit.';
    }

    $rawMetadata = ['apk_sha256' => $sha256, 'build_type' => 'unknown'];
    if ($toolsAvailable) {
        $manifestFields = [
            'package_id' => 'application-id',
            'version_name' => 'version-name',
            'version_code' => 'version-code',
            'min_sdk' => 'min-sdk',
            'target_sdk' => 'target-sdk',
            'debuggable' => 'debuggable',
        ];
        foreach ($manifestFields as $metadataKey => $manifestField) {
            $result = appmarketRunAndroidTool(
                $apkanalyzerPath,
                ['manifest', $manifestField, $apkPath]
            );
            $toolDetails['manifest_' . $metadataKey . '_exit_code'] = $result['exit_code'];
            $value = trim($result['output']);
            if (!$result['ok'] || $value === '') {
                $errors[] = 'Z APK se nepodařilo bezpečně načíst pole ' . $manifestField . '.';
                continue;
            }
            $rawMetadata[$metadataKey] = $value;
        }

        $debuggableValue = strtolower(trim((string)($rawMetadata['debuggable'] ?? '')));
        if (!in_array($debuggableValue, ['0', '1', 'false', 'true'], true)) {
            $errors[] = 'APK nevrátilo jednoznačnou informaci o debug režimu.';
            $rawMetadata['build_type'] = 'unknown';
        } else {
            $rawMetadata['debuggable'] = in_array($debuggableValue, ['1', 'true'], true);
            $rawMetadata['build_type'] = !empty($rawMetadata['debuggable']) ? 'debug' : 'release';
        }

        $permissionsResult = appmarketRunAndroidTool($apkanalyzerPath, ['manifest', 'permissions', $apkPath]);
        $toolDetails['permissions_exit_code'] = $permissionsResult['exit_code'];
        if (!$permissionsResult['ok']) {
            $errors[] = 'Z APK se nepodařilo bezpečně načíst oprávnění.';
        } else {
            $permissions = preg_split('/\R+/', $permissionsResult['output']) ?: [];
            if (count($permissions) > appmarketMaxPermissions()) {
                $errors[] = 'APK deklaruje příliš mnoho oprávnění pro bezpečnou kontrolu.';
            } else {
                $rawMetadata['permissions'] = $permissions;
            }
        }

        $signatureResult = appmarketRunAndroidTool($apksignerPath, ['verify', '--print-certs', $apkPath]);
        $toolDetails['signature_exit_code'] = $signatureResult['exit_code'];
        if (!$signatureResult['ok']) {
            $errors[] = $signatureResult['timed_out']
                ? 'Kontrola podpisu APK překročila bezpečný časový limit.'
                : 'APK nemá platný produkční podpis.';
        } elseif (preg_match(
            '/Signer #1 certificate SHA-256 digest:\s*([A-Fa-f0-9: ]{64,})/i',
            $signatureResult['output'],
            $match
        ) !== 1) {
            $errors[] = 'Z APK se nepodařilo bezpečně načíst SHA-256 podpisového certifikátu.';
        } else {
            $rawMetadata['certificate_sha256'] = $match[1];
            if (preg_match('/Signer #1 certificate DN:\s*(.+)/i', $signatureResult['output'], $match) === 1) {
                $rawMetadata['certificate_subject'] = trim($match[1]);
            }
        }
    }

    $metadata = appmarketNormalizeReleaseMetadata($rawMetadata);
    $metadata['apk_sha256'] = appmarketNormalizeSha256($sha256);
    $metadata['apk_size'] = $fileSize;

    $declaredMetadata = appmarketNormalizeReleaseMetadata($fallbackMetadata);
    foreach ([
        'package_id' => 'applicationId',
        'version_name' => 'versionName',
        'version_code' => 'versionCode',
        'certificate_sha256' => 'podpisový certifikát',
        'apk_sha256' => 'SHA-256 APK',
    ] as $metadataKey => $label) {
        if (!array_key_exists($metadataKey, $fallbackMetadata)
            && !($metadataKey === 'certificate_sha256'
                && array_key_exists('certificate_fingerprint_sha256', $fallbackMetadata))
        ) {
            continue;
        }
        $declaredValue = $declaredMetadata[$metadataKey] ?? null;
        $verifiedValue = $metadata[$metadataKey] ?? null;
        if ($declaredValue !== null
            && $declaredValue !== ''
            && (string)$declaredValue !== (string)$verifiedValue
        ) {
            $errors[] = 'Publisher metadata neodpovídají ověřené hodnotě ' . $label . '.';
        }
    }
    if ($metadata['package_id'] === '') {
        $errors[] = 'Z APK se nepodařilo zjistit platné applicationId.';
    }
    if ($metadata['version_name'] === '') {
        $errors[] = 'Z APK se nepodařilo zjistit platný versionName.';
    }
    if ($metadata['version_code'] === null) {
        $errors[] = 'Z APK se nepodařilo zjistit platný versionCode.';
    }
    if ($metadata['certificate_sha256'] === '') {
        $errors[] = 'Z APK se nepodařilo zjistit SHA-256 podpisového certifikátu.';
    }
    if ($metadata['debuggable'] || $metadata['build_type'] !== 'release') {
        $errors[] = 'Appmarket přijímá jen produkční release APK; debug a QA sestavení jsou zakázaná.';
    }
    $toolVerified = $toolsAvailable && $errors === [];
    $analysis = [
        'schema_version' => 1,
        'tool_verified' => $toolVerified,
        'tools' => $toolDetails,
        'metadata' => $metadata,
    ];

    return [
        'ok' => $errors === [],
        'errors' => array_values(array_unique($errors)),
        'metadata' => $metadata,
        'metadata_source' => 'apk',
        'tool_verified' => $toolVerified,
        'analysis_json' => appmarketNormalizeJsonMetadata($analysis),
    ];
}

function appmarketStagingDirectory(): string
{
    return koraStoragePath('appmarket/staging');
}

function appmarketStagingPath(string $extension = 'apk'): string
{
    $normalizedExtension = strtolower(trim($extension));
    if (!in_array($normalizedExtension, ['apk', 'json'], true)) {
        $normalizedExtension = 'apk';
    }

    return appmarketStagingDirectory() . DIRECTORY_SEPARATOR
        . bin2hex(random_bytes(16)) . '.' . $normalizedExtension;
}

function appmarketFileHasZipSignature(string $path): bool
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return false;
    }
    $signature = fread($handle, 4);
    fclose($handle);

    return is_string($signature) && str_starts_with($signature, "PK\x03\x04");
}

/**
 * @param array<string,mixed> $file
 * @return array{
 *     ok:bool,
 *     errors:list<string>,
 *     apk_path:string,
 *     apk_original_name:string,
 *     source_is_upload:bool,
 *     cleanup_paths:list<string>,
 *     analysis:array<string,mixed>
 * }
 */
function appmarketInspectReleaseUpload(array $file, string $metadataJson = ''): array
{
    $emptyResult = [
        'ok' => false,
        'errors' => [],
        'apk_path' => '',
        'apk_original_name' => '',
        'source_is_upload' => false,
        'cleanup_paths' => [],
        'analysis' => [],
    ];
    if (strlen($metadataJson) > appmarketMetadataMaxBytes()) {
        $emptyResult['errors'][] = 'Metadata vydání překračují bezpečný limit 64 KiB.';
        return $emptyResult;
    }

    $inspection = koraInspectUploadedFile($file, [
        'max_bytes' => koraDefaultUploadMaxSizeBytes(),
        'too_large_error' => 'Balíček překračuje nastavený limit uploadu ' . koraUploadMaxSizeLabel() . '.',
        'unsupported_type_error' => 'Nahrajte APK nebo .kora-app-release.zip.',
    ]);
    if (empty($inspection['ok'])) {
        $emptyResult['errors'][] = (string)($inspection['error'] ?? 'Soubor se nepodařilo ověřit.');
        return $emptyResult;
    }

    $sourcePath = (string)$inspection['tmp_path'];
    $originalName = safeDownloadName((string)$inspection['original_name'], 'app-release.apk');
    $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['apk', 'zip'], true) || !appmarketFileHasZipSignature($sourcePath)) {
        $emptyResult['errors'][] = 'Nahrajte platný Android APK nebo .kora-app-release.zip.';
        return $emptyResult;
    }

    $fallbackMetadata = [];
    if (trim($metadataJson) !== '') {
        try {
            $decoded = json_decode($metadataJson, true, 32, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $fallbackMetadata = $decoded;
            }
        } catch (JsonException $e) {
            $emptyResult['errors'][] = 'Metadata vydání nejsou platný JSON.';
            return $emptyResult;
        }
    }

    $apkPath = $sourcePath;
    $sourceIsUpload = true;
    $cleanupPaths = [];
    if ($extension === 'zip') {
        if (!class_exists(ZipArchive::class)) {
            $emptyResult['errors'][] = 'Server nemá rozšíření ZIP potřebné pro publisher balíček.';
            return $emptyResult;
        }

        $archive = new ZipArchive();
        if ($archive->open($sourcePath) !== true) {
            $emptyResult['errors'][] = 'Publisher balíček se nepodařilo otevřít.';
            return $emptyResult;
        }
        if ($archive->numFiles <= 0 || $archive->numFiles > appmarketMaxArchiveEntries()) {
            $archive->close();
            $emptyResult['errors'][] = 'Publisher balíček obsahuje příliš mnoho souborů.';
            return $emptyResult;
        }

        $manifestIndex = $archive->locateName('release.json', ZipArchive::FL_NOCASE);
        $apkIndex = false;
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entryName = (string)$archive->getNameIndex($index);
            if ($entryName !== basename($entryName)
                || str_contains($entryName, '..')
                || strtolower((string)pathinfo($entryName, PATHINFO_EXTENSION)) !== 'apk'
            ) {
                continue;
            }
            if ($apkIndex !== false) {
                $apkIndex = false;
                break;
            }
            $apkIndex = $index;
        }

        if ($manifestIndex === false || $apkIndex === false) {
            $archive->close();
            $emptyResult['errors'][] = 'Publisher balíček musí obsahovat právě jeden APK a soubor release.json.';
            return $emptyResult;
        }

        $archiveApkName = (string)$archive->getNameIndex((int)$apkIndex);
        $manifestStat = $archive->statIndex((int)$manifestIndex);
        $apkStat = $archive->statIndex((int)$apkIndex);
        if (!is_array($manifestStat)
            || !is_array($apkStat)
            || $manifestStat['size'] <= 0
            || $manifestStat['size'] > 65536
            || $apkStat['size'] <= 0
            || $apkStat['size'] > koraDefaultUploadMaxSizeBytes()
        ) {
            $archive->close();
            $emptyResult['errors'][] = 'Publisher balíček obsahuje neplatně velký manifest nebo APK.';
            return $emptyResult;
        }

        $manifestJson = $archive->getFromIndex((int)$manifestIndex);
        if (!is_string($manifestJson)) {
            $archive->close();
            $emptyResult['errors'][] = 'Metadata publisher balíčku se nepodařilo načíst.';
            return $emptyResult;
        }
        try {
            $decoded = json_decode($manifestJson, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new JsonException('Manifest must be an object.');
            }
            $fallbackMetadata = $decoded;
        } catch (JsonException $e) {
            $archive->close();
            $emptyResult['errors'][] = 'release.json v publisher balíčku není platný JSON.';
            return $emptyResult;
        }

        if (!appmarketPrivateStorageIsSafe()
            || !koraEnsureDirectory(appmarketStagingDirectory(), 0750)
        ) {
            $archive->close();
            $emptyResult['errors'][] = 'Soukromé úložiště Appmarketu musí být dostupné mimo veřejný webroot.';
            return $emptyResult;
        }
        $apkPath = appmarketStagingPath();
        $input = $archive->getStream((string)$archive->getNameIndex((int)$apkIndex));
        $output = fopen($apkPath, 'wb');
        $copied = false;
        if (is_resource($input) && is_resource($output)) {
            $copiedBytes = stream_copy_to_stream($input, $output, koraDefaultUploadMaxSizeBytes() + 1);
            $copied = is_int($copiedBytes)
                && $copiedBytes > 0
                && $copiedBytes <= koraDefaultUploadMaxSizeBytes();
        }
        if (is_resource($input)) {
            fclose($input);
        }
        if (is_resource($output)) {
            fclose($output);
        }
        $archive->close();
        if (!$copied || !appmarketFileHasZipSignature($apkPath)) {
            if (is_file($apkPath)) {
                unlink($apkPath);
            }
            $emptyResult['errors'][] = 'APK se z publisher balíčku nepodařilo bezpečně rozbalit.';
            return $emptyResult;
        }

        $sourceIsUpload = false;
        $cleanupPaths[] = $apkPath;
        $originalName = safeDownloadName(
            (string)($fallbackMetadata['apk_name'] ?? $archiveApkName),
            'app-release.apk'
        );
    }

    $analysis = appmarketAnalyzeApk($apkPath, $fallbackMetadata);
    if (empty($analysis['ok'])) {
        foreach ($cleanupPaths as $cleanupPath) {
            if (is_file($cleanupPath)) {
                unlink($cleanupPath);
            }
        }
        $emptyResult['errors'] = $analysis['errors'];
        return $emptyResult;
    }

    return [
        'ok' => true,
        'errors' => [],
        'apk_path' => $apkPath,
        'apk_original_name' => $originalName,
        'source_is_upload' => $sourceIsUpload,
        'cleanup_paths' => $cleanupPaths,
        'analysis' => $analysis,
    ];
}

/**
 * @return array{ok:bool,path:string,storage_name:string,error:string}
 */
function appmarketStorePrivateApk(string $sourcePath, string $sha256, bool $sourceIsUpload): array
{
    $storageName = appmarketApkStorageName($sha256);
    $targetPath = appmarketPrivateApkPath($storageName);
    if ($storageName === '' || $targetPath === '' || !is_file($sourcePath)) {
        return ['ok' => false, 'path' => '', 'storage_name' => '', 'error' => 'APK se nepodařilo připravit k uložení.'];
    }
    if (!appmarketPrivateStorageIsSafe()
        || !koraEnsureDirectory(appmarketPrivateApkDirectory(), 0750)
    ) {
        return [
            'ok' => false,
            'path' => '',
            'storage_name' => '',
            'error' => 'Soukromé úložiště APK musí být dostupné mimo veřejný webroot.',
        ];
    }

    if (is_file($targetPath)) {
        $existingHash = hash_file('sha256', $targetPath);
        if (is_string($existingHash) && hash_equals($sha256, strtolower($existingHash))) {
            if (!chmod($targetPath, 0640)) {
                return [
                    'ok' => false,
                    'path' => '',
                    'storage_name' => '',
                    'error' => 'Existující APK nemá bezpečná oprávnění.',
                ];
            }
            if (!$sourceIsUpload && $sourcePath !== $targetPath) {
                unlink($sourcePath);
            }
            return ['ok' => true, 'path' => $targetPath, 'storage_name' => $storageName, 'error' => ''];
        }
        return ['ok' => false, 'path' => '', 'storage_name' => '', 'error' => 'V soukromém úložišti existuje konfliktní soubor.'];
    }

    $stored = $sourceIsUpload
        ? move_uploaded_file($sourcePath, $targetPath)
        : rename($sourcePath, $targetPath);
    if (!$stored) {
        return ['ok' => false, 'path' => '', 'storage_name' => '', 'error' => 'APK se nepodařilo přesunout do soukromého úložiště.'];
    }
    if (!chmod($targetPath, 0640)) {
        if (is_file($targetPath) && !unlink($targetPath)) {
            koraLog('warning', 'appmarket insecure APK cleanup failed', [
                'storage_name_hash' => hash('sha256', $storageName),
            ]);
        }
        return [
            'ok' => false,
            'path' => '',
            'storage_name' => '',
            'error' => 'APK se nepodařilo uložit s bezpečnými oprávněními.',
        ];
    }

    return ['ok' => true, 'path' => $targetPath, 'storage_name' => $storageName, 'error' => ''];
}

function appmarketDeletePrivateApkIfUnused(PDO $pdo, string $storageName, ?int $excludeReleaseId = null): void
{
    $path = appmarketPrivateApkPath($storageName);
    if ($path === '') {
        return;
    }

    $sql = 'SELECT COUNT(*) FROM cms_appmarket_releases WHERE apk_storage_name = ?';
    $params = [$storageName];
    if ($excludeReleaseId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeReleaseId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ((int)$stmt->fetchColumn() === 0 && is_file($path) && !unlink($path)) {
        koraLog('warning', 'appmarket APK cleanup failed', [
            'storage_name_hash' => hash('sha256', $storageName),
        ]);
    }
}

function appmarketBearerToken(): string
{
    $authorization = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if (preg_match('/\ABearer\s+([A-Za-z0-9_-]{20,})\z/i', $authorization, $match) !== 1) {
        return '';
    }

    return $match[1];
}

/**
 * @return array<string,mixed>|null
 */
function appmarketAuthenticatePublishToken(PDO $pdo, string $token): ?array
{
    if (!str_starts_with($token, 'kam_') || strlen($token) > 200) {
        return null;
    }

    $hash = appmarketHashPublishToken($token);
    $stmt = $pdo->prepare(
        "SELECT t.*, a.name AS app_name, a.slug AS app_slug, a.package_id
         FROM cms_appmarket_publish_tokens t
         INNER JOIN cms_appmarket_apps a ON a.id = t.app_id
         WHERE t.token_hash = ?
           AND t.revoked_at IS NULL
           AND (t.expires_at IS NULL OR t.expires_at > NOW())
         LIMIT 1"
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!is_array($row)
        || !hash_equals((string)$row['token_hash'], $hash)
        || !in_array('release:create', appmarketNormalizeTokenScopes((string)$row['scopes']), true)
    ) {
        return null;
    }

    return $row;
}

/**
 * @param array<string,mixed> $payload
 */
function appmarketSendJson(int $statusCode, array $payload, bool $publicCache = false, bool $isHeadRequest = false): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: ' . ($publicCache ? 'public, max-age=300' : 'no-store, max-age=0'));
    if (!$publicCache) {
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Referrer-Policy: no-referrer');
    }
    sendNoSniffHeader();

    if (!$isHeadRequest) {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo is_string($encoded) ? $encoded : '{"error":"json_encoding_failed"}';
    }
    exit;
}

/**
 * @param array<string,mixed> $app
 * @param array<string,mixed>|null $release
 * @return array<string,mixed>
 */
function appmarketUpdatePayload(array $app, ?array $release, int $currentVersionCode): array
{
    $payload = [
        'schema_version' => 1,
        'package_id' => (string)($app['package_id'] ?? ''),
        'current_version_code' => max(0, $currentVersionCode),
        'update_available' => $release !== null,
        'latest' => null,
    ];
    if ($release === null) {
        return $payload;
    }

    $payload['latest'] = [
        'version_name' => (string)($release['version_name'] ?? ''),
        'version_code' => (int)($release['version_code'] ?? 0),
        'release_notes' => trim((string)($release['release_notes'] ?? '')),
        'published_at' => (string)($release['published_at'] ?? ''),
        'min_sdk' => $release['min_sdk'] ?? null,
        'target_sdk' => $release['target_sdk'] ?? null,
        'apk_size' => (int)($release['apk_size'] ?? 0),
        'apk_sha256' => (string)($release['apk_sha256'] ?? ''),
        'certificate_sha256' => (string)($release['certificate_fingerprint_sha256'] ?? ''),
        'release_url' => siteUrl(str_replace(
            BASE_URL,
            '',
            appmarketReleasePath($app, (int)($release['version_code'] ?? 0))
        )),
        'download_url' => siteUrl(str_replace(
            BASE_URL,
            '',
            appmarketDownloadPath($app, (int)($release['version_code'] ?? 0))
        )),
    ];

    return $payload;
}

/**
 * @param array<string,mixed> $upload
 */
function appmarketCleanupInspectedUpload(array $upload): void
{
    foreach (is_array($upload['cleanup_paths'] ?? null) ? $upload['cleanup_paths'] : [] as $path) {
        $normalized = (string)$path;
        if ($normalized !== '' && is_file($normalized)) {
            unlink($normalized);
        }
    }
}

/**
 * @param array<string,mixed> $app
 * @param array<string,mixed> $upload
 * @return array{ok:bool,release_id:int,errors:list<string>}
 */
function appmarketCreateReleaseDraft(
    PDO $pdo,
    array $app,
    array $upload,
    string $releaseNotes,
    ?int $createdByUserId = null
): array {
    $errors = [];
    $appId = (int)($app['id'] ?? 0);
    $analysis = is_array($upload['analysis'] ?? null) ? $upload['analysis'] : [];
    $metadata = is_array($analysis['metadata'] ?? null) ? $analysis['metadata'] : [];
    $packageId = appmarketNormalizePackageId((string)($metadata['package_id'] ?? ''));
    $versionName = appmarketNormalizeVersionName((string)($metadata['version_name'] ?? ''));
    $versionCode = appmarketNormalizeVersionCode($metadata['version_code'] ?? null);
    $fingerprint = appmarketNormalizeCertificateFingerprint(
        (string)($metadata['certificate_sha256'] ?? '')
    );
    $sha256 = appmarketNormalizeSha256((string)($metadata['apk_sha256'] ?? ''));

    if (empty($analysis['tool_verified'])
        || (string)($analysis['metadata_source'] ?? '') !== 'apk'
    ) {
        $errors[] = 'APK nebylo nezávisle ověřeno serverovými Android nástroji.';
    }
    if (!appmarketReleaseNotesValid($releaseNotes)) {
        $errors[] = 'Seznam změn může mít nejvýše 50 000 znaků.';
    }
    if ($appId <= 0 || $packageId === '' || $packageId !== (string)($app['package_id'] ?? '')) {
        $errors[] = 'ApplicationId APK neodpovídá vybrané aplikaci.';
    }
    if ($versionName === '' || $versionCode === null) {
        $errors[] = 'APK nemá platné označení verze.';
    } elseif (!appmarketReleaseVersionIsNewer($versionCode, appmarketLatestVersionCode($pdo, $appId))) {
        $errors[] = 'versionCode musí být vyšší než u všech dříve nahraných vydání této aplikace.';
    }
    if ($fingerprint === '') {
        $errors[] = 'APK nemá ověřitelný podpisový certifikát.';
    }
    if ($sha256 === '') {
        $errors[] = 'APK nemá platný SHA-256 kontrolní součet.';
    }
    if ($errors !== []) {
        appmarketCleanupInspectedUpload($upload);
        return ['ok' => false, 'release_id' => 0, 'errors' => $errors];
    }

    $stored = appmarketStorePrivateApk(
        (string)($upload['apk_path'] ?? ''),
        $sha256,
        !empty($upload['source_is_upload'])
    );
    if (!$stored['ok']) {
        appmarketCleanupInspectedUpload($upload);
        return ['ok' => false, 'release_id' => 0, 'errors' => [$stored['error']]];
    }

    try {
        $pdo->beginTransaction();

        $certificateStmt = $pdo->prepare(
            'SELECT id FROM cms_appmarket_certificates WHERE app_id = ? AND fingerprint_sha256 = ? LIMIT 1'
        );
        $certificateStmt->execute([$appId, $fingerprint]);
        $certificateId = (int)($certificateStmt->fetchColumn() ?: 0);
        if ($certificateId <= 0) {
            $pdo->prepare(
                "INSERT INTO cms_appmarket_certificates
                 (app_id, fingerprint_sha256, subject_dn, serial_number, valid_from, valid_to,
                  is_active, notes, created_by_user_id)
                 VALUES (?,?,?,?,?,?,0,?,?)"
            )->execute([
                $appId,
                $fingerprint,
                (string)($metadata['certificate_subject'] ?? ''),
                (string)($metadata['certificate_serial'] ?? ''),
                $metadata['certificate_valid_from'] ?? null,
                $metadata['certificate_valid_to'] ?? null,
                'Certifikát zjištěný při nahrání vydání; před zveřejněním vyžaduje schválení superadminem.',
                $createdByUserId,
            ]);
            $certificateId = (int)$pdo->lastInsertId();
        }

        $permissionsJson = appmarketNormalizeJsonMetadata($metadata['permissions'] ?? []);
        $pdo->prepare(
            "INSERT INTO cms_appmarket_releases
             (app_id, version_name, version_code, release_notes, min_sdk, target_sdk,
              package_id_snapshot, apk_storage_name, apk_original_name, apk_size, apk_sha256,
              certificate_id, certificate_fingerprint_sha256, permissions_json, analysis_json,
              metadata_source, status, created_by_user_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'draft', ?)"
        )->execute([
            $appId,
            $versionName,
            $versionCode,
            trim($releaseNotes),
            $metadata['min_sdk'] ?? null,
            $metadata['target_sdk'] ?? null,
            $packageId,
            $stored['storage_name'],
            safeDownloadName((string)($upload['apk_original_name'] ?? ''), $packageId . '-' . $versionName . '.apk'),
            max(0, (int)($metadata['apk_size'] ?? 0)),
            $sha256,
            $certificateId,
            $fingerprint,
            $permissionsJson,
            (string)($analysis['analysis_json'] ?? '[]'),
            'apk',
            $createdByUserId,
        ]);
        $releaseId = (int)$pdo->lastInsertId();
        $pdo->commit();

        return ['ok' => true, 'release_id' => $releaseId, 'errors' => []];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        appmarketDeletePrivateApkIfUnused($pdo, (string)$stored['storage_name']);
        koraLog('error', 'appmarket release draft creation failed', [
            'app_id' => $appId,
            'version_code' => $versionCode,
            'exception' => $e,
        ]);

        return [
            'ok' => false,
            'release_id' => 0,
            'errors' => ['Koncept vydání se nepodařilo uložit. Ověřte, zda versionCode už neexistuje.'],
        ];
    }
}

/**
 * @return array{ok:bool,errors:list<string>}
 */
function appmarketPublishRelease(PDO $pdo, int $releaseId, int $userId): array
{
    $release = appmarketFindRelease($pdo, $releaseId);
    if ($release === null || (string)$release['status'] !== 'draft') {
        return ['ok' => false, 'errors' => ['Zveřejnit lze jen existující koncept vydání.']];
    }

    $issues = appmarketReleasePublicationIssues($release);
    $latestPublished = appmarketLatestVersionCode($pdo, (int)$release['app_id'], true);
    if (!appmarketReleaseVersionIsNewer((int)$release['version_code'], $latestPublished)) {
        $issues[] = 'versionCode musí být vyšší než u aktuálně zveřejněného vydání.';
    }
    if ($issues !== []) {
        return ['ok' => false, 'errors' => array_values(array_unique($issues))];
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "UPDATE cms_appmarket_releases
             SET status = 'published', published_at = NOW(), published_by_user_id = ?
             WHERE id = ? AND status = 'draft'"
        )->execute([$userId, $releaseId]);
        $pdo->prepare(
            "UPDATE cms_appmarket_apps
             SET status = 'published', published_at = COALESCE(published_at, NOW())
             WHERE id = ?"
        )->execute([(int)$release['app_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        koraLog('error', 'appmarket release publication failed', [
            'release_id' => $releaseId,
            'exception' => $e,
        ]);
        return ['ok' => false, 'errors' => ['Vydání se nepodařilo zveřejnit.']];
    }

    return ['ok' => true, 'errors' => []];
}
