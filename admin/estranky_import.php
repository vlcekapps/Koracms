<?php
/**
 * eStránky importér – importuje články, sekce (kategorie) a fotogalerie z XML zálohy eStránek.
 */
require_once __DIR__ . '/layout.php';
requireCapability('import_export_manage', 'Přístup odepřen. Pro import z eStránek nemáte potřebné oprávnění.');

$pdo = db_connect();
$log = [];
$success = false;
$showForm = true;

/**
 * Dekóduje hodnotu z eStránky XML – některá pole jsou base64, některá plain text.
 * Heuristika: pokusí se dekódovat base64, ověří zda výsledek je platný UTF-8.
 */
function esDecodeValue(string $value): string
{
    if ($value === '') {
        return '';
    }

    $decoded = base64_decode($value, true);
    if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8') && $decoded !== $value) {
        // Ověříme, že to opravdu vypadá jako base64 (neobsahuje mezery = plain text)
        if (preg_match('/^[A-Za-z0-9+\/\r\n]+=*$/', trim($value))) {
            return $decoded;
        }
    }

    return $value;
}

/**
 * Načte sloupce z řádku XML.
 */
function esParseRow(SimpleXMLElement $row): array
{
    $data = [];
    foreach ($row->tablecolumn as $col) {
        $name = (string)$col['name'];
        $data[$name] = (string)$col;
    }
    return $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    set_time_limit(300);

    $xmlPath = trim($_POST['xml_path'] ?? '');

    if ($xmlPath === '' || !is_file($xmlPath)) {
        $log[] = '✗ XML soubor nenalezen: ' . h($xmlPath);
    } else {
        $showForm = false;
        $log[] = '▸ Načítám XML zálohu…';

        $xml = @simplexml_load_file($xmlPath);
        if ($xml === false) {
            $log[] = '✗ Nepodařilo se načíst XML soubor.';
        } else {
            $tables = [];
            foreach ($xml->table as $table) {
                $tableName = (string)$table['name'];
                $rows = [];
                foreach ($table->tablerow as $row) {
                    $rows[] = esParseRow($row);
                }
                $tables[$tableName] = $rows;
            }

            $log[] = '✓ XML načteno (' . count($tables) . ' tabulek, ' . array_sum(array_map('count', $tables)) . ' řádků)';

            // ── 1. Import sekcí → kategorie blogu ──
            $sectionMap = []; // es section id → cms category id
            $sections = $tables['a_sections'] ?? [];
            $insertedSections = 0;

            foreach ($sections as $sec) {
                $secId = (int)($sec['id'] ?? 0);
                $secTitle = esDecodeValue((string)($sec['title'] ?? ''));
                $lang = (int)($sec['lang'] ?? 1);

                if ($secId <= 0 || $secTitle === '' || $lang !== 1) {
                    continue;
                }

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
            $log[] = "✓ Sekce → kategorie: {$insertedSections} nových (celkem " . count($sections) . ")";

            // ── 2. Import článků ──
            $articles = $tables['a_articles'] ?? [];
            $insertedArticles = 0;
            $skippedArticles = 0;

            foreach ($articles as $art) {
                $lang = (int)($art['lang'] ?? 1);
                if ($lang !== 1) {
                    $skippedArticles++;
                    continue;
                }

                $title = esDecodeValue((string)($art['title'] ?? ''));
                $content = esDecodeValue((string)($art['content'] ?? ''));
                $annotation = esDecodeValue((string)($art['annotation'] ?? ''));
                $urlSlug = esDecodeValue((string)($art['url'] ?? ''));
                $published = (int)($art['publish'] ?? 1);
                $createdAt = (string)($art['created'] ?? date('Y-m-d H:i:s'));
                $updatedAt = (string)($art['updated'] ?? $createdAt);
                $sectionId = (int)($art['section'] ?? 0);

                if ($title === '') {
                    $skippedArticles++;
                    continue;
                }

                // Deduplikace
                $dupCheck = $pdo->prepare("SELECT id FROM cms_articles WHERE title = ? AND DATE(created_at) = DATE(?)");
                $dupCheck->execute([$title, $createdAt]);
                if ($dupCheck->fetchColumn()) {
                    $skippedArticles++;
                    continue;
                }

                // Perex z annotation
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
                )->execute([
                    $title, $slug, mb_substr($perex, 0, 500), $content,
                    $categoryId, $status, $createdAt, $updatedAt,
                ]);
                $insertedArticles++;
            }
            $log[] = "✓ Články: {$insertedArticles} importováno, {$skippedArticles} přeskočeno (celkem " . count($articles) . ")";

            // ── 3. Import fotogalerií s hierarchií ──
            $directories = $tables['p_directories'] ?? [];
            $dirLangs = $tables['p_directories_lang'] ?? [];
            $photos = $tables['p_photos'] ?? [];
            $photoLangs = $tables['p_photos_lang'] ?? [];

            // Mapování dir_id → title (z p_directories_lang, klíč = iid, lang=1)
            $dirTitles = [];
            foreach ($dirLangs as $dl) {
                $lang = (int)($dl['lang'] ?? 0);
                $dirId = (int)($dl['iid'] ?? 0);
                if ($lang === 1 && $dirId > 0) {
                    $dirTitles[$dirId] = (string)($dl['title'] ?? '');
                }
            }

            // Mapování photo_id → title (z p_photos_lang, klíč = iid, lang=1)
            $photoTitles = [];
            foreach ($photoLangs as $pl) {
                $lang = (int)($pl['lang'] ?? 0);
                $photoId = (int)($pl['iid'] ?? 0);
                if ($lang === 1 && $photoId > 0) {
                    $photoTitles[$photoId] = (string)($pl['title'] ?? '');
                }
            }

            // Sestavíme hierarchii adresářů: id → {title, parent, slug}
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

            // Importujeme alba ve dvou průchodech:
            // 1. průchod: vložíme všechna alba (bez parent_id)
            // 2. průchod: nastavíme parent_id
            $albumMap = []; // es dir id → cms album id
            $insertedAlbums = 0;

            foreach ($dirInfo as $dirId => $info) {
                $albumTitle = $info['title'];
                if ($albumTitle === '') {
                    $albumTitle = 'Album ' . $dirId;
                }

                $existing = $pdo->prepare("SELECT id FROM cms_gallery_albums WHERE name = ?");
                $existing->execute([$albumTitle]);
                $existingId = $existing->fetchColumn();

                if ($existingId) {
                    $albumMap[$dirId] = (int)$existingId;
                } else {
                    $albumSlug = uniqueGalleryAlbumSlug($pdo, slugify($info['slug'] ?: $albumTitle) ?: 'album-' . $dirId);
                    $pdo->prepare(
                        "INSERT INTO cms_gallery_albums (name, slug, description) VALUES (?, ?, '')"
                    )->execute([$albumTitle, $albumSlug]);
                    $albumMap[$dirId] = (int)$pdo->lastInsertId();
                    $insertedAlbums++;
                }
            }

            // 2. průchod: nastavíme parent_id
            $parentSet = 0;
            foreach ($dirInfo as $dirId => $info) {
                $parentEsId = $info['parent'];
                if ($parentEsId <= 0 || !isset($albumMap[$parentEsId]) || !isset($albumMap[$dirId])) {
                    continue;
                }
                $pdo->prepare("UPDATE cms_gallery_albums SET parent_id = ? WHERE id = ? AND (parent_id IS NULL OR parent_id = 0)")
                    ->execute([$albumMap[$parentEsId], $albumMap[$dirId]]);
                $parentSet++;
            }
            $log[] = "✓ Fotoalba: {$insertedAlbums} nových, {$parentSet} hierarchických vazeb (celkem " . count($directories) . ")";

            $insertedPhotos = 0;
            foreach ($photos as $photo) {
                $photoId = (int)($photo['id'] ?? 0);
                $dirId = (int)($photo['directory'] ?? 0);
                $filename = esDecodeValue((string)($photo['filename'] ?? ''));

                if ($photoId <= 0 || $filename === '' || !isset($albumMap[$dirId])) {
                    continue;
                }

                $photoTitle = $photoTitles[$photoId] ?? pathinfo($filename, PATHINFO_FILENAME);
                $safeFilename = preg_replace('/[^a-z0-9_\-\.]/i', '_', $filename);
                $albumId = $albumMap[$dirId];

                // Deduplikace
                $dupCheck = $pdo->prepare("SELECT id FROM cms_gallery_photos WHERE album_id = ? AND (title = ? OR filename = ?)");
                $dupCheck->execute([$albumId, $photoTitle, $safeFilename]);
                if ($dupCheck->fetchColumn()) {
                    continue;
                }

                $photoSlug = uniqueGalleryPhotoSlug($pdo, slugify($photoTitle) ?: 'foto-' . $photoId);
                $pdo->prepare(
                    "INSERT INTO cms_gallery_photos (album_id, filename, title, slug, sort_order) VALUES (?, ?, ?, ?, ?)"
                )->execute([$albumId, $safeFilename, $photoTitle, $photoSlug, (int)($photo['sort_order'] ?? 0)]);
                $insertedPhotos++;
            }
            $log[] = "✓ Fotografie: {$insertedPhotos} záznamů importováno (celkem " . count($photos) . "; samotné soubory je třeba zkopírovat ručně)";

            $success = true;
            logAction('estranky_import', 'xml=' . basename($xmlPath));
        }
    }
}

