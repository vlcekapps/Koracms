<?php
/**
 * eStránky importér – importuje články, sekce (kategorie) a fotogalerie z XML zálohy eStránek.
 */
require_once __DIR__ . '/../db.php';
requireCapability('import_export_manage', 'Přístup odepřen. Pro import z eStránek nemáte potřebné oprávnění.');

/**
 * Dekóduje hodnotu z eStránky XML – některá pole jsou base64, některá plain text.
 */
function esDecodeValue(string $value): string
{
    if ($value === '') {
        return '';
    }

    $decoded = base64_decode($value, true);
    if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8') && $decoded !== $value) {
        if (preg_match('/^[A-Za-z0-9+\/\r\n]+=*$/', trim($value))) {
            return $decoded;
        }
    }

    return $value;
}

function esParseRow(SimpleXMLElement $row): array
{
    $data = [];
    foreach ($row->tablecolumn as $col) {
        $data[(string)$col['name']] = (string)$col;
    }
    return $data;
}

$showForm = true;

$xmlPath = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (!empty($_FILES['xml_file']['tmp_name']) && is_uploaded_file($_FILES['xml_file']['tmp_name'])) {
        $xmlPath = $_FILES['xml_file']['tmp_name'];
        $showForm = false;
    }
}

if ($showForm) {
    require_once __DIR__ . '/layout.php';
    adminHeader('Import z eStránek');
?>
<p>Import obsahu ze zálohy webu eStránky.cz (XML formát). Importují se články, sekce (jako kategorie blogu), fotoalba a záznamy fotografií.</p>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  <p role="alert" class="error">Zadejte platnou cestu k XML záloze.</p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Zdroj dat</legend>

    <div style="margin-bottom:.75rem">
      <label for="xml_file">XML záloha z eStránek <span aria-hidden="true">*</span></label>
      <input type="file" id="xml_file" name="xml_file" required aria-required="true"
             accept=".xml,application/xml,text/xml"
             aria-describedby="xml-help">
      <small id="xml-help">Vyberte XML soubor zálohy exportovaný z eStránek.</small>
    </div>
  </fieldset>

  <div style="margin-top:1rem">
    <button type="submit" class="btn btn-primary"
            onclick="return confirm('Spustit import? Existující duplicitní záznamy budou přeskočeny.')">Spustit import</button>
    <a href="index.php" class="btn">Zpět</a>
  </div>
</form>

<section style="margin-top:2rem;padding:1rem;background:#fffbe6;border:1px solid #d7b600;border-radius:8px" aria-labelledby="import-info-heading">
  <h2 id="import-info-heading" style="margin-top:0">Co se importuje</h2>
  <ul>
    <li><strong>Články</strong> – z tabulky <code>a_articles</code> (pouze primární jazyk); base64-dekódovaný obsah a titulek</li>
    <li><strong>Sekce → Kategorie</strong> – z tabulky <code>a_sections</code> → kategorie blogu v Kora CMS</li>
    <li><strong>Fotoalba</strong> – z tabulky <code>p_directories</code> → galerie alba s hierarchií</li>
    <li><strong>Fotografie</strong> – záznamy z <code>p_photos</code> (samotné soubory stáhněte přes <a href="estranky_download_photos.php">Stažení fotek z eStránek</a>)</li>
  </ul>
  <p>Duplikáty (stejný název + datum) se automaticky přeskočí. Importují se pouze záznamy v primárním jazyce (lang=1).</p>
</section>

<?php
    adminFooter();
    exit;
}

// ── Streaming progress ──────────────────────────────────────────────────────

set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8"><title>Import z eStránek</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:700px;margin:2rem auto;padding:0 1rem}';
echo '#progress{background:#f5f7fa;border:1px solid #d6d6d6;border-radius:8px;padding:1rem;margin:1rem 0;min-height:150px;max-height:400px;overflow-y:auto;font-size:.9rem;line-height:1.6}';
echo '.done{background:#edf8ef;border:1px solid #2e7d32;border-radius:8px;padding:1rem;margin-top:1rem}</style></head><body>';
echo '<h1>Import z eStránek</h1>';
echo '<div id="progress" role="log" aria-live="polite" aria-label="Průběh importu"></div>';
echo '<div id="result"></div>';

$emit = function (string $message) {
    echo '<script>document.getElementById("progress").innerHTML+="' . addslashes($message) . '<br>";document.getElementById("progress").scrollTop=999999;</script>';
    if (ob_get_level()) ob_flush();
    flush();
};

$pdo = db_connect();

$emit('▸ Načítám XML zálohu…');

