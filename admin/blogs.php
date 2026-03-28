<?php
require_once __DIR__ . '/layout.php';
requireCapability('blog_taxonomies_manage', 'Přístup odepřen. Pro správu blogů nemáte potřebné oprávnění.');

$pdo = db_connect();
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name     = trim($_POST['name'] ?? '');
    $slug     = slugify(trim($_POST['slug'] ?? ''));
    $desc     = trim($_POST['description'] ?? '');
    $updateId = inputInt('post', 'update_id');

    if ($name === '') {
        $error = 'Název blogu je povinný.';
    } elseif ($slug === '') {
        $error = 'Slug blogu je povinný.';
    } elseif (in_array($slug, reservedBlogSlugs(), true)) {
        $error = 'Slug „' . h($slug) . '" je rezervovaný a nelze ho použít.';
    } elseif (is_dir(__DIR__ . '/../' . $slug) && ($updateId === null || getBlogBySlug($slug) === null || (int)getBlogBySlug($slug)['id'] !== $updateId)) {
        $error = 'Slug „' . h($slug) . '" koliduje s existujícím adresářem na serveru.';
    } elseif ($updateId !== null) {
        try {
            $pdo->prepare("UPDATE cms_blogs SET name = ?, slug = ?, description = ? WHERE id = ?")
                ->execute([$name, $slug, $desc, $updateId]);
            $success = 'Blog upraven.';
        } catch (\PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Slug blogu je už obsazený.' : 'Chyba při ukládání.';
        }
    } else {
        $sortOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM cms_blogs")->fetchColumn();
        try {
            $pdo->prepare("INSERT INTO cms_blogs (name, slug, description, sort_order) VALUES (?, ?, ?, ?)")
                ->execute([$name, $slug, $desc, $sortOrder]);
            $success = 'Blog vytvořen.';
        } catch (\PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Slug blogu je už obsazený.' : 'Chyba při ukládání.';
        }
    }
}

$blogs = $pdo->query(
    "SELECT b.*, (SELECT COUNT(*) FROM cms_articles WHERE blog_id = b.id) AS article_count
     FROM cms_blogs b ORDER BY b.sort_order, b.name"
)->fetchAll();

adminHeader('Správa blogů');
?>
<?php if ($success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="blog.php"><span aria-hidden="true">←</span> Zpět na články</a>
</p>

<form method="post" novalidate>
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

    <button type="submit" class="btn" style="margin-top:.5rem">Přidat blog</button>
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
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody data-sortable="blogs">
    <?php foreach ($blogs as $blog): ?>
      <tr data-sort-id="<?= (int)$blog['id'] ?>" tabindex="0" style="cursor:grab">
        <td>
          <?= h((string)$blog['name']) ?>
          <?php if ((string)($blog['description'] ?? '') !== ''): ?>
            <br><small class="field-help"><?= h((string)$blog['description']) ?></small>
          <?php endif; ?>
        </td>
        <td><code><?= h((string)$blog['slug']) ?></code></td>
        <td><?= (int)$blog['article_count'] ?></td>
        <td class="actions">
          <button type="button" class="btn blog-edit-btn" style="font-size:.85rem"
                  aria-label="Upravit blog <?= h((string)$blog['name']) ?>"
                  data-blog-id="<?= (int)$blog['id'] ?>"
                  data-blog-name="<?= h((string)$blog['name']) ?>"
                  data-blog-slug="<?= h((string)$blog['slug']) ?>"
                  data-blog-desc="<?= h((string)($blog['description'] ?? '')) ?>">Upravit</button>
          <form action="blog_blog_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$blog['id'] ?>">
            <?php if (count($blogs) > 1): ?>
              <button type="submit" class="btn btn-danger"
                      data-confirm="Smazat blog „<?= h((string)$blog['name']) ?>"? Články, kategorie a tagy budou přesunuty do jiného blogu.">Smazat</button>
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

<!-- Modal dialog pro editaci blogu -->
<div id="blog-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.54);z-index:1000"></div>
<section id="blog-dialog" role="dialog" aria-modal="true" aria-labelledby="blog-dialog-title"
         style="display:none;position:fixed;inset:50% auto auto 50%;transform:translate(-50%,-50%);
                width:min(30rem,calc(100vw - 2rem));max-height:calc(100vh - 2rem);overflow:auto;
                padding:1.2rem;border:1px solid #cbd5e1;border-radius:.9rem;background:#fff;
                box-shadow:0 28px 60px rgba(15,23,42,.28);z-index:1001">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h2 id="blog-dialog-title" style="margin:0;font-size:1.15rem">Upravit blog</h2>
    <button type="button" id="blog-dialog-close" class="btn" aria-label="Zavřít dialog">✕</button>
  </div>
  <form method="post" novalidate id="blog-dialog-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="update_id" id="bd-id">

    <label for="bd-name">Název blogu <span aria-hidden="true">*</span></label>
    <input type="text" id="bd-name" name="name" required aria-required="true" maxlength="255">

    <label for="bd-slug">Slug (URL) <span aria-hidden="true">*</span></label>
    <input type="text" id="bd-slug" name="slug" required aria-required="true" maxlength="100"
           pattern="[a-z0-9\-]+" title="Pouze malá písmena, číslice a pomlčky">

    <label for="bd-desc">Popis</label>
    <textarea id="bd-desc" name="description" rows="2"></textarea>

    <div class="button-row" style="margin-top:1rem">
      <button type="submit" class="btn">Uložit</button>
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

    function openDialog(btn) {
        lastTrigger = btn;
        document.getElementById('bd-id').value = btn.dataset.blogId;
        document.getElementById('bd-name').value = btn.dataset.blogName;
        document.getElementById('bd-slug').value = btn.dataset.blogSlug;
        document.getElementById('bd-desc').value = btn.dataset.blogDesc;
        overlay.style.display = '';
        dialog.style.display = '';
        document.getElementById('bd-name').focus();
    }

    function closeDialog() {
        overlay.style.display = 'none';
        dialog.style.display = 'none';
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

    // Auto-slug z názvu (nový blog formulář)
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
