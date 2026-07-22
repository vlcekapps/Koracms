<?php
/**
 * eStránky – stažení fotografií z webu.
 * Dávkové stahování (20 fotek naráz) s automatickým pokračováním.
 */
require_once __DIR__ . '/layout.php';
requireCapability('import_export_manage', 'Přístup odepřen.');

function estrankyPrivatePhotoBatchDirectory(): string
{
    return rtrim(koraStoragePath('imports/estranky_photos'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
}

function estrankyFallbackPhotoBatchDirectory(): string
{
    return dirname(__DIR__) . '/uploads/tmp/estranky_photos/';
}

function estrankyPhotoBatchDirectory(string $storageKey): string
{
    if ($storageKey === 'private') {
        return estrankyPrivatePhotoBatchDirectory();
    }

    return estrankyFallbackPhotoBatchDirectory();
}

function estrankyResolvePhotoBatchStorage(): ?string
{
    if (koraEnsureDirectory(estrankyPrivatePhotoBatchDirectory())) {
        return 'private';
    }
    if (koraEnsureDirectory(estrankyFallbackPhotoBatchDirectory())) {
        return 'fallback';
    }

    return null;
}

/**
 * @param array<string, mixed> $context
 */
function estrankyLogPhotoImportIssue(string $message, array $context = []): void
{
    koraLog('warning', $message, $context);
}

/**
 * @param list<array<string, mixed>> $photoList
 * @return array{id: string, storage: string}|null
 */
function estrankyCreatePhotoBatch(array $photoList): ?array
{
    $storageKey = estrankyResolvePhotoBatchStorage();
    if ($storageKey === null) {
        return null;
    }

    $batchId = 'estranky_' . bin2hex(random_bytes(16));
    $batchPath = estrankyPhotoBatchDirectory($storageKey) . $batchId . '.json';
    $encoded = json_encode($photoList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return null;
    }

    return file_put_contents($batchPath, $encoded) !== false
        ? ['id' => $batchId, 'storage' => $storageKey]
        : null;
}

/**
 * @return list<array<string, mixed>>
 */
function estrankyLoadPhotoBatch(string $batchId, string $storageKey): array
{
    $batchPath = estrankyPhotoBatchDirectory($storageKey) . basename($batchId) . '.json';
    if (!is_file($batchPath)) {
        return [];
    }

    $decoded = json_decode((string)file_get_contents($batchPath), true);
    return is_array($decoded) ? $decoded : [];
}

function estrankyDeletePhotoBatch(?string $batchId, ?string $storageKey): void
{
    if ($batchId === null || $batchId === '' || $storageKey === null || $storageKey === '') {
        return;
    }

    $batchPath = estrankyPhotoBatchDirectory($storageKey) . basename($batchId) . '.json';
    if (is_file($batchPath) && !@unlink($batchPath)) {
        estrankyLogPhotoImportIssue('estranky photo batch cleanup failed', [
            'storage' => $storageKey,
            'batch_id_hash' => hash('sha256', basename($batchId)),
            'path_hash' => hash('sha256', $batchPath),
        ]);
    }
}

function estrankyFetchRemotePhoto(string $url, ?int $maxBytes = null): string|false
{
    $safeUrl = normalizeServerFetchUrl($url, false);
    if ($safeUrl === '' || !function_exists('curl_init')) {
        return false;
    }

    $parts = parse_url($safeUrl);
    if (!is_array($parts)) {
        return false;
    }

    $host = trim((string)($parts['host'] ?? ''), '[]');
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
    $addresses = serverFetchResolvedAddresses($host);
    if ($host === '' || $addresses === []) {
        return false;
    }

    $maxBytes ??= koraDefaultUploadMaxSizeBytes();
    $maxBytes = max(1, $maxBytes);
    $data = '';
    $tooLarge = false;
    $curlHandle = curl_init($safeUrl);
    if ($curlHandle === false) {
        return false;
    }

    $resolvedAddress = $addresses[0];
    $resolveAddress = str_contains($resolvedAddress, ':') ? '[' . $resolvedAddress . ']' : $resolvedAddress;
    curl_setopt_array($curlHandle, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'KoraCMS-eStrankyImport/1.0',
        CURLOPT_HTTPHEADER => ['Accept: image/jpeg,image/png,image/gif,image/webp'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $resolveAddress],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$data, &$tooLarge, $maxBytes): int {
            $chunkLength = strlen($chunk);
            if (strlen($data) + $chunkLength > $maxBytes) {
                $tooLarge = true;
                return 0;
            }

            $data .= $chunk;
            return $chunkLength;
        },
    ]);

    $result = curl_exec($curlHandle);
    $httpCode = (int)curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
    curl_close($curlHandle);

    if ($result !== true || $tooLarge || $httpCode < 200 || $httpCode >= 300) {
        return false;
    }

    return $data;
}

