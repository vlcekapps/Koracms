<?php
/**
 * WordPress importér – importuje články, stránky, kategorie, tagy, komentáře a média z WP SQL dumpu.
 */
require_once __DIR__ . '/layout.php';
requireCapability('import_export_manage', 'Přístup odepřen. Pro import z WordPressu nemáte potřebné oprávnění.');

$pdo = db_connect();
$log = [];
$success = false;
$showForm = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    set_time_limit(600);

    $sqlPath = trim($_POST['sql_path'] ?? '');
    $mediaPath = trim($_POST['media_path'] ?? '');
    $wpPrefix = trim($_POST['wp_prefix'] ?? 'wp_');

    if ($sqlPath === '' || !is_file($sqlPath)) {
        $log[] = '✗ Soubor SQL dumpu nebyl nalezen: ' . h($sqlPath);
    } else {
        $showForm = false;

        // ── 1. Načtení SQL dumpu do dočasných tabulek ──
        $log[] = '▸ Načítám SQL dump…';
        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            $log[] = '✗ Nepodařilo se přečíst SQL soubor.';
        } else {
            // Detekce prefixu z SQL
            if (preg_match('/CREATE TABLE `(' . preg_quote($wpPrefix, '/') . '\w*?)posts`/', $sql, $m)) {
                $detectedPrefix = str_replace('posts', '', $m[1]);
                if ($detectedPrefix !== $wpPrefix) {
                    $log[] = "▸ Detekován prefix: {$detectedPrefix} (zadáno: {$wpPrefix})";
                    $wpPrefix = $detectedPrefix;
                }
            }

            // Přejmenujeme tabulky na tmp_ prefix, aby nekolidovaly
            $tmpPrefix = '_wpimp_';
            $sqlModified = str_replace("`{$wpPrefix}", "`{$tmpPrefix}", $sql);

            // Odstraníme CREATE DATABASE a USE příkazy
            $sqlModified = preg_replace('/^(CREATE DATABASE|USE\s)[^;]+;/mi', '', $sqlModified);

            // Spustíme SQL po částech (split by ;)
            $statements = preg_split('/;\s*$/m', $sqlModified);
            $tableCount = 0;
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) {
                    continue;
                }
                try {
                    $pdo->exec($stmt);
                    if (str_starts_with(strtoupper($stmt), 'CREATE TABLE')) {
                        $tableCount++;
                    }
                } catch (\PDOException $e) {
                    // Ignorujeme chyby u ALTER TABLE, indexů atd.
                    if (str_starts_with(strtoupper($stmt), 'CREATE TABLE') || str_starts_with(strtoupper($stmt), 'INSERT')) {
                        // Důležité příkazy logujeme
                        $log[] = '⚠ SQL chyba: ' . mb_substr($e->getMessage(), 0, 120);
                    }
                }
            }
            $log[] = "✓ SQL dump načten ({$tableCount} tabulek)";
            unset($sql, $sqlModified, $statements);

            $t = $tmpPrefix; // Zkratka

            // ── 2. Import kategorií ──
            try {
                $wpCats = $pdo->query(
                    "SELECT t.term_id, t.name, t.slug
                     FROM {$t}terms t
                     INNER JOIN {$t}term_taxonomy tt ON tt.term_id = t.term_id
                     WHERE tt.taxonomy = 'category' AND t.name != 'Nezařazené'
                     ORDER BY t.term_id"
                )->fetchAll();

                $catMap = []; // wp_term_id => cms_category_id
                $insertedCats = 0;
                foreach ($wpCats as $wpCat) {
                    // Existuje už?
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
                $log[] = "✓ Kategorie: {$insertedCats} nových (celkem " . count($wpCats) . " z WP)";
            } catch (\PDOException $e) {
                $log[] = '✗ Kategorie: ' . $e->getMessage();
                $catMap = [];
            }

            // ── 3. Import tagů ──
            try {
                $wpTags = $pdo->query(
                    "SELECT t.term_id, t.name, t.slug
                     FROM {$t}terms t
                     INNER JOIN {$t}term_taxonomy tt ON tt.term_id = t.term_id
                     WHERE tt.taxonomy = 'post_tag'
                     ORDER BY t.term_id"
                )->fetchAll();

                $tagMap = []; // wp_term_id => cms_tag_id
                $insertedTags = 0;
                foreach ($wpTags as $wpTag) {
                    $existing = $pdo->prepare("SELECT id FROM cms_tags WHERE slug = ?");
                    $existing->execute([slugify((string)$wpTag['name'])]);
                    $existingId = $existing->fetchColumn();
                    if ($existingId) {
                        $tagMap[(int)$wpTag['term_id']] = (int)$existingId;
                    } else {
                        $slug = slugify((string)$wpTag['name']);
                        if ($slug === '') {
                            $slug = 'tag-' . (int)$wpTag['term_id'];
                        }
                        $pdo->prepare("INSERT INTO cms_tags (name, slug) VALUES (?, ?)")->execute([(string)$wpTag['name'], $slug]);
                        $tagMap[(int)$wpTag['term_id']] = (int)$pdo->lastInsertId();
                        $insertedTags++;
                    }
                }
                $log[] = "✓ Tagy: {$insertedTags} nových (celkem " . count($wpTags) . " z WP)";
            } catch (\PDOException $e) {
                $log[] = '✗ Tagy: ' . $e->getMessage();
                $tagMap = [];
            }

            // ── 4. Mapování term_taxonomy_id → term_id ──
            $ttMap = []; // term_taxonomy_id => term_id
            try {
                $ttRows = $pdo->query("SELECT term_taxonomy_id, term_id, taxonomy FROM {$t}term_taxonomy")->fetchAll();
                foreach ($ttRows as $ttRow) {
                    $ttMap[(int)$ttRow['term_taxonomy_id']] = [
                        'term_id' => (int)$ttRow['term_id'],
                        'taxonomy' => (string)$ttRow['taxonomy'],
                    ];
                }
            } catch (\PDOException $e) {
                $log[] = '⚠ Term taxonomy mapování: ' . $e->getMessage();
            }

            // ── 5. Import článků (post_type = 'post') ──
            $articleMap = []; // wp_post_id => cms_article_id
            try {
                $wpPosts = $pdo->query(
                    "SELECT ID, post_title, post_name, post_content, post_excerpt,
                            post_date, post_modified, post_status, comment_status
                     FROM {$t}posts
                     WHERE post_type = 'post' AND post_status IN ('publish', 'draft', 'pending')
                     ORDER BY post_date ASC"
                )->fetchAll();

                $insertedArticles = 0;
                $skippedArticles = 0;
                foreach ($wpPosts as $wpPost) {
                    $title = trim((string)$wpPost['post_title']);
                    if ($title === '') {
                        $skippedArticles++;
                        continue;
                    }

                    // Kontrola duplicity
                    $dupCheck = $pdo->prepare("SELECT id FROM cms_articles WHERE title = ? AND DATE(created_at) = DATE(?)");
                    $dupCheck->execute([$title, (string)$wpPost['post_date']]);
                    if ($dupCheck->fetchColumn()) {
                        $skippedArticles++;
                        $articleMap[(int)$wpPost['ID']] = 0; // přeskočeno
                        continue;
                    }

                    // Perex / obsah split na <!--more-->
                    $content = (string)$wpPost['post_content'];
                    $perex = trim((string)$wpPost['post_excerpt']);

                    if (str_contains($content, '<!--more-->')) {
                        $parts = explode('<!--more-->', $content, 2);
                        if ($perex === '') {
                            $perex = trim(strip_tags($parts[0]));
                        }
                        $content = trim($parts[1]);
                    }

                    // Konverze WP bloků na HTML
                    $content = preg_replace('/<!-- wp:[a-z\/\-]+ (\{[^}]*\} )?-->/', '', $content);
                    $content = preg_replace('/<!-- \/wp:[a-z\/\-]+ -->/', '', $content);
                    $content = trim($content);

                    $slug = articleSlug((string)$wpPost['post_name'] ?: $title);
                    $slug = uniqueArticleSlug($pdo, $slug);

                    $status = $wpPost['post_status'] === 'publish' ? 'published' : 'pending';
                    $commentsEnabled = $wpPost['comment_status'] === 'open' ? 1 : 0;

                    $pdo->prepare(
                        "INSERT INTO cms_articles (title, slug, perex, content, comments_enabled, status, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        $title, $slug, mb_substr($perex, 0, 500), $content,
                        $commentsEnabled, $status,
                        (string)$wpPost['post_date'], (string)$wpPost['post_modified'],
                    ]);
                    $articleMap[(int)$wpPost['ID']] = (int)$pdo->lastInsertId();
                    $insertedArticles++;
                }
                $log[] = "✓ Články: {$insertedArticles} importováno, {$skippedArticles} přeskočeno (celkem " . count($wpPosts) . " z WP)";
            } catch (\PDOException $e) {
                $log[] = '✗ Články: ' . $e->getMessage();
            }

            // ── 6. Přiřazení kategorií a tagů k článkům ──
            try {
                $wpRels = $pdo->query(
                    "SELECT object_id, term_taxonomy_id FROM {$t}term_relationships"
                )->fetchAll();

                $assignedCats = 0;
                $assignedTags = 0;
                foreach ($wpRels as $rel) {
                    $wpPostId = (int)$rel['object_id'];
                    $ttId = (int)$rel['term_taxonomy_id'];
                    $cmsArticleId = $articleMap[$wpPostId] ?? 0;
                    if ($cmsArticleId <= 0 || !isset($ttMap[$ttId])) {
                        continue;
                    }

                    $termId = $ttMap[$ttId]['term_id'];
                    $taxonomy = $ttMap[$ttId]['taxonomy'];

                    if ($taxonomy === 'category' && isset($catMap[$termId])) {
                        $pdo->prepare("UPDATE cms_articles SET category_id = ? WHERE id = ? AND (category_id IS NULL OR category_id = 0)")
                            ->execute([$catMap[$termId], $cmsArticleId]);
                        $assignedCats++;
                    } elseif ($taxonomy === 'post_tag' && isset($tagMap[$termId])) {
                        try {
                            $pdo->prepare("INSERT IGNORE INTO cms_article_tags (article_id, tag_id) VALUES (?, ?)")
                                ->execute([$cmsArticleId, $tagMap[$termId]]);
                            $assignedTags++;
                        } catch (\PDOException $e) {
                            // Duplicitní vazba
                        }
                    }
                }
                $log[] = "✓ Vazby: {$assignedCats} kategorií, {$assignedTags} tagů přiřazeno";
            } catch (\PDOException $e) {
                $log[] = '✗ Vazby: ' . $e->getMessage();
            }

            // ── 7. Import stránek (post_type = 'page') ──
            try {
                $wpPages = $pdo->query(
                    "SELECT ID, post_title, post_name, post_content, post_date, post_modified, post_status, menu_order
                     FROM {$t}posts
                     WHERE post_type = 'page' AND post_status IN ('publish', 'draft')
                     ORDER BY menu_order, post_date"
                )->fetchAll();

                $insertedPages = 0;
                foreach ($wpPages as $wpPage) {
                    $title = trim((string)$wpPage['post_title']);
                    if ($title === '') {
                        continue;
                    }

                    $dupCheck = $pdo->prepare("SELECT id FROM cms_pages WHERE title = ?");
                    $dupCheck->execute([$title]);
                    if ($dupCheck->fetchColumn()) {
                        continue;
                    }

                    $content = (string)$wpPage['post_content'];
                    $content = preg_replace('/<!-- wp:[a-z\/\-]+ (\{[^}]*\} )?-->/', '', $content);
                    $content = preg_replace('/<!-- \/wp:[a-z\/\-]+ -->/', '', $content);
                    $content = trim($content);

                    $slug = pageSlug((string)$wpPage['post_name'] ?: $title);
                    $slug = uniquePageSlug($pdo, $slug);

                    $isPublished = $wpPage['post_status'] === 'publish' ? 1 : 0;
                    $navOrder = (int)$wpPage['menu_order'];

                    $pdo->prepare(
                        "INSERT INTO cms_pages (title, slug, content, is_published, show_in_nav, nav_order, created_at, updated_at)
                         VALUES (?, ?, ?, ?, 1, ?, ?, ?)"
                    )->execute([
                        $title, $slug, $content, $isPublished, $navOrder,
                        (string)$wpPage['post_date'], (string)$wpPage['post_modified'],
                    ]);
                    $insertedPages++;
                }
                $log[] = "✓ Stránky: {$insertedPages} importováno (celkem " . count($wpPages) . " z WP)";
            } catch (\PDOException $e) {
                $log[] = '✗ Stránky: ' . $e->getMessage();
            }

            // ── 8. Import komentářů ──
            try {
                $wpComments = $pdo->query(
                    "SELECT comment_post_ID, comment_author, comment_author_email,
                            comment_date, comment_content, comment_approved
                     FROM {$t}comments
                     WHERE comment_type IN ('', 'comment') AND comment_approved IN ('1', '0')
                     ORDER BY comment_date"
                )->fetchAll();

                $insertedComments = 0;
                foreach ($wpComments as $wpComment) {
                    $cmsArticleId = $articleMap[(int)$wpComment['comment_post_ID']] ?? 0;
                    if ($cmsArticleId <= 0) {
                        continue;
                    }

                    $content = trim((string)$wpComment['comment_content']);
                    if ($content === '') {
                        continue;
                    }

                    $isApproved = $wpComment['comment_approved'] === '1' ? 1 : 0;

                    $pdo->prepare(
                        "INSERT INTO cms_comments (article_id, author_name, author_email, content, is_approved, created_at)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    )->execute([
                        $cmsArticleId,
                        trim((string)$wpComment['comment_author']),
                        trim((string)$wpComment['comment_author_email']),
                        $content,
                        $isApproved,
                        (string)$wpComment['comment_date'],
                    ]);
                    $insertedComments++;
                }
                $log[] = "✓ Komentáře: {$insertedComments} importováno (celkem " . count($wpComments) . " z WP)";
            } catch (\PDOException $e) {
                $log[] = '✗ Komentáře: ' . $e->getMessage();
            }

            // ── 9. Import médií ──
            if ($mediaPath !== '' && is_dir($mediaPath)) {
                try {
                    $wpAttachments = $pdo->query(
                        "SELECT p.ID, p.guid, pm.meta_value AS file_path
                         FROM {$t}posts p
                         LEFT JOIN {$t}postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
                         WHERE p.post_type = 'attachment'
                           AND p.post_mime_type LIKE 'image/%'
                         ORDER BY p.ID"
                    )->fetchAll();

                    $destDir = dirname(__DIR__) . '/uploads/articles/';
                    $destThumbDir = $destDir . 'thumbs/';
                    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                    if (!is_dir($destThumbDir)) mkdir($destThumbDir, 0755, true);

                    $copiedMedia = 0;
                    foreach ($wpAttachments as $att) {
                        $filePath = trim((string)($att['file_path'] ?? ''));
                        if ($filePath === '') {
                            continue;
                        }

                        $srcFile = rtrim($mediaPath, '/\\') . '/' . $filePath;
                        if (!is_file($srcFile)) {
                            continue;
                        }

                        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                            continue;
                        }

                        $filename = uniqid('wp_', true) . '.' . $ext;
                        if (copy($srcFile, $destDir . $filename)) {
                            gallery_make_thumb($destDir . $filename, $destThumbDir . $filename, 400);
                            $copiedMedia++;
                        }
                    }
                    $log[] = "✓ Média: {$copiedMedia} obrázků zkopírováno (celkem " . count($wpAttachments) . " z WP)";
                } catch (\PDOException $e) {
                    $log[] = '✗ Média: ' . $e->getMessage();
                }
            } else {
                $log[] = '▸ Média: přeskočeno (cesta k WP uploads nezadána nebo neexistuje)';
            }

            // ── 10. Úklid dočasných tabulek ──
            $tmpTables = [];
            try {
                $tblStmt = $pdo->query("SHOW TABLES LIKE '{$tmpPrefix}%'");
                while ($tbl = $tblStmt->fetchColumn()) {
                    $tmpTables[] = $tbl;
                }
            } catch (\PDOException $e) {
                $log[] = '⚠ Nepodařilo se vypsat dočasné tabulky: ' . $e->getMessage();
            }

            foreach ($tmpTables as $tbl) {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `{$tbl}`");
                } catch (\PDOException $e) {
                    $log[] = '⚠ Nepodařilo se smazat dočasnou tabulku ' . $tbl;
                }
            }
            $log[] = '✓ Dočasné tabulky odstraněny (' . count($tmpTables) . ')';

            $success = true;
            logAction('wp_import', 'sql=' . basename($sqlPath));
        }
    }
}