adminHeader('Import z eStránek');
?>

<?php if ($showForm): ?>
<p>Import obsahu ze zálohy webu eStránky.cz (XML formát). Importují se články, sekce (jako kategorie blogu), fotoalba a záznamy fotografií.</p>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Zdroj dat</legend>

    <div style="margin-bottom:.75rem">
      <label for="xml_path">Cesta k XML záloze na serveru <span aria-hidden="true">*</span></label>
      <input type="text" id="xml_path" name="xml_path" required aria-required="true"
             style="width:100%;max-width:600px"
             placeholder="C:\Users\vlcek\Downloads\Backup_2026_03_28_08_33_51.xml"
             aria-describedby="xml-help">
      <small id="xml-help">Absolutní cesta k XML souboru zálohy z eStránek.</small>
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
    <li><strong>Články</strong> – z tabulky <code>a_articles</code> (pouze český jazyk); base64-dekódovaný obsah a titulek</li>
    <li><strong>Sekce → Kategorie</strong> – z tabulky <code>a_sections</code> → kategorie blogu v Kora CMS</li>
    <li><strong>Fotoalba</strong> – z tabulky <code>p_directories</code> → galerie alba</li>
    <li><strong>Fotografie</strong> – záznamy z <code>p_photos</code> (samotné soubory stáhněte přes <a href="estranky_download_photos.php">Stažení fotek z eStránek</a>)</li>
  </ul>
  <p>Duplikáty (stejný název + datum) se automaticky přeskočí. Importují se pouze záznamy v českém jazyce (lang=1).</p>
</section>
<?php else: ?>

<section aria-labelledby="import-result-heading">
  <h2 id="import-result-heading"><?= $success ? '✓ Import dokončen' : '✗ Import selhal' ?></h2>
  <ul>
    <?php foreach ($log as $line): ?>
      <li><?= $line ?></li>
    <?php endforeach; ?>
  </ul>
  <div style="margin-top:1rem">
    <a href="estranky_import.php" class="btn">Nový import</a>
    <a href="blog.php" class="btn">Přejít na články</a>
    <a href="index.php" class="btn">Dashboard</a>
  </div>
</section>
<?php endif; ?>

<?php adminFooter(); ?>
