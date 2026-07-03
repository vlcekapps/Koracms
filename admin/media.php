<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

$pdo = db_connect();
$perPage = 24;
$mediaDeleteDisabledReason = 'Použité médium nelze smazat.';

/**
 * @return array<string, string>
 */
function mediaAdminTypeOptions(): array
{
    return [
        '' => 'Všechny typy',
        'image' => 'Obrázky',
        'audio' => 'Audio',
        'video' => 'Video',
        'application' => 'Dokumenty a soubory',
        'text' => 'Textové soubory',
        'svg' => 'SVG soubory',
    ];
}

/**
 * @return array<string, string>
 */
function mediaAdminUsageOptions(): array
{
    return [
        '' => 'Použité i nepoužité',
        'used' => 'Jen použité',
        'unused' => 'Jen nepoužité',
    ];
}

/**
 * @return array<string, string>
 */
function mediaAdminMetadataOptions(): array
{
    return [
        '' => 'Všechna metadata',
        'missing_alt' => 'Chybí alt text',
        'missing_credit_license' => 'Chybí kredit nebo licence',
        'incomplete' => 'Neúplná metadata',
    ];
}

/**
 * @return array{
 *   q: string,
 *   type: string,
 *   visibility: string,
 *   collection: int,
 *   metadata: string,
 *   collection_edit: int,
 *   uploader: int,
 *   usage: string,
 *   page: int,
 *   edit: int
 * }
 */