$xml = @simplexml_load_file($xmlPath);
if ($xml === false) {
    $emit('✗ Nepodařilo se načíst XML soubor.');
    echo '</body></html>';
    exit;
}

$tables = [];
foreach ($xml->table as $table) {
    $tableName = (string)$table['name'];
    $rows = [];
    foreach ($table->tablerow as $row) {
        $rows[] = esParseRow($row);
    }
    $tables[$tableName] = $rows;
}

$totalRows = array_sum(array_map('count', $tables));
$emit('✓ XML načteno (' . count($tables) . ' tabulek, ' . $totalRows . ' řádků)');

// ── 1. Import sekcí → kategorie blogu ──
$emit('▸ Importuji sekce → kategorie…');
$sectionMap = [];
$sections = $tables['a_sections'] ?? [];
$insertedSections = 0;

foreach ($sections as $sec) {
    $secId = (int)($sec['id'] ?? 0);
    $secTitle = esDecodeValue((string)($sec['title'] ?? ''));
    $lang = (int)($sec['lang'] ?? 1);

    if ($secId <= 0 || $secTitle === '' || $lang !== 1) continue;

    $existing = $pdo->prepare("SELECT id FROM cms_categories WHERE name = ?");
    $existing->execute([$secTitle]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
        $sectionMap[$secId] = (int)$existingId;
    } else {
        $pdo->prepare("INSERT INTO cms_categories (name) VALUES (?)")->execute([$secTitle]);
        $sectionMap[$secId] = (int)$pdo->lastInsertId();
        $insertedSections++;
    }
}
$emit("✓ Sekce → kategorie: <strong>{$insertedSections}</strong> nových (celkem " . count($sections) . ')');

// ── 2. Import článků ──
$emit('▸ Importuji články…');
$articles = $tables['a_articles'] ?? [];
$insertedArticles = 0;
$skippedArticles = 0;

