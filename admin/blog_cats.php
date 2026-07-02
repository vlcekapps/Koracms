<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');
requireModuleEnabled('blog');

if (!hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blogs.php?msg=no_blog');
    exit;
}

$pdo = db_connect();
$success = false;
$error = '';
$fieldErrors = [];
$formValues = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'meta_title' => '',
    'meta_description' => '',
    'parent_id' => '',
];
$allBlogs = getTaxonomyManagedBlogsForUser();
if ($allBlogs === []) {
    requireCapability('blog_taxonomies_manage', 'Přístup odepřen. Pro správu kategorií blogu nemáte potřebné oprávnění.');
}

$allowedBlogIds = array_map(static fn (array $blog): int => (int)$blog['id'], $allBlogs);
$blogId = inputInt('get', 'blog_id') ?? inputInt('post', 'blog_id') ?? (int)($allBlogs[0]['id'] ?? 0);
if (!in_array($blogId, $allowedBlogIds, true)) {
    $blogId = (int)($allBlogs[0]['id'] ?? 0);
}

$currentBlog = getBlogById($blogId) ?? ($allBlogs[0] ?? getDefaultBlog());
$blogId = (int)($currentBlog['id'] ?? $blogId);
$editId = inputInt('get', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $slugInput = trim((string)($_POST['slug'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $metaTitle = trim((string)($_POST['meta_title'] ?? ''));
    $metaDescription = trim((string)($_POST['meta_description'] ?? ''));
    $updateId = inputInt('post', 'update_id');
    $parentId = inputInt('post', 'parent_id');
    $formValues = [
        'name' => $name,
        'slug' => $slugInput,
        'description' => $description,
        'meta_title' => $metaTitle,
        'meta_description' => $metaDescription,
        'parent_id' => $parentId !== null ? (string)$parentId : '',
    ];

    if ($name === '') {
        $fieldErrors['name'] = 'Název kategorie je povinný.';
    } elseif (!canCurrentUserManageBlogTaxonomies($blogId)) {
        $error = 'Vybraný blog nemůžete spravovat.';
    }

    $normalizedSlug = blogCategorySlug($slugInput !== '' ? $slugInput : $name);
    $slugWasGenerated = $slugInput === '';
    if ($normalizedSlug === '') {
        $normalizedSlug = 'kategorie';
    }
    if ($fieldErrors === [] && $error === '') {
        $uniqueSlug = uniqueBlogCategorySlug($pdo, $normalizedSlug, $blogId, $updateId);
        if (!$slugWasGenerated && $uniqueSlug !== $normalizedSlug) {
            $fieldErrors['slug'] = 'Tento slug už v tomto blogu používá jiná kategorie.';
        } else {
            $normalizedSlug = $uniqueSlug;
        }
    }

    if ($fieldErrors !== [] && $error === '') {
        $error = 'Zkontrolujte prosím zvýrazněná pole.';
    }

    if ($error === '' && $fieldErrors === [] && $updateId !== null) {
        if ($parentId === $updateId) {
            $parentId = null;
        }
        // Ochrana proti cyklické referenci – ověříme, že navržený rodič není přímým potomkem
        if ($parentId !== null) {
            $childStmt = $pdo->prepare("SELECT id FROM cms_categories WHERE parent_id = ? AND blog_id = ?");
            $childStmt->execute([$updateId, $blogId]);
            $childIds = array_column($childStmt->fetchAll(), 'id');
            if (in_array($parentId, array_map('intval', $childIds), true)) {
                $parentId = null;
            }
        }
        $pdo->prepare(
            "UPDATE cms_categories
             SET name = ?, slug = ?, parent_id = ?, description = ?, meta_title = ?, meta_description = ?
             WHERE id = ? AND blog_id = ?"
        )->execute([$name, $normalizedSlug, $parentId, $description, $metaTitle, $metaDescription, $updateId, $blogId]);
        $success = true;
        $editId = null;
    } elseif ($error === '' && $fieldErrors === []) {
        $pdo->prepare(
            "INSERT INTO cms_categories (name, slug, blog_id, parent_id, description, meta_title, meta_description)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([$name, $normalizedSlug, $blogId, $parentId, $description, $metaTitle, $metaDescription]);
        $success = true;
        $formValues = [
            'name' => '',
            'slug' => '',
            'description' => '',
            'meta_title' => '',
            'meta_description' => '',
            'parent_id' => '',
        ];
    }
}

$catStmt = $pdo->prepare(
    "SELECT id, name, slug, parent_id, description, meta_title, meta_description
     FROM cms_categories
     WHERE blog_id = ?
     ORDER BY name"
);
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

/**
 * @param array<int, list<array{id:int|string,name:string,slug?:string,parent_id:int|string|null,description?:string|null,meta_title?:string|null,meta_description?:string|null}>> $tree
 * @param array<int, array{id:int|string,name:string,slug?:string,parent_id:int|string|null,description?:string|null,meta_title?:string|null,meta_description?:string|null}> $byId
 */
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
/**
 * @param array<int, list<array{id:int|string,name:string,slug?:string,parent_id:int|string|null,description?:string|null,meta_title?:string|null,meta_description?:string|null}>> $tree
 * @return list<array{id:int|string,name:string,slug?:string,parent_id:int|string|null,description?:string|null,meta_title?:string|null,meta_description?:string|null,_depth:int}>
 */
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
    <a href="<?= h(blogIndexPath($currentBlog)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit blog na webu<?= newWindowLinkSrOnlySuffix() ?></a>
    <a href="<?= h(blogFeedPath($currentBlog)) ?>" target="_blank" rel="noopener noreferrer">RSS feed blogu<?= newWindowLinkSrOnlySuffix() ?></a>
  <?php endif; ?>
</p>

<?php if (count($allBlogs) > 1): ?>
<form method="get" class="button-row admin-stack-sm">
  <label for="blog_id">Blog:</label>
  <select id="blog_id" name="blog_id" class="admin-select-sm">
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
    <p id="category-slug-help">Slug je veřejná část URL. Když ho necháte prázdný, vygeneruje se z názvu.</p>
    <p id="category-description-help">Popis se zobrazí na veřejné stránce kategorie nad výpisem článků.</p>
    <div class="form-grid">
      <div>
        <label for="name">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" required aria-required="true" maxlength="255"
               value="<?= h($formValues['name']) ?>"<?= isset($fieldErrors['name']) ? ' aria-invalid="true" aria-describedby="category-name-error"' : '' ?>>
        <?php if (isset($fieldErrors['name'])): ?><p id="category-name-error" class="error"><?= h($fieldErrors['name']) ?></p><?php endif; ?>
      </div>
      <div>
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" maxlength="150"
               value="<?= h($formValues['slug']) ?>" aria-describedby="category-slug-help<?= isset($fieldErrors['slug']) ? ' category-slug-error' : '' ?>"<?= isset($fieldErrors['slug']) ? ' aria-invalid="true"' : '' ?>>
        <?php if (isset($fieldErrors['slug'])): ?><p id="category-slug-error" class="error"><?= h($fieldErrors['slug']) ?></p><?php endif; ?>
      </div>
      <div>
        <label for="parent_id">Nadřazená kategorie</label>
        <select id="parent_id" name="parent_id">
          <option value="">— Kořenová —</option>
          <?= renderBlogCategoryOptions($blogCatTree, $blogCatById) ?>
        </select>
      </div>
      <div>
        <label for="meta_title">Meta title</label>
        <input type="text" id="meta_title" name="meta_title" maxlength="160" value="<?= h($formValues['meta_title']) ?>">
      </div>
    </div>
    <div>
      <label for="description">Popis</label>
      <textarea id="description" name="description" rows="4" aria-describedby="category-description-help"><?= h($formValues['description']) ?></textarea>
    </div>
    <div>
      <label for="meta_description">Meta description</label>
      <textarea id="meta_description" name="meta_description" rows="3"><?= h($formValues['meta_description']) ?></textarea>
    </div>
    <button type="submit" class="btn admin-action-row">Přidat kategorii</button>
  </fieldset>
</form>

<h2>Přehled kategorií blogu</h2>
<?php if (empty($flatCategories)): ?>
  <p>Zatím tu nejsou žádné kategorie.</p>
<?php else: ?>
  <?= bulkActions('blog_categories', BASE_URL . '/admin/blog_cats.php?blog_id=' . $blogId, 'Hromadné akce s kategoriemi', 'kategorie', false) ?>
  <table>
    <caption>Přehled kategorií blogu</caption>
    <thead><tr><th scope="col"><label for="check-all" class="sr-only">Vybrat vše</label><input type="checkbox" id="check-all"></th><th scope="col">Název</th><th scope="col">Slug</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($flatCategories as $category): ?>
      <?php $indent = str_repeat('— ', (int)$category['_depth']); ?>
      <tr>
        <td><label for="blog-category-select-<?= (int)$category['id'] ?>" class="sr-only">Vybrat <?= h((string)$category['name']) ?></label><input type="checkbox" id="blog-category-select-<?= (int)$category['id'] ?>" name="ids[]" value="<?= (int)$category['id'] ?>" form="bulk-form"></td>
        <td>
          <?php if ($editId === (int)$category['id']): ?>
            <form method="post" class="form-stack" novalidate>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= (int)$category['id'] ?>">
              <input type="hidden" name="blog_id" value="<?= $blogId ?>">
              <div class="form-grid">
                <div>
                  <label for="edit-name-<?= (int)$category['id'] ?>">Název <span aria-hidden="true">*</span></label>
                  <input type="text" id="edit-name-<?= (int)$category['id'] ?>" name="name" required aria-required="true" maxlength="255"
                         value="<?= h((string)$category['name']) ?>" class="admin-input-auto">
                </div>
                <div>
                  <label for="edit-slug-<?= (int)$category['id'] ?>">Slug</label>
                  <input type="text" id="edit-slug-<?= (int)$category['id'] ?>" name="slug" maxlength="150"
                         value="<?= h((string)($category['slug'] ?? '')) ?>" class="admin-input-auto">
                </div>
                <div>
                  <label for="edit-parent-<?= (int)$category['id'] ?>">Nadřazená kategorie</label>
                  <select id="edit-parent-<?= (int)$category['id'] ?>" name="parent_id" class="admin-input-auto">
                    <option value="">— Kořenová —</option>
                    <?= renderBlogCategoryOptions($blogCatTree, $blogCatById, 0, 0, (int)$category['id']) ?>
                  </select>
                </div>
                <div>
                  <label for="edit-meta-title-<?= (int)$category['id'] ?>">Meta title</label>
                  <input type="text" id="edit-meta-title-<?= (int)$category['id'] ?>" name="meta_title" maxlength="160"
                         value="<?= h((string)($category['meta_title'] ?? '')) ?>" class="admin-input-auto">
                </div>
              </div>
              <div>
                <label for="edit-description-<?= (int)$category['id'] ?>">Popis</label>
                <textarea id="edit-description-<?= (int)$category['id'] ?>" name="description" rows="4"><?= h((string)($category['description'] ?? '')) ?></textarea>
              </div>
              <div>
                <label for="edit-meta-description-<?= (int)$category['id'] ?>">Meta description</label>
                <textarea id="edit-meta-description-<?= (int)$category['id'] ?>" name="meta_description" rows="3"><?= h((string)($category['meta_description'] ?? '')) ?></textarea>
              </div>
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
        <td><code><?= h((string)($category['slug'] ?? '')) ?></code></td>
        <td class="actions">
          <?php if ($editId !== (int)$category['id']): ?>
            <a href="blog_cats.php?edit=<?= (int)$category['id'] ?>&amp;blog_id=<?= $blogId ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <?php if ($currentBlog && trim((string)($category['slug'] ?? '')) !== ''): ?>
            <a href="<?= h(blogCategoryPath($currentBlog, $category)) ?>" class="btn" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
          <?php endif; ?>
          <form action="blog_cat_delete.php" method="post">
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
