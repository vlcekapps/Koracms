<?php
/**
 * WordPress importér – importuje články, stránky, kategorie, tagy, komentáře a média z WP SQL dumpu.
 */
ini_set('upload_max_filesize', '128M');
ini_set('post_max_size', '128M');
require_once __DIR__ . '/layout.php';
requireCapability('import_export_manage', 'Přístup odepřen. Pro import z WordPressu nemáte potřebné oprávnění.');

$pdo = db_connect();
$log = $_SESSION['import_log'] ?? null;
unset($_SESSION['import_log']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['sql_file']['tmp_name'])) {
    verifyCsrf();
    set_time_limit(600);

    $sqlPath = $_FILES['sql_file']['tmp_name'];
    $mediaPath = trim($_POST['media_path'] ?? '');
    $wpPrefix = trim($_POST['wp_prefix'] ?? 'wp_');

    if (!is_uploaded_file($sqlPath)) {
        $_SESSION['import_log'] = ['✗ Neplatný soubor.'];
        header('Location: wp_import.php');
        exit;
    }

    $log = [];

    $sql = file_get_contents($sqlPath);
    if ($sql === false) {
        $_SESSION['import_log'] = ['✗ Nepodařilo se přečíst SQL soubor.'];
        header('Location: wp_import.php');
        exit;
    }

    // Detekce prefixu
    if (preg_match('/CREATE TABLE `(' . preg_quote($wpPrefix, '/') . '\w*?)posts`/', $sql, $m)) {
        $detectedPrefix = str_replace('posts', '', $m[1]);
        if ($detectedPrefix !== $wpPrefix) {
            $log[] = "▸ Detekován prefix: {$detectedPrefix}";
            $wpPrefix = $detectedPrefix;
        }
    }

    $tmpPrefix = '_wpimp_';
    $sqlModified = $sql;

    // Odstranit CREATE DATABASE a USE (i s backticks)
    $sqlModified = preg_replace('/^\s*(CREATE DATABASE|USE\s)[^;]*;/mi', '', $sqlModified);

    // Detekovat název databáze z USE příkazu a odstranit kvalifikované názvy tabulek
    if (preg_match('/USE\s+`([^`]+)`/i', $sql, $dbMatch)) {
        $dbName = $dbMatch[1];
        // `dbname`.`wp62_posts` → `_wpimp_posts`
        $sqlModified = str_replace("`{$dbName}`.`{$wpPrefix}", "`{$tmpPrefix}", $sqlModified);
        // Backup: i s mezerami kolem tečky
        $sqlModified = str_replace("`{$dbName}` . `{$wpPrefix}", "`{$tmpPrefix}", $sqlModified);
    }

    // Nekvalifikované názvy: `wp62_posts` → `_wpimp_posts`
    $sqlModified = str_replace("`{$wpPrefix}", "`{$tmpPrefix}", $sqlModified);

    $statements = preg_split('/;\s*$/m', $sqlModified);
    $tableCount = 0;
    $sqlErrors = [];
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) continue;
        try {
            $pdo->exec($stmt);
            if (str_starts_with(strtoupper($stmt), 'CREATE TABLE')) $tableCount++;
        } catch (\PDOException $e) {
            $code = $e->getCode();
            if (!isset($sqlErrors[$code])) {
                $sqlErrors[$code] = ['msg' => mb_substr($e->getMessage(), 0, 100), 'count' => 0];
            }
            $sqlErrors[$code]['count']++;
        }
    }
    foreach ($sqlErrors as $err) {
        $log[] = '⚠ SQL (' . $err['count'] . '×): ' . $err['msg'];
    }
    $log[] = "✓ SQL dump načten ({$tableCount} tabulek)";
    unset($sql, $sqlModified, $statements);

    $t = $tmpPrefix;

    // ── Kategorie ──
    $catMap = [];
    try {
        $wpCats = $pdo->query("SELECT t.term_id, t.name FROM {$t}terms t INNER JOIN {$t}term_taxonomy tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'category' AND t.name != 'Nezařazené'")->fetchAll();
        $insertedCats = 0;
        foreach ($wpCats as $c) {
            $ex = $pdo->prepare("SELECT id FROM cms_categories WHERE name = ?"); $ex->execute([(string)$c['name']]);
            if ($eid = $ex->fetchColumn()) { $catMap[(int)$c['term_id']] = (int)$eid; }
            else { $pdo->prepare("INSERT INTO cms_categories (name) VALUES (?)")->execute([(string)$c['name']]); $catMap[(int)$c['term_id']] = (int)$pdo->lastInsertId(); $insertedCats++; }
        }
        $log[] = "✓ Kategorie: {$insertedCats} nových (celkem " . count($wpCats) . ")";
    } catch (\PDOException $e) { $log[] = '✗ Kategorie: ' . $e->getMessage(); }

    // ── Tagy ──
    $tagMap = [];
    try {
        $wpTags = $pdo->query("SELECT t.term_id, t.name FROM {$t}terms t INNER JOIN {$t}term_taxonomy tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'post_tag'")->fetchAll();
        $insertedTags = 0;
        foreach ($wpTags as $tg) {
            $slug = slugify((string)$tg['name']) ?: 'tag-' . (int)$tg['term_id'];
            $ex = $pdo->prepare("SELECT id FROM cms_tags WHERE slug = ?"); $ex->execute([$slug]);
            if ($eid = $ex->fetchColumn()) { $tagMap[(int)$tg['term_id']] = (int)$eid; }
            else { $pdo->prepare("INSERT INTO cms_tags (name, slug) VALUES (?, ?)")->execute([(string)$tg['name'], $slug]); $tagMap[(int)$tg['term_id']] = (int)$pdo->lastInsertId(); $insertedTags++; }
        }
        $log[] = "✓ Tagy: {$insertedTags} nových (celkem " . count($wpTags) . ")";
    } catch (\PDOException $e) { $log[] = '✗ Tagy: ' . $e->getMessage(); }

    // ── Term taxonomy mapa ──
    $ttMap = [];
    try {
        foreach ($pdo->query("SELECT term_taxonomy_id, term_id, taxonomy FROM {$t}term_taxonomy")->fetchAll() as $r) {
            $ttMap[(int)$r['term_taxonomy_id']] = ['term_id' => (int)$r['term_id'], 'taxonomy' => (string)$r['taxonomy']];
        }
    } catch (\PDOException $e) {}

    // ── Články ──
    $articleMap = [];
    try {
        $wpPosts = $pdo->query("SELECT ID, post_title, post_name, post_content, post_excerpt, post_date, post_modified, post_status, comment_status FROM {$t}posts WHERE post_type = 'post' AND post_status IN ('publish','draft','pending') ORDER BY post_date ASC")->fetchAll();
        $inserted = 0; $skipped = 0;
        foreach ($wpPosts as $p) {
            $title = trim((string)$p['post_title']);
            if ($title === '') { $skipped++; continue; }
            $dup = $pdo->prepare("SELECT id FROM cms_articles WHERE title = ? AND DATE(created_at) = DATE(?)"); $dup->execute([$title, (string)$p['post_date']]);
            if ($dup->fetchColumn()) { $skipped++; $articleMap[(int)$p['ID']] = 0; continue; }

            $content = (string)$p['post_content']; $perex = trim((string)$p['post_excerpt']);
            if (str_contains($content, '<!--more-->')) { $parts = explode('<!--more-->', $content, 2); if ($perex === '') $perex = trim(strip_tags($parts[0])); $content = trim($parts[1]); }
            $content = preg_replace('/<!-- wp:[a-z\/\-]+ (\{[^}]*\} )?-->/', '', $content);
            $content = preg_replace('/<!-- \/wp:[a-z\/\-]+ -->/', '', $content);
            $content = trim($content);

            $slug = uniqueArticleSlug($pdo, articleSlug((string)$p['post_name'] ?: $title));
            $pdo->prepare("INSERT INTO cms_articles (title, slug, perex, content, comments_enabled, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$title, $slug, mb_substr($perex, 0, 500), $content, $p['comment_status'] === 'open' ? 1 : 0, $p['post_status'] === 'publish' ? 'published' : 'pending', (string)$p['post_date'], (string)$p['post_modified']]);
            $articleMap[(int)$p['ID']] = (int)$pdo->lastInsertId(); $inserted++;
        }
        $log[] = "✓ Články: {$inserted} importováno, {$skipped} přeskočeno (celkem " . count($wpPosts) . ")";
    } catch (\PDOException $e) { $log[] = '✗ Články: ' . $e->getMessage(); }

    // ── Vazby ──
    try {
        $rels = $pdo->query("SELECT object_id, term_taxonomy_id FROM {$t}term_relationships")->fetchAll();
        $ac = 0; $at = 0;
        foreach ($rels as $r) {
            $aid = $articleMap[(int)$r['object_id']] ?? 0; $ttId = (int)$r['term_taxonomy_id'];
            if ($aid <= 0 || !isset($ttMap[$ttId])) continue;
            $tid = $ttMap[$ttId]['term_id']; $tax = $ttMap[$ttId]['taxonomy'];
            if ($tax === 'category' && isset($catMap[$tid])) { $pdo->prepare("UPDATE cms_articles SET category_id = ? WHERE id = ? AND (category_id IS NULL OR category_id = 0)")->execute([$catMap[$tid], $aid]); $ac++; }
            elseif ($tax === 'post_tag' && isset($tagMap[$tid])) { try { $pdo->prepare("INSERT IGNORE INTO cms_article_tags (article_id, tag_id) VALUES (?,?)")->execute([$aid, $tagMap[$tid]]); $at++; } catch (\PDOException $e) {} }
        }
        $log[] = "✓ Vazby: {$ac} kategorií, {$at} tagů";
    } catch (\PDOException $e) { $log[] = '✗ Vazby: ' . $e->getMessage(); }

    // ── Stránky ──
    try {
        $wpPages = $pdo->query("SELECT post_title, post_name, post_content, post_date, post_modified, post_status, menu_order FROM {$t}posts WHERE post_type = 'page' AND post_status IN ('publish','draft') ORDER BY menu_order")->fetchAll();
        $ip = 0;
        foreach ($wpPages as $pg) {
            $title = trim((string)$pg['post_title']); if ($title === '') continue;
            $dup = $pdo->prepare("SELECT id FROM cms_pages WHERE title = ?"); $dup->execute([$title]); if ($dup->fetchColumn()) continue;
            $content = preg_replace('/<!-- \/?wp:[a-z\/\-]+[^>]*-->/', '', (string)$pg['post_content']);
            $slug = uniquePageSlug($pdo, pageSlug((string)$pg['post_name'] ?: $title));
            $pdo->prepare("INSERT INTO cms_pages (title, slug, content, is_published, show_in_nav, nav_order, created_at, updated_at) VALUES (?,?,?,?,1,?,?,?)")
                ->execute([$title, $slug, trim($content), $pg['post_status'] === 'publish' ? 1 : 0, (int)$pg['menu_order'], (string)$pg['post_date'], (string)$pg['post_modified']]);
            $ip++;
        }
        $log[] = "✓ Stránky: {$ip} importováno (celkem " . count($wpPages) . ")";
    } catch (\PDOException $e) { $log[] = '✗ Stránky: ' . $e->getMessage(); }

    // ── Komentáře ──
    try {
        $wpComments = $pdo->query("SELECT comment_post_ID, comment_author, comment_author_email, comment_date, comment_content, comment_approved FROM {$t}comments WHERE comment_type IN ('','comment') AND comment_approved IN ('1','0')")->fetchAll();
        $ic = 0;
        foreach ($wpComments as $cm) {
            $aid = $articleMap[(int)$cm['comment_post_ID']] ?? 0; if ($aid <= 0) continue;
            $txt = trim((string)$cm['comment_content']); if ($txt === '') continue;
            $pdo->prepare("INSERT INTO cms_comments (article_id, author_name, author_email, content, is_approved, created_at) VALUES (?,?,?,?,?,?)")
                ->execute([$aid, trim((string)$cm['comment_author']), trim((string)$cm['comment_author_email']), $txt, $cm['comment_approved'] === '1' ? 1 : 0, (string)$cm['comment_date']]);
            $ic++;
        }
        $log[] = "✓ Komentáře: {$ic} importováno (celkem " . count($wpComments) . ")";
    } catch (\PDOException $e) { $log[] = '✗ Komentáře: ' . $e->getMessage(); }

    // ── Média ──
    if ($mediaPath !== '' && is_dir($mediaPath)) {
        try {
            $atts = $pdo->query("SELECT p.ID, pm.meta_value AS file_path FROM {$t}posts p LEFT JOIN {$t}postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file' WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'")->fetchAll();
            $destDir = dirname(__DIR__) . '/uploads/articles/'; $thumbDir = $destDir . 'thumbs/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true); if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
            $cm = 0;
            foreach ($atts as $att) {
                $fp = trim((string)($att['file_path'] ?? '')); if ($fp === '') continue;
                $src = rtrim($mediaPath, '/\\') . '/' . $fp; if (!is_file($src)) continue;
                $ext = strtolower(pathinfo($fp, PATHINFO_EXTENSION)); if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) continue;
                $fn = uniqid('wp_', true) . '.' . $ext;
                if (copy($src, $destDir . $fn)) { gallery_make_thumb($destDir . $fn, $thumbDir . $fn, 400); $cm++; }
            }
            $log[] = "✓ Média: {$cm} obrázků (celkem " . count($atts) . ")";
        } catch (\PDOException $e) { $log[] = '✗ Média: ' . $e->getMessage(); }
    } else {
        $log[] = '▸ Média: přeskočeno';
    }

    // ── Úklid ──
    $tmpTables = [];
    try { $s = $pdo->query("SHOW TABLES LIKE '{$tmpPrefix}%'"); while ($tb = $s->fetchColumn()) $tmpTables[] = $tb; } catch (\PDOException $e) {}
    foreach ($tmpTables as $tb) { try { $pdo->exec("DROP TABLE IF EXISTS `{$tb}`"); } catch (\PDOException $e) {} }
    $log[] = "✓ Dočasné tabulky odstraněny (" . count($tmpTables) . ")";

    logAction('wp_import', 'sql=' . basename((string)($_FILES['sql_file']['name'] ?? 'unknown')));
    @unlink($sqlPath);

    $_SESSION['import_log'] = $log;
    header('Location: wp_import.php');
    exit;
}

