<?php
/**
 * Centrální knihovna médií – upload, prohlížení, mazání souborů.
 */
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

$pdo = db_connect();
$success = '';
$error   = '';

$filterMime = trim($_GET['type'] ?? '');
$filterQ    = trim($_GET['q'] ?? '');
$perPage    = 36;

// ── Upload ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['media_files'])) {
    verifyCsrf();
    $uploadDir = dirname(__DIR__) . '/uploads/media/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedMime = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/aac', 'audio/flac',
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
        'application/pdf', 'application/zip',
        'text/plain', 'text/csv',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    $maxSize = 10 * 1024 * 1024;
    $uploaded = 0;

    $files = $_FILES['media_files'];
    $count = is_array($files['name']) ? count($files['name']) : 0;

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $tmp = $files['tmp_name'][$i];
        $origName = basename($files['name'][$i]);

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if (!in_array($mime, $allowedMime, true)) continue;
        if ($files['size'][$i] > $maxSize) continue;

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $filename = uniqid('m_', true) . '.' . $ext;

        if (move_uploaded_file($tmp, $uploadDir . $filename)) {
            $pdo->prepare(
                "INSERT INTO cms_media (filename, original_name, mime_type, file_size, folder, uploaded_by)
                 VALUES (?, ?, ?, ?, 'media', ?)"
            )->execute([$filename, $origName, $mime, $files['size'][$i], currentUserId()]);

            if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
                $thumbDir = $uploadDir . 'thumbs/';
                if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
                gallery_make_thumb($uploadDir . $filename, $thumbDir . $filename, 300);
                generateWebp($uploadDir . $filename);
            }
            $uploaded++;
        }
    }

    if ($uploaded > 0) {
        $success = "Nahráno {$uploaded} souborů.";
        logAction('media_upload', "count={$uploaded}");
    } else {
        $error = 'Žádný soubor nebyl nahrán. Zkontrolujte typ a velikost.';
    }
}

// ── Smazání ──────────────────────────────────────────────────────────────────
if (isset($_POST['delete_id'])) {
    verifyCsrf();
    $delId = inputInt('post', 'delete_id');
    if ($delId !== null) {
        $media = $pdo->prepare("SELECT filename, folder FROM cms_media WHERE id = ?");
        $media->execute([$delId]);
        $media = $media->fetch();
        if ($media) {
            $dir = dirname(__DIR__) . '/uploads/' . $media['folder'] . '/';
            @unlink($dir . $media['filename']);
            @unlink($dir . 'thumbs/' . $media['filename']);
            $webpName = preg_replace('/\.[a-z]+$/i', '.webp', $media['filename']);
            @unlink($dir . $webpName);
            $pdo->prepare("DELETE FROM cms_media WHERE id = ?")->execute([$delId]);
            $success = 'Soubor smazán.';
            logAction('media_delete', "id={$delId}");
        }
    }
}

// ── Alt text update ──────────────────────────────────────────────────────────
if (isset($_POST['update_alt_id'])) {
    verifyCsrf();
    $altId = inputInt('post', 'update_alt_id');
    $altText = trim($_POST['alt_text'] ?? '');
    if ($altId !== null) {
        $pdo->prepare("UPDATE cms_media SET alt_text = ? WHERE id = ?")->execute([$altText, $altId]);
        $success = 'Alt text uložen.';
    }
}

// ── Výpis ────────────────────────────────────────────────────────────────────
$where = [];
$params = [];
if ($filterMime !== '') {
    $where[] = 'mime_type LIKE ?';
    $params[] = $filterMime . '%';
}
if ($filterQ !== '') {
    $where[] = '(original_name LIKE ? OR alt_text LIKE ?)';
    $params[] = '%' . $filterQ . '%';
    $params[] = '%' . $filterQ . '%';
}
$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$pag = paginate($pdo, "SELECT COUNT(*) FROM cms_media {$whereSql}", $params, $perPage);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pag;

