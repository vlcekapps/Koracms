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

/**
 * Parsuje WXR soubor a vrátí strukturovaná data.
 */
function wpParseWxr(string $xmlPath): ?array
{
    $xml = @simplexml_load_file($xmlPath);
    if ($xml === false) return null;

    $ns = $xml->getNamespaces(true);
    $wpNsUri = $ns['wp'] ?? 'http://wordpress.org/export/1.2/';
    $contentNsUri = $ns['content'] ?? 'http://purl.org/rss/1.0/modules/content/';
    $excerptNsUri = $ns['excerpt'] ?? 'http://wordpress.org/export/1.2/excerpt/';

    $wp = $xml->channel->children($wpNsUri);

    $categories = [];
    foreach ($wp->category as $c) {
        $slug = (string)($c->category_nicename ?? '');
        $name = (string)($c->cat_name ?? '');
        if ($name !== '' && $name !== 'Nezařazené' && $name !== 'Uncategorized') {
            $categories[$slug] = $name;
        }
    }

    $tags = [];
    foreach ($wp->tag as $t) {
        $slug = (string)($t->tag_slug ?? '');
        $name = (string)($t->tag_name ?? '');
        if ($name !== '') $tags[$slug] = $name;
    }

    $posts = [];
    $pages = [];
    foreach ($xml->channel->item as $item) {
        $itemWp = $item->children($wpNsUri);
        $contentNs = $item->children($contentNsUri);
        $excerptNs = $item->children($excerptNsUri);

        $postType = (string)($itemWp->post_type ?? '');
        $status = (string)($itemWp->status ?? '');
        if (!in_array($status, ['publish', 'draft', 'pending'], true)) continue;

        $title = trim((string)($item->title ?? ''));
        if ($title === '') continue;

        $entry = [
            'wp_id'     => (int)($itemWp->post_id ?? 0),
            'title'     => $title,
            'slug'      => (string)($itemWp->post_name ?? ''),
            'content'   => (string)($contentNs->encoded ?? ''),
            'excerpt'   => trim((string)($excerptNs->encoded ?? '')),
            'date'      => (string)($itemWp->post_date ?? ''),
            'status'    => $status,
            'comment_status' => (string)($itemWp->comment_status ?? 'open'),
            'menu_order' => (int)($itemWp->menu_order ?? 0),
            'categories' => [],
            'tags'       => [],
            'comments'   => [],
        ];

        foreach ($item->category as $cat) {
            $domain = (string)$cat['domain'];
            $nicename = (string)$cat['nicename'];
            if ($domain === 'category') $entry['categories'][] = $nicename;
            elseif ($domain === 'post_tag') $entry['tags'][] = $nicename;
        }

        foreach ($itemWp->comment as $cm) {
            $cmType = (string)($cm->comment_type ?? '');
            $cmApproved = (string)($cm->comment_approved ?? '');
            if ($cmType !== '' && $cmType !== 'comment') continue;
            if (!in_array($cmApproved, ['0', '1'], true)) continue;
            $cmContent = trim((string)($cm->comment_content ?? ''));
            if ($cmContent === '') continue;

            $entry['comments'][] = [
                'author'  => trim((string)($cm->comment_author ?? '')),
                'email'   => trim((string)($cm->comment_author_email ?? '')),
                'content' => $cmContent,
                'approved' => $cmApproved === '1' ? 1 : 0,
                'date'    => (string)($cm->comment_date ?? ''),
            ];
        }

        if ($postType === 'post') $posts[] = $entry;
        elseif ($postType === 'page') $pages[] = $entry;
    }

    return [
        'title'       => (string)($xml->channel->title ?? ''),
        'description' => (string)($xml->channel->description ?? ''),
        'wxr'         => (string)($wp->wxr_version ?? ''),
        'categories'  => $categories,
        'tags'        => $tags,
        'posts'       => $posts,
        'pages'       => $pages,
    ];
}

