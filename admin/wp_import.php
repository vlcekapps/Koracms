<?php
/**
 * WordPress importér – importuje články, stránky, kategorie, tagy, komentáře a média z WP SQL dumpu.
 */
ini_set('upload_max_filesize', '128M');
ini_set('post_max_size', '128M');
require_once __DIR__ . '/../db.php';
requireCapability('import_export_manage', 'Přístup odepřen. Pro import z WordPressu nemáte potřebné oprávnění.');

$showForm = true;
$sqlPath = '';
$mediaPath = '';
$wpPrefix = 'wp_';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $mediaPath = trim($_POST['media_path'] ?? '');
    $wpPrefix = trim($_POST['wp_prefix'] ?? 'wp_');

    if (!empty($_FILES['sql_file']['tmp_name']) && is_uploaded_file($_FILES['sql_file']['tmp_name'])) {
        $sqlPath = $_FILES['sql_file']['tmp_name'];
        $showForm = false;
    }
}

if ($showForm) {
    require_once __DIR__ . '/layout.php';
    adminHeader('Import z WordPressu');
?>
<p>Import obsahu z WordPress SQL dumpu (exportovaný přes phpMyAdmin). Importují se články, stránky, kategorie, tagy, komentáře a volitelně média.</p>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  <p role="alert" class="error">Nahrajte SQL dump soubor.</p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Zdroj dat</legend>

    <div style="margin-bottom:.75rem">
      <label for="sql_file">SQL dump z WordPressu <span aria-hidden="true">*</span></label>
      <input type="file" id="sql_file" name="sql_file" required aria-required="true"
             accept=".sql,.sql.gz,application/sql,text/plain"
             aria-describedby="sql-help">
      <small id="sql-help">SQL soubor exportovaný z phpMyAdmin (WordPress databáze).</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="media_path">Cesta k WP uploads adresáři (nepovinné)</label>
      <input type="text" id="media_path" name="media_path"
             style="width:100%;max-width:600px"
             aria-describedby="media-help">
      <small id="media-help">Pokud máte na serveru adresář <code>wp-content/uploads</code>, zadejte cestu pro import obrázků.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="wp_prefix">Prefix WP tabulek</label>
      <input type="text" id="wp_prefix" name="wp_prefix" value="wp_"
             style="width:10rem"
             aria-describedby="prefix-help">
      <small id="prefix-help">Obvykle <code>wp_</code>. Autodetekce z SQL souboru.</small>
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
    <li><strong>Články</strong> – WP <code>post_type = 'post'</code> → Kora CMS články s perex/content splittem na <code>&lt;!--more--&gt;</code></li>
    <li><strong>Stránky</strong> – WP <code>post_type = 'page'</code> → statické stránky</li>
    <li><strong>Kategorie</strong> – WP taxonomie <code>category</code> → kategorie blogu</li>
    <li><strong>Tagy</strong> – WP taxonomie <code>post_tag</code> → tagy článků</li>
    <li><strong>Komentáře</strong> – schválené i čekající komentáře k článkům</li>
    <li><strong>Média</strong> – obrázky z WP uploads (pokud zadáte cestu)</li>
  </ul>
  <p>Duplicitní záznamy (stejný název + datum) se automaticky přeskočí. WP blokové komentáře (<code>&lt;!-- wp:* --&gt;</code>) se odstraní.</p>
</section>

<?php
    adminFooter();
    exit;
}

// ── Streaming progress ──────────────────────────────────────────────────────

set_time_limit(600);

while (ob_get_level()) {
    ob_end_clean();
}
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
ini_set('zlib.output_compression', '0');
ini_set('implicit_flush', '1');

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no');

echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8"><title>Import z WordPressu</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:700px;margin:2rem auto;padding:0 1rem}';
echo '#progress{background:#f5f7fa;border:1px solid #d6d6d6;border-radius:8px;padding:1rem;margin:1rem 0;min-height:150px;max-height:400px;overflow-y:auto;font-size:.9rem;line-height:1.6}';
echo '.done{background:#edf8ef;border:1px solid #2e7d32;border-radius:8px;padding:1rem;margin-top:1rem}';
echo '.fail{background:#fff0f0;border:1px solid #c62828;border-radius:8px;padding:1rem;margin-top:1rem}</style></head><body>';
echo str_repeat(' ', 4096);
echo '<h1>Import z WordPressu</h1>';
echo '<div id="progress" role="log" aria-live="polite" aria-label="Průběh importu"><p>Probíhá import, čekejte prosím…</p></div>';
echo '<div id="result"></div>';
flush();

$emit = function (string $message) {
    echo '<script>document.getElementById("progress").innerHTML+="' . addslashes($message) . '<br>";document.getElementById("progress").scrollTop=999999;</script>';
    echo str_repeat(' ', 256);
    flush();
};

$pdo = db_connect();

// ── 1. Načtení SQL dumpu ──
$emit('▸ Načítám SQL dump…');
$sql = file_get_contents($sqlPath);
if ($sql === false) {
    $emit('✗ Nepodařilo se přečíst SQL soubor.');
    echo '<script>document.getElementById("result").innerHTML=\'<div class="fail"><h2>✗ Import selhal</h2><p><a href="wp_import.php">Zkusit znovu</a></p></div>\';</script>';
    echo '</body></html>';
    exit;
}

if (preg_match('/CREATE TABLE `(' . preg_quote($wpPrefix, '/') . '\w*?)posts`/', $sql, $m)) {
    $detectedPrefix = str_replace('posts', '', $m[1]);
    if ($detectedPrefix !== $wpPrefix) {
        $emit("▸ Detekován prefix: <strong>{$detectedPrefix}</strong>");
        $wpPrefix = $detectedPrefix;
    }
}

$tmpPrefix = '_wpimp_';
$sqlModified = str_replace("`{$wpPrefix}", "`{$tmpPrefix}", $sql);
$sqlModified = preg_replace('/^(CREATE DATABASE|USE\s)[^;]+;/mi', '', $sqlModified);

$statements = preg_split('/;\s*$/m', $sqlModified);
$tableCount = 0;
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) continue;
    try {
        $pdo->exec($stmt);
        if (str_starts_with(strtoupper($stmt), 'CREATE TABLE')) $tableCount++;
    } catch (\PDOException $e) {
        if (str_starts_with(strtoupper($stmt), 'CREATE TABLE') || str_starts_with(strtoupper($stmt), 'INSERT')) {
            $emit('⚠ SQL: ' . addslashes(mb_substr($e->getMessage(), 0, 100)));
        }
    }
}
$emit("✓ SQL dump načten (<strong>{$tableCount}</strong> tabulek)");
unset($sql, $sqlModified, $statements);

$t = $tmpPrefix;

// ── 2. Import kategorií ──
$emit('▸ Importuji kategorie…');
$catMap = [];
try {
    $wpCats = $pdo->query(
        "SELECT t.term_id, t.name, t.slug FROM {$t}terms t
         INNER JOIN {$t}term_taxonomy tt ON tt.term_id = t.term_id
         WHERE tt.taxonomy = 'category' AND t.name != 'Nezařazené' ORDER BY t.term_id"
    )->fetchAll();
    $insertedCats = 0;
    foreach ($wpCats as $wpCat) {
        $existing = $pdo->prepare("SELECT id FROM cms_categories WHERE name = ?");
        $existing->execute([(string)$wpCat['name']]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            $catMap[(int)$wpCat['term_id']] = (int)$existingId;
        } else {
            $pdo->prepare("INSERT INTO cms_categories (name) VALUES (?)")->execute([(string)$wpCat['name']]);
            $catMap[(int)$wpCat['term_id']] = (int)$pdo->lastInsertId();
            $insertedCats++;
        }
    }
    $emit("✓ Kategorie: <strong>{$insertedCats}</strong> nových (celkem " . count($wpCats) . ')');
} catch (\PDOException $e) {
    $emit('✗ Kategorie: ' . addslashes($e->getMessage()));
}