$stmt = $pdo->prepare(
    "SELECT m.*, COALESCE(NULLIF(u.nickname,''), u.email, '–') AS uploader_name
     FROM cms_media m LEFT JOIN cms_users u ON u.id = m.uploaded_by
     {$whereSql} ORDER BY m.created_at DESC LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$items = $stmt->fetchAll();

$editId = inputInt('get', 'edit');

$filterParams = [];
if ($filterMime !== '') $filterParams['type'] = $filterMime;
if ($filterQ !== '') $filterParams['q'] = $filterQ;
$paginBase = BASE_URL . '/admin/media.php' . ($filterParams !== [] ? '?' . http_build_query($filterParams) . '&' : '?');

adminHeader('Knihovna médií');
?>
<?php if ($success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate style="margin-bottom:1.5rem">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nahrát soubory</legend>
    <label for="media_files">Vyberte soubory <span aria-hidden="true">*</span></label>
    <input type="file" id="media_files" name="media_files[]" multiple required aria-required="true"
           accept="image/*,audio/*,video/*,.pdf,.zip,.doc,.docx,.xls,.xlsx,.csv,.txt" aria-describedby="media-upload-help">
    <small id="media-upload-help" class="field-help">Obrázky, audio, video a dokumenty. Max 10 MB na soubor.</small>
    <button type="submit" class="btn" style="margin-top:.5rem">Nahrát</button>
  </fieldset>
</form>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
  <label for="q" class="visually-hidden">Hledat</label>
  <input type="search" id="q" name="q" placeholder="Hledat v médiích…"
         value="<?= h($filterQ) ?>" style="width:250px">
  <label for="type" class="visually-hidden">Typ</label>
  <select id="type" name="type" style="min-width:150px">
    <option value="">Všechny typy</option>
    <option value="image/"<?= $filterMime === 'image/' ? ' selected' : '' ?>>Obrázky</option>
    <option value="audio/"<?= $filterMime === 'audio/' ? ' selected' : '' ?>>Audio</option>
    <option value="video/"<?= $filterMime === 'video/' ? ' selected' : '' ?>>Video</option>
    <option value="application/pdf"<?= $filterMime === 'application/pdf' ? ' selected' : '' ?>>PDF</option>
    <option value="application/"<?= $filterMime === 'application/' ? ' selected' : '' ?>>Dokumenty</option>
  </select>
  <button type="submit" class="btn">Filtrovat</button>
  <?php if ($filterMime !== '' || $filterQ !== ''): ?>
    <a href="media.php" class="btn">Zrušit</a>
  <?php endif; ?>
</form>

<?php if (empty($items)): ?>
  <p>Žádná média<?= ($filterMime !== '' || $filterQ !== '') ? ' pro zadaný filtr' : '' ?>.</p>
<?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem">
    <?php foreach ($items as $m):
      $isImage = str_starts_with($m['mime_type'], 'image/');
      $isAudio = str_starts_with($m['mime_type'], 'audio/');
      $isVideo = str_starts_with($m['mime_type'], 'video/');
      $thumbUrl = $isImage
          ? BASE_URL . '/uploads/' . $m['folder'] . '/thumbs/' . rawurlencode($m['filename'])
          : '';
      $fullUrl = BASE_URL . '/uploads/' . $m['folder'] . '/' . rawurlencode($m['filename']);
      $fileIcon = $isAudio ? 'AUDIO' : ($isVideo ? 'VIDEO' : 'FILE');
    ?>
      <div style="border:1px solid #d6d6d6;border-radius:.75rem;overflow:hidden;background:#fff">
        <?php if ($isImage && $thumbUrl !== ''): ?>
          <a href="<?= h($fullUrl) ?>" target="_blank" rel="noopener">
            <img src="<?= h($thumbUrl) ?>" alt="<?= h($m['alt_text'] ?: $m['original_name']) ?>" loading="lazy"
                 style="width:100%;aspect-ratio:1;object-fit:cover;display:block">
          </a>
        <?php else: ?>
          <div style="aspect-ratio:1;display:flex;align-items:center;justify-content:center;background:#f0f2f5;font-size:2rem" aria-hidden="true">
            <?= h($fileIcon) ?>
          </div>
        <?php endif; ?>
        <div style="padding:.5rem;font-size:.82rem">
          <strong style="word-break:break-word"><?= h($m['original_name']) ?></strong>
          <br><small style="color:#555"><?= h(number_format($m['file_size'] / 1024, 0, ',', ' ')) ?> KB · <?= h($m['mime_type']) ?></small>

          <?php if ($editId === (int)$m['id']): ?>
            <form method="post" style="margin-top:.3rem">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_alt_id" value="<?= (int)$m['id'] ?>">
              <label for="alt-<?= (int)$m['id'] ?>" class="visually-hidden">Alt text</label>
              <input type="text" id="alt-<?= (int)$m['id'] ?>" name="alt_text" value="<?= h($m['alt_text']) ?>"
                     placeholder="Popis obrázku" style="width:100%;font-size:.82rem">
              <button type="submit" class="btn" style="font-size:.78rem;margin-top:.2rem">Uložit alt</button>
            </form>
          <?php else: ?>
            <div style="margin-top:.3rem;display:flex;gap:.3rem;flex-wrap:wrap">
              <a href="media.php?edit=<?= (int)$m['id'] ?><?= $filterMime !== '' ? '&type=' . urlencode($filterMime) : '' ?>" class="btn" style="font-size:.78rem" aria-label="Upravit alt text pro <?= h($m['original_name']) ?>">Alt</a>
              <button type="button" class="btn" style="font-size:.78rem" data-copy="<?= h($fullUrl) ?>" aria-label="Kopírovat URL <?= h($m['original_name']) ?>">URL</button>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="delete_id" value="<?= (int)$m['id'] ?>">
                <button type="submit" class="btn btn-danger" style="font-size:.78rem"
                        data-confirm="Smazat soubor <?= h($m['original_name']) ?>?"
                        aria-label="Smazat <?= h($m['original_name']) ?>">✕</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?= renderPager($page, $pages, $paginBase, 'Stránkování knihovny médií', 'Předchozí', 'Další') ?>
<?php endif; ?>

<script nonce="<?= cspNonce() ?>">
document.querySelectorAll('[data-copy]').forEach(function(btn){
  btn.addEventListener('click', function(){
    navigator.clipboard.writeText(this.dataset.copy).then(function(){
      btn.textContent = '✓';
      setTimeout(function(){ btn.textContent = 'URL'; }, 1500);
    });
  });
});
</script>

<?php adminFooter(); ?>
