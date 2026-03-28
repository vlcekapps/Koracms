<?php
require_once __DIR__ . '/layout.php';
requireCapability('blog_taxonomies_manage', 'Přístup odepřen. Pro správu štítků blogu nemáte potřebné oprávnění.');

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
        $error = 'Název štítku je povinný.';
    } else {
        $slug = slugify($name);
        if ($updateId !== null) {
            $pdo->prepare("UPDATE cms_tags SET name = ?, slug = ? WHERE id = ? AND blog_id = ?")
                ->execute([$name, $slug, $updateId, $blogId]);
            logAction('tag_edit', "id={$updateId} name={$name}");
            $editId = null;
        } else {
            try {
                $pdo->prepare("INSERT INTO cms_tags (name, slug, blog_id) VALUES (?, ?, ?)")
                    ->execute([$name, $slug, $blogId]);
                logAction('tag_add', "name={$name} blog_id={$blogId}");
            } catch (\PDOException $e) {
                $error = 'Štítek s tímto názvem nebo slugem už v tomto blogu existuje.';
            }
        }
        if ($error === '') {
            $success = true;
        }
    }
}

$tagStmt = $pdo->prepare("SELECT id, name, slug FROM cms_tags WHERE blog_id = ? ORDER BY name");
$tagStmt->execute([$blogId]);
$tags = $tagStmt->fetchAll();

adminHeader('Štítky blogu' . (isMultiBlog() && $currentBlog ? ' – ' . $currentBlog['name'] : ''));
?>

<?php if ($success): ?><p class="success" role="status">Štítek uložen.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="blog.php?blog=<?= (int)$blogId ?>"><span aria-hidden="true">←</span> Zpět na články</a>
  <a href="blogs.php">Správa blogů</a>
  <a href="blog_cats.php?blog_id=<?= (int)$blogId ?>">Kategorie blogu</a>
  <?php if ($currentBlog): ?>
    <a href="<?= h(blogIndexPath($currentBlog)) ?>" target="_blank" rel="noopener">Zobrazit blog na webu</a>
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
    <legend>Nový štítek</legend>
    <label for="name">Název <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" required aria-required="true" maxlength="100">
    <button type="submit" style="margin-top:.5rem">Přidat štítek</button>
  </fieldset>
</form>

<h2>Přehled štítků blogu</h2>
<?php if (empty($tags)): ?>
  <p>Zatím tu nejsou žádné štítky.</p>
<?php else: ?>
  <table>
    <caption>Přehled štítků blogu</caption>
    <thead><tr><th scope="col">Název</th><th scope="col">Slug</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($tags as $tag): ?>
      <tr>
        <td>
          <?php if ($editId === (int)$tag['id']): ?>
            <form method="post" style="display:flex;gap:.4rem;align-items:center">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= (int)$tag['id'] ?>">
              <input type="hidden" name="blog_id" value="<?= $blogId ?>">
              <input type="text" name="name" required aria-required="true" maxlength="100"
                     value="<?= h($tag['name']) ?>" style="width:auto">
              <button type="submit" class="btn">Uložit</button>
              <a href="blog_tags.php?blog_id=<?= $blogId ?>">Zrušit</a>
            </form>
          <?php else: ?>
            <?= h($tag['name']) ?>
          <?php endif; ?>
        </td>
        <td><code><?= h($tag['slug']) ?></code></td>
        <td class="actions">
          <?php if ($editId !== (int)$tag['id']): ?>
            <a href="blog_tags.php?edit=<?= (int)$tag['id'] ?>&amp;blog_id=<?= $blogId ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="blog_tag_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$tag['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat štítek?">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php adminFooter(); ?>