adminHeader('Import z WordPressu');
?>

<?php if ($log !== null): ?>
  <section style="background:#edf8ef;border:1px solid #2e7d32;border-radius:8px;padding:1rem;margin-bottom:1.5rem" aria-labelledby="import-result-heading">
    <h2 id="import-result-heading" style="margin-top:0">✓ Import dokončen</h2>
    <ul style="margin:0">
      <?php foreach ($log as $line): ?>
        <li><?= $line ?></li>
      <?php endforeach; ?>
    </ul>
    <p style="margin-bottom:0"><a href="blog.php">Články</a> · <a href="pages.php">Stránky</a> · <a href="index.php">Dashboard</a></p>
  </section>
<?php endif; ?>

<p>Import obsahu z WordPress SQL dumpu (exportovaný přes phpMyAdmin). Importují se články, stránky, kategorie, tagy, komentáře a volitelně média.</p>

<form method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Zdroj dat</legend>

    <div style="margin-bottom:.75rem">
      <label for="sql_file">SQL dump z WordPressu <span aria-hidden="true">*</span></label>
      <input type="file" id="sql_file" name="sql_file" required aria-required="true"
             accept=".sql,.sql.gz,application/sql,text/plain" aria-describedby="sql-help">
      <small id="sql-help">SQL soubor exportovaný z phpMyAdmin (WordPress databáze).</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="media_path">Cesta k WP uploads adresáři (nepovinné)</label>
      <input type="text" id="media_path" name="media_path" style="width:100%;max-width:600px" aria-describedby="media-help">
      <small id="media-help">Pokud máte na serveru adresář <code>wp-content/uploads</code>, zadejte cestu pro import obrázků.</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="wp_prefix">Prefix WP tabulek</label>
      <input type="text" id="wp_prefix" name="wp_prefix" value="wp_" style="width:10rem" aria-describedby="prefix-help">
      <small id="prefix-help">Obvykle <code>wp_</code>. Autodetekce z SQL souboru.</small>
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
    <li><strong>Články</strong> – WP <code>post_type = 'post'</code> → Kora CMS články s perex/content splittem na <code>&lt;!--more--&gt;</code></li>
    <li><strong>Stránky</strong> – WP <code>post_type = 'page'</code> → statické stránky</li>
    <li><strong>Kategorie</strong> – WP taxonomie <code>category</code> → kategorie blogu</li>
    <li><strong>Tagy</strong> – WP taxonomie <code>post_tag</code> → tagy článků</li>
    <li><strong>Komentáře</strong> – schválené i čekající komentáře k článkům</li>
    <li><strong>Média</strong> – obrázky z WP uploads (pokud zadáte cestu)</li>
  </ul>
  <p>Duplicitní záznamy se automaticky přeskočí. WP blokové komentáře se odstraní. Import je bezpečný pro opakované spuštění.</p>
</section>

<?php adminFooter(); ?>
