<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('blog_taxonomies_manage', 'Přístup odepřen. Pro správu blogů nemáte potřebné oprávnění.');

$pdo = db_connect();
$success = '';
$error   = '';
$message = trim($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name      = trim($_POST['name'] ?? '');
    $slug      = slugify(trim($_POST['slug'] ?? ''));
    $desc      = trim($_POST['description'] ?? '');
    $introContent = trim($_POST['intro_content'] ?? '');
    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $rssSubtitle = trim($_POST['rss_subtitle'] ?? '');
    $commentsDefault = isset($_POST['comments_default']) ? 1 : 0;
    $feedItemLimit = max(1, min(100, (int)($_POST['feed_item_limit'] ?? 20)));
    $showInNav = isset($_POST['show_in_nav']) ? 1 : 0;
    $updateId  = inputInt('post', 'update_id');

    $existingBlog = null;
    if ($updateId !== null) {
        $existingStmt = $pdo->prepare("SELECT * FROM cms_blogs WHERE id = ?");
        $existingStmt->execute([$updateId]);
        $existingBlog = $existingStmt->fetch() ?: null;
        if (!$existingBlog) {
            $error = 'Vybraný blog nebyl nalezen.';
        }
    }

    if ($error === '') {
        if ($name === '') {
            $error = 'Název blogu je povinný.';
        } elseif ($slug === '') {
            $error = 'Slug blogu je povinný.';
        } elseif (in_array($slug, reservedBlogSlugs(), true)) {
            $error = 'Slug „' . $slug . '“ je rezervovaný a nelze ho použít.';
        } elseif (is_dir(__DIR__ . '/../' . $slug) && ($updateId === null || getBlogBySlug($slug) === null || (int)getBlogBySlug($slug)['id'] !== $updateId)) {
            $error = 'Slug „' . $slug . '“ koliduje s existujícím adresářem na serveru.';
        }
    }

    $logoFile = trim((string)($existingBlog['logo_file'] ?? ''));
    $logoAltText = trim((string)($_POST['logo_alt_text'] ?? ($existingBlog['logo_alt_text'] ?? '')));
    $logoAltText = mb_substr($logoAltText, 0, 255);
    if ($error === '') {
        $logoUpload = uploadBlogLogo($_FILES['logo_file'] ?? [], $logoFile);
        if ($logoUpload['error'] !== '') {
            $error = $logoUpload['error'];
        } else {
            $logoFile = $logoUpload['filename'];
            if (isset($_POST['logo_file_delete']) && empty($_FILES['logo_file']['name']) && $logoFile !== '') {
                deleteBlogLogoFile($logoFile);
                $logoFile = '';
            }
            if ($logoFile === '') {
                $logoAltText = '';
            }
        }
    }

    if ($error === '' && $updateId !== null) {
        try {
            $oldSlug = (string)($existingBlog['slug'] ?? '');
            if ($oldSlug !== '' && $oldSlug !== $slug) {
                saveBlogSlugRedirect($pdo, $updateId, $oldSlug);
            }
            $pdo->prepare("UPDATE cms_blogs SET name = ?, slug = ?, description = ?, intro_content = ?, logo_file = ?, logo_alt_text = ?, meta_title = ?, meta_description = ?, rss_subtitle = ?, comments_default = ?, feed_item_limit = ?, show_in_nav = ? WHERE id = ?")
                ->execute([$name, $slug, $desc, $introContent, $logoFile, $logoAltText, $metaTitle, $metaDescription, $rssSubtitle, $commentsDefault, $feedItemLimit, $showInNav, $updateId]);
            clearBlogCache();
            $success = 'Blog upraven.';
        } catch (\PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Slug blogu je už obsazený.' : 'Chyba při ukládání.';
        }
    } elseif ($error === '') {
        $sortOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM cms_blogs")->fetchColumn();
        $creatorUserId = (int)(currentUserId() ?? 0);
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO cms_blogs (name, slug, description, intro_content, logo_file, logo_alt_text, meta_title, meta_description, rss_subtitle, comments_default, feed_item_limit, sort_order, show_in_nav, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$name, $slug, $desc, $introContent, $logoFile, $logoAltText, $metaTitle, $metaDescription, $rssSubtitle, $commentsDefault, $feedItemLimit, $sortOrder, $showInNav, $creatorUserId > 0 ? $creatorUserId : null]);
            $newBlogId = (int)$pdo->lastInsertId();
            if ($creatorUserId > 0 && $newBlogId > 0) {
                $pdo->prepare(
                    "INSERT INTO cms_blog_members (blog_id, user_id, member_role)
                     VALUES (?, ?, 'manager')
                     ON DUPLICATE KEY UPDATE member_role = VALUES(member_role)"
                )->execute([$newBlogId, $creatorUserId]);
            }
            $pdo->commit();
            clearBlogCache();
            $success = 'Blog vytvořen.';
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (!empty($logoUpload['uploaded']) && $logoFile !== '') {
                deleteBlogLogoFile($logoFile);
            }
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Slug blogu je už obsazený.' : 'Chyba při ukládání.';
        }
    }
}