foreach ($articles as $idx => $art) {
    $lang = (int)($art['lang'] ?? 1);
    if ($lang !== 1) { $skippedArticles++; continue; }

    $title = esDecodeValue((string)($art['title'] ?? ''));
    $content = esDecodeValue((string)($art['content'] ?? ''));
    $annotation = esDecodeValue((string)($art['annotation'] ?? ''));
    $urlSlug = esDecodeValue((string)($art['url'] ?? ''));
    $published = (int)($art['publish'] ?? 1);
    $createdAt = (string)($art['created'] ?? date('Y-m-d H:i:s'));
    $updatedAt = (string)($art['updated'] ?? $createdAt);
    $sectionId = (int)($art['section'] ?? 0);

    if ($title === '') { $skippedArticles++; continue; }

    $dupCheck = $pdo->prepare("SELECT id FROM cms_articles WHERE title = ? AND DATE(created_at) = DATE(?)");
    $dupCheck->execute([$title, $createdAt]);
    if ($dupCheck->fetchColumn()) { $skippedArticles++; continue; }

    $perex = trim(strip_tags($annotation));
    if ($perex === '' && str_contains($content, '<!--more-->')) {
        $parts = explode('<!--more-->', $content, 2);
        $perex = trim(strip_tags($parts[0]));
        $content = trim($parts[1]);
    }

    $slug = articleSlug($urlSlug !== '' ? $urlSlug : $title);
    $slug = uniqueArticleSlug($pdo, $slug);

    $categoryId = $sectionMap[$sectionId] ?? null;
    $status = $published ? 'published' : 'pending';

    $pdo->prepare(
        "INSERT INTO cms_articles (title, slug, perex, content, category_id, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([$title, $slug, mb_substr($perex, 0, 500), $content, $categoryId, $status, $createdAt, $updatedAt]);
    $insertedArticles++;

    if ($insertedArticles % 10 === 0) {
        $emit("  … {$insertedArticles} článků");
    }
}
$emit("✓ Články: <strong>{$insertedArticles}</strong> importováno, {$skippedArticles} přeskočeno (celkem " . count($articles) . ')');

// ── 3. Import fotogalerií s hierarchií ──
$emit('▸ Importuji fotoalba…');
$directories = $tables['p_directories'] ?? [];
$dirLangs = $tables['p_directories_lang'] ?? [];
$photos = $tables['p_photos'] ?? [];
$photoLangs = $tables['p_photos_lang'] ?? [];

$dirTitles = [];
foreach ($dirLangs as $dl) {
    if ((int)($dl['lang'] ?? 0) === 1 && (int)($dl['iid'] ?? 0) > 0) {
        $dirTitles[(int)$dl['iid']] = (string)($dl['title'] ?? '');
    }
}

$photoTitles = [];
foreach ($photoLangs as $pl) {
    if ((int)($pl['lang'] ?? 0) === 1 && (int)($pl['iid'] ?? 0) > 0) {
        $photoTitles[(int)$pl['iid']] = (string)($pl['title'] ?? '');
    }
}

$dirInfo = [];
foreach ($directories as $dir) {
    $dirId = (int)($dir['id'] ?? 0);
    if ($dirId <= 0) continue;
    $dirInfo[$dirId] = [
        'title'  => $dirTitles[$dirId] ?? ('Album ' . $dirId),
        'parent' => (int)($dir['parent_directory'] ?? 0),
        'slug'   => esDecodeValue((string)($dir['dir'] ?? '')),
    ];
}

$albumMap = [];
$insertedAlbums = 0;

foreach ($dirInfo as $dirId => $info) {
    $albumTitle = $info['title'] !== '' ? $info['title'] : 'Album ' . $dirId;

    $existing = $pdo->prepare("SELECT id FROM cms_gallery_albums WHERE name = ?");
    $existing->execute([$albumTitle]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
        $albumMap[$dirId] = (int)$existingId;
    } else {
        $albumSlug = uniqueGalleryAlbumSlug($pdo, slugify($info['slug'] ?: $albumTitle) ?: 'album-' . $dirId);
        $pdo->prepare("INSERT INTO cms_gallery_albums (name, slug, description) VALUES (?, ?, '')")
            ->execute([$albumTitle, $albumSlug]);
        $albumMap[$dirId] = (int)$pdo->lastInsertId();
        $insertedAlbums++;
    }
}

$parentSet = 0;
foreach ($dirInfo as $dirId => $info) {
    if ($info['parent'] <= 0 || !isset($albumMap[$info['parent']]) || !isset($albumMap[$dirId])) continue;
    $pdo->prepare("UPDATE cms_gallery_albums SET parent_id = ? WHERE id = ? AND (parent_id IS NULL OR parent_id = 0)")
        ->execute([$albumMap[$info['parent']], $albumMap[$dirId]]);
    $parentSet++;
}
$emit("✓ Fotoalba: <strong>{$insertedAlbums}</strong> nových, {$parentSet} hierarchických vazeb");

// ── 4. Import fotografií ──
$emit('▸ Importuji záznamy fotografií…');
$insertedPhotos = 0;

foreach ($photos as $photo) {
    $photoId = (int)($photo['id'] ?? 0);
    $dirId = (int)($photo['directory'] ?? 0);
    $filename = esDecodeValue((string)($photo['filename'] ?? ''));

    if ($photoId <= 0 || $filename === '' || !isset($albumMap[$dirId])) continue;

    $photoTitle = $photoTitles[$photoId] ?? pathinfo($filename, PATHINFO_FILENAME);
    $safeFilename = preg_replace('/[^a-z0-9_\-\.]/i', '_', $filename);
    $albumId = $albumMap[$dirId];

    $dupCheck = $pdo->prepare("SELECT id FROM cms_gallery_photos WHERE album_id = ? AND (title = ? OR filename = ?)");
    $dupCheck->execute([$albumId, $photoTitle, $safeFilename]);
    if ($dupCheck->fetchColumn()) continue;

    $photoSlug = uniqueGalleryPhotoSlug($pdo, slugify($photoTitle) ?: 'foto-' . $photoId);
    $pdo->prepare("INSERT INTO cms_gallery_photos (album_id, filename, title, slug, sort_order) VALUES (?, ?, ?, ?, ?)")
        ->execute([$albumId, $safeFilename, $photoTitle, $photoSlug, (int)($photo['sort_order'] ?? 0)]);
    $insertedPhotos++;
}
$emit("✓ Fotografie: <strong>{$insertedPhotos}</strong> záznamů (soubory stáhněte přes <a href=\"estranky_download_photos.php\">Stažení fotek</a>)");

logAction('estranky_import', 'xml=' . basename($xmlPath));

// Úklid nahraného souboru
if (is_file($xmlPath)) {
    @unlink($xmlPath);
}

echo '<script>document.getElementById("result").innerHTML=\'<div class="done"><h2>✓ Import dokončen</h2>';
echo '<p>Vše bylo úspěšně importováno.</p>';
echo '<p><a href="estranky_import.php">Nový import</a> · <a href="estranky_download_photos.php">Stáhnout fotky</a> · <a href="blog.php">Články</a> · <a href="gallery_albums.php">Galerie</a> · <a href="index.php">Dashboard</a></p>';
echo '</div>\';</script>';

echo '</body></html>';
