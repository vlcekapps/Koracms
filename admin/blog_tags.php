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
$fieldErrorMessages = [];
$deleteConfirmError = trim((string)($_GET['delete_error'] ?? '')) === 'confirm_required';
$deleteErrorId = inputInt('get', 'delete_error_id');
$formValues = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'meta_title' => '',
    'meta_description' => '',
];
$allBlogs = getTaxonomyManagedBlogsForUser();
if ($allBlogs === []) {
    requireCapability('blog_taxonomies_manage', 'Přístup odepřen. Pro správu štítků blogu nemáte potřebné oprávnění.');
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
    $formValues = [
        'name' => $name,
        'slug' => $slugInput,
        'description' => $description,
        'meta_title' => $metaTitle,
        'meta_description' => $metaDescription,
    ];

    if ($name === '') {
        $fieldErrors['name'] = true;
        $fieldErrorMessages['name'] = 'Doplňte krátký název štítku, například Rozhovory.';
    } elseif (!canCurrentUserManageBlogTaxonomies($blogId)) {
        $error = 'Vybraný blog nemůžete spravovat.';
    }

    $normalizedSlug = blogTagSlug($slugInput !== '' ? $slugInput : $name);
    $slugWasGenerated = $slugInput === '';
    if ($normalizedSlug === '') {
        $normalizedSlug = 'stitek';
    }
    if ($fieldErrors === [] && $error === '') {
        $uniqueSlug = uniqueBlogTagSlug($pdo, $normalizedSlug, $blogId, $updateId);
        if (!$slugWasGenerated && $uniqueSlug !== $normalizedSlug) {
            $fieldErrors['slug'] = true;
            $fieldErrorMessages['slug'] = 'Zadejte jiný unikátní slug, nebo pole nechte prázdné a CMS ho vytvoří z názvu.';
        } else {
            $normalizedSlug = $uniqueSlug;
        }
    }

    if ($fieldErrors !== [] && $error === '') {
        $error = 'Štítek blogu nejde uložit. U zvýrazněných polí je konkrétní nápověda.';
    }

    if ($error === '' && $fieldErrors === []) {
        if ($updateId !== null) {
            $existingTagForRedirect = null;
            $existingTagStmt = $pdo->prepare(
                "SELECT id, name, slug, blog_id
                 FROM cms_tags
                 WHERE id = ? AND blog_id = ?
                 LIMIT 1"
            );
            $existingTagStmt->execute([$updateId, $blogId]);
            $existingTagForRedirect = $existingTagStmt->fetch() ?: null;

            $pdo->prepare(
                "UPDATE cms_tags
                 SET name = ?, slug = ?, description = ?, meta_title = ?, meta_description = ?
                 WHERE id = ? AND blog_id = ?"
            )->execute([$name, $normalizedSlug, $description, $metaTitle, $metaDescription, $updateId, $blogId]);
            if ($existingTagForRedirect && $currentBlog && blogTagSlug((string)($existingTagForRedirect['slug'] ?? '')) !== '') {
                $updatedTagForRedirect = $existingTagForRedirect;
                $updatedTagForRedirect['name'] = $name;
                $updatedTagForRedirect['slug'] = $normalizedSlug;
                upsertPathRedirect(
                    $pdo,
                    blogTagPath($currentBlog, $existingTagForRedirect),
                    blogTagPath($currentBlog, $updatedTagForRedirect),
                    301
                );
            }
            logAction('tag_edit', 'id=' . $updateId . ' name=' . $name);
            $editId = null;
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO cms_tags (name, slug, blog_id, description, meta_title, meta_description)
                     VALUES (?, ?, ?, ?, ?, ?)"
                )->execute([$name, $normalizedSlug, $blogId, $description, $metaTitle, $metaDescription]);
                logAction('tag_add', 'name=' . $name . ' blog_id=' . $blogId);
            } catch (\PDOException $e) {
                $error = 'Štítek blogu nejde uložit, protože název nebo slug už v tomto blogu existuje. U zvýrazněných polí je konkrétní nápověda.';
                $fieldErrors['name'] = true;
                $fieldErrors['slug'] = true;
                $fieldErrorMessages['name'] = 'Zadejte jiný název štítku, který se v tomto blogu ještě nepoužívá.';
                $fieldErrorMessages['slug'] = 'Zadejte jiný unikátní slug, nebo pole nechte prázdné a CMS ho vytvoří z názvu.';
            }
        }
        if ($error === '') {
            $success = true;
            $formValues = [
                'name' => '',
                'slug' => '',
                'description' => '',
                'meta_title' => '',
                'meta_description' => '',
            ];
        }
    }
}

if ($deleteConfirmError) {
    $error = 'Štítek blogu nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.';
}
$successMessage = $success ? 'Štítek uložen.' : '';
if (trim((string)($_GET['deleted'] ?? '')) === '1') {
    $successMessage = 'Štítek byl smazán.';
}
$createFormHasError = $error !== '' && $editId === null && !$deleteConfirmError;