$blogs = $pdo->query(
    "SELECT b.*,
            u.email AS creator_email,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email, '') AS creator_label,
            (SELECT COUNT(*) FROM cms_articles WHERE blog_id = b.id) AS article_count,
            (SELECT COUNT(*) FROM cms_blog_members WHERE blog_id = b.id) AS member_count
     FROM cms_blogs b
     LEFT JOIN cms_users u ON u.id = b.created_by_user_id
     ORDER BY b.sort_order, b.name"
)->fetchAll();
$defaultBlogId = (int)(getDefaultBlog()['id'] ?? 0);

adminHeader('Správa blogů');
?>
<?php if ($message === 'no_blog'): ?>
  <p role="status"><strong>Nejdřív vytvořte blog.</strong> Kategorie, štítky i články se spravují až uvnitř existujícího blogu.</p>
<?php endif; ?>
<?php if ($success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="blog.php"><span aria-hidden="true">←</span> Zpět na články</a>
</p>

<form method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nový blog</legend>
    <label for="name">Název blogu <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="name" name="name" required aria-required="true" maxlength="255">

    <label for="slug">Slug (URL) <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="100"
           pattern="[a-z0-9\-]+" title="Pouze malá písmena, číslice a pomlčky"
           aria-describedby="blog-slug-help">
    <small id="blog-slug-help" class="field-help">Slug se použije jako adresa blogu, např. <code>/recepty/</code>. Vyplní se automaticky z názvu.</small>

    <label for="description">Popis</label>
    <textarea id="description" name="description" rows="2"></textarea>
    <small class="field-help">Popis se zobrazí jako úvod blogu na veřejném webu.</small>

    <label for="intro_content">Rozšířený úvod blogu</label>
    <textarea id="intro_content" name="intro_content" rows="5" aria-describedby="blog-intro-help"></textarea>
    <small id="blog-intro-help" class="field-help">Volitelné. Delší text nebo HTML blok pod názvem a popisem blogu, vhodný třeba pro úvod, CTA nebo vysvětlení zaměření blogu. <?= adminHtmlSnippetSupportMarkup() ?></small>
    <?php renderAdminContentReferencePicker('intro_content'); ?>

    <label for="meta_title">Meta titulek</label>
    <input type="text" id="meta_title" name="meta_title" maxlength="160" aria-describedby="blog-meta-help">

    <label for="meta_description">Meta popis</label>
    <textarea id="meta_description" name="meta_description" rows="2" aria-describedby="blog-meta-help"></textarea>
    <small id="blog-meta-help" class="field-help">Volitelně. Když je nevyplníte, blog použije svůj název a popis.</small>

    <label for="rss_subtitle">Podtitulek RSS feedu</label>
    <input type="text" id="rss_subtitle" name="rss_subtitle" maxlength="255" aria-describedby="blog-feed-help">

    <label for="feed_item_limit">Počet článků v RSS feedu</label>
    <input type="number" id="feed_item_limit" name="feed_item_limit" min="1" max="100" value="20" style="width:auto" aria-describedby="blog-feed-help">
    <small id="blog-feed-help" class="field-help">Použije se pro samostatný feed konkrétního blogu.</small>

    <label for="logo_file">Logo blogu</label>
    <input type="file" id="logo_file" name="logo_file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="blog-logo-help">
    <small id="blog-logo-help" class="field-help">Volitelné. Logo se zobrazí nad popisem blogu na jeho veřejném indexu. Podporované jsou JPEG, PNG, GIF a WebP; pevný rozměr není nutný.</small>

    <label for="logo_alt_text">Alternativní text loga</label>
    <input type="text" id="logo_alt_text" name="logo_alt_text" maxlength="255" aria-describedby="blog-logo-alt-help">
    <small id="blog-logo-alt-help" class="field-help">Volitelné. Pokud pole necháte prázdné, logo zůstane dekorativní a čtečky obrazovek ho přeskočí. Vyplňte ho jen tehdy, když logo nese smysluplnou informaci.</small>

    <div style="margin-top:.5rem">
      <label><input type="checkbox" name="comments_default" value="1" checked> Ve výchozím stavu povolit komentáře u nových článků</label>
    </div>

    <div style="margin-top:.5rem">
      <label><input type="checkbox" name="show_in_nav" value="1" checked> Zobrazit v navigaci webu</label>
    </div>

    <button type="submit" class="btn" style="margin-top:.5rem">Vytvořit blog</button>
  </fieldset>
</form>

<h2>Přehled blogů</h2>
<?php if (empty($blogs)): ?>
  <p>Zatím tu nejsou žádné blogy.</p>
<?php else: ?>
  <table>
    <caption>Přehled blogů</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Slug</th>
        <th scope="col">Články</th>
        <th scope="col">Tým</th>
        <th scope="col">Zakladatel</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody data-sortable="blogs">
    <?php foreach ($blogs as $blog): ?>
      <tr data-sort-id="<?= (int)$blog['id'] ?>" tabindex="0" style="cursor:grab">
        <td>
          <?= h((string)$blog['name']) ?>
          <?php if ((int)$blog['id'] === $defaultBlogId): ?>
            <small class="field-help">(výchozí blog)</small>
          <?php endif; ?>
          <?php if (!(int)($blog['show_in_nav'] ?? 1)): ?>
            <small class="field-help">(mimo navigaci)</small>
          <?php endif; ?>
          <?php if ((string)($blog['description'] ?? '') !== ''): ?>
            <br><small class="field-help"><?= h((string)$blog['description']) ?></small>
          <?php endif; ?>
        </td>
        <td><code><?= h((string)$blog['slug']) ?></code></td>
        <td><?= (int)$blog['article_count'] ?></td>
        <td>
          <?= (int)($blog['member_count'] ?? 0) ?>
          <?php if ((int)($blog['member_count'] ?? 0) > 0): ?>
            <br><small class="field-help">Přiřazených autorů a správců</small>
          <?php else: ?>
            <br><small class="field-help">Bez týmu</small>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!empty($blog['created_by_user_id'])): ?>
            <?php $blogCreatorLabel = trim((string)($blog['creator_label'] ?? '')) !== '' ? (string)$blog['creator_label'] : ('Uživatel #' . (int)$blog['created_by_user_id']); ?>
            <?= h($blogCreatorLabel) ?>
            <?php if (!empty($blog['creator_email']) && (string)$blog['creator_email'] !== $blogCreatorLabel): ?>
              <br><small class="field-help"><?= h((string)$blog['creator_email']) ?></small>
            <?php endif; ?>
          <?php else: ?>
            <span class="field-help">Neevidován</span>
            <br><small class="field-help">Doplníte na stránce Tým blogu.</small>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="<?= h(blogIndexPath($blog)) ?>" class="btn" target="_blank" rel="noopener">Zobrazit na webu</a>
          <a href="<?= h(blogFeedPath($blog)) ?>" class="btn" target="_blank" rel="noopener">RSS feed</a>
          <button type="button" class="btn blog-edit-btn" style="font-size:.85rem"
                  aria-label="Upravit blog <?= h((string)$blog['name']) ?>"
                  aria-haspopup="dialog"
                  aria-controls="blog-dialog"
                  aria-expanded="false"
                  data-blog-id="<?= (int)$blog['id'] ?>"
                  data-blog-name="<?= h((string)$blog['name']) ?>"
                  data-blog-slug="<?= h((string)$blog['slug']) ?>"
                  data-blog-desc="<?= h((string)($blog['description'] ?? '')) ?>"
                  data-blog-intro-content="<?= h((string)($blog['intro_content'] ?? '')) ?>"
                  data-blog-meta-title="<?= h((string)($blog['meta_title'] ?? '')) ?>"
                  data-blog-meta-description="<?= h((string)($blog['meta_description'] ?? '')) ?>"
                  data-blog-rss-subtitle="<?= h((string)($blog['rss_subtitle'] ?? '')) ?>"
                  data-blog-comments-default="<?= (int)($blog['comments_default'] ?? 1) ?>"
                  data-blog-feed-item-limit="<?= (int)($blog['feed_item_limit'] ?? 20) ?>"
                  data-blog-nav="<?= (int)($blog['show_in_nav'] ?? 1) ?>"
                  data-blog-logo-alt="<?= h((string)($blog['logo_alt_text'] ?? '')) ?>"
                  data-blog-logo-url="<?= h(blogLogoUrl($blog)) ?>">Upravit</button>
          <a href="blog_pages.php?blog_id=<?= (int)$blog['id'] ?>" class="btn">Stránky blogu</a>
          <a href="blog_members.php?blog_id=<?= (int)$blog['id'] ?>" class="btn">Tým blogu</a>
          <form action="blog_blog_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$blog['id'] ?>">
            <?php if (count($blogs) > 1): ?>
              <button type="submit" class="btn btn-danger"
                      data-confirm="<?= h('Smazat blog „' . (string)$blog['name'] . '“? Články, kategorie a štítky budou přesunuty do jiného blogu.') ?>">Smazat</button>
            <?php else: ?>
              <button type="submit" class="btn btn-danger"
                      data-confirm="POZOR: Toto je poslední blog! Smazáním nenávratně odstraníte VŠECHNY články (<?= (int)$blog['article_count'] ?>), kategorie i tagy. Opravdu chcete pokračovat?">Smazat</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<div id="blog-overlay" hidden style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.54);z-index:1000"></div>