$pdo = db_connect();
$log = $_SESSION['import_log'] ?? null;
unset($_SESSION['import_log']);
$estrankyPhotoFieldErrors = $_SESSION['estranky_photo_field_errors'] ?? [];
unset($_SESSION['estranky_photo_field_errors']);
if (!is_array($estrankyPhotoFieldErrors)) {
    $estrankyPhotoFieldErrors = [];
}
$estrankyPhotoFieldErrors = array_filter(array_map('strval', $estrankyPhotoFieldErrors));
$estrankyPhotoFieldErrorNames = array_keys($estrankyPhotoFieldErrors);
$estrankyPhotoFormState = $_SESSION['estranky_photo_form_state'] ?? [];
unset($_SESSION['estranky_photo_form_state']);
if (!is_array($estrankyPhotoFormState)) {
    $estrankyPhotoFormState = [];
}
$estrankyPhotoSiteUrlInput = (string)($estrankyPhotoFormState['site_url'] ?? '');
$estrankyPhotoXmlRequiredErrorMessage = 'Vyberte XML zálohu z eStránek, ze které se má sestavit seznam fotografií ke stažení.';
$estrankyPhotoXmlInvalidErrorMessage = 'XML zálohu se nepodařilo načíst. Nahrajte stejný platný neprázdný XML soubor, který jste použili pro import obsahu z eStránek.';
$estrankyPhotoSiteUrlErrorMessage = 'Zadejte hlavní URL veřejně dostupného webu na eStránkách jako http/https adresu nebo doménu bez schématu. Interní a rezervované adresy, nestandardní porty, přihlašovací údaje a nebezpečná schémata nejsou povolené.';
$estrankyPhotoCurlErrorMessage = 'Server nemá aktivní rozšíření PHP cURL, které je pro bezpečné stahování fotografií z eStránek povinné.';
$estrankyPhotoConfirmErrorMessage = 'Před stahováním potvrďte, že jste zkontroloval(a) XML zálohu, základní URL webu a cílové album pro fotografie.';
$batchSize = 20;

