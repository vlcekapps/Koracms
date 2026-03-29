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
        error_log('estranky photo batch cleanup failed: ' . $batchPath);
    }
}

function estrankyFetchRemotePhoto(string $url): string|false
{
    if (function_exists('curl_init')) {
        $curlHandle = curl_init($url);
        if ($curlHandle !== false) {
            curl_setopt_array($curlHandle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT => 'KoraCMS-eStrankyImport/1.0',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $data = curl_exec($curlHandle);
            $httpCode = (int)curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
            curl_close($curlHandle);
            if (is_string($data) && $httpCode >= 200 && $httpCode < 400) {
                return $data;
            }
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: KoraCMS-eStrankyImport/1.0\r\n",
            'timeout' => 30,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    return @file_get_contents($url, false, $context);
}

$pdo = db_connect();
$log = $_SESSION['import_log'] ?? null;
unset($_SESSION['import_log']);
$batchSize = 20;

// ── Krok 1: Upload XML a příprava ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['xml_file']['tmp_name'])) {
    verifyCsrf();

    $xmlPath = $_FILES['xml_file']['tmp_name'];
    $siteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');
    if ($siteUrl !== '' && !str_starts_with($siteUrl, 'http://') && !str_starts_with($siteUrl, 'https://')) {
        $siteUrl = 'https://' . $siteUrl;
    }
    $parentAlbumId = inputInt('post', 'parent_album_id');

    if (!is_uploaded_file($xmlPath) || $siteUrl === '') {
        $_SESSION['import_log'] = ['<span aria-hidden="true">✗</span> Zadejte XML soubor a URL webu.'];
        header('Location: estranky_download_photos.php');
        exit;
    }

    $xml = @simplexml_load_file($xmlPath);
    if ($xml === false) {
        $_SESSION['import_log'] = ['<span aria-hidden="true">✗</span> Nepodařilo se načíst XML soubor.'];
        header('Location: estranky_download_photos.php');
        exit;
    }

    // Sestavit seznam fotek k stažení
    $photoList = [];
    foreach ($xml->table as $table) {
        if ((string)$table['name'] !== 'p_photos') continue;
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

        $safeFilename = preg_replace('/[^a-z0-9_\-\.]/i', '_', $filename);
        $destFile = $destDir . $safeFilename;

        if (is_file($destFile)) {
            $dl['skipped']++;
            continue;
        }

        $url = $siteUrl . '/img/original/' . $photoId . '/' . rawurlencode($filename);

        $data = estrankyFetchRemotePhoto($url);

        if ($data === false || strlen($data) < 100) {
            $dl['failed']++;
            continue;
        }

        $header = substr($data, 0, 4);
        $isImage = str_starts_with($header, "\xFF\xD8")
                || str_starts_with($header, "\x89PNG")
                || str_starts_with($header, "GIF8")
                || str_starts_with($header, "RIFF");

        if (!$isImage) {
            $dl['failed']++;
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
            } catch (\PDOException $e) {}

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
            error_log('estranky parent album move: ' . $e->getMessage());
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

adminHeader('Stažení fotografií z eStránek');
?>

<?php if ($isDownloading): ?>
  <?php $dl = $_SESSION['photo_dl']; $progress = $dl['total'] > 0 ? round($dl['offset'] / $dl['total'] * 100) : 0; ?>
  <section style="background:#e3f2fd;border:1px solid #1565c0;border-radius:8px;padding:1rem;margin-bottom:1.5rem" role="status" aria-live="polite" aria-labelledby="dl-progress-heading">
    <h2 id="dl-progress-heading" style="margin-top:0">Stahování probíhá…</h2>
    <p>Zpracováno <?= (int)$dl['offset'] ?> z <?= (int)$dl['total'] ?> fotografií (<?= $progress ?>%).</p>
    <p>Staženo: <?= (int)$dl['downloaded'] ?> | Přeskočeno: <?= (int)$dl['skipped'] ?> | Neúspěšných: <?= (int)$dl['failed'] ?></p>
    <progress value="<?= (int)$dl['offset'] ?>" max="<?= (int)$dl['total'] ?>" style="width:100%;height:1.5rem"><?= $progress ?>%</progress>
    <p style="margin-bottom:0"><small>Stránka se automaticky obnovuje po každé dávce <?= $batchSize ?> fotek.</small></p>
  </section>
<?php endif; ?>

<?php if ($log !== null): ?>
  <section style="background:#edf8ef;border:1px solid #2e7d32;border-radius:8px;padding:1rem;margin-bottom:1.5rem" aria-labelledby="dl-result-heading">
    <h2 id="dl-result-heading" style="margin-top:0"><span aria-hidden="true">✓</span> Stahování dokončeno</h2>
    <ul style="margin:0">
      <?php foreach ($log as $line): ?>
        <li><?= $line ?></li>
      <?php endforeach; ?>
    </ul>
    <p style="margin-bottom:0"><a href="gallery_albums.php">Galerie</a> · <a href="estranky_download_photos.php">Stáhnout znovu</a></p>
  </section>
<?php endif; ?>

<?php if (!$isDownloading): ?>
<p>Stáhne originální fotografie z webu eStránek podle XML zálohy. Fotky se uloží do <code>uploads/gallery/</code> a propojí se s importovanými záznamy galerie.</p>

<form method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Parametry stahování</legend>

    <div style="margin-bottom:.75rem">
      <label for="xml_file">XML záloha z eStránek <span aria-hidden="true">*</span></label>
      <input type="file" id="xml_file" name="xml_file" required aria-required="true"
             accept=".xml,application/xml,text/xml"
             aria-describedby="xml-help">
      <small id="xml-help">Stejný XML soubor zálohy, který jste použili pro import obsahu.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="site_url">URL webu na eStránkách <span aria-hidden="true">*</span></label>
      <input type="url" id="site_url" name="site_url" required aria-required="true"
             style="width:100%;max-width:600px"
             placeholder="https://www.example.cz"
             aria-describedby="url-help">
      <small id="url-help">Hlavní URL webu na eStránkách. Pokud nezadáte https://, doplní se automaticky.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="parent_album_id">Importovat alba do:</label>
      <select id="parent_album_id" name="parent_album_id" style="min-width:200px" aria-describedby="album-help">
        <option value="0">Nikam (do kořene galerie)</option>
        <?php
        $albumsForSelect = $pdo->query("SELECT id, name FROM cms_gallery_albums ORDER BY name")->fetchAll();
        foreach ($albumsForSelect as $alb): ?>
          <option value="<?= (int)$alb['id'] ?>"><?= h((string)$alb['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <small id="album-help" class="field-help">Importovaná alba se vytvoří jako podalba vybraného alba. Volba „Nikam" zachová stávající chování.</small>
    </div>
  </fieldset>

  <div style="margin-top:1rem">
    <button type="submit" class="btn">Spustit stahování</button>
    <a href="index.php" class="btn">Zpět</a>
  </div>
</form>

<section style="margin-top:2rem;padding:1rem;background:#fffbe6;border:1px solid #d7b600;border-radius:8px" aria-labelledby="dl-info-heading">
  <h2 id="dl-info-heading" style="margin-top:0">Jak to funguje</h2>
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
