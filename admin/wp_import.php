<?php
/**
 * WordPress importér – importuje z WordPress XML exportu (WXR formát).
 * WordPress admin → Nástroje → Export → Veškerý obsah → Stáhnout soubor exportu.
 */
require_once __DIR__ . '/layout.php';
requireCapability('import_export_manage', 'Přístup odepřen. Pro import z WordPressu nemáte potřebné oprávnění.');

$pdo = db_connect();
$log = $_SESSION['import_log'] ?? null;
unset($_SESSION['import_log']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['wxr_file']['tmp_name'])) {
    verifyCsrf();
    set_time_limit(600);

    $xmlPath = $_FILES['wxr_file']['tmp_name'];
    if (!is_uploaded_file($xmlPath)) {
        $_SESSION['import_log'] = ['✗ Neplatný soubor.'];
        header('Location: wp_import.php');
        exit;
    }

    $log = [];

    // WXR je RSS-based XML s WordPress namespace
    $xmlContent = file_get_contents($xmlPath);
    if ($xmlContent === false) {
        $_SESSION['import_log'] = ['✗ Nepodařilo se přečíst soubor.'];
        header('Location: wp_import.php');
        exit;
    }

    // Registrace namespace
    $xml = @simplexml_load_string($xmlContent);
    if ($xml === false) {
        $_SESSION['import_log'] = ['✗ Nepodařilo se parsovat XML. Ujistěte se, že jde o WordPress XML export.'];
        header('Location: wp_import.php');
        exit;
    }

    $namespaces = $xml->getNamespaces(true);
    $wp = $xml->channel->children($namespaces['wp'] ?? 'http://wordpress.org/export/1.2/');
    $dc = isset($namespaces['dc']) ? $namespaces['dc'] : 'http://purl.org/dc/elements/1.1/';
    $content_ns = isset($namespaces['content']) ? $namespaces['content'] : 'http://purl.org/rss/1.0/modules/content/';
    $excerpt_ns = isset($namespaces['excerpt']) ? $namespaces['excerpt'] : 'http://wordpress.org/export/1.2/excerpt/';

    $wxrVersion = (string)($wp->wxr_version ?? '');
    $log[] = "✓ WordPress XML export načten (WXR verze: " . ($wxrVersion ?: 'neznámá') . ")";
    $log[] = "▸ Web: " . h((string)($xml->channel->title ?? ''));

    // ── 1. Kategorie ──
    $catMap = []; // wp slug → cms category id
    $insertedCats = 0;
    foreach ($wp->category as $wpCat) {
        $catName = (string)($wpCat->cat_name ?? '');
        $catSlug = (string)($wpCat->category_nicename ?? '');
        if ($catName === '' || $catName === 'Nezařazené' || $catName === 'Uncategorized') continue;

        $existing = $pdo->prepare("SELECT id FROM cms_categories WHERE name = ?");
        $existing->execute([$catName]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            $catMap[$catSlug] = (int)$existingId;
        } else {
            $pdo->prepare("INSERT INTO cms_categories (name) VALUES (?)")->execute([$catName]);
            $catMap[$catSlug] = (int)$pdo->lastInsertId();
            $insertedCats++;
        }
    }
    $log[] = "✓ Kategorie: {$insertedCats} nových";

    // ── 2. Tagy ──
    $tagMap = []; // wp slug → cms tag id
    $insertedTags = 0;
    foreach ($wp->tag as $wpTag) {
        $tagName = (string)($wpTag->tag_name ?? '');
        $tagSlug = (string)($wpTag->tag_slug ?? '');
        if ($tagName === '' || $tagSlug === '') continue;

        $existing = $pdo->prepare("SELECT id FROM cms_tags WHERE slug = ?");
        $existing->execute([$tagSlug]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            $tagMap[$tagSlug] = (int)$existingId;
        } else {
            $cmsSlug = slugify($tagName) ?: $tagSlug;
            $pdo->prepare("INSERT INTO cms_tags (name, slug) VALUES (?, ?)")->execute([$tagName, $cmsSlug]);
            $tagMap[$tagSlug] = (int)$pdo->lastInsertId();
            $insertedTags++;
        }
    }
    $log[] = "✓ Tagy: {$insertedTags} nových";

    // ── 3. Články a stránky ──
    $insertedArticles = 0;
    $skippedArticles = 0;
    $insertedPages = 0;
    $insertedComments = 0;
    $articleMap = []; // wp post_id → cms article_id

    foreach ($xml->channel->item as $item) {
        $wpNs = $item->children($namespaces['wp'] ?? 'http://wordpress.org/export/1.2/');
        $contentNs = $item->children($content_ns);
        $excerptNs = $item->children($excerpt_ns);

        $postType = (string)($wpNs->post_type ?? 'post');
        $postStatus = (string)($wpNs->status ?? 'publish');
        $wpPostId = (int)($wpNs->post_id ?? 0);

        if (!in_array($postType, ['post', 'page'], true)) continue;
        if (!in_array($postStatus, ['publish', 'draft', 'pending'], true)) continue;

        $title = trim((string)($item->title ?? ''));
        if ($title === '') { $skippedArticles++; continue; }

        $content = (string)($contentNs->encoded ?? '');
        $excerpt = trim((string)($excerptNs->encoded ?? ''));
        $postName = (string)($wpNs->post_name ?? '');
        $postDate = (string)($wpNs->post_date ?? '');
        $commentStatus = (string)($wpNs->comment_status ?? 'open');
        $menuOrder = (int)($wpNs->menu_order ?? 0);

        // <!--more--> split
        if (str_contains($content, '<!--more-->')) {
            $parts = explode('<!--more-->', $content, 2);
            if ($excerpt === '') {
                $excerpt = trim(strip_tags($parts[0]));
            }
            $content = trim($parts[1]);
        }

        // Odstranění WP bloků
        $content = preg_replace('/<!-- \/?wp:[a-z\/\-]+[^>]*-->/', '', $content);
        $content = trim($content);

        if ($postType === 'post') {
            // Deduplikace
            $dupCheck = $pdo->prepare("SELECT id FROM cms_articles WHERE title = ? AND DATE(created_at) = DATE(?)");
            $dupCheck->execute([$title, $postDate]);
            if ($dupCheck->fetchColumn()) {
                $skippedArticles++;
                continue;
            }

            $slug = uniqueArticleSlug($pdo, articleSlug($postName ?: $title));
            $status = $postStatus === 'publish' ? 'published' : 'pending';

            $pdo->prepare(
                "INSERT INTO cms_articles (title, slug, perex, content, comments_enabled, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([$title, $slug, mb_substr($excerpt, 0, 500), $content, $commentStatus === 'open' ? 1 : 0, $status, $postDate]);
            $articleId = (int)$pdo->lastInsertId();
            $articleMap[$wpPostId] = $articleId;
            $insertedArticles++;

            // Přiřazení kategorií a tagů z <category> elementů
            foreach ($item->category as $cat) {
                $domain = (string)$cat['domain'];
                $nicename = (string)$cat['nicename'];

                if ($domain === 'category' && isset($catMap[$nicename])) {
                    $pdo->prepare("UPDATE cms_articles SET category_id = ? WHERE id = ? AND (category_id IS NULL OR category_id = 0)")
                        ->execute([$catMap[$nicename], $articleId]);
                } elseif ($domain === 'post_tag' && isset($tagMap[$nicename])) {
                    try {
                        $pdo->prepare("INSERT IGNORE INTO cms_article_tags (article_id, tag_id) VALUES (?, ?)")
                            ->execute([$articleId, $tagMap[$nicename]]);
                    } catch (\PDOException $e) {}
                }
            }

            // Komentáře
            foreach ($wpNs->comment as $wpComment) {
                $commentContent = trim((string)($wpComment->comment_content ?? ''));
                $commentApproved = (string)($wpComment->comment_approved ?? '0');
                $commentType = (string)($wpComment->comment_type ?? '');

                if ($commentContent === '' || !in_array($commentApproved, ['0', '1'], true)) continue;
                if ($commentType !== '' && $commentType !== 'comment') continue;

                $pdo->prepare(
                    "INSERT INTO cms_comments (article_id, author_name, author_email, content, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?)"
                )->execute([
                    $articleId,
                    trim((string)($wpComment->comment_author ?? '')),
                    trim((string)($wpComment->comment_author_email ?? '')),
                    $commentContent,
                    $commentApproved === '1' ? 1 : 0,
                    (string)($wpComment->comment_date ?? $postDate),
                ]);
                $insertedComments++;
            }
        } elseif ($postType === 'page') {
            $dupCheck = $pdo->prepare("SELECT id FROM cms_pages WHERE title = ?");
            $dupCheck->execute([$title]);
            if ($dupCheck->fetchColumn()) continue;

            $slug = uniquePageSlug($pdo, pageSlug($postName ?: $title));
            $pdo->prepare(
                "INSERT INTO cms_pages (title, slug, content, is_published, show_in_nav, nav_order, created_at) VALUES (?, ?, ?, ?, 1, ?, ?)"
            )->execute([$title, $slug, $content, $postStatus === 'publish' ? 1 : 0, $menuOrder, $postDate]);
            $insertedPages++;
        }
    }

    $log[] = "✓ Články: {$insertedArticles} importováno, {$skippedArticles} přeskočeno";
    $log[] = "✓ Stránky: {$insertedPages} importováno";
    $log[] = "✓ Komentáře: {$insertedComments} importováno";

    logAction('wp_import', 'wxr=' . basename((string)($_FILES['wxr_file']['name'] ?? 'unknown')));
    @unlink($xmlPath);

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

<p>Import obsahu z WordPress XML exportu. V administraci WordPressu přejděte na <strong>Nástroje → Export → Veškerý obsah</strong> a stáhněte XML soubor.</p>

<form method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Zdroj dat</legend>

    <div style="margin-bottom:.75rem">
      <label for="wxr_file">WordPress XML export (WXR) <span aria-hidden="true">*</span></label>
      <input type="file" id="wxr_file" name="wxr_file" required aria-required="true"
             accept=".xml,application/xml,text/xml"
             aria-describedby="wxr-help">
      <small id="wxr-help">XML soubor exportovaný z WordPress admin → Nástroje → Export.</small>
    </div>
  </fieldset>

  <div style="margin-top:1rem">
    <button type="submit" class="btn btn-primary"
            onclick="this.disabled=true;this.textContent='Importuji, čekejte prosím…';this.form.submit();return true;">Spustit import</button>
    <a href="index.php" class="btn">Zpět</a>
  </div>
</form>

<section style="margin-top:2rem;padding:1rem;background:#fffbe6;border:1px solid #d7b600;border-radius:8px" aria-labelledby="import-info-heading">
  <h2 id="import-info-heading" style="margin-top:0">Jak získat export z WordPressu</h2>
  <ol>
    <li>Přihlaste se do administrace WordPressu</li>
    <li>Přejděte na <strong>Nástroje → Export</strong></li>
    <li>Vyberte <strong>Veškerý obsah</strong></li>
    <li>Klikněte na <strong>Stáhnout soubor exportu</strong></li>
    <li>Stažený XML soubor nahrajte sem</li>
  </ol>
  <h3>Co se importuje</h3>
  <ul>
    <li><strong>Články</strong> – s perex/content splittem na <code>&lt;!--more--&gt;</code>; WP bloky se odstraní</li>
    <li><strong>Stránky</strong> – statické stránky</li>
    <li><strong>Kategorie</strong> → kategorie blogu</li>
    <li><strong>Tagy</strong> → tagy článků s vazbami</li>
    <li><strong>Komentáře</strong> – schválené i čekající</li>
  </ul>
  <p>Duplicitní záznamy se automaticky přeskočí. Import je bezpečný pro opakované spuštění.</p>
</section>

<?php adminFooter(); ?>
