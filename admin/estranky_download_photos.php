<?php
/**
 * eStránky – stažení fotografií z webu.
 * Projde XML zálohu, zjistí ID a filename fotek a stáhne originály z /img/original/{id}/{filename}.
 */
require_once __DIR__ . '/../db.php';
requireCapability('import_export_manage', 'Přístup odepřen.');

$log = [];
$success = false;
$showForm = true;
$totalPhotos = 0;
$downloaded = 0;
$skipped = 0;
$failed = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    set_time_limit(1800); // 30 minut pro stahování

    $xmlPath = trim($_POST['xml_path'] ?? '');
    $siteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');

    if ($xmlPath === '' || !is_file($xmlPath)) {
        $log[] = '✗ XML soubor nenalezen: ' . h($xmlPath);
    } elseif ($siteUrl === '') {
        $log[] = '✗ URL webu je povinná.';
    } else {
        $showForm = false;

        $xml = @simplexml_load_file($xmlPath);
        if ($xml === false) {
            $log[] = '✗ Nepodařilo se načíst XML soubor.';
        } else {
            // Parsujeme fotky
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
            $destDir = dirname(__DIR__) . '/uploads/gallery/';
            $thumbDir = $destDir . 'thumbs/';

            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

            $log[] = "▸ Nalezeno {$totalPhotos} fotografií v záloze";
            $log[] = '▸ Stahuji z ' . h($siteUrl) . '/img/original/…';

            foreach ($photos as $photo) {
                $photoId = (int)($photo['id'] ?? 0);
                $rawFilename = (string)($photo['filename'] ?? '');

                // Dekódujeme base64 filename
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

                // Cílové jméno – zachováme originální název s prefixem ID
                $safeFilename = preg_replace('/[^a-z0-9_\-\.]/i', '_', $filename);
                $destFile = $destDir . $safeFilename;

                // Přeskočíme existující
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

                // Ověříme, že to je obrázek (první bajty)
                $header = substr($data, 0, 4);
                $isImage = str_starts_with($header, "\xFF\xD8") // JPEG
                        || str_starts_with($header, "\x89PNG")  // PNG
                        || str_starts_with($header, "GIF8")     // GIF
                        || str_starts_with($header, "RIFF");    // WEBP

                if (!$isImage) {
                    $failed++;
                    continue;
                }

                if (file_put_contents($destFile, $data) !== false) {
                    // Thumbnail
                    gallery_make_thumb($destFile, $thumbDir . $safeFilename, 400);

                    // Aktualizujeme filename v DB (pokud už byl importován)
                    try {
                        $pdo = db_connect();
                        $origTitle = pathinfo($filename, PATHINFO_FILENAME);
                        $pdo->prepare(
                            "UPDATE cms_gallery_photos SET filename = ? WHERE filename = ? OR title = ?"
                        )->execute([$safeFilename, $filename, $origTitle]);
                    } catch (\PDOException $e) {
                        // Nemusí existovat záznam
                    }

                    $downloaded++;
                } else {
                    $failed++;
                }
            }

            $log[] = "✓ Staženo: {$downloaded}";
            $log[] = "▸ Přeskočeno (už existují): {$skipped}";
            if ($failed > 0) {
                $log[] = "⚠ Neúspěšných: {$failed}";
            }

            $success = true;
            logAction('estranky_download_photos', "total={$totalPhotos} downloaded={$downloaded} skipped={$skipped} failed={$failed}");
        }
    }
}

require_once __DIR__ . '/layout.php';
adminHeader('Stažení fotografií z eStránek');
?>

<?php if ($showForm): ?>
<p>Stáhne originální fotografie z webu eStránek podle XML zálohy. Fotky se uloží do <code>uploads/gallery/</code> a propojí se s importovanými záznamy galerie.</p>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Parametry stahování</legend>

    <div style="margin-bottom:.75rem">
      <label for="xml_path">Cesta k XML záloze <span aria-hidden="true">*</span></label>
      <input type="text" id="xml_path" name="xml_path" required aria-required="true"
             style="width:100%;max-width:600px"
             placeholder="C:\Users\vlcek\Downloads\Backup_2026_03_28_08_33_51.xml"
             aria-describedby="xml-help">
      <small id="xml-help">Stejný soubor, který jste použili pro import obsahu.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="site_url">URL webu na eStránkách <span aria-hidden="true">*</span></label>
      <input type="url" id="site_url" name="site_url" required aria-required="true"
             style="width:100%;max-width:600px"
             placeholder="https://www.sndopravaka.cz"
             value="https://www.sndopravaka.cz"
             aria-describedby="url-help">
      <small id="url-help">Hlavní URL webu na eStránkách (bez lomítka na konci).</small>
    </div>
  </fieldset>

  <div style="margin-top:1rem">
    <button type="submit" class="btn btn-primary"
            onclick="return confirm('Stáhnout fotografie? U 347 fotek to může trvat několik minut.')">Spustit stahování</button>
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
</section>
<?php else: ?>

<section aria-labelledby="dl-result-heading">
  <h2 id="dl-result-heading"><?= $success ? '✓ Stahování dokončeno' : '✗ Stahování selhalo' ?></h2>
  <ul>
    <?php foreach ($log as $line): ?>
      <li><?= $line ?></li>
    <?php endforeach; ?>
  </ul>
  <div style="margin-top:1rem">
    <a href="estranky_download_photos.php" class="btn">Spustit znovu</a>
    <a href="gallery_albums.php" class="btn">Galerie</a>
    <a href="index.php" class="btn">Dashboard</a>
  </div>
</section>
<?php endif; ?>

<?php adminFooter(); ?>