$tagStmt = $pdo->prepare(
    "SELECT t.id, t.name, t.slug, t.description, t.meta_title, t.meta_description,
            COUNT(DISTINCT a.id) AS article_count
     FROM cms_tags t
     LEFT JOIN cms_article_tags at ON at.tag_id = t.id
     LEFT JOIN cms_articles a ON a.id = at.article_id AND a.deleted_at IS NULL
     WHERE t.blog_id = ?
     GROUP BY t.id, t.name, t.slug, t.description, t.meta_title, t.meta_description
     ORDER BY t.name"
);
$tagStmt->execute([$blogId]);
$tags = $tagStmt->fetchAll();

adminHeader('Štítky blogu' . (isMultiBlog() && $currentBlog ? ' – ' . $currentBlog['name'] : ''));
?>

<?php if ($successMessage !== ''): ?><p class="success" role="status"><?= h($successMessage) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p id="form-error" class="error" role="alert" aria-atomic="true"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="blog.php?blog=<?= (int)$blogId ?>"><span aria-hidden="true">←</span> Zpět na články</a>
  <a href="blogs.php">Správa blogů</a>
  <a href="blog_members.php?blog_id=<?= (int)$blogId ?>">Tým blogu</a>
  <a href="blog_cats.php?blog_id=<?= (int)$blogId ?>">Kategorie blogu</a>
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

