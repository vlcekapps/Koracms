<?php
/**
 * eStránky – stažení fotografií z webu.
 * Projde XML zálohu, zjistí ID a filename fotek a stáhne originály z /img/original/{id}/{filename}.
 */
require_once __DIR__ . '/../db.php';
requireCapability('import_export_manage', 'Přístup odepřen.');

$showForm = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $xmlPath = trim($_POST['xml_path'] ?? '');
    $siteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');

    if ($xmlPath !== '' && is_file($xmlPath) && $siteUrl !== '') {
        $showForm = false;
    }
}

if ($showForm) {
    require_once __DIR__ . '/layout.php';
    adminHeader('Stažení fotografií z eStránek');
?>
<p>Stáhne originální fotografie z webu eStránek podle XML zálohy. Fotky se uloží do <code>uploads/gallery/</code> a propojí se s importovanými záznamy galerie.</p>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  <p role="alert" class="error">Zadejte platnou cestu k XML záloze a URL webu.</p>
<?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Parametry stahování</legend>

    <div style="margin-bottom:.75rem">
      <label for="xml_path">Cesta k XML záloze <span aria-hidden="true">*</span></label>
      <input type="text" id="xml_path" name="xml_path" required aria-required="true"
             style="width:100%;max-width:600px"
             aria-describedby="xml-help">
      <small id="xml-help">Absolutní cesta k XML souboru zálohy z eStránek.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="site_url">URL webu na eStránkách <span aria-hidden="true">*</span></label>
      <input type="url" id="site_url" name="site_url" required aria-required="true"
             style="width:100%;max-width:600px"
             placeholder="https://www.example.cz"
             aria-describedby="url-help">
      <small id="url-help">Hlavní URL webu na eStránkách (bez lomítka na konci).</small>
    </div>
  </fieldset>

  <div style="margin-top:1rem">
    <button type="submit" class="btn btn-primary"
            onclick="return confirm('Spustit stahování fotografií?')">Spustit stahování</button>
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

<?php
    adminFooter();
    exit;
}

// ── Streaming progress ──────────────────────────────────────────────────────

set_time_limit(1800);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8"><title>Stahování fotek – eStránky</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:700px;margin:2rem auto;padding:0 1rem}';
echo '#progress{background:#f5f7fa;border:1px solid #d6d6d6;border-radius:8px;padding:1rem;margin:1rem 0;min-height:200px;max-height:500px;overflow-y:auto;font-size:.9rem;line-height:1.6}';
echo '.bar{background:#e2e8f0;border-radius:4px;height:24px;margin:1rem 0;overflow:hidden}.bar-fill{background:#1a5fb4;height:100%;transition:width .3s;text-align:center;color:#fff;font-size:.8rem;line-height:24px}';
echo '.done{background:#edf8ef;border:1px solid #2e7d32;border-radius:8px;padding:1rem;margin-top:1rem}</style></head><body>';
echo '<h1>Stahování fotografií z eStránek</h1>';
echo '<div class="bar"><div class="bar-fill" id="bar" style="width:0%">0 %</div></div>';
echo '<div id="progress" role="log" aria-live="polite" aria-label="Průběh stahování"></div>';
echo '<div id="result"></div>';

$emit = function (string $message) {
    echo '<script>document.getElementById("progress").innerHTML+="' . addslashes($message) . '<br>";document.getElementById("progress").scrollTop=999999;</script>';
    if (ob_get_level()) ob_flush();
    flush();
};

$setBar = function (int $current, int $total) {
    $pct = $total > 0 ? min(100, (int)round($current / $total * 100)) : 0;
    echo "<script>document.getElementById('bar').style.width='{$pct}%';document.getElementById('bar').textContent='{$pct} %';</script>";
    if (ob_get_level()) ob_flush();
    flush();
};

$xml = @simplexml_load_file($xmlPath);
if ($xml === false) {
    $emit('✗ Nepodařilo se načíst XML soubor.');
    echo '</body></html>';
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
$emit("▸ Nalezeno <strong>{$totalPhotos}</strong> fotografií v záloze");
$emit('▸ Stahuji z ' . htmlspecialchars($siteUrl) . '/img/original/…');

$destDir = dirname(__DIR__) . '/uploads/gallery/';
$thumbDir = $destDir . 'thumbs/';
if (!is_dir($destDir)) mkdir($destDir, 0755, true);
if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

$downloaded = 0;
$skipped = 0;
$failed = 0;
$pdo = db_connect();

foreach ($photos as $i => $photo) {
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
        $setBar($i + 1, $totalPhotos);
        continue;
    }

    $safeFilename = preg_replace('/[^a-z0-9_\-\.]/i', '_', $filename);
    $destFile = $destDir . $safeFilename;

    if (is_file($destFile)) {
        $skipped++;
        $setBar($i + 1, $totalPhotos);
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
        $emit('⚠ #' . $photoId . ' ' . htmlspecialchars($filename) . ' – stažení selhalo');
        $setBar($i + 1, $totalPhotos);
        continue;
    }

    $header = substr($data, 0, 4);
    $isImage = str_starts_with($header, "\xFF\xD8")
            || str_starts_with($header, "\x89PNG")
            || str_starts_with($header, "GIF8")
            || str_starts_with($header, "RIFF");

    if (!$isImage) {
        $failed++;
        $setBar($i + 1, $totalPhotos);
        continue;
    }

    if (file_put_contents($destFile, $data) !== false) {
        gallery_make_thumb($destFile, $thumbDir . $safeFilename, 400);

        try {
            $origTitle = pathinfo($filename, PATHINFO_FILENAME);
            $pdo->prepare(
                "UPDATE cms_gallery_photos SET filename = ? WHERE filename = ? OR title = ?"
            )->execute([$safeFilename, $filename, $origTitle]);
        } catch (\PDOException $e) {
            // Nemusí existovat záznam
        }

        $downloaded++;
        $emit('✓ #' . $photoId . ' ' . htmlspecialchars($filename) . ' (' . round(strlen($data) / 1024) . ' KB)');
    } else {
        $failed++;
    }

    $setBar($i + 1, $totalPhotos);
}

logAction('estranky_download_photos', "total={$totalPhotos} downloaded={$downloaded} skipped={$skipped} failed={$failed}");

$setBar($totalPhotos, $totalPhotos);
echo '<script>document.getElementById("result").innerHTML=\'<div class="done"><h2>✓ Stahování dokončeno</h2>';
echo '<ul><li>Staženo: <strong>' . $downloaded . '</strong></li>';
echo '<li>Přeskočeno (už existují): <strong>' . $skipped . '</strong></li>';
echo '<li>Neúspěšných: <strong>' . $failed . '</strong></li>';
echo '<li>Celkem fotek v záloze: <strong>' . $totalPhotos . '</strong></li></ul>';
echo '<p><a href="estranky_download_photos.php">Spustit znovu</a> · <a href="gallery_albums.php">Galerie</a> · <a href="index.php">Dashboard</a></p>';
echo '</div>\';</script>';

echo '</body></html>';
