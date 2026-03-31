<?php
/**
 * eStránky importér – importuje články, sekce (kategorie) a fotogalerie z XML zálohy eStránek.
 */
require_once __DIR__ . '/layout.php';
requireCapability('import_export_manage', 'Přístup odepřen. Pro import z eStránek nemáte potřebné oprávnění.');

function esDecodeValue(string $value): string
{
    if ($value === '') return '';
    $decoded = base64_decode($value, true);
    if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8') && $decoded !== $value) {
        if (preg_match('/^[A-Za-z0-9+\/\r\n]+=*$/', trim($value))) {
            return $decoded;
        }
    }
    return $value;
}

$pdo = db_connect();
$log = $_SESSION['import_log'] ?? null;
unset($_SESSION['import_log']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['xml_file']['tmp_name'])) {
    verifyCsrf();
    set_time_limit(300);

    $xmlPath = $_FILES['xml_file']['tmp_name'];
    if (!is_uploaded_file($xmlPath)) {
        $_SESSION['import_log'] = ['<span aria-hidden="true">✗</span> Neplatný soubor.'];
        header('Location: estranky_import.php');
        exit;
    }

    $log = [];
    $xml = @simplexml_load_file($xmlPath);
    if ($xml === false) {
        $log[] = '<span aria-hidden="true">✗</span> Nepodařilo se načíst XML soubor.';
        $_SESSION['import_log'] = $log;
        header('Location: estranky_import.php');
        exit;
    }

    $tables = [];
    foreach ($xml->table as $table) {
        $tableName = (string)$table['name'];
        $rows = [];
        foreach ($table->tablerow as $row) {
            $data = [];
            foreach ($row->tablecolumn as $col) {
                $data[(string)$col['name']] = (string)$col;
            }
            $rows[] = $data;
        }
        $tables[$tableName] = $rows;
    }

    $totalRows = array_sum(array_map('count', $tables));
    $log[] = '<span aria-hidden="true">✓</span> XML načteno (' . count($tables) . " tabulek, {$totalRows} řádků)";

    // ── 0. Název a popis webu ze settings ──
    $settings = $tables['settings'] ?? [];
    $esSettings = [];
    foreach ($settings as $s) {
        $key = base64_decode((string)($s['identifier'] ?? ''));
        $lang = (int)($s['lang'] ?? 0);
        if ($key !== '' && $lang <= 1) {
            $esSettings[$key] = (string)($s['data'] ?? '');
        }
    }

    $esSiteTitle = trim($esSettings['s_title_text'] ?? '');
    $esSiteDesc = trim($esSettings['user_description'] ?? '');

    // Cílový blog
    $targetBlogId = inputInt('post', 'target_blog_id');
    if ($targetBlogId !== null && $targetBlogId > 0) {
        $targetBlog = getBlogById($targetBlogId);
    } else {
        $targetBlog = null;
    }
    if (!$targetBlog && $esSiteTitle !== '') {
        $blogSlug = slugify($esSiteTitle) ?: 'import';
        $blogSlug = substr($blogSlug, 0, 100);
        $creatorUserId = (int)(currentUserId() ?? 0);
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO cms_blogs (name, slug, description, sort_order, created_by_user_id) VALUES (?, ?, ?, ?, ?)")
                ->execute([
                    $esSiteTitle,
                    $blogSlug,
                    $esSiteDesc,
                    (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM cms_blogs")->fetchColumn(),
                    $creatorUserId > 0 ? $creatorUserId : null,
                ]);
            $createdBlogId = (int)$pdo->lastInsertId();
            if ($creatorUserId > 0 && $createdBlogId > 0) {
                $pdo->prepare(
                    "INSERT INTO cms_blog_members (blog_id, user_id, member_role)
                     VALUES (?, ?, 'manager')
                     ON DUPLICATE KEY UPDATE member_role = VALUES(member_role)"
                )->execute([$createdBlogId, $creatorUserId]);
            }
            $pdo->commit();
            $targetBlog = getBlogById($createdBlogId);
            $log[] = '<span aria-hidden="true">✓</span> Vytvořen blog: ' . $esSiteTitle;
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $targetBlog = getDefaultBlog();
        }
    }
    if (!$targetBlog) {
        $targetBlog = getDefaultBlog();
    }
    $blogId = (int)$targetBlog['id'];

    if ($esSiteTitle !== '' || $esSiteDesc !== '') {
        $pdo->prepare("UPDATE cms_blogs SET name = ?, description = ? WHERE id = ?")
            ->execute([$esSiteTitle !== '' ? $esSiteTitle : $targetBlog['name'], $esSiteDesc !== '' ? $esSiteDesc : ($targetBlog['description'] ?? ''), $blogId]);
        $log[] = '<span aria-hidden="true">✓</span> Blog: ' . ($esSiteTitle !== '' ? $esSiteTitle : $targetBlog['name']) . " (id={$blogId})";
    }

    // ── 1. Sekce → kategorie ──
    $sectionMap = [];
    $sections = $tables['a_sections'] ?? [];
    $insertedSections = 0;
    foreach ($sections as $sec) {
        $secId = (int)($sec['id'] ?? 0);
        $secTitle = esDecodeValue((string)($sec['title'] ?? ''));
        if ($secId <= 0 || $secTitle === '' || (int)($sec['lang'] ?? 1) !== 1) continue;

        $existing = $pdo->prepare("SELECT id FROM cms_categories WHERE name = ? AND blog_id = ?");
        $existing->execute([$secTitle, $blogId]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            $sectionMap[$secId] = (int)$existingId;
        } else {
            $pdo->prepare("INSERT INTO cms_categories (name, blog_id) VALUES (?, ?)")->execute([$secTitle, $blogId]);
            $sectionMap[$secId] = (int)$pdo->lastInsertId();
            $insertedSections++;
        }
    }
    $log[] = "<span aria-hidden=\"true\">✓</span> Sekce → kategorie: {$insertedSections} nových (celkem " . count($sections) . ")";

    // ── 2. Články ──
    $articles = $tables['a_articles'] ?? [];
    $insertedArticles = 0;
    $skippedArticles = 0;
    foreach ($articles as $art) {
        if ((int)($art['lang'] ?? 1) !== 1) { $skippedArticles++; continue; }

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
        $slug = uniqueArticleSlug($pdo, $slug, null, $blogId);
        $categoryId = $sectionMap[$sectionId] ?? null;

        $pdo->prepare(
            "INSERT INTO cms_articles (title, slug, perex, content, category_id, blog_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$title, $slug, mb_substr($perex, 0, 500), $content, $categoryId, $blogId, $published ? 'published' : 'pending', $createdAt, $updatedAt]);
        $insertedArticles++;
    }
    $log[] = "<span aria-hidden=\"true\">✓</span> Články: {$insertedArticles} importováno, {$skippedArticles} přeskočeno (celkem " . count($articles) . ")";

    // ── 3. Fotoalba s hierarchií ──
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
            $pdo->prepare("INSERT INTO cms_gallery_albums (name, slug, description) VALUES (?, ?, '')")->execute([$albumTitle, $albumSlug]);
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
    $log[] = "<span aria-hidden=\"true\">✓</span> Fotoalba: {$insertedAlbums} nových, {$parentSet} hierarchických vazeb";

    // ── 4. Fotografie ──
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
    $log[] = '<span aria-hidden="true">✓</span> Fotografie: ' . $insertedPhotos . ' záznamů (soubory stáhněte přes Stažení fotek z eStránek)';

    logAction('estranky_import', 'xml=' . basename((string)($_FILES['xml_file']['name'] ?? 'unknown')));
    @unlink($xmlPath);

    $_SESSION['import_log'] = $log;
    header('Location: estranky_import.php');
    exit;
}

adminHeader('Import z eStránek');
?>

<?php if ($log !== null): ?>
  <section style="background:#edf8ef;border:1px solid #2e7d32;border-radius:8px;padding:1rem;margin-bottom:1.5rem" aria-labelledby="import-result-heading">
    <h2 id="import-result-heading" style="margin-top:0"><span aria-hidden="true">✓</span> Import dokončen</h2>
    <ul style="margin:0">
      <?php foreach ($log as $line): ?>
        <li><?= $line ?></li>
      <?php endforeach; ?>
    </ul>
    <p style="margin-bottom:0"><a href="blog.php">Přejít na články</a> · <a href="gallery_albums.php">Galerie</a> · <a href="estranky_download_photos.php">Stáhnout fotky</a></p>
  </section>
<?php endif; ?>

<p>Import obsahu ze zálohy webu eStránky.cz (XML formát). Importují se články, sekce (jako kategorie blogu), fotoalba a záznamy fotografií.</p>

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
    <div style="margin-bottom:.75rem">
      <label for="es_target_blog">Importovat články do blogu:</label>
      <select id="es_target_blog" name="target_blog_id" style="min-width:200px">
        <option value="0">Vytvořit nový blog z importu</option>
        <?php foreach (getAllBlogs() as $b): ?>
          <option value="<?= (int)$b['id'] ?>"><?= h((string)$b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </fieldset>

  <div style="margin-top:1rem">
    <button type="submit" class="btn btn-primary"
            onclick="this.disabled=true;this.textContent='Importuji, čekejte prosím…';this.form.submit();return true;">Spustit import</button>
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
  <p>Duplikáty (stejný název + datum) se automaticky přeskočí. Import je bezpečný pro opakované spuštění.</p>
</section>

<?php adminFooter(); ?>