// ── Krok 1: Upload XML a příprava ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $siteUrlRaw = trim((string)($_POST['site_url'] ?? ''));
    $siteUrl = rtrim(normalizeServerFetchUrl($siteUrlRaw), '/');
    $parentAlbumId = inputInt('post', 'parent_album_id');
    $estrankyPhotoConfirmed = isset($_POST['confirm_estranky_photo_download'])
        && (string)$_POST['confirm_estranky_photo_download'] === '1';

    /** @var array<string,mixed> $xmlFile */
    $xmlFile = is_array($_FILES['xml_file'] ?? null) ? $_FILES['xml_file'] : [];
    $upload = koraInspectUploadedFile($xmlFile, [
        'no_file_error' => $estrankyPhotoXmlRequiredErrorMessage,
        'invalid_upload_error' => $estrankyPhotoXmlInvalidErrorMessage,
        'empty_file_error' => $estrankyPhotoXmlInvalidErrorMessage,
    ]);

    $postedFieldErrors = [];
    if (!function_exists('curl_init')) {
        $postedFieldErrors['site_url'] = $estrankyPhotoCurlErrorMessage;
    } elseif ($siteUrl === '') {
        $postedFieldErrors['site_url'] = $estrankyPhotoSiteUrlErrorMessage;
    }
    if (empty($upload['ok'])) {
        $postedFieldErrors['xml_file'] = (string)($upload['error'] ?? $estrankyPhotoXmlInvalidErrorMessage);
    }
    if (!$estrankyPhotoConfirmed) {
        $postedFieldErrors['confirm_estranky_photo_download'] = $estrankyPhotoConfirmErrorMessage;
    }
    if ($postedFieldErrors !== []) {
        $_SESSION['import_log'] = ['<span aria-hidden="true">✗</span> Opravte prosím označená pole a spusťte stahování znovu.'];
        $_SESSION['estranky_photo_field_errors'] = $postedFieldErrors;
        $_SESSION['estranky_photo_form_state'] = ['site_url' => $siteUrlRaw];
        header('Location: estranky_download_photos.php');
        exit;
    }
    $xmlPath = (string)$upload['tmp_path'];

    $xml = @simplexml_load_file($xmlPath);
    if ($xml === false) {
        $_SESSION['import_log'] = ['<span aria-hidden="true">✗</span> ' . h($estrankyPhotoXmlInvalidErrorMessage)];
        $_SESSION['estranky_photo_field_errors'] = ['xml_file' => $estrankyPhotoXmlInvalidErrorMessage];
        $_SESSION['estranky_photo_form_state'] = ['site_url' => $siteUrlRaw];
        header('Location: estranky_download_photos.php');
        exit;
    }

    // Sestavit seznam fotek k stažení
    $photoList = [];
    foreach ($xml->table as $table) {
        if ((string)$table['name'] !== 'p_photos') {
            continue;
        }
        foreach ($table->tablerow as $row) {
            $data = [];
            foreach ($row->tablecolumn as $col) {
                $data[(string)$col['name']] = (string)$col;
            }
            $photoList[] = $data;
        }
    }

    // Kontrola: existují záznamy fotek v DB?
    $dbPhotoCount = (int)$pdo->query("SELECT COUNT(*) FROM cms_gallery_photos")->fetchColumn();
    if ($dbPhotoCount === 0) {
        $_SESSION['import_log'] = [
            '<span aria-hidden="true">⚠</span> V databázi nejsou žádné záznamy fotografií.',
            'Nejprve spusťte <a href="estranky_import.php">Import z eStránek</a>, který vytvoří alba a záznamy fotek. Pak se vraťte sem a stáhněte soubory.',
        ];
        header('Location: estranky_download_photos.php');
        exit;
    }

    // Uložit do session pro dávkové zpracování
    $batchInfo = estrankyCreatePhotoBatch($photoList);
    if ($batchInfo === null) {
        $_SESSION['import_log'] = ['<span aria-hidden="true">✗</span> Nepodařilo se připravit dávkové stahování fotografií.'];
        header('Location: estranky_download_photos.php');
        exit;
    }

    $_SESSION['photo_dl'] = [
        'batch_id' => $batchInfo['id'],
        'batch_storage' => $batchInfo['storage'],
        'site_url' => $siteUrl,
        'parent_album_id' => $parentAlbumId,
        'offset' => 0,
        'downloaded' => 0,
        'skipped' => 0,
        'failed' => 0,
        'total' => count($photoList),
        'csrf' => csrfToken(),
    ];

    header('Location: estranky_download_photos.php?batch=1');
    exit;
}

