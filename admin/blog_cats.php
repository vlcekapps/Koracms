<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

if (!hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blogs.php?msg=no_blog');
    exit;
}

$pdo = db_connect();
$success = false;
$error = '';
$allBlogs = getTaxonomyManagedBlogsForUser();
if ($allBlogs === []) {
    requireCapability('blog_taxonomies_manage', 'Přístup odepřen. Pro správu kategorií blogu nemáte potřebné oprávnění.');
}

$allowedBlogIds = array_map(static fn(array $blog): int => (int)$blog['id'], $allBlogs);
$blogId = inputInt('get', 'blog_id') ?? inputInt('post', 'blog_id') ?? (int)($allBlogs[0]['id'] ?? 0);
if (!in_array($blogId, $allowedBlogIds, true)) {
    $blogId = (int)($allBlogs[0]['id'] ?? 0);
}

$currentBlog = getBlogById($blogId) ?? ($allBlogs[0] ?? getDefaultBlog());
$blogId = (int)($currentBlog['id'] ?? $blogId);
$editId = inputInt('get', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $updateId = inputInt('post', 'update_id');
    $parentId = inputInt('post', 'parent_id');

    if ($name === '') {
        $error = 'Název kategorie je povinný.';
    } elseif (!canCurrentUserManageBlogTaxonomies($blogId)) {
        $error = 'Vybraný blog nemůžete spravovat.';
    } elseif ($updateId !== null) {
        if ($parentId === $updateId) {
            $parentId = null;
        }
        $pdo->prepare("UPDATE cms_categories SET name = ?, parent_id = ? WHERE id = ? AND blog_id = ?")->execute([$name, $parentId, $updateId, $blogId]);
        $success = true;
        $editId = null;
    } else {
        $pdo->prepare("INSERT INTO cms_categories (name, blog_id, parent_id) VALUES (?, ?, ?)")->execute([$name, $blogId, $parentId]);
        $success = true;
    }
}

$catStmt = $pdo->prepare("SELECT id, name, parent_id FROM cms_categories WHERE blog_id = ? ORDER BY name");
$catStmt->execute([$blogId]);
$categories = $catStmt->fetchAll();

// Sestavíme strom kategorií
$blogCatTree = [];
$blogCatById = [];
foreach ($categories as $cat) {
    $blogCatById[(int)$cat['id']] = $cat;
}
foreach ($categories as $cat) {
    $pid = $cat['parent_id'] !== null ? (int)$cat['parent_id'] : 0;
    $blogCatTree[$pid][] = $cat;
}

function renderBlogCategoryOptions(array $tree, array $byId, int $parentId = 0, int $depth = 0, ?int $excludeId = null): string
{
    $out = '';
    foreach ($tree[$parentId] ?? [] as $cat) {
        $cid = (int)$cat['id'];
        if ($cid === $excludeId) {
            continue;
        }
        $prefix = str_repeat('— ', $depth);
        $out .= '<option value="' . $cid . '">' . h($prefix . $cat['name']) . '</option>';
        $out .= renderBlogCategoryOptions($tree, $byId, $cid, $depth + 1, $excludeId);
    }
    return $out;
}

// Seřadíme kategorie dle stromu pro zobrazení v tabulce
function flattenBlogCategoryTree(array $tree, int $parentId = 0, int $depth = 0): array
{
    $result = [];
    foreach ($tree[$parentId] ?? [] as $cat) {
        $cat['_depth'] = $depth;
        $result[] = $cat;
        $result = array_merge($result, flattenBlogCategoryTree($tree, (int)$cat['id'], $depth + 1));
    }
    return $result;
}
$flatCategories = flattenBlogCategoryTree($blogCatTree);

adminHeader('Kategorie blogu' . (isMultiBlog() && $currentBlog ? ' – ' . $currentBlog['name'] : ''));
?>
<?php if ($success): ?><p class="success" role="status">Kategorie uložena.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="blog.php?blog=<?= (int)$blogId ?>"><span aria-hidden="true">←</span> Zpět na články</a>
  <a href="blogs.php">Správa blogů</a>
  <a href="blog_members.php?blog_id=<?= (int)$blogId ?>">Tým blogu</a>
  <a href="blog_tags.php?blog_id=<?= (int)$blogId ?>">Štítky blogu</a>
  <?php if ($currentBlog): ?>
    <a href="<?= h(blogIndexPath($currentBlog)) ?>" target="_blank" rel="noopener">Zobrazit blog na webu</a>
    <a href="<?= h(blogFeedPath($currentBlog)) ?>" target="_blank" rel="noopener">RSS feed blogu</a>
  <?php endif; ?>
</p>

<?php if (count($allBlogs) > 1): ?>
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
    <div style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label for="name">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" required aria-required="true" maxlength="255">
      </div>
      <div>
        <label for="parent_id">Nadřazená kategorie</label>
        <select id="parent_id" name="parent_id">
          <option value="">— Kořenová —</option>
          <?= renderBlogCategoryOptions($blogCatTree, $blogCatById) ?>
        </select>
      </div>
      <button type="submit">Přidat kategorii</button>
    </div>
  </fieldset>
</form>

<h2>Přehled kategorií blogu</h2>
<?php if (empty($flatCategories)): ?>
  <p>Zatím tu nejsou žádné kategorie.</p>
<?php else: ?>
  <?= bulkActions('blog_categories', BASE_URL . '/admin/blog_cats.php?blog_id=' . $blogId, 'Hromadné akce s kategoriemi', 'kategorie', false) ?>
  <table>
    <caption>Přehled kategorií blogu</caption>
    <thead><tr><th scope="col"><input type="checkbox" id="check-all" aria-label="Vybrat vše"></th><th scope="col">Název</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($flatCategories as $category): ?>
      <?php $indent = str_repeat('— ', (int)($category['_depth'] ?? 0)); ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int)$category['id'] ?>" form="bulk-form" aria-label="Vybrat <?= h((string)$category['name']) ?>"></td>
        <td>
          <?php if ($editId === (int)$category['id']): ?>
            <form method="post" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap" novalidate>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= (int)$category['id'] ?>">
              <input type="hidden" name="blog_id" value="<?= $blogId ?>">
              <input type="text" name="name" required aria-required="true" maxlength="255"
                     value="<?= h((string)$category['name']) ?>" style="width:auto">
              <label for="edit-parent-<?= (int)$category['id'] ?>" class="sr-only">Nadřazená kategorie</label>
              <select id="edit-parent-<?= (int)$category['id'] ?>" name="parent_id" style="width:auto">
                <option value="">— Kořenová —</option>
                <?= renderBlogCategoryOptions($blogCatTree, $blogCatById, 0, 0, (int)$category['id']) ?>
              </select>
              <script nonce="<?= cspNonce() ?>">
              (function(){
                var sel = document.getElementById('edit-parent-<?= (int)$category['id'] ?>');
                var val = '<?= (int)($category['parent_id'] ?? 0) ?>';
                if (val !== '0' && sel) { for (var i = 0; i < sel.options.length; i++) { if (sel.options[i].value === val) { sel.selectedIndex = i; break; } } }
              })();
              </script>
              <button type="submit" class="btn">Uložit</button>
              <a href="blog_cats.php?blog_id=<?= $blogId ?>">Zrušit</a>
            </form>
          <?php else: ?>
            <?= h($indent . (string)$category['name']) ?>
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