function mediaAdminStateFromRequest(): array
{
    $page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $edit = filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $uploader = filter_var($_GET['uploader'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $collection = filter_var($_GET['collection'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $collectionEdit = filter_var($_GET['collection_edit'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    $type = trim((string)($_GET['type'] ?? ''));
    if (!array_key_exists($type, mediaAdminTypeOptions())) {
        $type = '';
    }

    $visibility = normalizeMediaVisibility((string)($_GET['visibility'] ?? ''));
    if (trim((string)($_GET['visibility'] ?? '')) === '') {
        $visibility = '';
    }

    $usage = trim((string)($_GET['usage'] ?? ''));
    if (!array_key_exists($usage, mediaAdminUsageOptions())) {
        $usage = '';
    }

    $metadata = trim((string)($_GET['metadata'] ?? ''));
    if (!array_key_exists($metadata, mediaAdminMetadataOptions())) {
        $metadata = '';
    }

    return [
        'q' => trim((string)($_GET['q'] ?? '')),
        'type' => $type,
        'visibility' => $visibility,
        'collection' => $collection !== false ? (int)$collection : 0,
        'metadata' => $metadata,
        'collection_edit' => $collectionEdit !== false ? (int)$collectionEdit : 0,
        'uploader' => $uploader !== false ? (int)$uploader : 0,
        'usage' => $usage,
        'page' => $page !== false ? (int)$page : 1,
        'edit' => $edit !== false ? (int)$edit : 0,
    ];
}

/**
 * @param array{
 *   q: string,
 *   type: string,
 *   visibility: string,
 *   collection: int,
 *   metadata: string,
 *   collection_edit: int,
 *   uploader: int,
 *   usage: string,
 *   page: int,
 *   edit: int
 * } $state
 */
function mediaAdminPath(array $state): string
{
    $query = [];
    if (trim($state['q']) !== '') {
        $query['q'] = trim((string)$state['q']);
    }
    if (trim($state['type']) !== '') {
        $query['type'] = trim((string)$state['type']);
    }
    if (trim($state['visibility']) !== '') {
        $query['visibility'] = trim((string)$state['visibility']);
    }
    if ((int)$state['collection'] > 0) {
        $query['collection'] = (int)$state['collection'];
    }
    if (trim((string)$state['metadata']) !== '') {
        $query['metadata'] = trim((string)$state['metadata']);
    }
    if ((int)$state['collection_edit'] > 0) {
        $query['collection_edit'] = (int)$state['collection_edit'];
    }
    if ($state['uploader'] > 0) {
        $query['uploader'] = (int)$state['uploader'];
    }
    if (trim($state['usage']) !== '') {
        $query['usage'] = trim((string)$state['usage']);
    }
    if ($state['page'] > 1) {
        $query['page'] = (int)$state['page'];
    }
    if ($state['edit'] > 0) {
        $query['edit'] = (int)$state['edit'];
    }

    return BASE_URL . '/admin/media.php' . ($query !== [] ? '?' . http_build_query($query) : '');
}

function mediaAdminRedirectTarget(string $fallback): string
{
    $requestedTarget = trim((string)($_POST['return_to'] ?? ''));
    return internalRedirectTarget($requestedTarget, $fallback);
}

function mediaAdminRedirectWithFlash(string $target): void
{
    header('Location: ' . $target);
    exit;
}

/**
 * @return list<array{id:int|string,label:string}>
 */
function mediaAdminUploaderOptions(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT u.id,
                COALESCE(NULLIF(u.nickname, ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), u.email) AS label
         FROM cms_users u
         INNER JOIN cms_media m ON m.uploaded_by = u.id
         GROUP BY u.id, label
         ORDER BY label"
    );

    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * @return array{0:string,1:list<string>}
 */
function mediaAdminTypeSql(string $type): array
{
    return match ($type) {
        'image' => ["m.mime_type LIKE ? AND m.mime_type <> 'image/svg+xml'", ['image/%']],
        'audio' => ["m.mime_type LIKE ?", ['audio/%']],
        'video' => ["m.mime_type LIKE ?", ['video/%']],
        'application' => ["m.mime_type LIKE ?", ['application/%']],
        'text' => ["m.mime_type LIKE ?", ['text/%']],
        'svg' => ["m.mime_type = ?", ['image/svg+xml']],
        default => ['', []],
    };
}

function mediaAdminMetadataSql(string $metadata): string
{
    $missingImageAlt = "(m.mime_type LIKE 'image/%' AND m.mime_type <> 'image/svg+xml' AND TRIM(COALESCE(m.alt_text, '')) = '')";
    $missingCreditOrLicense = "(TRIM(COALESCE(m.credit, '')) = '' OR TRIM(COALESCE(m.license_label, '')) = '')";

    return match ($metadata) {
        'missing_alt' => $missingImageAlt,
        'missing_credit_license' => $missingCreditOrLicense,
        'incomplete' => '(' . $missingImageAlt . ' OR ' . $missingCreditOrLicense . ')',
        default => '',
    };
}

/**
 * @param array{
 *   q: string,
 *   type: string,
 *   visibility: string,
 *   collection: int,
 *   metadata: string,
 *   collection_edit: int,
 *   uploader: int,
 *   usage: string,
 *   page: int,
 *   edit: int
 * } $state
 * @return list<array<string, mixed>>
 */
function mediaAdminFetchItems(PDO $pdo, array $state): array
{
    $where = [];
    $params = [];

    if ($state['q'] !== '') {
        $where[] = '(m.original_name LIKE ? OR m.alt_text LIKE ? OR m.caption LIKE ? OR m.description LIKE ? OR m.credit LIKE ? OR m.license_label LIKE ? OR c.name LIKE ?)';
        $like = '%' . $state['q'] . '%';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }

    if ($state['visibility'] !== '') {
        $where[] = 'm.visibility = ?';
        $params[] = $state['visibility'];
    }

    if ((int)$state['uploader'] > 0) {
        $where[] = 'm.uploaded_by = ?';
        $params[] = (int)$state['uploader'];
    }

    if ((int)$state['collection'] > 0) {
        $where[] = 'm.collection_id = ?';
        $params[] = (int)$state['collection'];
    }

    [$typeSql, $typeParams] = mediaAdminTypeSql((string)$state['type']);
    if ($typeSql !== '') {
        $where[] = $typeSql;
        $params = array_merge($params, $typeParams);
    }

    $metadataSql = mediaAdminMetadataSql((string)$state['metadata']);
    if ($metadataSql !== '') {
        $where[] = $metadataSql;
    }

    $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare(
        "SELECT m.*,
                COALESCE(NULLIF(u.nickname, ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), u.email, '—') AS uploader_name,
                c.name AS collection_name
         FROM cms_media m
         LEFT JOIN cms_users u ON u.id = m.uploaded_by
         LEFT JOIN cms_media_collections c ON c.id = m.collection_id
         {$whereSql}
         ORDER BY m.created_at DESC, m.id DESC"
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    if ($state['usage'] !== '') {
        $items = array_values(array_filter(
            $items,
            static function (array $item) use ($state): bool {
                $used = mediaHasUsage($item);
                return $state['usage'] === 'used' ? $used : !$used;
            }
        ));
    }

    return $items;
}

$state = mediaAdminStateFromRequest();
$fallbackState = $state;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = trim((string)($_POST['action'] ?? ''));
    $fallbackTarget = mediaAdminPath($fallbackState);
    $target = mediaAdminRedirectTarget($fallbackTarget);

    if ($action === 'save_collection') {
        $collectionId = inputInt('post', 'collection_id') ?? 0;
        $name = trim((string)($_POST['collection_name'] ?? ''));
        $requestedSlug = trim((string)($_POST['collection_slug'] ?? ''));
        $slug = $requestedSlug !== '' ? normalizeMediaCollectionSlug($requestedSlug) : uniqueMediaCollectionSlug($pdo, $name, $collectionId);
        $licenseUrl = normalizeMediaLicenseUrl((string)($_POST['collection_default_license_url'] ?? ''));
        $defaultVisibility = normalizeMediaVisibility((string)($_POST['collection_default_visibility'] ?? 'public'));

        if ($name === '') {
            mediaFlashSet('error', 'Název kolekce je povinný.');
            mediaAdminRedirectWithFlash($target);
        }
        if ($requestedSlug !== '' && mediaCollectionSlugExists($pdo, $slug, $collectionId)) {
            mediaFlashSet('error', 'Slug kolekce už existuje.');
            mediaAdminRedirectWithFlash($target);
        }
        if (trim((string)($_POST['collection_default_license_url'] ?? '')) !== '' && $licenseUrl === '') {
            mediaFlashSet('error', 'Licenční URL kolekce musí začínat http:// nebo https://.');
            mediaAdminRedirectWithFlash($target);
        }

        if ($collectionId > 0 && mediaCollectionById($pdo, $collectionId) !== null) {
            $pdo->prepare(
                "UPDATE cms_media_collections
                 SET name = ?, slug = ?, description = ?, default_visibility = ?, default_credit = ?,
                     default_license_label = ?, default_license_url = ?, sort_order = ?, updated_at = NOW()
                 WHERE id = ?"
            )->execute([
                $name,
                $slug,
                trim((string)($_POST['collection_description'] ?? '')),
                $defaultVisibility,
                trim((string)($_POST['collection_default_credit'] ?? '')),
                trim((string)($_POST['collection_default_license_label'] ?? '')),
                $licenseUrl,
                max(0, (int)($_POST['collection_sort_order'] ?? 0)),
                $collectionId,
            ]);
            mediaFlashSet('success', 'Kolekce médií byla uložena.');
            logAction('media_collection_update', 'id=' . $collectionId);
        } else {
            $pdo->prepare(
                "INSERT INTO cms_media_collections
                 (name, slug, description, default_visibility, default_credit, default_license_label, default_license_url, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $name,
                $slug,
                trim((string)($_POST['collection_description'] ?? '')),
                $defaultVisibility,
                trim((string)($_POST['collection_default_credit'] ?? '')),
                trim((string)($_POST['collection_default_license_label'] ?? '')),
                $licenseUrl,
                max(0, (int)($_POST['collection_sort_order'] ?? 0)),
            ]);
            mediaFlashSet('success', 'Kolekce médií byla vytvořena.');
            logAction('media_collection_create', 'id=' . (int)$pdo->lastInsertId());
        }

        mediaAdminRedirectWithFlash(mediaAdminPath(array_merge($state, ['collection_edit' => 0])));
    }

    if ($action === 'delete_collection') {
        $collectionId = inputInt('post', 'collection_id') ?? 0;
        $collection = mediaCollectionById($pdo, $collectionId);
        if ($collection === null) {
            mediaFlashSet('error', 'Kolekce nebyla nalezena.');
            mediaAdminRedirectWithFlash($target);
        }

        $pdo->prepare("UPDATE cms_media SET collection_id = NULL WHERE collection_id = ?")->execute([$collectionId]);
        $pdo->prepare("DELETE FROM cms_media_collections WHERE id = ?")->execute([$collectionId]);
        mediaFlashSet('success', 'Kolekce byla smazána. Média v knihovně zůstala zachovaná.');
        logAction('media_collection_delete', 'id=' . $collectionId);
        mediaAdminRedirectWithFlash(mediaAdminPath(array_merge($state, ['collection' => 0, 'collection_edit' => 0])));
    }

    if ($action === 'upload') {
        $uploadCollectionId = inputInt('post', 'upload_collection_id') ?? 0;
        $uploadCollection = $uploadCollectionId > 0 ? mediaCollectionById($pdo, $uploadCollectionId) : null;
        if ($uploadCollectionId > 0 && $uploadCollection === null) {
            mediaFlashSet('error', 'Vybraná kolekce nebyla nalezena.');
            mediaAdminRedirectWithFlash($target);
        }
        $uploadVisibility = $uploadCollection !== null
            ? normalizeMediaVisibility((string)($uploadCollection['default_visibility'] ?? 'public'))
            : normalizeMediaVisibility((string)($_POST['upload_visibility'] ?? 'public'));
        $files = $_FILES['media_files'] ?? null;
        $uploadedCount = 0;
        $errors = [];

        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            mediaFlashSet('error', 'Nebyl vybrán žádný soubor.');
            mediaAdminRedirectWithFlash($target);
        }

        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $singleFile = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];

            if ($singleFile['name'] === '' && (int)$singleFile['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $stored = mediaStoreUploadedFile($singleFile, $uploadVisibility);
            if (!$stored['ok']) {
                $errors[] = ($singleFile['name'] !== '' ? $singleFile['name'] : 'Soubor') . ': ' . $stored['error'];
                continue;
            }

            $pdo->prepare(
                "INSERT INTO cms_media
                 (filename, original_name, mime_type, file_size, folder, collection_id, alt_text, caption, description,
                  credit, license_label, license_url, visibility, uploaded_by)
                 VALUES (?, ?, ?, ?, 'media', ?, '', '', '', ?, ?, ?, ?, ?)"
            )->execute([
                $stored['filename'],
                $stored['original_name'],
                $stored['mime_type'],
                (int)$stored['file_size'],
                $uploadCollection !== null ? (int)$uploadCollection['id'] : null,
                $uploadCollection !== null ? trim((string)($uploadCollection['default_credit'] ?? '')) : '',
                $uploadCollection !== null ? trim((string)($uploadCollection['default_license_label'] ?? '')) : '',
                $uploadCollection !== null ? normalizeMediaLicenseUrl((string)($uploadCollection['default_license_url'] ?? '')) : '',
                $uploadVisibility,
                currentUserId(),
            ]);
            $uploadedCount++;
        }

        if ($uploadedCount > 0) {
            $altHint = $uploadedCount === 1
                ? 'Nahráno. Nezapomeňte vyplnit alt text u obrázků.'
                : 'Nahráno ' . $uploadedCount . ' souborů. Nezapomeňte vyplnit alt text u obrázků.';
            mediaFlashSet('success', $altHint);
            logAction('media_upload', 'count=' . $uploadedCount . ';visibility=' . $uploadVisibility);
        }
        if ($errors !== []) {
            mediaFlashSet('error', implode(' ', $errors));
        }

        mediaAdminRedirectWithFlash($target);
    }

    if ($action === 'update_meta') {
        $mediaId = inputInt('post', 'media_id');
        $media = $mediaId !== null ? mediaGetById($mediaId) : null;
        if ($media === null) {
            mediaFlashSet('error', 'Médium nebylo nalezeno.');
            mediaAdminRedirectWithFlash($target);
        }

        $newVisibility = normalizeMediaVisibility((string)($_POST['visibility'] ?? 'public'));
        if ($newVisibility === 'private' && mediaHasUsage($media)) {
            mediaFlashSet('error', 'Použité médium nelze přepnout do soukromého režimu, dokud je vložené v obsahu.');
            mediaAdminRedirectWithFlash($target);
        }

        if ($newVisibility !== normalizeMediaVisibility((string)($media['visibility'] ?? 'public'))) {
            $switchResult = mediaSwitchVisibility($media, $newVisibility);
            if (!$switchResult['ok']) {
                mediaFlashSet('error', (string)$switchResult['error']);
                mediaAdminRedirectWithFlash($target);
            }
        }

        $collectionId = inputInt('post', 'collection_id') ?? 0;
        if ($collectionId > 0 && mediaCollectionById($pdo, $collectionId) === null) {
            mediaFlashSet('error', 'Vybraná kolekce nebyla nalezena.');
            mediaAdminRedirectWithFlash($target);
        }
        $licenseUrlInput = trim((string)($_POST['license_url'] ?? ''));
        $licenseUrl = normalizeMediaLicenseUrl($licenseUrlInput);
        if ($licenseUrlInput !== '' && $licenseUrl === '') {
            mediaFlashSet('error', 'Licenční URL musí začínat http:// nebo https://.');
            mediaAdminRedirectWithFlash($target);
        }

        $pdo->prepare(
            "UPDATE cms_media
             SET collection_id = ?, alt_text = ?, caption = ?, description = ?, credit = ?,
                 license_label = ?, license_url = ?, visibility = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([
            $collectionId > 0 ? $collectionId : null,
            trim((string)($_POST['alt_text'] ?? '')),
            trim((string)($_POST['caption'] ?? '')),
            trim((string)($_POST['description'] ?? '')),
            trim((string)($_POST['credit'] ?? '')),
            trim((string)($_POST['license_label'] ?? '')),
            $licenseUrl,
            $newVisibility,
            $mediaId,
        ]);

        mediaFlashSet('success', 'Metadata média byla uložena.');
        logAction('media_update', 'id=' . $mediaId);
        mediaAdminRedirectWithFlash($target);
    }

    if ($action === 'replace') {
        $mediaId = inputInt('post', 'media_id');
        $media = $mediaId !== null ? mediaGetById($mediaId) : null;
        if ($media === null) {
            mediaFlashSet('error', 'Médium nebylo nalezeno.');
            mediaAdminRedirectWithFlash($target);
        }

        $stored = mediaStoreUploadedFile(
            $_FILES['replacement_file'] ?? [],
            normalizeMediaVisibility((string)($media['visibility'] ?? 'public')),
            $media
        );
        if (!$stored['ok']) {
            mediaFlashSet('error', (string)$stored['error']);
            mediaAdminRedirectWithFlash($target);
        }

        $pdo->prepare(
            "UPDATE cms_media
             SET filename = ?, original_name = ?, mime_type = ?, file_size = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([
            $stored['filename'],
            $stored['original_name'],
            $stored['mime_type'],
            (int)$stored['file_size'],
            $mediaId,
        ]);

        mediaFlashSet('success', 'Soubor byl nahrazen bez změny jeho ID.');
        logAction('media_replace', 'id=' . $mediaId);
        mediaAdminRedirectWithFlash($target);
    }

    if ($action === 'delete') {
        $mediaId = inputInt('post', 'media_id');
        $media = $mediaId !== null ? mediaGetById($mediaId) : null;
        if ($media === null) {
            mediaFlashSet('error', 'Médium nebylo nalezeno.');
            mediaAdminRedirectWithFlash($target);
        }

        if (mediaHasUsage($media)) {
            mediaFlashSet('error', 'Použité médium nelze smazat, dokud je vložené v obsahu.');
            mediaAdminRedirectWithFlash($target);
        }

        mediaDeletePhysicalFiles($media);
        $pdo->prepare("DELETE FROM cms_media WHERE id = ?")->execute([$mediaId]);
        mediaFlashSet('success', 'Soubor byl smazán.');
        logAction('media_delete', 'id=' . $mediaId);
        mediaAdminRedirectWithFlash($target);
    }

    if ($action === 'bulk') {
        $selectedIds = array_values(array_unique(array_filter(array_map(
            static fn ($value): int => (int)$value,
            (array)($_POST['media_ids'] ?? [])
        ), static fn (int $value): bool => $value > 0)));

        $bulkAction = trim((string)($_POST['bulk_action'] ?? ''));
        $bulkCollectionId = inputInt('post', 'bulk_collection_id') ?? 0;
        $bulkCollection = $bulkCollectionId > 0 ? mediaCollectionById($pdo, $bulkCollectionId) : null;
        if (in_array($bulkAction, ['assign_collection', 'apply_collection_defaults'], true)
            && ($bulkCollectionId <= 0 || $bulkCollection === null)) {
            mediaFlashSet('error', 'Pro tuto hromadnou akci vyberte existující kolekci.');
            mediaAdminRedirectWithFlash($target);
        }
        if ($selectedIds === []) {
            mediaFlashSet('error', 'Nevybrali jste žádná média.');
            mediaAdminRedirectWithFlash($target);
        }

        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM cms_media WHERE id IN ({$placeholders})");
        $stmt->execute($selectedIds);
        $mediaRows = $stmt->fetchAll();

        $updatedCount = 0;
        $deletedCount = 0;
        $blockedCount = 0;

        foreach ($mediaRows as $media) {
            $mediaId = (int)$media['id'];
            if ($bulkAction === 'make_public') {
                if (normalizeMediaVisibility((string)($media['visibility'] ?? 'public')) !== 'public') {
                    $switchResult = mediaSwitchVisibility($media, 'public');
                    if (!$switchResult['ok']) {
                        $blockedCount++;
                        continue;
                    }
                    $pdo->prepare("UPDATE cms_media SET visibility = 'public' WHERE id = ?")->execute([$mediaId]);
                    $updatedCount++;
                }
                continue;
            }

            if ($bulkAction === 'make_private') {
                if (mediaHasUsage($media)) {
                    $blockedCount++;
                    continue;
                }
                if (normalizeMediaVisibility((string)($media['visibility'] ?? 'public')) !== 'private') {
                    $switchResult = mediaSwitchVisibility($media, 'private');
                    if (!$switchResult['ok']) {
                        $blockedCount++;
                        continue;
                    }
                    $pdo->prepare("UPDATE cms_media SET visibility = 'private' WHERE id = ?")->execute([$mediaId]);
                    $updatedCount++;
                }
                continue;
            }

            if ($bulkAction === 'assign_collection') {
                $pdo->prepare("UPDATE cms_media SET collection_id = ?, updated_at = NOW() WHERE id = ?")->execute([$bulkCollectionId, $mediaId]);
                $updatedCount++;
                continue;
            }

            if ($bulkAction === 'apply_collection_defaults') {
                $pdo->prepare(
                    "UPDATE cms_media
                     SET collection_id = ?,
                         credit = CASE WHEN TRIM(COALESCE(credit, '')) = '' THEN ? ELSE credit END,
                         license_label = CASE WHEN TRIM(COALESCE(license_label, '')) = '' THEN ? ELSE license_label END,
                         license_url = CASE WHEN TRIM(COALESCE(license_url, '')) = '' THEN ? ELSE license_url END,
                         updated_at = NOW()
                     WHERE id = ?"
                )->execute([
                    $bulkCollectionId,
                    trim((string)($bulkCollection['default_credit'] ?? '')),
                    trim((string)($bulkCollection['default_license_label'] ?? '')),
                    normalizeMediaLicenseUrl((string)($bulkCollection['default_license_url'] ?? '')),
                    $mediaId,
                ]);
                $updatedCount++;
                continue;
            }

            if ($bulkAction === 'delete_unused') {
                if (mediaHasUsage($media)) {
                    $blockedCount++;
                    continue;
                }
                mediaDeletePhysicalFiles($media);
                $pdo->prepare("DELETE FROM cms_media WHERE id = ?")->execute([$mediaId]);
                $deletedCount++;
            }
        }

        if ($bulkAction === 'make_public') {
            mediaFlashSet('success', 'Veřejně označeno ' . $updatedCount . ' médií.');
        } elseif ($bulkAction === 'make_private') {
            mediaFlashSet('success', 'Soukromě označeno ' . $updatedCount . ' médií.');
        } elseif ($bulkAction === 'assign_collection') {
            mediaFlashSet('success', 'Do kolekce přiřazeno ' . $updatedCount . ' médií.');
        } elseif ($bulkAction === 'apply_collection_defaults') {
            mediaFlashSet('success', 'Výchozí metadata z kolekce doplněna u ' . $updatedCount . ' médií.');
        } elseif ($bulkAction === 'delete_unused') {
            mediaFlashSet('success', 'Smazáno ' . $deletedCount . ' nepoužitých médií.');
        } else {
            mediaFlashSet('error', 'Neplatná hromadná akce.');
            mediaAdminRedirectWithFlash($target);
        }

        if ($blockedCount > 0) {
            mediaFlashSet('error', 'U ' . $blockedCount . ' médií byla akce zablokována kvůli použití nebo chybě přesunu.');
        }

        logAction('media_bulk', 'action=' . $bulkAction . ';count=' . count($mediaRows));
        mediaAdminRedirectWithFlash($target);
    }
}

$flash = mediaFlashPull();
$successMessages = array_values(array_filter((array)($flash['success'] ?? []), 'is_string'));
$errorMessages = array_values(array_filter((array)($flash['error'] ?? []), 'is_string'));

$allItems = mediaAdminFetchItems($pdo, $state);
$totalItems = count($allItems);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
if ($state['page'] > $totalPages) {
    $state['page'] = $totalPages;
}
$offset = ($state['page'] - 1) * $perPage;
$items = array_slice($allItems, $offset, $perPage);

$usageFlags = [];
foreach ($items as $item) {
    $usageFlags[(int)$item['id']] = mediaHasUsage($item);
}

$editItem = $state['edit'] > 0 ? mediaGetById($state['edit']) : null;
$editUsages = $editItem ? mediaFindUsages($editItem, 50) : [];
$uploaderOptions = mediaAdminUploaderOptions($pdo);
$collectionOptions = mediaCollectionOptions($pdo);
$editCollection = $state['collection_edit'] > 0 ? mediaCollectionById($pdo, $state['collection_edit']) : null;
$basePath = mediaAdminPath(array_merge($state, ['page' => 1, 'edit' => 0]));
$pagerBase = $basePath . (str_contains($basePath, '?') ? '&' : '?');

adminHeader('Knihovna médií');
?>
<?php foreach ($successMessages as $message): ?>
  <p class="success" role="status"><?= h($message) ?></p>
<?php endforeach; ?>
<?php foreach ($errorMessages as $message): ?>
  <p class="error" role="alert"><?= h($message) ?></p>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" novalidate class="media-upload-form">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="action" value="upload">
  <input type="hidden" name="return_to" value="<?= h(mediaAdminPath($state)) ?>">
  <fieldset>
    <legend>Nahrát soubory do knihovny</legend>
    <label for="media_files">Vyberte soubory <span aria-hidden="true">*</span></label>
    <input type="file" id="media_files" name="media_files[]" multiple required aria-required="true"
           accept="image/jpeg,image/png,image/gif,image/webp,audio/*,video/*,.pdf,.zip,.doc,.docx,.xls,.xlsx,.csv,.txt"
           aria-describedby="media-upload-help">
    <small id="media-upload-help" class="field-help">Obrázky, audio, video a dokumenty. SVG už knihovna z bezpečnostních důvodů nepřijímá. Max 10 MB na soubor.</small>

    <label for="upload_collection_id">Kolekce médií</label>
    <select id="upload_collection_id" name="upload_collection_id" aria-describedby="upload-collection-help">
      <option value="">Bez kolekce</option>
      <?php foreach ($collectionOptions as $collection): ?>
        <option value="<?= (int)$collection['id'] ?>"><?= h((string)$collection['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <small id="upload-collection-help" class="field-help">Při uploadu do kolekce se použije její výchozí viditelnost, kredit a licence.</small>

    <label for="upload_visibility">Viditelnost nových souborů</label>
    <select id="upload_visibility" name="upload_visibility">
      <?php foreach (mediaVisibilityOptions() as $visibilityValue => $visibilityLabel): ?>
        <option value="<?= h($visibilityValue) ?>"><?= h($visibilityLabel) ?></option>
      <?php endforeach; ?>
    </select>
    <small class="field-help">Veřejná média lze vkládat do obsahu. Soukromá média se vydávají jen přes chráněný endpoint pro správce obsahu. Pokud vyberete kolekci, má přednost výchozí viditelnost kolekce.</small>

    <button type="submit" class="btn media-upload-submit">Nahrát soubory</button>
  </fieldset>
</form>

<section class="media-collections-section" aria-labelledby="media-collections-heading">
  <h2 id="media-collections-heading">Kolekce médií</h2>
  <p class="field-help">Kolekce pomáhají držet větší knihovnu jako archiv: nové uploady mohou převzít výchozí viditelnost, kredit a licenci.</p>

  <form method="post" novalidate class="media-collection-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="save_collection">
    <input type="hidden" name="collection_id" value="<?= $editCollection !== null ? (int)$editCollection['id'] : 0 ?>">
    <input type="hidden" name="return_to" value="<?= h(mediaAdminPath($state)) ?>">
    <fieldset>
      <legend><?= $editCollection !== null ? 'Upravit kolekci médií' : 'Přidat kolekci médií' ?></legend>

      <label for="collection_name">Název kolekce <span aria-hidden="true">*</span></label>
      <input type="text" id="collection_name" name="collection_name" required aria-required="true" value="<?= h((string)($editCollection['name'] ?? '')) ?>">

      <label for="collection_slug">Slug kolekce</label>
      <input type="text" id="collection_slug" name="collection_slug" aria-describedby="collection-slug-help" value="<?= h((string)($editCollection['slug'] ?? '')) ?>">
      <small id="collection-slug-help" class="field-help">Prázdný slug se vygeneruje z názvu. Slug musí být unikátní.</small>

      <label for="collection_description">Popis kolekce</label>
      <textarea id="collection_description" name="collection_description" rows="3"><?= h((string)($editCollection['description'] ?? '')) ?></textarea>

      <label for="collection_default_visibility">Výchozí viditelnost</label>
      <select id="collection_default_visibility" name="collection_default_visibility">
        <?php foreach (mediaVisibilityOptions() as $visibilityValue => $visibilityLabel): ?>
          <option value="<?= h($visibilityValue) ?>"<?= normalizeMediaVisibility((string)($editCollection['default_visibility'] ?? 'public')) === $visibilityValue ? ' selected' : '' ?>><?= h($visibilityLabel) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="collection_default_credit">Výchozí kredit</label>
      <input type="text" id="collection_default_credit" name="collection_default_credit" value="<?= h((string)($editCollection['default_credit'] ?? '')) ?>">

      <label for="collection_default_license_label">Výchozí licence</label>
      <input type="text" id="collection_default_license_label" name="collection_default_license_label" value="<?= h((string)($editCollection['default_license_label'] ?? '')) ?>">

      <label for="collection_default_license_url">URL licence</label>
      <input type="url" id="collection_default_license_url" name="collection_default_license_url" aria-describedby="collection-license-help" value="<?= h((string)($editCollection['default_license_url'] ?? '')) ?>">
      <small id="collection-license-help" class="field-help">Použijte jen bezpečné adresy začínající http:// nebo https://.</small>

      <label for="collection_sort_order">Pořadí</label>
      <input type="number" id="collection_sort_order" name="collection_sort_order" min="0" step="1" value="<?= (int)($editCollection['sort_order'] ?? 0) ?>">

      <div class="button-row">
        <button type="submit" class="btn btn-success"><?= $editCollection !== null ? 'Uložit kolekci' : 'Přidat kolekci' ?></button>
        <?php if ($editCollection !== null): ?>
          <a href="<?= h(mediaAdminPath(array_merge($state, ['collection_edit' => 0]))) ?>" class="btn">Zrušit úpravu kolekce</a>
        <?php endif; ?>
      </div>
    </fieldset>
  </form>

  <?php if ($collectionOptions === []): ?>
    <p>Zatím není vytvořená žádná kolekce médií.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <caption>Přehled kolekcí médií</caption>
        <thead>
          <tr>
            <th scope="col">Kolekce</th>
            <th scope="col">Výchozí metadata</th>
            <th scope="col">Pořadí</th>
            <th scope="col">Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($collectionOptions as $collection): ?>
            <tr>
              <th scope="row">
                <?= h((string)$collection['name']) ?>
                <span class="table-meta"><?= h((string)$collection['slug']) ?></span>
              </th>
              <td>
                <span class="table-meta"><?= h(mediaVisibilityOptions()[normalizeMediaVisibility((string)$collection['default_visibility'])] ?? 'Veřejné') ?></span>
                <?php if (trim((string)($collection['default_credit'] ?? '')) !== ''): ?>
                  <span class="table-meta">Kredit: <?= h((string)$collection['default_credit']) ?></span>
                <?php endif; ?>
                <?php if (trim((string)($collection['default_license_label'] ?? '')) !== ''): ?>
                  <span class="table-meta">Licence: <?= h((string)$collection['default_license_label']) ?></span>
                <?php endif; ?>
              </td>
              <td><?= (int)$collection['sort_order'] ?></td>
              <td>
                <div class="button-row">
                  <a class="btn" href="<?= h(mediaAdminPath(array_merge($state, ['collection_edit' => (int)$collection['id']]))) ?>">Upravit</a>
                  <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="delete_collection">
                    <input type="hidden" name="collection_id" value="<?= (int)$collection['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= h(mediaAdminPath($state)) ?>">
                    <button type="submit" class="btn btn-danger" data-confirm="Smazat kolekci <?= h((string)$collection['name']) ?>? Média zůstanou zachována.">Smazat</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<form method="get" class="media-filter-form">
  <fieldset>
    <legend>Filtry knihovny médií</legend>
    <div class="media-filter-grid">
      <div>
        <label for="q">Hledat</label>
        <input type="search" id="q" name="q" value="<?= h($state['q']) ?>" placeholder="Název, alt text, titulek, kredit">
      </div>
      <div>
        <label for="type">Typ média</label>
        <select id="type" name="type">
          <?php foreach (mediaAdminTypeOptions() as $value => $label): ?>
            <option value="<?= h($value) ?>"<?= $state['type'] === $value ? ' selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="visibility">Viditelnost</label>
        <select id="visibility" name="visibility">
          <option value="">Veřejná i soukromá</option>
          <?php foreach (mediaVisibilityOptions() as $value => $label): ?>
            <option value="<?= h($value) ?>"<?= $state['visibility'] === $value ? ' selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="collection">Kolekce</label>
        <select id="collection" name="collection">
          <option value="">Všechny kolekce</option>
          <?php foreach ($collectionOptions as $collection): ?>
            <option value="<?= (int)$collection['id'] ?>"<?= (int)$state['collection'] === (int)$collection['id'] ? ' selected' : '' ?>><?= h((string)$collection['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="metadata">Kontrola metadat</label>
        <select id="metadata" name="metadata">
          <?php foreach (mediaAdminMetadataOptions() as $value => $label): ?>
            <option value="<?= h($value) ?>"<?= $state['metadata'] === $value ? ' selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="usage">Použití</label>
        <select id="usage" name="usage">
          <?php foreach (mediaAdminUsageOptions() as $value => $label): ?>
            <option value="<?= h($value) ?>"<?= $state['usage'] === $value ? ' selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="uploader">Nahrál uživatel</label>
        <select id="uploader" name="uploader">
          <option value="">Všichni uživatelé</option>
          <?php foreach ($uploaderOptions as $uploader): ?>
            <option value="<?= (int)$uploader['id'] ?>"<?= (int)$state['uploader'] === (int)$uploader['id'] ? ' selected' : '' ?>><?= h((string)$uploader['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="button-row media-filter-actions">
        <button type="submit" class="btn">Použít filtry</button>
        <?php if ($state['q'] !== '' || $state['type'] !== '' || $state['visibility'] !== '' || $state['usage'] !== '' || $state['metadata'] !== '' || (int)$state['collection'] > 0 || (int)$state['uploader'] > 0): ?>
          <a href="media.php" class="btn">Zrušit</a>
        <?php endif; ?>
      </div>
    </div>
  </fieldset>
</form>

<p class="field-help media-result-summary">
  Nalezeno médií: <strong><?= number_format($totalItems, 0, ',', ' ') ?></strong>.
  Použitá média nelze smazat ani přepnout do soukromého režimu, dokud zůstávají vložená v obsahu.
</p>

<?php if ($items === []): ?>
  <p>Žádná média neodpovídají zadanému filtru.</p>
<?php else: ?>
  <form method="post" class="media-bulk-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="bulk">
    <input type="hidden" name="return_to" value="<?= h(mediaAdminPath($state)) ?>">
    <fieldset>
      <legend>Hromadné akce s médii</legend>
      <div class="button-row media-bulk-actions">
        <label for="bulk_action" class="media-bulk-label">Akce</label>
        <select id="bulk_action" name="bulk_action" class="media-bulk-select">
          <option value="">Vyberte akci</option>
          <option value="make_public">Změnit na veřejné</option>
          <option value="make_private">Změnit na soukromé</option>
          <option value="assign_collection">Přiřadit do kolekce</option>
          <option value="apply_collection_defaults">Doplnit výchozí metadata kolekce</option>
          <option value="delete_unused">Smazat nepoužitá</option>
        </select>
        <label for="bulk_collection_id" class="media-bulk-label">Kolekce pro akci</label>
        <select id="bulk_collection_id" name="bulk_collection_id" class="media-bulk-select">
          <option value="">Vyberte kolekci</option>
          <?php foreach ($collectionOptions as $collection): ?>
            <option value="<?= (int)$collection['id'] ?>"><?= h((string)$collection['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Provést</button>
      </div>

      <div class="media-grid">
        <?php foreach ($items as $item):
            $mediaId = (int)$item['id'];
            $kind = mediaDisplayKind($item);
            $previewUrl = $kind === 'image' ? mediaThumbUrl($item) : '';
            $fileUrl = mediaFileUrl($item);
            $isUsed = $usageFlags[$mediaId] ?? false;
            $isPublic = mediaIsPublic($item);
            $isSvg = mediaIsSvgMime((string)$item['mime_type']);
            $editUrl = mediaAdminPath(array_merge($state, ['edit' => $mediaId]));
            ?>
          <article class="media-card">
            <div class="media-card__header">
              <label class="media-card__checkbox">
                <input type="checkbox" name="media_ids[]" value="<?= $mediaId ?>">
                Vybrat
              </label>
              <span class="status-badge <?= $isUsed ? 'status-badge--published' : 'status-badge--neutral' ?>"><?= $isUsed ? 'Použité' : 'Nepoužité' ?></span>
            </div>

            <?php if ($previewUrl !== ''): ?>
              <a href="<?= h($fileUrl) ?>" target="_blank" rel="noopener noreferrer" class="media-card__link">
                <img src="<?= h($previewUrl) ?>" alt="<?= h(trim((string)($item['alt_text'] ?? '')) !== '' ? (string)$item['alt_text'] : (string)$item['original_name']) ?>" loading="lazy" class="media-card__image">
                <?= newWindowLinkSrOnlySuffix() ?>
              </a>
            <?php else: ?>
              <div class="media-card__placeholder">
                <?= h(strtoupper($kind === 'file' ? ($isSvg ? 'SVG' : 'FILE') : $kind)) ?>
              </div>
            <?php endif; ?>

            <div class="media-card__body">
              <div>
                <strong class="media-card__title"><?= h((string)$item['original_name']) ?></strong>
                <span class="table-meta"><?= h((string)$item['mime_type']) ?> · <?= h(number_format(((int)$item['file_size']) / 1024, 0, ',', ' ')) ?> KB</span>
                <span class="table-meta">Nahrál: <?= h((string)$item['uploader_name']) ?></span>
                <?php if (trim((string)($item['collection_name'] ?? '')) !== ''): ?>
                  <span class="table-meta">Kolekce: <?= h((string)$item['collection_name']) ?></span>
                <?php endif; ?>
              </div>

              <div class="status-stack">
                <span class="status-badge <?= $isPublic ? 'status-badge--published' : 'status-badge--hidden' ?>"><?= $isPublic ? 'Veřejné' : 'Soukromé' ?></span>
                <?php $metadataStatus = mediaMetadataStatus($item); ?>
                <span class="status-badge <?= $metadataStatus === 'complete' ? 'status-badge--published' : 'status-badge--pending' ?>"><?= h(mediaMetadataStatusLabel($item)) ?></span>
                <?php if (trim((string)($item['caption'] ?? '')) !== ''): ?>
                  <span class="table-meta">Titulek: <?= h((string)$item['caption']) ?></span>
                <?php endif; ?>
                <?php if (trim((string)($item['credit'] ?? '')) !== ''): ?>
                  <span class="table-meta">Kredit: <?= h((string)$item['credit']) ?></span>
                <?php endif; ?>
                <?php if (trim((string)($item['license_label'] ?? '')) !== ''): ?>
                  <span class="table-meta">Licence: <?= h((string)$item['license_label']) ?></span>
                <?php endif; ?>
              </div>

              <div class="button-row">
                <a href="<?= h($editUrl) ?>" class="btn">Upravit</a>
                <a href="<?= h($fileUrl) ?>" class="btn" target="_blank" rel="noopener noreferrer">Otevřít<span class="sr-only"> <?= h((string)$item['original_name']) ?></span><?= newWindowLinkSrOnlySuffix() ?></a>
                <?php if ($isPublic): ?>
                  <button type="button" class="btn" data-copy="<?= h($fileUrl) ?>" data-label="<?= h((string)$item['original_name']) ?>">Kopírovat URL</button>
                <?php endif; ?>
              </div>

              <div class="button-row">
                <button type="submit"
                        form="delete-media-<?= $mediaId ?>"
                        class="btn btn-danger"
                        <?= $isUsed ? ' disabled aria-disabled="true"' : '' ?>
                        data-confirm="Smazat soubor <?= h((string)$item['original_name']) ?>?">Smazat<?php if ($isUsed): ?><span class="sr-only"> – <?= h($mediaDeleteDisabledReason) ?></span><?php endif; ?></button>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </fieldset>
  </form>

  <?php foreach ($items as $item): ?>
    <form id="delete-media-<?= (int)$item['id'] ?>" method="post" hidden>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="media_id" value="<?= (int)$item['id'] ?>">
      <input type="hidden" name="return_to" value="<?= h(mediaAdminPath(array_merge($state, ['edit' => 0]))) ?>">
    </form>
  <?php endforeach; ?>

  <?= renderPager($state['page'], $totalPages, $pagerBase, 'Stránkování knihovny médií', 'Předchozí', 'Další') ?>
<?php endif; ?>

<?php if ($editItem !== null): ?>
  <section class="media-edit-section">
    <h2 class="media-edit-title">Úprava média: <?= h((string)$editItem['original_name']) ?></h2>
    <p class="field-help">Zde upravíte metadata, viditelnost a případně nahradíte soubor bez změny jeho ID a odkazů.</p>

    <div class="media-edit-grid">
      <div>
        <form method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="action" value="update_meta">
          <input type="hidden" name="media_id" value="<?= (int)$editItem['id'] ?>">
          <input type="hidden" name="return_to" value="<?= h(mediaAdminPath(array_merge($state, ['edit' => (int)$editItem['id']]))) ?>">
          <fieldset>
            <legend>Metadata a viditelnost</legend>

            <label for="collection_id">Kolekce</label>
            <select id="collection_id" name="collection_id" aria-describedby="collection-id-help">
              <option value="">Bez kolekce</option>
              <?php foreach ($collectionOptions as $collection): ?>
                <option value="<?= (int)$collection['id'] ?>"<?= (int)($editItem['collection_id'] ?? 0) === (int)$collection['id'] ? ' selected' : '' ?>><?= h((string)$collection['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <small id="collection-id-help" class="field-help">Zařazení do kolekce pomáhá filtrovat archiv. Změna kolekce sama nepřepisuje už vyplněná metadata média.</small>

            <label for="alt_text">Alt text</label>
            <input type="text" id="alt_text" name="alt_text" aria-describedby="alt-text-help" value="<?= h((string)($editItem['alt_text'] ?? '')) ?>">
            <?php if (trim((string)($editItem['alt_text'] ?? '')) === '' && mediaIsImageMime((string)($editItem['mime_type'] ?? ''))): ?>
              <small id="alt-text-help" class="field-help media-alt-warning"><strong>Upozornění:</strong> Obrázek nemá vyplněný alt text. Pro přístupnost (WCAG 2.2) jej prosím doplňte.</small>
            <?php else: ?>
              <small id="alt-text-help" class="field-help">Popis obrázku pro čtečky obrazovky a vyhledávače.</small>
            <?php endif; ?>

            <label for="caption">Titulek</label>
            <textarea id="caption" name="caption" rows="3" class="media-caption-textarea"><?= h((string)($editItem['caption'] ?? '')) ?></textarea>

            <label for="description">Popis</label>
            <textarea id="description" name="description" rows="4" aria-describedby="media-description-help"><?= h((string)($editItem['description'] ?? '')) ?></textarea>
            <small id="media-description-help" class="field-help">Delší interní nebo redakční popis média. Picker jej použije ve výsledku hledání, ale nevkládá ho do HTML obrázku.</small>

            <label for="credit">Kredit</label>
            <input type="text" id="credit" name="credit" value="<?= h((string)($editItem['credit'] ?? '')) ?>">

            <label for="license_label">Licence</label>
            <input type="text" id="license_label" name="license_label" value="<?= h((string)($editItem['license_label'] ?? '')) ?>">

            <label for="license_url">URL licence</label>
            <input type="url" id="license_url" name="license_url" aria-describedby="license-url-help" value="<?= h((string)($editItem['license_url'] ?? '')) ?>">
            <small id="license-url-help" class="field-help">Volitelné. Povolené jsou jen adresy začínající http:// nebo https://.</small>

            <label for="visibility_edit">Viditelnost</label>
            <select id="visibility_edit" name="visibility">
              <?php foreach (mediaVisibilityOptions() as $visibilityValue => $visibilityLabel): ?>
                <option value="<?= h($visibilityValue) ?>"<?= normalizeMediaVisibility((string)($editItem['visibility'] ?? 'public')) === $visibilityValue ? ' selected' : '' ?>><?= h($visibilityLabel) ?></option>
              <?php endforeach; ?>
            </select>
            <small class="field-help">Soukromé médium není dostupné veřejně a nevkládá se do veřejného content pickeru.</small>

            <div class="button-row media-edit-actions">
              <button type="submit" class="btn btn-success">Uložit metadata</button>
              <a href="<?= h(mediaAdminPath(array_merge($state, ['edit' => 0]))) ?>" class="btn">Zavřít detail</a>
            </div>
          </fieldset>
        </form>

        <form method="post" enctype="multipart/form-data" novalidate class="media-replace-form">
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="action" value="replace">
          <input type="hidden" name="media_id" value="<?= (int)$editItem['id'] ?>">
          <input type="hidden" name="return_to" value="<?= h(mediaAdminPath(array_merge($state, ['edit' => (int)$editItem['id']]))) ?>">
          <fieldset>
            <legend>Nahradit soubor</legend>
            <label for="replacement_file">Nový soubor</label>
            <input type="file" id="replacement_file" name="replacement_file" required aria-required="true"
                   accept="image/jpeg,image/png,image/gif,image/webp,audio/*,video/*,.pdf,.zip,.doc,.docx,.xls,.xlsx,.csv,.txt">
            <small class="field-help">Náhradní soubor musí zůstat ve stejné MIME rodině. U veřejných souborů se zachovává i přípona, aby staré odkazy zůstaly funkční.</small>
            <button type="submit" class="btn media-replace-submit">Nahradit soubor</button>
          </fieldset>
        </form>
      </div>

      <div>
        <fieldset>
          <legend>Informace o souboru</legend>
          <dl class="media-info-list">
            <dt><strong>ID</strong></dt>
            <dd><?= (int)$editItem['id'] ?></dd>
            <dt><strong>Název</strong></dt>
            <dd class="media-info-list__value--break"><?= h((string)$editItem['original_name']) ?></dd>
            <dt><strong>MIME</strong></dt>
            <dd><?= h((string)$editItem['mime_type']) ?></dd>
            <dt><strong>Velikost</strong></dt>
            <dd><?= h(number_format(((int)$editItem['file_size']) / 1024, 0, ',', ' ')) ?> KB</dd>
            <dt><strong>Kolekce</strong></dt>
            <dd><?= h((string)($editItem['collection_id'] ?? '') !== '' && (int)($editItem['collection_id'] ?? 0) > 0 ? (string)(mediaCollectionById($pdo, (int)$editItem['collection_id'])['name'] ?? 'Neznámá kolekce') : 'Bez kolekce') ?></dd>
            <dt><strong>Stav metadat</strong></dt>
            <dd><?= h(mediaMetadataStatusLabel($editItem)) ?></dd>
            <dt><strong>URL</strong></dt>
            <dd class="media-info-list__value--url"><a href="<?= h(mediaFileUrl($editItem)) ?>" target="_blank" rel="noopener noreferrer"><?= h(mediaFileUrl($editItem)) ?><?= newWindowLinkSrOnlySuffix() ?></a></dd>
            <dt><strong>Použití</strong></dt>
            <dd><?= $editUsages === [] ? 'Nepoužité' : 'Použité na ' . count($editUsages) . ' místě/místech' ?></dd>
          </dl>
        </fieldset>

        <fieldset class="media-usage-fieldset">
          <legend>Použití v obsahu</legend>
          <?php if ($editUsages === []): ?>
            <p class="media-empty-usage">Toto médium zatím není nikde nalezené.</p>
          <?php else: ?>
            <ul class="media-usage-list">
              <?php foreach ($editUsages as $usage): ?>
                <li>
                  <strong><?= h((string)$usage['label']) ?>:</strong>
                  <?php if (trim((string)$usage['admin_url']) !== ''): ?>
                    <a href="<?= h((string)$usage['admin_url']) ?>"><?= h((string)$usage['title']) ?></a>
                  <?php else: ?>
                    <?= h((string)$usage['title']) ?>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </fieldset>
      </div>
    </div>
  </section>
<?php endif; ?>

<script nonce="<?= cspNonce() ?>">
(() => {
  const liveRegion = document.getElementById('a11y-live');
  const setLiveStatus = (message) => {
    if (!liveRegion) {
      return;
    }
    liveRegion.textContent = '';
    window.setTimeout(() => {
      liveRegion.textContent = message;
    }, 30);
  };

  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const url = button.getAttribute('data-copy') || '';
      const label = button.getAttribute('data-label') || 'soubor';
      if (!url) {
        return;
      }

      try {
        await navigator.clipboard.writeText(url);
        const originalText = button.textContent;
        button.textContent = 'Zkopírováno';
        setLiveStatus('URL pro ' + label + ' byla zkopírována do schránky.');
        window.setTimeout(() => {
          button.textContent = originalText;
        }, 1600);
      } catch (error) {
        window.prompt('Kopírování do schránky se nepodařilo. Zkopírujte URL ručně:', url);
        setLiveStatus('Kopírování do schránky se nepodařilo, zobrazilo se náhradní dialogové okno.');
      }
    });
  });
})();
</script>

<?php adminFooter(); ?>