adminHeader('Import z WordPressu');
?>

<?php if ($showForm): ?>
<p>Import obsahu z WordPress SQL dumpu (exportovaný přes phpMyAdmin). Importují se články, stránky, kategorie, tagy, komentáře a volitelně média.</p>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Zdroj dat</legend>

    <div style="margin-bottom:.75rem">
      <label for="sql_path">Cesta k SQL dumpu na serveru <span aria-hidden="true">*</span></label>
      <input type="text" id="sql_path" name="sql_path" required aria-required="true"
             style="width:100%;max-width:600px"
             placeholder="/cesta/k/dump.sql"
             aria-describedby="sql-help">
      <small id="sql-help">Absolutní cesta k SQL souboru na tomto serveru (phpMyAdmin export).</small>
    </div>

    <div style="margin-bottom:.75rem">
      <label for="media_path">Cesta k WP uploads adresáři (nepovinné)</label>
      <input type="text" id="media_path" name="media_path"
             style="width:100%;max-width:600px"
             placeholder="/cesta/k/wp-content/uploads"
             aria-describedby="media-help">
      <small id="media-help">Pokud chcete importovat obrázky, zadejte cestu k adresáři <code>wp-content/uploads</code>.</small>
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
<?php else: ?>

<section aria-labelledby="import-result-heading">
  <h2 id="import-result-heading"><?= $success ? '✓ Import dokončen' : '✗ Import selhal' ?></h2>
  <ul>
    <?php foreach ($log as $line): ?>
      <li><?= $line ?></li>
    <?php endforeach; ?>
  </ul>
  <div style="margin-top:1rem">
    <a href="wp_import.php" class="btn">Nový import</a>
    <a href="blog.php" class="btn">Přejít na články</a>
    <a href="index.php" class="btn">Dashboard</a>
  </div>
</section>
<?php endif; ?>

<?php adminFooter(); ?>