// ── 3. Import tagů ──
$emit('▸ Importuji tagy…');
$tagMap = [];
try {
    $wpTags = $pdo->query(
        "SELECT t.term_id, t.name, t.slug FROM {$t}terms t
         INNER JOIN {$t}term_taxonomy tt ON tt.term_id = t.term_id
         WHERE tt.taxonomy = 'post_tag' ORDER BY t.term_id"
    )->fetchAll();
    $insertedTags = 0;
    foreach ($wpTags as $wpTag) {
        $existing = $pdo->prepare("SELECT id FROM cms_tags WHERE slug = ?");
        $existing->execute([slugify((string)$wpTag['name'])]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            $tagMap[(int)$wpTag['term_id']] = (int)$existingId;
        } else {
            $slug = slugify((string)$wpTag['name']) ?: 'tag-' . (int)$wpTag['term_id'];
            $pdo->prepare("INSERT INTO cms_tags (name, slug) VALUES (?, ?)")->execute([(string)$wpTag['name'], $slug]);
            $tagMap[(int)$wpTag['term_id']] = (int)$pdo->lastInsertId();
            $insertedTags++;
        }
    }
    $emit("✓ Tagy: <strong>{$insertedTags}</strong> nových (celkem " . count($wpTags) . ')');
} catch (\PDOException $e) {
    $emit('✗ Tagy: ' . addslashes($e->getMessage()));
}

// ── 4. Term taxonomy mapování ──
$ttMap = [];
try {
    foreach ($pdo->query("SELECT term_taxonomy_id, term_id, taxonomy FROM {$t}term_taxonomy")->fetchAll() as $ttRow) {
        $ttMap[(int)$ttRow['term_taxonomy_id']] = ['term_id' => (int)$ttRow['term_id'], 'taxonomy' => (string)$ttRow['taxonomy']];
    }
} catch (\PDOException $e) {
    $emit('⚠ Term taxonomy: ' . addslashes($e->getMessage()));
}