<section id="blog-dialog" role="dialog" aria-modal="true" aria-labelledby="blog-dialog-title" aria-describedby="blog-dialog-description" hidden
         style="display:none;position:fixed;inset:50% auto auto 50%;transform:translate(-50%,-50%);
                width:min(30rem,calc(100vw - 2rem));max-height:calc(100vh - 2rem);overflow:auto;
                padding:1.2rem;border:1px solid #cbd5e1;border-radius:.9rem;background:#fff;
                box-shadow:0 28px 60px rgba(15,23,42,.28);z-index:1001">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h2 id="blog-dialog-title" style="margin:0;font-size:1.15rem">Upravit blog</h2>
    <button type="button" id="blog-dialog-close" class="btn" aria-label="Zavřít dialog">✕</button>
  </div>
  <p id="blog-dialog-description" class="field-help" style="margin-top:0">Upravte název, adresu, logo a viditelnost blogu v navigaci webu.</p>
  <form method="post" enctype="multipart/form-data" novalidate id="blog-dialog-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="update_id" id="bd-id">

    <label for="bd-name">Název blogu <span aria-hidden="true">*</span></label>
    <input type="text" id="bd-name" name="name" required aria-required="true" maxlength="255">

    <label for="bd-slug">Slug (URL) <span aria-hidden="true">*</span></label>
    <input type="text" id="bd-slug" name="slug" required aria-required="true" maxlength="100"
           pattern="[a-z0-9\-]+" title="Pouze malá písmena, číslice a pomlčky">

    <label for="bd-desc">Popis</label>
    <textarea id="bd-desc" name="description" rows="2"></textarea>

    <label for="bd-intro-content">Rozšířený úvod blogu</label>
    <textarea id="bd-intro-content" name="intro_content" rows="5" aria-describedby="bd-intro-help"></textarea>
    <small id="bd-intro-help" class="field-help">Volitelné. Delší text nebo HTML blok pod názvem a popisem blogu. <?= adminHtmlSnippetSupportMarkup() ?></small>
    <?php renderAdminContentReferencePicker('bd-intro-content'); ?>

    <label for="bd-meta-title">Meta titulek</label>
    <input type="text" id="bd-meta-title" name="meta_title" maxlength="160">

    <label for="bd-meta-description">Meta popis</label>
    <textarea id="bd-meta-description" name="meta_description" rows="2"></textarea>

    <label for="bd-rss-subtitle">Podtitulek RSS feedu</label>
    <input type="text" id="bd-rss-subtitle" name="rss_subtitle" maxlength="255">

    <label for="bd-feed-item-limit">Počet článků v RSS feedu</label>
    <input type="number" id="bd-feed-item-limit" name="feed_item_limit" min="1" max="100" style="width:auto">

    <div id="bd-logo-current" hidden style="margin-top:.75rem">
      <span class="field-help">Aktuální logo</span><br>
      <img id="bd-logo-preview" src="" alt="" style="display:block;margin-top:.4rem;max-width:min(100%,20rem);max-height:7rem;height:auto;width:auto">
    </div>

    <label for="bd-logo-file" style="margin-top:.75rem">Logo blogu</label>
    <input type="file" id="bd-logo-file" name="logo_file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="bd-logo-help">
    <small id="bd-logo-help" class="field-help">Volitelné. Logo se zobrazí nad popisem blogu na jeho veřejném indexu.</small>

    <label for="bd-logo-alt-text" style="margin-top:.75rem">Alternativní text loga</label>
    <input type="text" id="bd-logo-alt-text" name="logo_alt_text" maxlength="255" aria-describedby="bd-logo-alt-help">
    <small id="bd-logo-alt-help" class="field-help">Volitelné. Když pole necháte prázdné, logo zůstane dekorativní a čtečky ho přeskočí.</small>

    <div id="bd-logo-delete-wrap" style="margin-top:.5rem" hidden>
      <label><input type="checkbox" name="logo_file_delete" value="1" id="bd-logo-delete"> Odebrat aktuální logo</label>
    </div>

    <div style="margin-top:.5rem">
      <label><input type="checkbox" name="comments_default" value="1" id="bd-comments-default"> Ve výchozím stavu povolit komentáře u nových článků</label>
    </div>

    <div style="margin-top:.5rem">
      <label><input type="checkbox" name="show_in_nav" value="1" id="bd-nav"> Zobrazit v navigaci webu</label>
    </div>

    <div class="button-row" style="margin-top:1rem">
      <button type="submit" class="btn">Uložit změny</button>
      <button type="button" id="blog-dialog-cancel" class="btn">Zrušit</button>
    </div>
  </form>