// ── Krok 2: Dávkové stahování ───────────────────────────────────────────────
if (isset($_GET['batch']) && isset($_SESSION['photo_dl'])) {
    set_time_limit(120);
    $dl = $_SESSION['photo_dl'];
    $siteUrl = $dl['site_url'];
    $offset = $dl['offset'];
    $batchStorage = (string)($dl['batch_storage'] ?? '');
    $allPhotos = estrankyLoadPhotoBatch((string)($dl['batch_id'] ?? ''), $batchStorage);

    if ($allPhotos === []) {
        unset($_SESSION['photo_dl']);
        $_SESSION['import_log'] = ['<span aria-hidden="true">⚠</span> Dávka fotografií už není k dispozici. Nahrajte prosím XML znovu.'];
        header('Location: estranky_download_photos.php');
        exit;
    }

    $photos = array_slice($allPhotos, $offset, $batchSize);

    // Uvolnit session – browser může navigovat jiné stránky během stahování
    session_write_close();

    $destDir = dirname(__DIR__) . '/uploads/gallery/';
    $thumbDir = $destDir . 'thumbs/';
    if (!koraEnsureDirectory($destDir) || !koraEnsureDirectory($thumbDir)) {
        session_start();
        $_SESSION['import_log'] = ['<span aria-hidden="true">✗</span> Nepodařilo se vytvořit cílové adresáře galerie.'];
        unset($_SESSION['photo_dl']);
        estrankyDeletePhotoBatch((string)($dl['batch_id'] ?? ''), $batchStorage);
        header('Location: estranky_download_photos.php');
        exit;
    }

    foreach ($photos as $photo) {
        $photoId = (int)($photo['id'] ?? 0);
        $rawFilename = (string)($photo['filename'] ?? '');

        $decoded = base64_decode($rawFilename, true);
        if ($decoded !== false && preg_match('/\.[a-z]{3,4}$/i', $decoded)) {
            $filename = $decoded;
        } else {
            $filename = $rawFilename;
        }

        if ($photoId <= 0 || $filename === '') {
            $dl['failed']++;
            continue;
        }

        $url = $siteUrl . '/img/original/' . $photoId . '/' . rawurlencode($filename);
        $data = estrankyFetchRemotePhoto($url, koraDefaultUploadMaxSizeBytes());
        if ($data === false || strlen($data) < 100) {
            $dl['failed']++;
            continue;
        }

        $imageInfo = @getimagesizefromstring($data);
        $imageExtension = is_array($imageInfo) ? match ((int)$imageInfo[2]) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp',
            default => '',
        } : '';
        if ($imageExtension === '') {
            $dl['failed']++;
            continue;
        }

        $filenameStem = preg_replace('/[^a-z0-9_-]/i', '_', pathinfo($filename, PATHINFO_FILENAME));
        $filenameStem = trim((string)$filenameStem, '_-');
        $safeFilename = ($filenameStem !== '' ? $filenameStem : 'photo_' . $photoId) . '.' . $imageExtension;
        $destFile = $destDir . $safeFilename;
        if (is_file($destFile)) {
            $dl['skipped']++;
            continue;
        }
        if (file_put_contents($destFile, $data) !== false) {
            gallery_make_thumb($destFile, $thumbDir . $safeFilename, 400);
            generateWebp($destFile);
            generateWebp($thumbDir . $safeFilename);

            try {
                $origTitle = pathinfo($filename, PATHINFO_FILENAME);
                $pdo->prepare(
                    "UPDATE cms_gallery_photos SET filename = ? WHERE filename = ? OR title = ?"
                )->execute([$safeFilename, $filename, $origTitle]);
            } catch (\PDOException $e) {
            }

            $dl['downloaded']++;
        } else {
            $dl['failed']++;
        }
    }

    $dl['offset'] += $batchSize;

    // Znovu otevřít session pro zápis progress
    session_start();
    $_SESSION['photo_dl'] = $dl;

    // Další dávka nebo dokončení?
    if ($dl['offset'] < $dl['total']) {
        session_write_close();
        header('Location: estranky_download_photos.php?batch=1');
        exit;
    }

    // Hotovo – přesun alb pod parent
    if ($dl['parent_album_id'] !== null && $dl['parent_album_id'] > 0) {
        try {
            $pdo->prepare(
                "UPDATE cms_gallery_albums SET parent_id = ? WHERE parent_id IS NULL AND id IN (
                    SELECT DISTINCT album_id FROM cms_gallery_photos WHERE album_id IS NOT NULL
                ) AND id != ?"
            )->execute([$dl['parent_album_id'], $dl['parent_album_id']]);
        } catch (\PDOException $e) {
            estrankyLogPhotoImportIssue('estranky parent album move failed', [
                'parent_album_id' => (int)$dl['parent_album_id'],
                'exception' => $e,
            ]);
        }
    }

    $resultLog = [];
    $resultLog[] = '<span aria-hidden="true">▸</span> Celkem fotek v záloze: ' . $dl['total'];
    $resultLog[] = '<span aria-hidden="true">✓</span> Staženo: <strong>' . $dl['downloaded'] . '</strong>';
    $resultLog[] = '<span aria-hidden="true">▸</span> Přeskočeno (už existují): ' . $dl['skipped'];
    if ($dl['failed'] > 0) {
        $resultLog[] = '<span aria-hidden="true">⚠</span> Neúspěšných: ' . $dl['failed'];
    }

    logAction('estranky_download_photos', "total={$dl['total']} downloaded={$dl['downloaded']} skipped={$dl['skipped']} failed={$dl['failed']}");

    $_SESSION['import_log'] = $resultLog;
    unset($_SESSION['photo_dl']);
    estrankyDeletePhotoBatch((string)($dl['batch_id'] ?? ''), $batchStorage);
    header('Location: estranky_download_photos.php');
    exit;
}