// ── Krok 2: Samotný import ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_import']) && !empty($_POST['wxr_cached'])) {
    verifyCsrf();
    set_time_limit(600);

    $cachedPath = $_POST['wxr_cached'];
    if ($cachedPath === '' || !is_file($cachedPath) || !str_contains(basename($cachedPath), 'kora_wp_import_')) {
        $_SESSION['import_log'] = ['<span aria-hidden="true">✗</span> Dočasný soubor nenalezen. Zkuste import znovu.'];
        header('Location: wp_import.php');
        exit;
    }

    $data = wpParseWxr($cachedPath);
    if ($data === null) {
        $_SESSION['import_log'] = ['<span aria-hidden="true">✗</span> Nepodařilo se parsovat XML.'];
        @unlink($cachedPath);
        header('Location: wp_import.php');
        exit;
    }

    $selectedCats = (array)($_POST['import_cats'] ?? []);
    $importUncategorized = isset($_POST['import_uncategorized']);
    $importPages = isset($_POST['import_pages']);
    $importSiteInfo = isset($_POST['import_site_info']);
    $targetBlogId = inputInt('post', 'target_blog_id');

    // Cílový blog – existující nebo nový
    if ($targetBlogId !== null && $targetBlogId > 0) {
        $targetBlog = getBlogById($targetBlogId);
    } else {
        $targetBlog = null;
    }
    if (!$targetBlog && $importSiteInfo && $data['title'] !== '') {
        $blogSlug = slugify($data['title']) ?: 'import';
        $blogSlug = substr($blogSlug, 0, 100);
        try {
            $pdo->prepare("INSERT INTO cms_blogs (name, slug, description, sort_order) VALUES (?, ?, ?, ?)")
                ->execute([$data['title'], $blogSlug, $data['description'] ?? '', (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM cms_blogs")->fetchColumn()]);
            $targetBlog = getBlogById((int)$pdo->lastInsertId());
        } catch (\PDOException $e) {
            $targetBlog = getDefaultBlog();
        }
    }
    if (!$targetBlog) {
        $targetBlog = getDefaultBlog();
    }
    $blogId = (int)$targetBlog['id'];

    $log = [];
    $log[] = '<span aria-hidden="true">✓</span> WXR načten: ' . h($data['title']);

    // Název a popis blogu
    if ($importSiteInfo && $data['title'] !== '') {
        $pdo->prepare("UPDATE cms_blogs SET name = ?, description = ? WHERE id = ?")
            ->execute([$data['title'], $data['description'] ?? '', $blogId]);
        $log[] = '<span aria-hidden="true">✓</span> Blog: ' . h($data['title']) . " (id={$blogId})";
    }

    // Kategorie
    $catMap = [];
    $insertedCats = 0;
    foreach ($data['categories'] as $slug => $name) {
        if (!in_array($slug, $selectedCats, true)) continue;
        $ex = $pdo->prepare("SELECT id FROM cms_categories WHERE name = ? AND blog_id = ?"); $ex->execute([$name, $blogId]);
        if ($eid = $ex->fetchColumn()) { $catMap[$slug] = (int)$eid; }
        else { $pdo->prepare("INSERT INTO cms_categories (name, blog_id) VALUES (?, ?)")->execute([$name, $blogId]); $catMap[$slug] = (int)$pdo->lastInsertId(); $insertedCats++; }
    }
    $log[] = "<span aria-hidden=\"true\">✓</span> Kategorie: {$insertedCats} nových";

    // Tagy
    $tagMap = [];
    $insertedTags = 0;
    foreach ($data['tags'] as $slug => $name) {
        $cmsSlug = slugify($name) ?: $slug;
        $ex = $pdo->prepare("SELECT id FROM cms_tags WHERE slug = ? AND blog_id = ?"); $ex->execute([$cmsSlug, $blogId]);
        if ($eid = $ex->fetchColumn()) { $tagMap[$slug] = (int)$eid; }
        else { $pdo->prepare("INSERT INTO cms_tags (name, slug, blog_id) VALUES (?, ?, ?)")->execute([$name, $cmsSlug, $blogId]); $tagMap[$slug] = (int)$pdo->lastInsertId(); $insertedTags++; }
    }
    $log[] = "<span aria-hidden=\"true\">✓</span> Tagy: {$insertedTags} nových";

    // Články
    $insertedArticles = 0;
    $skippedArticles = 0;
    $insertedComments = 0;
    foreach ($data['posts'] as $post) {
        // Filtr: článek musí patřit do vybrané kategorie, nebo být nekategorizovaný (pokud zapnuto)
        $postCats = $post['categories'];
        $matchesCat = array_intersect($postCats, $selectedCats) !== [];
        $isUncategorized = empty($postCats);

        if (!$matchesCat && !($isUncategorized && $importUncategorized)) {
            $skippedArticles++;
            continue;
        }

        $title = $post['title'];
        $dup = $pdo->prepare("SELECT id FROM cms_articles WHERE title = ? AND DATE(created_at) = DATE(?)");
        $dup->execute([$title, $post['date']]);
        if ($dup->fetchColumn()) { $skippedArticles++; continue; }

        $content = $post['content'];
        $excerpt = $post['excerpt'];
        if (str_contains($content, '<!--more-->')) {
            $parts = explode('<!--more-->', $content, 2);
            if ($excerpt === '') $excerpt = trim(strip_tags($parts[0]));
            $content = trim($parts[1]);
        }
        $content = preg_replace('/<!-- \/?wp:[a-z\/\-]+[^>]*-->/', '', $content);
        $content = trim($content);

        $slug = uniqueArticleSlug($pdo, articleSlug($post['slug'] ?: $title), null, $blogId);
        $status = $post['status'] === 'publish' ? 'published' : 'pending';

        $pdo->prepare("INSERT INTO cms_articles (title, slug, perex, content, comments_enabled, blog_id, status, created_at) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$title, $slug, mb_substr($excerpt, 0, 500), $content, $post['comment_status'] === 'open' ? 1 : 0, $blogId, $status, $post['date']]);
        $articleId = (int)$pdo->lastInsertId();
        $insertedArticles++;

        foreach ($postCats as $catSlug) {
            if (isset($catMap[$catSlug])) {
                $pdo->prepare("UPDATE cms_articles SET category_id = ? WHERE id = ? AND (category_id IS NULL OR category_id = 0)")
                    ->execute([$catMap[$catSlug], $articleId]);
            }
        }
        foreach ($post['tags'] as $tagSlug) {
            if (isset($tagMap[$tagSlug])) {
                try { $pdo->prepare("INSERT IGNORE INTO cms_article_tags (article_id, tag_id) VALUES (?,?)")->execute([$articleId, $tagMap[$tagSlug]]); } catch (\PDOException $e) {}
            }
        }
        foreach ($post['comments'] as $cm) {
            $pdo->prepare("INSERT INTO cms_comments (article_id, author_name, author_email, content, is_approved, created_at) VALUES (?,?,?,?,?,?)")
                ->execute([$articleId, $cm['author'], $cm['email'], $cm['content'], $cm['approved'], $cm['date']]);
            $insertedComments++;
        }
    }
    $log[] = "<span aria-hidden=\"true\">✓</span> Články: {$insertedArticles} importováno, {$skippedArticles} přeskočeno (filtr/duplikát)";
    $log[] = "<span aria-hidden=\"true\">✓</span> Komentáře: {$insertedComments} importováno";

    // Stránky
    $insertedPages = 0;
    if ($importPages) {
        foreach ($data['pages'] as $page) {
            $dup = $pdo->prepare("SELECT id FROM cms_pages WHERE title = ?"); $dup->execute([$page['title']]);
            if ($dup->fetchColumn()) continue;
            $content = preg_replace('/<!-- \/?wp:[a-z\/\-]+[^>]*-->/', '', $page['content']);
            $slug = uniquePageSlug($pdo, pageSlug($page['slug'] ?: $page['title']));
            $pdo->prepare("INSERT INTO cms_pages (title, slug, content, is_published, show_in_nav, nav_order, created_at) VALUES (?,?,?,?,1,?,?)")
                ->execute([$page['title'], $slug, trim($content), $page['status'] === 'publish' ? 1 : 0, $page['menu_order'], $page['date']]);
            $insertedPages++;
        }
    }
    $log[] = "<span aria-hidden=\"true\">✓</span> Stránky: {$insertedPages} importováno";

    logAction('wp_import', 'wxr, articles=' . $insertedArticles);
    @unlink($cachedPath);

    $_SESSION['import_log'] = $log;
    header('Location: wp_import.php');
    exit;
}

// ── Krok 1: Náhled po uploadu ──
$preview = null;
$cachedPath = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['wxr_file']['tmp_name']) && !isset($_POST['do_import'])) {
    verifyCsrf();
    $xmlPath = $_FILES['wxr_file']['tmp_name'];
    if (is_uploaded_file($xmlPath)) {
        // Uložíme do uploads/tmp (spolehlivější než sys temp na Windows)
        $tmpDir = dirname(__DIR__) . '/uploads/tmp';
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
        $cachedPath = $tmpDir . '/kora_wp_import_' . bin2hex(random_bytes(8)) . '.xml';
        if (!move_uploaded_file($xmlPath, $cachedPath)) {
            copy($xmlPath, $cachedPath);
        }
        $preview = wpParseWxr($cachedPath);
        if ($preview === null) {
            @unlink($cachedPath);
            $cachedPath = '';
        }
    }
}

adminHeader('Import z WordPressu');
?>

<?php if ($log !== null): ?>
  <section style="background:#edf8ef;border:1px solid #2e7d32;border-radius:8px;padding:1rem;margin-bottom:1.5rem" aria-labelledby="import-result-heading">
    <h2 id="import-result-heading" style="margin-top:0"><span aria-hidden="true">✓</span> Import dokončen</h2>
    <ul style="margin:0">
      <?php foreach ($log as $line): ?>
        <li><?= $line ?></li>
      <?php endforeach; ?>
    </ul>
    <p style="margin-bottom:0"><a href="blog.php">Články</a> · <a href="pages.php">Stránky</a> · <a href="index.php">Dashboard</a></p>
  </section>
<?php endif; ?>

<?php if ($preview !== null): ?>
  <?php
    // Spočítáme články per kategorie
    $catCounts = [];
    $uncatCount = 0;
    foreach ($preview['posts'] as $p) {
        if (empty($p['categories'])) { $uncatCount++; continue; }
        foreach ($p['categories'] as $cs) {
            $catCounts[$cs] = ($catCounts[$cs] ?? 0) + 1;
        }
    }
  ?>
  <section style="background:#fff4e6;border:1px solid #d7b600;border-radius:8px;padding:1rem;margin-bottom:1.5rem" aria-labelledby="preview-heading">
    <h2 id="preview-heading" style="margin-top:0">Náhled obsahu k importu</h2>
    <p>
      <strong>Web:</strong> <?= h($preview['title']) ?> ·
      <strong>Články:</strong> <?= count($preview['posts']) ?> ·
      <strong>Stránky:</strong> <?= count($preview['pages']) ?> ·
      <strong>Kategorie:</strong> <?= count($preview['categories']) ?> ·
      <strong>Tagy:</strong> <?= count($preview['tags']) ?>
    </p>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="do_import" value="1">
      <input type="hidden" name="wxr_cached" value="<?= h($cachedPath) ?>">

      <fieldset>
        <legend>Vyberte kategorie k importu</legend>
        <p>Zaškrtněte kategorie, jejichž články chcete importovat. Články ze spamových kategorií můžete nechat odškrtnuté.</p>

        <?php foreach ($preview['categories'] as $slug => $name): ?>
          <div style="margin:.3rem 0">
            <label>
              <input type="checkbox" name="import_cats[]" value="<?= h($slug) ?>" checked>
              <?= h($name) ?>
              <small style="color:#555">(<?= $catCounts[$slug] ?? 0 ?> článků)</small>
            </label>
          </div>
        <?php endforeach; ?>

        <?php if ($uncatCount > 0): ?>
          <div style="margin:.5rem 0;padding-top:.3rem;border-top:1px solid #e0d8c8">
            <label>
              <input type="checkbox" name="import_uncategorized" value="1">
              Nekategorizované články
              <small style="color:#555">(<?= $uncatCount ?> článků – může obsahovat spam)</small>
            </label>
          </div>
        <?php endif; ?>
      </fieldset>

      <fieldset style="margin-top:1rem">
        <legend>Další volby</legend>
        <div style="margin:.3rem 0">
          <label for="wp_target_blog">Importovat do blogu:</label>
          <select id="wp_target_blog" name="target_blog_id" style="min-width:200px">
            <option value="0">Vytvořit nový blog z importu</option>
            <?php foreach (getAllBlogs() as $b): ?>
              <option value="<?= (int)$b['id'] ?>"><?= h((string)$b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="margin:.3rem 0">
          <label>
            <input type="checkbox" name="import_site_info" value="1" checked>
            Převzít název a popis do vybraného blogu
            <small style="color:#555">(<?= h($preview['title']) ?>)</small>
          </label>
        </div>
        <div style="margin:.3rem 0">
          <label>
            <input type="checkbox" name="import_pages" value="1" checked>
            Importovat statické stránky (<?= count($preview['pages']) ?>)
          </label>
        </div>
      </fieldset>

      <div style="margin-top:1rem">
        <button type="submit" class="btn btn-primary"
                onclick="this.disabled=true;this.textContent='Importuji, čekejte prosím…';this.form.submit();return true;">Importovat vybraný obsah</button>
        <a href="wp_import.php" class="btn">Zrušit</a>
      </div>
    </form>
  </section>
<?php else: ?>
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
              onclick="this.disabled=true;this.textContent='Načítám náhled…';this.form.submit();return true;">Načíst a zobrazit náhled</button>
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
      <li><strong>Kategorie</strong> → kategorie blogu (vybíráte, které chcete)</li>
      <li><strong>Tagy</strong> → tagy článků s vazbami</li>
      <li><strong>Komentáře</strong> – schválené i čekající</li>
    </ul>
    <p>Po nahrání souboru se zobrazí náhled s možností vybrat kategorie. Duplicitní záznamy se přeskočí.</p>
  </section>
<?php endif; ?>

<?php adminFooter(); ?>
