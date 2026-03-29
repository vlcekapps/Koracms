<?php
require_once __DIR__ . '/layout.php';
requireCapability('blog_taxonomies_manage', 'Přístup odepřen. Pro správu kategorií blogu nemáte potřebné oprávnění.');

if (!hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blogs.php?msg=no_blog');
    exit;
}

$pdo = db_connect();
$success = false;
$error = '';
$allBlogs = getAllBlogs();
$blogId = inputInt('get', 'blog_id') ?? inputInt('post', 'blog_id') ?? (int)(getDefaultBlog()['id'] ?? 1);
$currentBlog = getBlogById($blogId) ?? getDefaultBlog();
$blogId = (int)($currentBlog['id'] ?? $blogId);
$editId = inputInt('get', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $updateId = inputInt('post', 'update_id');

    if ($name === '') {
        $error = 'Název kategorie je povinný.';
    } elseif ($updateId !== null) {
        $pdo->prepare("UPDATE cms_categories SET name = ? WHERE id = ? AND blog_id = ?")->execute([$name, $updateId, $blogId]);
        $success = true;
        $editId = null;
    } else {
        $pdo->prepare("INSERT INTO cms_categories (name, blog_id) VALUES (?, ?)")->execute([$name, $blogId]);
        $success = true;
    }
}

$catStmt = $pdo->prepare("SELECT id, name FROM cms_categories WHERE blog_id = ? ORDER BY name");
$catStmt->execute([$blogId]);
$categories = $catStmt->fetchAll();

adminHeader('Kategorie blogu' . (isMultiBlog() && $currentBlog ? ' – ' . $currentBlog['name'] : ''));
?>
<?php if ($success): ?><p class="success" role="status">Kategorie uložena.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="blog.php?blog=<?= (int)$blogId ?>"><span aria-hidden="true">←</span> Zpět na články</a>
  <a href="blogs.php">Správa blogů</a>
  <a href="blog_tags.php?blog_id=<?= (int)$blogId ?>">Štítky blogu</a>
  <?php if ($currentBlog): ?>
    <a href="<?= h(blogIndexPath($currentBlog)) ?>" target="_blank" rel="noopener">Zobrazit blog na webu</a>
    <a href="<?= h(blogFeedPath($currentBlog)) ?>" target="_blank" rel="noopener">RSS feed blogu</a>
  <?php endif; ?>
</p>

<?php if (isMultiBlog()): ?>
<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;align-items:center">
  <label for="blog_id">Blog:</label>
  <select id="blog_id" name="blog_id" style="min-width:150px">
    <?php foreach ($allBlogs as $blog): ?>
      <option value="<?= (int)$blog['id'] ?>"<?= (int)$blog['id'] === $blogId ? ' selected' : '' ?>><?= h((string)$blog['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn">Zobrazit</button>
</form>
<?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="blog_id" value="<?= $blogId ?>">
  <fieldset>
    <legend>Nová kategorie</legend>
    <label for="name">Název <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" required aria-required="true" maxlength="255">
    <button type="submit" style="margin-top:.5rem">Přidat kategorii</button>
  </fieldset>
</form>

<h2>Přehled kategorií blogu</h2>
<?php if (empty($categories)): ?>
  <p>Zatím tu nejsou žádné kategorie.</p>
<?php else: ?>
  <?= bulkActions('blog_categories', BASE_URL . '/admin/blog_cats.php?blog_id=' . $blogId, 'Hromadné akce s kategoriemi', 'kategorie', false) ?>
  <table>
    <caption>Přehled kategorií blogu</caption>
    <thead><tr><th scope="col"><input type="checkbox" id="check-all" aria-label="Vybrat vše"></th><th scope="col">Název</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $category): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int)$category['id'] ?>" form="bulk-form" aria-label="Vybrat <?= h((string)$category['name']) ?>"></td>
        <td>
          <?php if ($editId === (int)$category['id']): ?>
            <form method="post" style="display:flex;gap:.4rem;align-items:center">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= (int)$category['id'] ?>">
              <input type="hidden" name="blog_id" value="<?= $blogId ?>">
              <input type="text" name="name" required aria-required="true" maxlength="255"
                     value="<?= h($category['name']) ?>" style="width:auto">
              <button type="submit" class="btn">Uložit</button>
              <a href="blog_cats.php?blog_id=<?= $blogId ?>">Zrušit</a>
            </form>
          <?php else: ?>
            <?= h($category['name']) ?>
          <?php endif; ?>
        </td>
        <td class="actions">
          <?php if ($editId !== (int)$category['id']): ?>
            <a href="blog_cats.php?edit=<?= (int)$category['id'] ?>&amp;blog_id=<?= $blogId ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="blog_cat_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat kategorii?">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= bulkCheckboxJs() ?>
<?php endif; ?>
<?php adminFooter(); ?>