// ── Zobrazení stránky ────────────────────────────────────────────────────────
$isDownloading = false;
if (isset($_SESSION['photo_dl'])) {
    $currentBatchId = (string)($_SESSION['photo_dl']['batch_id'] ?? '');
    $currentBatchStorage = (string)($_SESSION['photo_dl']['batch_storage'] ?? '');
    if ($currentBatchId !== '' && $currentBatchStorage !== '' && estrankyLoadPhotoBatch($currentBatchId, $currentBatchStorage) !== []) {
        $isDownloading = true;
    } else {
        estrankyDeletePhotoBatch($currentBatchId, $currentBatchStorage);
        unset($_SESSION['photo_dl']);
        if ($log === null) {
            $log = ['<span aria-hidden="true">⚠</span> Předchozí dávka fotografií nebyla dokončena a byla vyčištěna. Nahrajte prosím XML znovu.'];
        }
    }
}

$downloadState = [];
$downloadProgress = 0;
if ($isDownloading) {
    $downloadState = $_SESSION['photo_dl'];
    $downloadTotal = (int)($downloadState['total'] ?? 0);
    $downloadOffset = (int)($downloadState['offset'] ?? 0);
    $downloadProgress = $downloadTotal > 0 ? round($downloadOffset / $downloadTotal * 100) : 0;
}

$albumsForSelect = !$isDownloading
    ? $pdo->query("SELECT id, name FROM cms_gallery_albums ORDER BY name")->fetchAll()
    : [];

adminHeader('Stažení fotografií z eStránek');
?>

<?php if ($isDownloading): ?>
  <section class="admin-panel admin-panel--info" role="status" aria-live="polite" aria-labelledby="dl-progress-heading">
    <h2 id="dl-progress-heading" class="admin-panel__heading">Stahování probíhá…</h2>
    <p>Zpracováno <?= (int)$downloadState['offset'] ?> z <?= (int)$downloadState['total'] ?> fotografií (<?= $downloadProgress ?>%).</p>
    <p>Staženo: <?= (int)$downloadState['downloaded'] ?> | Přeskočeno: <?= (int)$downloadState['skipped'] ?> | Neúspěšných: <?= (int)$downloadState['failed'] ?></p>
    <progress class="admin-progress admin-progress--lg" value="<?= (int)$downloadState['offset'] ?>" max="<?= (int)$downloadState['total'] ?>"><?= $downloadProgress ?>%</progress>
    <p class="admin-panel__footer"><small>Stránka se automaticky obnovuje po každé dávce <?= $batchSize ?> fotek.</small></p>
  </section>
<?php endif; ?>

<?php if ($log !== null): ?>
  <?php
    $estrankyPhotoLogHasError = false;
    $estrankyPhotoLogHasWarning = false;
    foreach ((array)$log as $line) {
        $estrankyPhotoLogHasError = $estrankyPhotoLogHasError || str_contains((string)$line, '✗');
        $estrankyPhotoLogHasWarning = $estrankyPhotoLogHasWarning || str_contains((string)$line, '⚠');
    }
    $estrankyPhotoPanelClass = $estrankyPhotoLogHasError ? 'admin-panel--danger' : ($estrankyPhotoLogHasWarning ? 'admin-panel--warning' : 'admin-panel--success');
    $estrankyPhotoHeadingIcon = $estrankyPhotoLogHasError ? '✗' : ($estrankyPhotoLogHasWarning ? '⚠' : '✓');
    $estrankyPhotoHeadingText = $estrankyPhotoLogHasError ? 'Stahování se nepodařilo připravit' : ($estrankyPhotoLogHasWarning ? 'Stahování vyžaduje pozornost' : 'Stahování dokončeno');
    ?>
  <section class="admin-panel <?= h($estrankyPhotoPanelClass) ?>" aria-labelledby="dl-result-heading"<?= $estrankyPhotoLogHasError ? ' role="alert"' : ' role="status"' ?>>
    <h2 id="dl-result-heading" class="admin-panel__heading"><span aria-hidden="true"><?= h($estrankyPhotoHeadingIcon) ?></span> <?= h($estrankyPhotoHeadingText) ?></h2>
    <ul class="admin-panel__list">
      <?php foreach ($log as $line): ?>
        <li><?= $line ?></li>
      <?php endforeach; ?>
    </ul>
    <p class="admin-panel__footer"><a href="gallery_albums.php">Galerie</a> · <a href="estranky_download_photos.php">Stáhnout znovu</a></p>
  </section>
<?php endif; ?>

<?php if (!$isDownloading): ?>
<p>Stáhne originální fotografie z webu eStránek podle XML zálohy. Fotky se uloží do <code>uploads/gallery/</code> a propojí se s importovanými záznamy galerie.</p>

