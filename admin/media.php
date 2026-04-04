<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

$pdo = db_connect();
$perPage = 24;

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

function mediaAdminUsageOptions(): array
{
    return [
        '' => 'Použité i nepoužité',
        'used' => 'Jen použité',
        'unused' => 'Jen nepoužité',
    ];
}

function mediaAdminStateFromRequest(): array
{
    $page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $edit = filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $uploader = filter_var($_GET['uploader'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

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

    return [
        'q' => trim((string)($_GET['q'] ?? '')),
        'type' => $type,
        'visibility' => $visibility,
        'uploader' => $uploader !== false ? (int)$uploader : 0,
        'usage' => $usage,
        'page' => $page !== false ? (int)$page : 1,
        'edit' => $edit !== false ? (int)$edit : 0,
    ];
}

function mediaAdminPath(array $state): string
{
    $query = [];
    if (trim((string)($state['q'] ?? '')) !== '') {
        $query['q'] = trim((string)$state['q']);
    }
    if (trim((string)($state['type'] ?? '')) !== '') {
        $query['type'] = trim((string)$state['type']);
    }
    if (trim((string)($state['visibility'] ?? '')) !== '') {
        $query['visibility'] = trim((string)$state['visibility']);
    }
    if ((int)($state['uploader'] ?? 0) > 0) {
        $query['uploader'] = (int)$state['uploader'];
    }
    if (trim((string)($state['usage'] ?? '')) !== '') {
        $query['usage'] = trim((string)$state['usage']);
    }
    if ((int)($state['page'] ?? 1) > 1) {
        $query['page'] = (int)$state['page'];
    }
    if ((int)($state['edit'] ?? 0) > 0) {
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

function mediaAdminFetchItems(PDO $pdo, array $state): array
{
    $where = [];
    $params = [];

    if ($state['q'] !== '') {
        $where[] = '(m.original_name LIKE ? OR m.alt_text LIKE ? OR m.caption LIKE ? OR m.credit LIKE ?)';
        $like = '%' . $state['q'] . '%';
        array_push($params, $like, $like, $like, $like);
    }

    if ($state['visibility'] !== '') {
        $where[] = 'm.visibility = ?';
        $params[] = $state['visibility'];
    }

    if ((int)$state['uploader'] > 0) {
        $where[] = 'm.uploaded_by = ?';
        $params[] = (int)$state['uploader'];
    }

    [$typeSql, $typeParams] = mediaAdminTypeSql((string)$state['type']);
    if ($typeSql !== '') {
        $where[] = $typeSql;
        $params = array_merge($params, $typeParams);
    }

    $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare(
        "SELECT m.*,
                COALESCE(NULLIF(u.nickname, ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), u.email, '—') AS uploader_name
         FROM cms_media m
         LEFT JOIN cms_users u ON u.id = m.uploaded_by
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

    if ($action === 'upload') {
        $uploadVisibility = normalizeMediaVisibility((string)($_POST['upload_visibility'] ?? 'public'));
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

            if (($singleFile['name'] ?? '') === '' && (int)($singleFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $stored = mediaStoreUploadedFile($singleFile, $uploadVisibility);
            if (!$stored['ok']) {
                $errors[] = ($singleFile['name'] ?? 'Soubor') . ': ' . $stored['error'];
                continue;
            }

            $pdo->prepare(
                "INSERT INTO cms_media
                 (filename, original_name, mime_type, file_size, folder, alt_text, caption, credit, visibility, uploaded_by)
                 VALUES (?, ?, ?, ?, 'media', '', '', '', ?, ?)"
            )->execute([
                $stored['filename'],
                $stored['original_name'],
                $stored['mime_type'],
                (int)$stored['file_size'],
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

        $pdo->prepare(
            "UPDATE cms_media
             SET alt_text = ?, caption = ?, credit = ?, visibility = ?
             WHERE id = ?"
        )->execute([
            trim((string)($_POST['alt_text'] ?? '')),
            trim((string)($_POST['caption'] ?? '')),
            trim((string)($_POST['credit'] ?? '')),
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
             SET filename = ?, original_name = ?, mime_type = ?, file_size = ?
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
            static fn($value): int => (int)$value,
            (array)($_POST['media_ids'] ?? [])
        ), static fn(int $value): bool => $value > 0)));

        $bulkAction = trim((string)($_POST['bulk_action'] ?? ''));
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

<form method="post" enctype="multipart/form-data" novalidate style="margin-bottom:1.5rem">
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

    <label for="upload_visibility">Viditelnost nových souborů</label>
    <select id="upload_visibility" name="upload_visibility">
      <?php foreach (mediaVisibilityOptions() as $visibilityValue => $visibilityLabel): ?>
        <option value="<?= h($visibilityValue) ?>"><?= h($visibilityLabel) ?></option>
      <?php endforeach; ?>
    </select>
    <small class="field-help">Veřejná média lze vkládat do obsahu. Soukromá média se vydávají jen přes chráněný endpoint pro správce obsahu.</small>

    <button type="submit" class="btn" style="margin-top:.75rem">Nahrát soubory</button>
  </fieldset>
</form>

<form method="get" style="margin-bottom:1rem">
  <fieldset>
    <legend>Filtry knihovny médií</legend>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;align-items:end">
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
      <div class="button-row" style="margin-top:1.4rem">
        <button type="submit" class="btn">Použít filtry</button>
        <?php if ($state['q'] !== '' || $state['type'] !== '' || $state['visibility'] !== '' || $state['usage'] !== '' || (int)$state['uploader'] > 0): ?>
          <a href="media.php" class="btn">Zrušit</a>
        <?php endif; ?>
      </div>
    </div>
  </fieldset>
</form>

<p class="field-help" style="margin-bottom:1rem">
  Nalezeno médií: <strong><?= number_format($totalItems, 0, ',', ' ') ?></strong>.
  Použitá média nelze smazat ani přepnout do soukromého režimu, dokud zůstávají vložená v obsahu.
</p>

<?php if ($items === []): ?>
  <p>Žádná média neodpovídají zadanému filtru.</p>
<?php else: ?>
  <form method="post" style="margin-bottom:1rem">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="bulk">
    <input type="hidden" name="return_to" value="<?= h(mediaAdminPath($state)) ?>">
    <fieldset>
      <legend>Hromadné akce s médii</legend>
      <div class="button-row" style="margin-bottom:1rem">
        <label for="bulk_action" style="margin-top:0">Akce</label>
        <select id="bulk_action" name="bulk_action" style="max-width:280px">
          <option value="">Vyberte akci</option>
          <option value="make_public">Změnit na veřejné</option>
          <option value="make_private">Změnit na soukromé</option>
          <option value="delete_unused">Smazat nepoužitá</option>
        </select>
        <button type="submit" class="btn">Provést</button>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem">
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
          <article style="border:1px solid #d6d6d6;border-radius:.85rem;background:#fff;overflow:hidden;display:flex;flex-direction:column">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:.55rem .7rem;border-bottom:1px solid #e5e7eb;background:#f8fafc">
              <label style="margin:0;font-weight:600;display:flex;align-items:center;gap:.45rem">
                <input type="checkbox" name="media_ids[]" value="<?= $mediaId ?>">
                Vybrat
              </label>
              <span class="status-badge <?= $isUsed ? 'status-badge--published' : 'status-badge--neutral' ?>"><?= $isUsed ? 'Použité' : 'Nepoužité' ?></span>
            </div>

            <?php if ($previewUrl !== ''): ?>
              <a href="<?= h($fileUrl) ?>" target="_blank" rel="noopener" style="display:block">
                <img src="<?= h($previewUrl) ?>" alt="<?= h(trim((string)($item['alt_text'] ?? '')) !== '' ? (string)$item['alt_text'] : (string)$item['original_name']) ?>" loading="lazy" style="width:100%;aspect-ratio:1;object-fit:cover;display:block">
              </a>
            <?php else: ?>
              <div style="aspect-ratio:1;display:flex;align-items:center;justify-content:center;background:#f0f2f5;color:#334155;font-weight:700">
                <?= h(strtoupper($kind === 'file' ? ($isSvg ? 'SVG' : 'FILE') : $kind)) ?>
              </div>
            <?php endif; ?>

            <div style="padding:.75rem;display:grid;gap:.45rem">
              <div>
                <strong style="display:block;word-break:break-word"><?= h((string)$item['original_name']) ?></strong>
                <span class="table-meta"><?= h((string)$item['mime_type']) ?> · <?= h(number_format(((int)$item['file_size']) / 1024, 0, ',', ' ')) ?> KB</span>
                <span class="table-meta">Nahrál: <?= h((string)$item['uploader_name']) ?></span>
              </div>

              <div class="status-stack">
                <span class="status-badge <?= $isPublic ? 'status-badge--published' : 'status-badge--hidden' ?>"><?= $isPublic ? 'Veřejné' : 'Soukromé' ?></span>
                <?php if (trim((string)($item['caption'] ?? '')) !== ''): ?>
                  <span class="table-meta">Titulek: <?= h((string)$item['caption']) ?></span>
                <?php endif; ?>
                <?php if (trim((string)($item['credit'] ?? '')) !== ''): ?>
                  <span class="table-meta">Kredit: <?= h((string)$item['credit']) ?></span>
                <?php endif; ?>
              </div>

              <div class="button-row">
                <a href="<?= h($editUrl) ?>" class="btn">Upravit</a>
                <a href="<?= h($fileUrl) ?>" class="btn" target="_blank" rel="noopener">Otevřít</a>
                <?php if ($isPublic): ?>
                  <button type="button" class="btn" data-copy="<?= h($fileUrl) ?>" data-label="<?= h((string)$item['original_name']) ?>">Kopírovat URL</button>
                <?php endif; ?>
              </div>

              <div class="button-row">
                <button type="submit"
                        form="delete-media-<?= $mediaId ?>"
                        class="btn btn-danger"
                        <?= $isUsed ? ' disabled aria-disabled="true" title="Použité médium nelze smazat."' : '' ?>
                        data-confirm="Smazat soubor <?= h((string)$item['original_name']) ?>?">Smazat</button>
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
  <section style="margin-top:2rem;border-top:1px solid #d6d6d6;padding-top:1.5rem">
    <h2 style="margin-top:0">Úprava média: <?= h((string)$editItem['original_name']) ?></h2>
    <p class="field-help">Zde upravíte metadata, viditelnost a případně nahradíte soubor bez změny jeho ID a odkazů.</p>

    <div style="display:grid;grid-template-columns:minmax(260px,1fr) minmax(260px,1fr);gap:1.5rem">
      <div>
        <form method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="action" value="update_meta">
          <input type="hidden" name="media_id" value="<?= (int)$editItem['id'] ?>">
          <input type="hidden" name="return_to" value="<?= h(mediaAdminPath(array_merge($state, ['edit' => (int)$editItem['id']]))) ?>">
          <fieldset>
            <legend>Metadata a viditelnost</legend>

            <label for="alt_text">Alt text</label>
            <input type="text" id="alt_text" name="alt_text" aria-describedby="alt-text-help" value="<?= h((string)($editItem['alt_text'] ?? '')) ?>">
            <?php if (trim((string)($editItem['alt_text'] ?? '')) === '' && mediaIsImageMime((string)($editItem['mime_type'] ?? ''))): ?>
              <small id="alt-text-help" class="field-help" style="color:#b42318"><strong>Upozornění:</strong> Obrázek nemá vyplněný alt text. Pro přístupnost (WCAG 2.2) jej prosím doplňte.</small>
            <?php else: ?>
              <small id="alt-text-help" class="field-help">Popis obrázku pro čtečky obrazovky a vyhledávače.</small>
            <?php endif; ?>

            <label for="caption">Titulek</label>
            <textarea id="caption" name="caption" rows="3" style="min-height:7rem"><?= h((string)($editItem['caption'] ?? '')) ?></textarea>

            <label for="credit">Kredit</label>
            <input type="text" id="credit" name="credit" value="<?= h((string)($editItem['credit'] ?? '')) ?>">

            <label for="visibility_edit">Viditelnost</label>
            <select id="visibility_edit" name="visibility">
              <?php foreach (mediaVisibilityOptions() as $visibilityValue => $visibilityLabel): ?>
                <option value="<?= h($visibilityValue) ?>"<?= normalizeMediaVisibility((string)($editItem['visibility'] ?? 'public')) === $visibilityValue ? ' selected' : '' ?>><?= h($visibilityLabel) ?></option>
              <?php endforeach; ?>
            </select>
            <small class="field-help">Soukromé médium není dostupné veřejně a nevkládá se do veřejného content pickeru.</small>

            <div class="button-row" style="margin-top:1rem">
              <button type="submit" class="btn btn-success">Uložit metadata</button>
              <a href="<?= h(mediaAdminPath(array_merge($state, ['edit' => 0]))) ?>" class="btn">Zavřít detail</a>
            </div>
          </fieldset>
        </form>

        <form method="post" enctype="multipart/form-data" novalidate style="margin-top:1rem">
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
            <button type="submit" class="btn" style="margin-top:.8rem">Nahradit soubor</button>
          </fieldset>
        </form>
      </div>

      <div>
        <fieldset>
          <legend>Informace o souboru</legend>
          <dl style="display:grid;grid-template-columns:auto 1fr;gap:.35rem .75rem;margin:0">
            <dt><strong>ID</strong></dt>
            <dd style="margin:0"><?= (int)$editItem['id'] ?></dd>
            <dt><strong>Název</strong></dt>
            <dd style="margin:0;word-break:break-word"><?= h((string)$editItem['original_name']) ?></dd>
            <dt><strong>MIME</strong></dt>
            <dd style="margin:0"><?= h((string)$editItem['mime_type']) ?></dd>
            <dt><strong>Velikost</strong></dt>
            <dd style="margin:0"><?= h(number_format(((int)$editItem['file_size']) / 1024, 0, ',', ' ')) ?> KB</dd>
            <dt><strong>URL</strong></dt>
            <dd style="margin:0;word-break:break-all"><a href="<?= h(mediaFileUrl($editItem)) ?>" target="_blank" rel="noopener"><?= h(mediaFileUrl($editItem)) ?></a></dd>
            <dt><strong>Použití</strong></dt>
            <dd style="margin:0"><?= $editUsages === [] ? 'Nepoužité' : 'Použité na ' . count($editUsages) . ' místě/místech' ?></dd>
          </dl>
        </fieldset>

        <fieldset style="margin-top:1rem">
          <legend>Použití v obsahu</legend>
          <?php if ($editUsages === []): ?>
            <p style="margin:0">Toto médium zatím není nikde nalezené.</p>
          <?php else: ?>
            <ul style="margin:0;padding-left:1.2rem">
              <?php foreach ($editUsages as $usage): ?>
                <li>
                  <strong><?= h((string)$usage['label']) ?>:</strong>
                  <?php if (trim((string)($usage['admin_url'] ?? '')) !== ''): ?>
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