</section>

<script nonce="<?= cspNonce() ?>">
(function () {
    var overlay = document.getElementById('blog-overlay');
    var dialog = document.getElementById('blog-dialog');
    var closeBtn = document.getElementById('blog-dialog-close');
    var cancelBtn = document.getElementById('blog-dialog-cancel');
    var lastTrigger = null;
    var previousBodyOverflow = '';
    var logoPreviewWrap = document.getElementById('bd-logo-current');
    var logoPreview = document.getElementById('bd-logo-preview');
    var logoDeleteWrap = document.getElementById('bd-logo-delete-wrap');
    var logoDelete = document.getElementById('bd-logo-delete');
    var logoFileInput = document.getElementById('bd-logo-file');
    var logoAltInput = document.getElementById('bd-logo-alt-text');

    function openDialog(btn) {
        lastTrigger = btn;
        document.getElementById('bd-id').value = btn.dataset.blogId;
        document.getElementById('bd-name').value = btn.dataset.blogName;
        document.getElementById('bd-slug').value = btn.dataset.blogSlug;
        document.getElementById('bd-desc').value = btn.dataset.blogDesc;
        document.getElementById('bd-intro-content').value = btn.dataset.blogIntroContent || '';
        document.getElementById('bd-meta-title').value = btn.dataset.blogMetaTitle || '';
        document.getElementById('bd-meta-description').value = btn.dataset.blogMetaDescription || '';
        document.getElementById('bd-rss-subtitle').value = btn.dataset.blogRssSubtitle || '';
        document.getElementById('bd-comments-default').checked = btn.dataset.blogCommentsDefault !== '0';
        document.getElementById('bd-feed-item-limit').value = btn.dataset.blogFeedItemLimit || '20';
        document.getElementById('bd-nav').checked = btn.dataset.blogNav === '1';
        logoDelete.checked = false;
        logoFileInput.value = '';
        logoAltInput.value = btn.dataset.blogLogoAlt || '';
        if (btn.dataset.blogLogoUrl) {
            logoPreview.src = btn.dataset.blogLogoUrl;
            logoPreview.alt = btn.dataset.blogLogoAlt || '';
            logoPreviewWrap.hidden = false;
            logoDeleteWrap.hidden = false;
        } else {
            logoPreview.src = '';
            logoPreview.alt = '';
            logoPreviewWrap.hidden = true;
            logoDeleteWrap.hidden = true;
        }
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        overlay.hidden = false;
        dialog.hidden = false;
        overlay.style.display = '';
        dialog.style.display = '';
        btn.setAttribute('aria-expanded', 'true');
        window.requestAnimationFrame(function () {
            document.getElementById('bd-name').focus();
        });
    }

    function closeDialog() {
        if (lastTrigger) {
            lastTrigger.setAttribute('aria-expanded', 'false');
        }
        document.body.style.overflow = previousBodyOverflow;
        overlay.style.display = 'none';
        dialog.style.display = 'none';
        overlay.hidden = true;
        dialog.hidden = true;
        if (lastTrigger) lastTrigger.focus();
    }

    document.querySelectorAll('.blog-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function() { openDialog(this); });
    });
    closeBtn.addEventListener('click', closeDialog);
    cancelBtn.addEventListener('click', closeDialog);
    overlay.addEventListener('click', closeDialog);
    dialog.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { e.preventDefault(); closeDialog(); }
        if (e.key === 'Tab') {
            var focusable = Array.from(dialog.querySelectorAll('input:not([type=hidden]),textarea,button,select'));
            if (focusable.length === 0) return;
            if (e.shiftKey && document.activeElement === focusable[0]) { e.preventDefault(); focusable[focusable.length-1].focus(); }
            else if (!e.shiftKey && document.activeElement === focusable[focusable.length-1]) { e.preventDefault(); focusable[0].focus(); }
        }
    });

    var nameInput = document.getElementById('name');
    var slugInput = document.getElementById('slug');
    var slugManuallyEdited = false;
    slugInput.addEventListener('input', function () { slugManuallyEdited = true; });
    nameInput.addEventListener('input', function () {
        if (slugManuallyEdited) return;
        slugInput.value = this.value
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    });
})();
</script>

<?= sortableJs() ?>
<?php adminFooter(); ?>
