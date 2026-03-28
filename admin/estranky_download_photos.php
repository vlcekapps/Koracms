<?php
/**
 * eStránky – stažení fotografií z webu.
 * Projde XML zálohu, zjistí ID a filename fotek a stáhne originály z /img/original/{id}/{filename}.
 */
require_once __DIR__ . '/layout.php';
requireCapability('import_export_manage', 'Přístup odepřen.');

$pdo = db_connect();
$log = $_SESSION['import_log'] ?? null;
unset($_SESSION['import_log']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['xml_file']['tmp_name'])) {
    verifyCsrf();
    set_time_limit(1800);

    $xmlPath = $_FILES['xml_file']['tmp_name'];
    $siteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');
    if ($siteUrl !== '' && !str_starts_with($siteUrl, 'http://') && !str_starts_with($siteUrl, 'https://')) {
        $siteUrl = 'https://' . $siteUrl;
    }
    $parentAlbumId = inputInt('post', 'parent_album_id');

    if (!is_uploaded_file($xmlPath) || $siteUrl === '') {
        $_SESSION['import_log'] = ['✗ Zadejte XML soubor a URL webu.'];
        header('Location: estranky_download_photos.php');
        exit;
    }

    $xml = @simplexml_load_file($xmlPath);
    if ($xml === false) {
        $_SESSION['import_log'] = ['✗ Nepodařilo se načíst XML soubor.'];
        header('Location: estranky_download_photos.php');
        exit;
    }

    $photos = [];
    foreach ($xml->table as $table) {
        if ((string)$table['name'] !== 'p_photos') continue;
        foreach ($table->tablerow as $row) {
            $data = [];
            foreach ($row->tablecolumn as $col) {
                $data[(string)$col['name']] = (string)$col;
            }
            $photos[] = $data;
        }
    }

    $totalPhotos = count($photos);
    $log = [];
    $log[] = "▸ Nalezeno {$totalPhotos} fotografií v záloze";
    $log[] = '▸ Zdroj: ' . h($siteUrl);

    $destDir = dirname(__DIR__) . '/uploads/gallery/';
    $thumbDir = $destDir . 'thumbs/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

    $downloaded = 0;
    $skipped = 0;
    $failed = 0;

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
            $failed++;
            continue;
        }

        $safeFilename = preg_replace('/[^a-z0-9_\-\.]/i', '_', $filename);
        $destFile = $destDir . $safeFilename;

        if (is_file($destFile)) {
            $skipped++;
            continue;
        }

        $url = $siteUrl . '/img/original/' . $photoId . '/' . rawurlencode($filename);

        $ctx = stream_context_create([
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

        $data = @file_get_contents($url, false, $ctx);

        if ($data === false || strlen($data) < 100) {
            $failed++;
            continue;
        }

        $header = substr($data, 0, 4);
        $isImage = str_starts_with($header, "\xFF\xD8")
                || str_starts_with($header, "\x89PNG")
                || str_starts_with($header, "GIF8")
                || str_starts_with($header, "RIFF");

        if (!$isImage) {
            $failed++;
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

            $downloaded++;
        } else {
            $failed++;
        }
    }

    $log[] = "✓ Staženo: <strong>{$downloaded}</strong>";
    $log[] = "▸ Přeskočeno (už existují): {$skipped}";
    if ($failed > 0) {
        $log[] = "⚠ Neúspěšných: {$failed}";
    }
    $log[] = "▸ Celkem fotek v záloze: {$totalPhotos}";

    // Přesunout importované root alba pod vybrané cílové album
    if ($parentAlbumId !== null && $parentAlbumId > 0) {
        try {
            $pdo->prepare(
                "UPDATE cms_gallery_albums SET parent_id = ? WHERE parent_id IS NULL AND id IN (
                    SELECT DISTINCT album_id FROM cms_gallery_photos WHERE album_id IS NOT NULL
                ) AND id != ?"
            )->execute([$parentAlbumId, $parentAlbumId]);
            $parentName = $pdo->prepare("SELECT name FROM cms_gallery_albums WHERE id = ?");
            $parentName->execute([$parentAlbumId]);
            $log[] = '✓ Alba přesunuta pod „' . h((string)($parentName->fetchColumn() ?: '?')) . '"';
        } catch (\PDOException $e) {
            error_log('estranky parent album move: ' . $e->getMessage());
        }
    }

    logAction('estranky_download_photos', "total={$totalPhotos} downloaded={$downloaded} skipped={$skipped} failed={$failed}");
    @unlink($xmlPath);

    $_SESSION['import_log'] = $log;
    header('Location: estranky_download_photos.php');
    exit;
}

adminHeader('Stažení fotografií z eStránek');
?>

<?php if ($log !== null): ?>
  <section style="background:#edf8ef;border:1px solid #2e7d32;border-radius:8px;padding:1rem;margin-bottom:1.5rem" aria-labelledby="dl-result-heading">
    <h2 id="dl-result-heading" style="margin-top:0">✓ Stahování dokončeno</h2>
    <ul style="margin:0">
      <?php foreach ($log as $line): ?>
        <li><?= $line ?></li>
      <?php endforeach; ?>
    </ul>
    <p style="margin-bottom:0"><a href="gallery_albums.php">Galerie</a> · <a href="estranky_download_photos.php">Stáhnout znovu</a></p>
  </section>
<?php endif; ?>

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
      <small id="url-help">Hlavní URL webu na eStránkách (bez lomítka na konci).</small>
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
    <button type="submit" class="btn btn-primary"
            onclick="this.disabled=true;this.textContent='Stahuji fotografie, čekejte prosím…';this.form.submit();return true;">Spustit stahování</button>
    <a href="index.php" class="btn">Zpět</a>
  </div>
</form>

<section style="margin-top:2rem;padding:1rem;background:#fffbe6;border:1px solid #d7b600;border-radius:8px" aria-labelledby="dl-info-heading">
  <h2 id="dl-info-heading" style="margin-top:0">Jak to funguje</h2>
  <ul>
    <li>Skript projde XML zálohu a zjistí ID a název každé fotky</li>
    <li>Stáhne originál z <code>/img/original/{id}/{filename}</code></li>
    <li>Uloží do <code>uploads/gallery/</code> a vytvoří thumbnail</li>
    <li>Aktualizuje filename v DB záznamech galerie</li>
    <li>Existující soubory se přeskočí (bezpečné pro opakované spuštění)</li>
  </ul>
  <p><strong>Poznámka:</strong> Stahování může trvat několik minut (závisí na počtu fotek a rychlosti připojení). Tlačítko se deaktivuje a po dokončení se zobrazí výsledek.</p>
</section>

<?php adminFooter(); ?>