<form method="post" novalidate<?= $createFormHasError ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="blog_id" value="<?= $blogId ?>">
  <fieldset>
    <legend>Nový štítek</legend>
    <p id="tag-slug-help">Slug je veřejná část URL. Když ho necháte prázdný, vygeneruje se z názvu.</p>
    <p id="tag-description-help">Popis se zobrazí na veřejné stránce štítku nad výpisem článků.</p>
    <div class="form-grid">
      <div>
        <label for="name">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" required aria-required="true" maxlength="100"
               value="<?= h($formValues['name']) ?>" aria-describedby="tag-name-help<?= isset($fieldErrors['name']) ? ' tag-name-error' : '' ?>"<?= isset($fieldErrors['name']) ? ' aria-invalid="true"' : '' ?>>
        <small id="tag-name-help" class="field-help">Použijte krátký štítek pro téma článků, například Rozhovory.</small>
        <?php if (isset($fieldErrors['name'])): ?><p id="tag-name-error" class="error"><?= h($fieldErrorMessages['name']) ?></p><?php endif; ?>
      </div>
      <div>
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" maxlength="100"
               value="<?= h($formValues['slug']) ?>" aria-describedby="tag-slug-help<?= isset($fieldErrors['slug']) ? ' tag-slug-error' : '' ?>"<?= isset($fieldErrors['slug']) ? ' aria-invalid="true"' : '' ?>>
        <?php if (isset($fieldErrors['slug'])): ?><p id="tag-slug-error" class="error"><?= h($fieldErrorMessages['slug']) ?></p><?php endif; ?>
      </div>
      <div>
        <label for="meta_title">Meta title</label>
        <input type="text" id="meta_title" name="meta_title" maxlength="160" value="<?= h($formValues['meta_title']) ?>">
      </div>
    </div>
    <div>
      <label for="description">Popis</label>
      <textarea id="description" name="description" rows="4" aria-describedby="tag-description-help"><?= h($formValues['description']) ?></textarea>
    </div>
    <div>
      <label for="meta_description">Meta description</label>
      <textarea id="meta_description" name="meta_description" rows="3"><?= h($formValues['meta_description']) ?></textarea>
    </div>
    <button type="submit" class="btn admin-action-row">Přidat štítek</button>
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
      <?php
        $tagId = (int)$tag['id'];
        $deleteConfirmField = 'confirm_blog_tag_delete_' . $tagId;
        $deleteConfirmId = 'confirm-blog-tag-delete-' . $tagId;
        $deleteReviewId = 'blog-tag-delete-review-' . $tagId;
        $deleteFieldErrorId = 'confirm-blog-tag-delete-' . $tagId . '-error';
        $deleteHasError = $deleteConfirmError && $deleteErrorId === $tagId;
        $deleteErrorFields = $deleteHasError ? [$deleteConfirmField] : [];
        ?>
      <tr>
        <td>
          <?php if ($editId === $tagId): ?>
            <?php
            $tagEditHasErrors = $fieldErrors !== [] && !$deleteConfirmError;
              $tagEditName = $tagEditHasErrors ? $formValues['name'] : (string)$tag['name'];
              $tagEditSlug = $tagEditHasErrors ? $formValues['slug'] : (string)$tag['slug'];
              $tagEditDescription = $tagEditHasErrors ? $formValues['description'] : (string)($tag['description'] ?? '');
              $tagEditMetaTitle = $tagEditHasErrors ? $formValues['meta_title'] : (string)($tag['meta_title'] ?? '');
              $tagEditMetaDescription = $tagEditHasErrors ? $formValues['meta_description'] : (string)($tag['meta_description'] ?? '');
              ?>
            <form method="post" class="form-stack" novalidate<?= $tagEditHasErrors ? ' aria-describedby="form-error"' : '' ?>>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= $tagId ?>">
              <input type="hidden" name="blog_id" value="<?= $blogId ?>">
              <div class="form-grid">
                <div>
                  <label for="edit-name-<?= $tagId ?>">Název <span aria-hidden="true">*</span></label>
                  <input type="text" id="edit-name-<?= $tagId ?>" name="name" required aria-required="true" maxlength="100"
                         value="<?= h($tagEditName) ?>" class="admin-input-auto" aria-describedby="edit-tag-name-help-<?= $tagId ?><?= isset($fieldErrors['name']) ? ' edit-tag-name-error-' . $tagId : '' ?>"<?= isset($fieldErrors['name']) ? ' aria-invalid="true"' : '' ?>>
                  <small id="edit-tag-name-help-<?= $tagId ?>" class="field-help">Použijte krátký štítek pro téma článků, například Rozhovory.</small>
                  <?php if (isset($fieldErrors['name'])): ?><p id="edit-tag-name-error-<?= $tagId ?>" class="error"><?= h($fieldErrorMessages['name']) ?></p><?php endif; ?>
                </div>
                <div>
                  <label for="edit-slug-<?= $tagId ?>">Slug</label>
                  <input type="text" id="edit-slug-<?= $tagId ?>" name="slug" maxlength="100"
                         value="<?= h($tagEditSlug) ?>" class="admin-input-auto" aria-describedby="edit-tag-slug-help-<?= $tagId ?><?= isset($fieldErrors['slug']) ? ' edit-tag-slug-error-' . $tagId : '' ?>"<?= isset($fieldErrors['slug']) ? ' aria-invalid="true"' : '' ?>>
                  <small id="edit-tag-slug-help-<?= $tagId ?>" class="field-help">Slug je veřejná část URL. Když ho necháte prázdný, vygeneruje se z názvu.</small>
                  <?php if (isset($fieldErrors['slug'])): ?><p id="edit-tag-slug-error-<?= $tagId ?>" class="error"><?= h($fieldErrorMessages['slug']) ?></p><?php endif; ?>
                </div>
                <div>
                  <label for="edit-meta-title-<?= $tagId ?>">Meta title</label>
                  <input type="text" id="edit-meta-title-<?= $tagId ?>" name="meta_title" maxlength="160"
                         value="<?= h($tagEditMetaTitle) ?>" class="admin-input-auto">
                </div>
              </div>
              <div>
                <label for="edit-description-<?= $tagId ?>">Popis</label>
                <textarea id="edit-description-<?= $tagId ?>" name="description" rows="4"><?= h($tagEditDescription) ?></textarea>
              </div>
              <div>
                <label for="edit-meta-description-<?= $tagId ?>">Meta description</label>
                <textarea id="edit-meta-description-<?= $tagId ?>" name="meta_description" rows="3"><?= h($tagEditMetaDescription) ?></textarea>
              </div>
              <p class="button-row button-row--start">
                <button type="submit" class="btn">Uložit</button>
                <a href="blog_tags.php?blog_id=<?= $blogId ?>">Zrušit</a>
              </p>
            </form>
          <?php else: ?>
            <?= h((string)$tag['name']) ?>
          <?php endif; ?>
        </td>
        <td><code><?= h((string)$tag['slug']) ?></code></td>
        <td class="actions">
          <?php if ($editId !== $tagId): ?>
            <a href="blog_tags.php?edit=<?= $tagId ?>&amp;blog_id=<?= $blogId ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <?php if ($currentBlog): ?>
            <a href="<?= h(blogTagPath($currentBlog, $tag)) ?>" class="btn" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
          <?php endif; ?>
          <form action="blog_tag_delete.php" method="post"
                class="admin-inline-form"
                novalidate<?= $deleteHasError ? ' aria-describedby="form-error"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= $tagId ?>">
            <fieldset class="admin-inline-fieldset">
              <legend class="sr-only">Smazání štítku blogu <?= h((string)$tag['name']) ?></legend>
              <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
                Smazání odebere štítek z <?= (int)$tag['article_count'] ?> článků. Články zůstanou zachované bez tohoto štítku.
              </p>
              <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
                <input
                  type="checkbox"
                  id="<?= h($deleteConfirmId) ?>"
                  name="<?= h($deleteConfirmField) ?>"
                  value="1"
                  required
                  aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
                Potvrzuji smazání tohoto štítku blogu.
              </label>
              <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před smazáním štítku potvrďte, že jste zkontrolovali dopad na články.', $deleteFieldErrorId); ?>
              <button type="submit" class="btn btn-danger"
                      data-confirm="Smazat štítek? Články zůstanou zachované bez tohoto štítku.">Smazat</button>
            </fieldset>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php adminFooter(); ?>
