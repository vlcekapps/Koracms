<?php
require_once __DIR__ . '/layout.php';
requireCapability('blog_taxonomies_manage', 'Přístup odepřen. Pro správu blogů nemáte potřebné oprávnění.');

$pdo = db_connect();
$success = '';
$error   = '';

$editId = inputInt('get', 'edit');

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
            $editId = null;
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
    <tbody>
    <?php foreach ($blogs as $blog): ?>
      <tr>
        <td>
          <?php if ($editId === (int)$blog['id']): ?>
            <form method="post" style="display:flex;flex-direction:column;gap:.4rem">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= (int)$blog['id'] ?>">
              <input type="text" name="name" required aria-required="true" aria-label="Název blogu" maxlength="255"
                     value="<?= h((string)$blog['name']) ?>" style="width:auto">
              <input type="text" name="slug" required aria-required="true" aria-label="Slug blogu" maxlength="100"
                     pattern="[a-z0-9\-]+" value="<?= h((string)$blog['slug']) ?>" style="width:auto">
              <textarea name="description" rows="2" aria-label="Popis blogu" style="width:auto"><?= h((string)$blog['description']) ?></textarea>
              <div class="button-row">
                <button type="submit" class="btn">Uložit</button>
                <a href="blogs.php">Zrušit</a>
              </div>
            </form>
          <?php else: ?>
            <?= h((string)$blog['name']) ?>
            <?php if ((string)$blog['description'] !== ''): ?>
              <br><small class="field-help"><?= h((string)$blog['description']) ?></small>
            <?php endif; ?>
          <?php endif; ?>
        </td>
        <td><code><?= h((string)$blog['slug']) ?></code></td>
        <td><?= (int)$blog['article_count'] ?></td>
        <td class="actions">
          <?php if ($editId !== (int)$blog['id']): ?>
            <a href="blogs.php?edit=<?= (int)$blog['id'] ?>" class="btn">Upravit</a>
          <?php endif; ?>
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

<script nonce="<?= cspNonce() ?>">
(function () {
    var nameInput = document.getElementById('name');
    var slugInput = document.getElementById('slug');
    var slugManuallyEdited = false;

    slugInput.addEventListener('input', function () {
        slugManuallyEdited = true;
    });

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

<?php adminFooter(); ?>