<form method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Parametry stahování</legend>

    <div class="admin-field-row">
      <label for="xml_file">XML záloha z eStránek <span aria-hidden="true">*</span></label>
      <input type="file" id="xml_file" name="xml_file" required aria-required="true"
             accept=".xml,application/xml,text/xml"
             <?= adminFieldAttributes('xml_file', $estrankyPhotoFieldErrorNames, [], ['xml-help'], 'xml-error') ?>>
      <small id="xml-help">Stejný XML soubor zálohy, který jste použili pro import obsahu.</small>
      <?php adminRenderFieldError('xml_file', $estrankyPhotoFieldErrorNames, [], $estrankyPhotoFieldErrors['xml_file'] ?? '', 'xml-error'); ?>
    </div>

    <div class="admin-field-row">
      <label for="site_url">URL webu na eStránkách <span aria-hidden="true">*</span></label>
      <input type="url" id="site_url" name="site_url" required aria-required="true"
             class="admin-input-wide"
             placeholder="https://www.example.cz"
             value="<?= h($estrankyPhotoSiteUrlInput) ?>"
             <?= adminFieldAttributes('site_url', $estrankyPhotoFieldErrorNames, [], ['url-help'], 'site-url-error') ?>>
      <small id="url-help">Hlavní veřejná URL webu na eStránkách. Pokud nezadáte https://, doplní se automaticky. Import vyžaduje cURL, ověřuje TLS a nepovolí interní adresu ani nestandardní port.</small>
      <?php adminRenderFieldError('site_url', $estrankyPhotoFieldErrorNames, [], $estrankyPhotoFieldErrors['site_url'] ?? '', 'site-url-error'); ?>
    </div>

    <div class="admin-field-row">
      <label for="parent_album_id">Importovat alba do:</label>
      <select id="parent_album_id" name="parent_album_id" class="admin-select-md" aria-describedby="album-help">
        <option value="0">Nikam (do kořene galerie)</option>
        <?php foreach ($albumsForSelect as $alb): ?>
          <option value="<?= (int)$alb['id'] ?>"><?= h((string)$alb['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <small id="album-help" class="field-help">Importovaná alba se vytvoří jako podalba vybraného alba. Volba „Nikam" zachová stávající chování.</small>
    </div>
  </fieldset>

  <fieldset>
    <legend>Kontrola před stahováním</legend>
    <p id="estranky-photo-review-help" class="field-help">
      Stahování vytvoří soubory v galerii, náhledy a WebP varianty a může aktualizovat vazby importovaných fotografií. Zkontrolujte XML zálohu, URL webu a cílové album.
    </p>
    <label for="confirm_estranky_photo_download" class="admin-checkbox-label">
      <input type="checkbox" id="confirm_estranky_photo_download" name="confirm_estranky_photo_download" value="1" required
             <?= adminFieldAttributes('confirm_estranky_photo_download', $estrankyPhotoFieldErrorNames, [], ['estranky-photo-review-help'], 'estranky-photo-confirm-error') ?>>
      Potvrzuji, že jsem zkontroloval(a) zdroj fotografií a chci spustit dávkové stahování.
    </label>
    <?php adminRenderFieldError('confirm_estranky_photo_download', $estrankyPhotoFieldErrorNames, [], $estrankyPhotoFieldErrors['confirm_estranky_photo_download'] ?? '', 'estranky-photo-confirm-error'); ?>
  </fieldset>

  <div class="button-row admin-action-row">
    <button type="submit" class="btn">Spustit stahování</button>
    <a href="index.php" class="btn">Zpět</a>
  </div>
</form>

<section class="admin-panel admin-panel--warning admin-panel--spaced" aria-labelledby="dl-info-heading">
  <h2 id="dl-info-heading" class="admin-panel__heading">Jak to funguje</h2>
  <ul>
    <li>Skript projde XML zálohu a zjistí ID a název každé fotky</li>
    <li>Stáhne originál z <code>/img/original/{id}/{filename}</code></li>
    <li>Stahování probíhá po dávkách (<?= $batchSize ?> fotek), stránka se automaticky obnovuje</li>
    <li>Uloží do <code>uploads/gallery/</code>, vytvoří thumbnail a WebP verzi</li>
    <li>Existující soubory se přeskočí (bezpečné pro opakované spuštění)</li>
  </ul>
</section>
<?php endif; ?>

<?php adminFooter(); ?>