// ── 5. Import článků ──
$emit('▸ Importuji články…');
$articleMap = [];
try {
    $wpPosts = $pdo->query(
        "SELECT ID, post_title, post_name, post_content, post_excerpt, post_date, post_modified, post_status, comment_status
         FROM {$t}posts WHERE post_type = 'post' AND post_status IN ('publish', 'draft', 'pending') ORDER BY post_date ASC"
    )->fetchAll();

    $insertedArticles = 0;
    $skippedArticles = 0;
    foreach ($wpPosts as $wpPost) {
        $title = trim((string)$wpPost['post_title']);
        if ($title === '') { $skippedArticles++; continue; }

        $dupCheck = $pdo->prepare("SELECT id FROM cms_articles WHERE title = ? AND DATE(created_at) = DATE(?)");
        $dupCheck->execute([$title, (string)$wpPost['post_date']]);
        if ($dupCheck->fetchColumn()) { $skippedArticles++; $articleMap[(int)$wpPost['ID']] = 0; continue; }

        $content = (string)$wpPost['post_content'];
        $perex = trim((string)$wpPost['post_excerpt']);
        if (str_contains($content, '<!--more-->')) {
            $parts = explode('<!--more-->', $content, 2);
            if ($perex === '') $perex = trim(strip_tags($parts[0]));
            $content = trim($parts[1]);
        }
        $content = preg_replace('/<!-- wp:[a-z\/\-]+ (\{[^}]*\} )?-->/', '', $content);
        $content = preg_replace('/<!-- \/wp:[a-z\/\-]+ -->/', '', $content);
        $content = trim($content);

        $slug = articleSlug((string)$wpPost['post_name'] ?: $title);
        $slug = uniqueArticleSlug($pdo, $slug);
        $status = $wpPost['post_status'] === 'publish' ? 'published' : 'pending';
        $commentsEnabled = $wpPost['comment_status'] === 'open' ? 1 : 0;

        $pdo->prepare(
            "INSERT INTO cms_articles (title, slug, perex, content, comments_enabled, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$title, $slug, mb_substr($perex, 0, 500), $content, $commentsEnabled, $status, (string)$wpPost['post_date'], (string)$wpPost['post_modified']]);
        $articleMap[(int)$wpPost['ID']] = (int)$pdo->lastInsertId();
        $insertedArticles++;

        if ($insertedArticles % 50 === 0) $emit("  … {$insertedArticles} článků");
    }
    $emit("✓ Články: <strong>{$insertedArticles}</strong> importováno, {$skippedArticles} přeskočeno (celkem " . count($wpPosts) . ')');
} catch (\PDOException $e) {
    $emit('✗ Články: ' . addslashes($e->getMessage()));
}

// ── 6. Vazby kategorií a tagů ──
$emit('▸ Přiřazuji kategorie a tagy…');
try {
    $wpRels = $pdo->query("SELECT object_id, term_taxonomy_id FROM {$t}term_relationships")->fetchAll();
    $assignedCats = 0;
    $assignedTags = 0;
    foreach ($wpRels as $rel) {
        $wpPostId = (int)$rel['object_id'];
        $ttId = (int)$rel['term_taxonomy_id'];
        $cmsArticleId = $articleMap[$wpPostId] ?? 0;
        if ($cmsArticleId <= 0 || !isset($ttMap[$ttId])) continue;

        $termId = $ttMap[$ttId]['term_id'];
        $taxonomy = $ttMap[$ttId]['taxonomy'];

        if ($taxonomy === 'category' && isset($catMap[$termId])) {
            $pdo->prepare("UPDATE cms_articles SET category_id = ? WHERE id = ? AND (category_id IS NULL OR category_id = 0)")
                ->execute([$catMap[$termId], $cmsArticleId]);
            $assignedCats++;
        } elseif ($taxonomy === 'post_tag' && isset($tagMap[$termId])) {
            try {
                $pdo->prepare("INSERT IGNORE INTO cms_article_tags (article_id, tag_id) VALUES (?, ?)")->execute([$cmsArticleId, $tagMap[$termId]]);
                $assignedTags++;
            } catch (\PDOException $e) {}
        }
    }
    $emit("✓ Vazby: <strong>{$assignedCats}</strong> kategorií, <strong>{$assignedTags}</strong> tagů");
} catch (\PDOException $e) {
    $emit('✗ Vazby: ' . addslashes($e->getMessage()));
}

// ── 7. Import stránek ──
$emit('▸ Importuji stránky…');
try {
    $wpPages = $pdo->query(
        "SELECT ID, post_title, post_name, post_content, post_date, post_modified, post_status, menu_order
         FROM {$t}posts WHERE post_type = 'page' AND post_status IN ('publish', 'draft') ORDER BY menu_order, post_date"
    )->fetchAll();
    $insertedPages = 0;
    foreach ($wpPages as $wpPage) {
        $title = trim((string)$wpPage['post_title']);
        if ($title === '') continue;
        $dupCheck = $pdo->prepare("SELECT id FROM cms_pages WHERE title = ?");
        $dupCheck->execute([$title]);
        if ($dupCheck->fetchColumn()) continue;

        $content = (string)$wpPage['post_content'];
        $content = preg_replace('/<!-- wp:[a-z\/\-]+ (\{[^}]*\} )?-->/', '', $content);
        $content = preg_replace('/<!-- \/wp:[a-z\/\-]+ -->/', '', $content);

        $slug = pageSlug((string)$wpPage['post_name'] ?: $title);
        $slug = uniquePageSlug($pdo, $slug);

        $pdo->prepare("INSERT INTO cms_pages (title, slug, content, is_published, show_in_nav, nav_order, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?)")
            ->execute([$title, $slug, trim($content), $wpPage['post_status'] === 'publish' ? 1 : 0, (int)$wpPage['menu_order'], (string)$wpPage['post_date'], (string)$wpPage['post_modified']]);
        $insertedPages++;
    }
    $emit("✓ Stránky: <strong>{$insertedPages}</strong> importováno (celkem " . count($wpPages) . ')');
} catch (\PDOException $e) {
    $emit('✗ Stránky: ' . addslashes($e->getMessage()));
}

// ── 8. Import komentářů ──
$emit('▸ Importuji komentáře…');
try {
    $wpComments = $pdo->query(
        "SELECT comment_post_ID, comment_author, comment_author_email, comment_date, comment_content, comment_approved
         FROM {$t}comments WHERE comment_type IN ('', 'comment') AND comment_approved IN ('1', '0') ORDER BY comment_date"
    )->fetchAll();
    $insertedComments = 0;
    foreach ($wpComments as $wpComment) {
        $cmsArticleId = $articleMap[(int)$wpComment['comment_post_ID']] ?? 0;
        if ($cmsArticleId <= 0) continue;
        $content = trim((string)$wpComment['comment_content']);
        if ($content === '') continue;

        $pdo->prepare("INSERT INTO cms_comments (article_id, author_name, author_email, content, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$cmsArticleId, trim((string)$wpComment['comment_author']), trim((string)$wpComment['comment_author_email']), $content, $wpComment['comment_approved'] === '1' ? 1 : 0, (string)$wpComment['comment_date']]);
        $insertedComments++;
    }
    $emit("✓ Komentáře: <strong>{$insertedComments}</strong> importováno (celkem " . count($wpComments) . ')');
} catch (\PDOException $e) {
    $emit('✗ Komentáře: ' . addslashes($e->getMessage()));
}

// ── 9. Import médií ──
if ($mediaPath !== '' && is_dir($mediaPath)) {
    $emit('▸ Importuji média…');
    try {
        $wpAttachments = $pdo->query(
            "SELECT p.ID, p.guid, pm.meta_value AS file_path FROM {$t}posts p
             LEFT JOIN {$t}postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
             WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' ORDER BY p.ID"
        )->fetchAll();
        $destDir = dirname(__DIR__) . '/uploads/articles/';
        $destThumbDir = $destDir . 'thumbs/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        if (!is_dir($destThumbDir)) mkdir($destThumbDir, 0755, true);
        $copiedMedia = 0;
        foreach ($wpAttachments as $att) {
            $filePath = trim((string)($att['file_path'] ?? ''));
            if ($filePath === '') continue;
            $srcFile = rtrim($mediaPath, '/\\') . '/' . $filePath;
            if (!is_file($srcFile)) continue;
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) continue;
            $filename = uniqid('wp_', true) . '.' . $ext;
            if (copy($srcFile, $destDir . $filename)) {
                gallery_make_thumb($destDir . $filename, $destThumbDir . $filename, 400);
                $copiedMedia++;
            }
        }
        $emit("✓ Média: <strong>{$copiedMedia}</strong> obrázků zkopírováno (celkem " . count($wpAttachments) . ')');
    } catch (\PDOException $e) {
        $emit('✗ Média: ' . addslashes($e->getMessage()));
    }
} else {
    $emit('▸ Média: přeskočeno (cesta k WP uploads nezadána)');
}

// ── 10. Úklid ──
$emit('▸ Odstraňuji dočasné tabulky…');
$tmpTables = [];
try {
    $tblStmt = $pdo->query("SHOW TABLES LIKE '{$tmpPrefix}%'");
    while ($tbl = $tblStmt->fetchColumn()) $tmpTables[] = $tbl;
} catch (\PDOException $e) {}
foreach ($tmpTables as $tbl) {
    try { $pdo->exec("DROP TABLE IF EXISTS `{$tbl}`"); } catch (\PDOException $e) {}
}
$emit("✓ Dočasné tabulky odstraněny (" . count($tmpTables) . ')');

logAction('wp_import', 'sql=' . basename($sqlPath));
if (is_file($sqlPath)) @unlink($sqlPath);

echo '<script>document.getElementById("result").innerHTML=\'<div class="done"><h2>✓ Import dokončen</h2>';
echo '<p>Vše bylo úspěšně importováno.</p>';
echo '<p><a href="wp_import.php">Nový import</a> · <a href="blog.php">Články</a> · <a href="pages.php">Stránky</a> · <a href="index.php">Dashboard</a></p>';
echo '</div>\';</script>';

echo '</body></html>';
