<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id = inputInt('get', 'id');
$article = null;

if ($id === null && !hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blog.php?msg=no_blog');
    exit;
}

if ($id !== null) {
    if (canManageOwnBlogOnly()) {
        $stmt = $pdo->prepare("SELECT * FROM cms_articles WHERE id = ? AND author_id = ?");
        $stmt->execute([$id, currentUserId()]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM cms_articles WHERE id = ?");
        $stmt->execute([$id]);
    }
    $article = $stmt->fetch();
    if (!$article) {
        header('Location: blog.php');
        exit;
    }
}

$allBlogs = getAllBlogs();
$currentBlogId = (int)($article['blog_id'] ?? ($_GET['blog_id'] ?? (getDefaultBlog()['id'] ?? 1)));
$currentBlog = getBlogById($currentBlogId) ?? getDefaultBlog();
$currentBlogId = (int)($currentBlog['id'] ?? $currentBlogId);
$articleListUrl = BASE_URL . '/admin/blog.php' . (isMultiBlog() ? '?blog=' . $currentBlogId : '');
$blogCategoriesUrl = BASE_URL . '/admin/blog_cats.php?blog_id=' . $currentBlogId;
$blogTagsUrl = BASE_URL . '/admin/blog_tags.php?blog_id=' . $currentBlogId;
$blogPublicUrl = $currentBlog ? blogIndexPath($currentBlog) : '';

$catStmt = $pdo->prepare("SELECT id, name FROM cms_categories WHERE blog_id = ? ORDER BY name");
$catStmt->execute([$currentBlogId]);
$categories = $catStmt->fetchAll();

$allTags = [];
$articleTagIds = [];
try {
    $tagStmt2 = $pdo->prepare("SELECT id, name FROM cms_tags WHERE blog_id = ? ORDER BY name");
    $tagStmt2->execute([$currentBlogId]);
    $allTags = $tagStmt2->fetchAll();
    if ($id !== null) {
        $tagStmt = $pdo->prepare("SELECT tag_id FROM cms_article_tags WHERE article_id = ?");
        $tagStmt->execute([$id]);
        $articleTagIds = array_column($tagStmt->fetchAll(), 'tag_id');
    }
} catch (\PDOException $e) {
    error_log('admin/blog_form tags: ' . $e->getMessage());
}

$blogFormOptions = [];
if (isMultiBlog()) {
    foreach ($allBlogs as $blogEntry) {
        $blogFormOptions[(int)$blogEntry['id']] = [
            'categories' => [],
            'tags' => [],
        ];
    }

    try {
        $allCategoriesStmt = $pdo->query("SELECT id, blog_id, name FROM cms_categories ORDER BY blog_id, name");
        foreach ($allCategoriesStmt->fetchAll() as $categoryRow) {
            $blogId = (int)($categoryRow['blog_id'] ?? 0);
            if (!isset($blogFormOptions[$blogId])) {
                continue;
            }
            $blogFormOptions[$blogId]['categories'][] = [
                'id' => (int)$categoryRow['id'],
                'name' => (string)$categoryRow['name'],
            ];
        }
    } catch (\PDOException $e) {
        error_log('admin/blog_form all categories: ' . $e->getMessage());
    }

    try {
        $allTagsStmt = $pdo->query("SELECT id, blog_id, name FROM cms_tags ORDER BY blog_id, name");
        foreach ($allTagsStmt->fetchAll() as $tagRow) {
            $blogId = (int)($tagRow['blog_id'] ?? 0);
            if (!isset($blogFormOptions[$blogId])) {
                continue;
            }
            $blogFormOptions[$blogId]['tags'][] = [
                'id' => (int)$tagRow['id'],
                'name' => (string)$tagRow['name'],
            ];
        }
    } catch (\PDOException $e) {
        error_log('admin/blog_form all tags: ' . $e->getMessage());
    }
}

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$err = trim($_GET['err'] ?? '');
$publishAtInput = '';
if (!empty($article['publish_at'])) {
    $publishAtInput = date('Y-m-d\TH:i', strtotime((string)$article['publish_at']));
}

$pageTitle = $article ? 'Upravit článek' : 'Přidat článek';
if (isMultiBlog() && $currentBlog) {
    $pageTitle .= ' – ' . (string)$currentBlog['name'];
}
adminHeader($pageTitle);
?>

<p class="button-row button-row--start">
  <a href="<?= h($articleListUrl) ?>"><span aria-hidden="true">←</span> Zpět na články</a>
  <?php if (isMultiBlog() && $currentBlog): ?>
    <a id="blog-link-categories" href="<?= h($blogCategoriesUrl) ?>">Kategorie blogu</a>
    <a id="blog-link-tags" href="<?= h($blogTagsUrl) ?>">Štítky blogu</a>
    <a id="blog-link-public" href="<?= h($blogPublicUrl) ?>" target="_blank" rel="noopener">Zobrazit blog na webu</a>
  <?php endif; ?>
</p>

<?php if (isMultiBlog() && $currentBlog): ?>
  <p class="field-help" id="blog-form-context">
    Článek právě patří do blogu <strong id="blog-context-name"><?= h((string)$currentBlog['name']) ?></strong>.
    Pokud blog změníte, upraví se i dostupné kategorie, štítky a odkazy nahoře.
  </p>
<?php endif; ?>

<?php if ($article): ?>
  <p>
    <a href="revisions.php?type=article&amp;id=<?= (int)$article['id'] ?>">Historie revizí</a>
    ·
    <form action="convert_content.php" method="post" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="direction" value="article_to_page">
      <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
      <button type="submit" class="btn"
              data-confirm="Převést článek na statickou stránku? Článek bude smazán a nahrazen stránkou.">Převést na stránku</button>
    </form>
  </p>
<?php endif; ?>

<?php if ($err === 'slug'): ?>
  <p role="alert" class="error" id="form-error">Slug článku je povinný a musí být unikátní.</p>
<?php endif; ?>

<form method="post" action="blog_save.php" enctype="multipart/form-data" novalidate<?= $err === 'slug' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="redirect" id="blog-redirect" value="<?= h($articleListUrl) ?>">
  <?php if ($article): ?>
    <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
  <?php endif; ?>

  <?php if (isMultiBlog()): ?>
    <label for="blog_id">Blog</label>
    <select id="blog_id" name="blog_id" aria-describedby="blog-id-help blog-form-context">
      <?php foreach ($allBlogs as $b): ?>
        <option value="<?= (int)$b['id'] ?>"<?= (int)$b['id'] === $currentBlogId ? ' selected' : '' ?>><?= h((string)$b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <small id="blog-id-help" class="field-help">Po změně blogu se kategorie a štítky upraví rovnou ve formuláři bez obnovení stránky.</small>
  <?php else: ?>
    <input type="hidden" name="blog_id" value="<?= $currentBlogId ?>">
  <?php endif; ?>

  <?php if ($article && !empty($article['author_id'])): ?>
    <?php
    try {
        $authorStmt = $pdo->prepare("SELECT first_name, last_name, nickname, email FROM cms_users WHERE id = ?");
        $authorStmt->execute([(int)$article['author_id']]);
        $authorRow = $authorStmt->fetch();
        if ($authorRow) {
            $authorName = $authorRow['nickname'] !== '' ? $authorRow['nickname'] : trim($authorRow['first_name'] . ' ' . $authorRow['last_name']);
            if ($authorName === '') {
                $authorName = $authorRow['email'];
            }
        } else {
            $authorName = '–';
        }
    } catch (\PDOException $e) {
        $authorName = '–';
    }
    ?>
    <p style="color:#555;font-size:.9rem;margin-bottom:1rem">
      Autor: <strong><?= h($authorName) ?></strong>
    </p>
  <?php elseif (!$article): ?>
    <p style="color:#555;font-size:.9rem;margin-bottom:1rem">
      Autor: <strong><?= h(currentUserDisplayName()) ?></strong>
    </p>
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje článku</legend>

    <label for="title">Titulek <span aria-hidden="true">*</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h($article['title'] ?? '') ?>">

    <label for="slug">Slug (URL článku) <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           aria-describedby="blog-slug-help"
           value="<?= h($article['slug'] ?? '') ?>">
    <small id="blog-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Použijte malá písmena, číslice a pomlčky.</small>

    <label for="category_id">Kategorie</label>
    <select id="category_id" name="category_id"<?= isMultiBlog() ? ' aria-describedby="blog-category-help"' : '' ?>>
      <option value="">– bez kategorie –</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>" <?= ((int)($article['category_id'] ?? 0) === (int)$category['id']) ? 'selected' : '' ?>>
          <?= h($category['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php if (isMultiBlog()): ?>
      <small id="blog-category-help" class="field-help">Nabídka kategorií odpovídá právě vybranému blogu.</small>
    <?php endif; ?>
  </fieldset>

  <fieldset id="article-tags-fieldset" style="margin-top:1rem;border:1px solid #ccc;padding:.5rem 1rem"<?= empty($allTags) ? ' hidden' : '' ?><?= isMultiBlog() ? ' aria-describedby="blog-tags-help"' : '' ?>>
    <legend>Štítky článku</legend>
    <div id="article-tags-options">
      <?php foreach ($allTags as $tag): ?>
        <label style="display:inline-block;margin-right:1rem;font-weight:normal">
          <input type="checkbox" name="tags[]" value="<?= (int)$tag['id'] ?>"
                 <?= in_array((int)$tag['id'], $articleTagIds, true) ? 'checked' : '' ?>>
          <?= h($tag['name']) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <?php if (isMultiBlog()): ?>
      <small id="blog-tags-help" class="field-help">Dostupné štítky se mění podle vybraného blogu.</small>
    <?php endif; ?>
  </fieldset>

  <fieldset>
    <legend>Text článku</legend>

    <label for="perex">Perex (krátký úvod)</label>
    <textarea id="perex" name="perex" rows="3"><?= h($article['perex'] ?? '') ?></textarea>

    <label for="content">Text článku <span aria-hidden="true">*</span></label>
    <textarea id="content" name="content" rows="15" required aria-required="true"<?= !$useWysiwyg ? ' aria-describedby="blog-content-help"' : '' ?>><?= h($article['content'] ?? '') ?></textarea>
    <?php if (!$useWysiwyg): ?><small id="blog-content-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small><?php endif; ?>
    <?php if (!$useWysiwyg): ?>
      <?php renderAdminContentReferencePicker('content'); ?>
    <?php endif; ?>

    <label for="image">Náhledový obrázek</label>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp"
           aria-describedby="<?= !empty($article['image_file']) ? 'blog-image-current' : 'blog-image-help' ?>">
    <?php if (!empty($article['image_file'])): ?>
      <small id="blog-image-current" class="field-help">Aktuální obrázek: <a href="<?= BASE_URL ?>/uploads/articles/<?= rawurlencode((string)$article['image_file']) ?>"
             target="_blank" rel="noopener noreferrer"><?= h((string)$article['image_file']) ?></a>.</small>
    <?php else: ?>
      <small id="blog-image-help" class="field-help">Volitelné. Hodí se pro úvodní náhled článku.</small>
    <?php endif; ?>
    <?php if (!empty($article['image_file'])): ?>
      <label style="font-weight:normal;margin-top:.3rem">
        <input type="checkbox" name="image_delete" value="1"> Smazat stávající obrázek
      </label>
    <?php endif; ?>

    <label for="publish_at">Plánované publikování</label>
    <input type="datetime-local" id="publish_at" name="publish_at" aria-describedby="blog-publish-at-help"
           style="width:auto" value="<?= h($publishAtInput) ?>">
    <small id="blog-publish-at-help" class="field-help">Nechte prázdné, pokud se má článek zveřejnit hned.</small>

    <label for="unpublish_at">Plánované zrušení publikace</label>
    <input type="datetime-local" id="unpublish_at" name="unpublish_at" aria-describedby="blog-unpublish-at-help"
           style="width:auto" value="<?= h(!empty($article['unpublish_at']) ? date('Y-m-d\TH:i', strtotime((string)$article['unpublish_at'])) : '') ?>">
    <small id="blog-unpublish-at-help" class="field-help">Volitelné. Článek se v zadaný čas automaticky skryje z veřejného webu.</small>
  </fieldset>

  <fieldset style="margin-top:1rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Komentáře</legend>
    <div>
      <input type="checkbox" id="comments_enabled" name="comments_enabled" value="1" aria-describedby="blog-comments-help"
             <?= (int)($article['comments_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
      <label for="comments_enabled" style="display:inline;font-weight:normal">
        Povolit komentáře u tohoto článku
      </label>
    </div>
    <small id="blog-comments-help" class="field-help">Globální pravidla moderace nastavíte v základním nastavení webu.</small>
  </fieldset>

  <fieldset style="margin-top:1.5rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Vyhledávače a sdílení</legend>
    <small id="blog-seo-help" class="field-help" style="margin-top:0">Nepovinné. Ponechte prázdné pro automatické hodnoty.</small>
    <label for="meta_title">Meta titulek</label>
    <input type="text" id="meta_title" name="meta_title" maxlength="160" aria-describedby="blog-seo-help"
           value="<?= h($article['meta_title'] ?? '') ?>">

    <label for="meta_description">Meta popis</label>
    <textarea id="meta_description" name="meta_description" rows="2" aria-describedby="blog-seo-help"
              style="min-height:0"><?= h($article['meta_description'] ?? '') ?></textarea>
  </fieldset>

  <fieldset style="margin-top:1rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Interní poznámka</legend>
    <label for="admin_note" class="visually-hidden">Interní poznámka</label>
    <textarea id="admin_note" name="admin_note" rows="2" aria-describedby="admin-note-help"
              style="min-height:0"><?= h($article['admin_note'] ?? '') ?></textarea>
    <small id="admin-note-help" class="field-help">Viditelná jen v administraci. Na veřejném webu se nezobrazuje.</small>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit"><?= $article ? 'Uložit změny' : 'Přidat článek' ?></button>
    <a href="<?= h($articleListUrl) ?>" style="margin-left:1rem">Zrušit</a>
    <?php if ($article && !empty($article['preview_token'])): ?>
      <a href="<?= h(articlePreviewPath($article)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Náhled</a>
    <?php elseif ($article): ?>
      <small style="margin-left:1rem;color:#666">(Uložte pro aktivaci odkazu „Náhled“)</small>
    <?php endif; ?>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    const blogSelect = document.getElementById('blog_id');
    const redirectInput = document.getElementById('blog-redirect');
    const contextName = document.getElementById('blog-context-name');
    const categoryLink = document.getElementById('blog-link-categories');
    const tagLink = document.getElementById('blog-link-tags');
    const publicLink = document.getElementById('blog-link-public');
    const categorySelect = document.getElementById('category_id');
    const tagsFieldset = document.getElementById('article-tags-fieldset');
    const tagsContainer = document.getElementById('article-tags-options');
    const blogOptions = <?= json_encode($blogFormOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const blogMeta = <?= json_encode(array_values(array_map(static function (array $blogEntry): array {
        return [
            'id' => (int)$blogEntry['id'],
            'name' => (string)$blogEntry['name'],
            'publicUrl' => blogIndexPath($blogEntry),
        ];
    }, $allBlogs)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const blogMetaById = Object.fromEntries(blogMeta.map((blogEntry) => [String(blogEntry.id), blogEntry]));
    let slugManual = <?= $article && !empty($article['slug']) ? 'true' : 'false' ?>;
    const rememberedSelections = {
        '<?= (int)$currentBlogId ?>': {
            categoryId: '<?= (int)($article['category_id'] ?? 0) ?>' !== '0' ? '<?= (int)($article['category_id'] ?? 0) ?>' : '',
            tags: <?= json_encode(array_map('intval', $articleTagIds)) ?>,
        }
    };

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const rememberCurrentSelections = () => {
        if (!blogSelect || !categorySelect || !tagsContainer) {
            return;
        }

        rememberedSelections[blogSelect.value] = {
            categoryId: categorySelect.value,
            tags: Array.from(tagsContainer.querySelectorAll('input[name="tags[]"]:checked')).map((input) => Number(input.value)),
        };
    };

    const renderBlogOptions = (blogId) => {
        if (!categorySelect || !tagsContainer || !tagsFieldset || !blogOptions[blogId]) {
            return;
        }

        const state = rememberedSelections[blogId] || { categoryId: '', tags: [] };
        const selectedTags = new Set((state.tags || []).map((value) => Number(value)));
        const categoryMarkup = ['<option value="">â€“ bez kategorie â€“</option>'];

        (blogOptions[blogId].categories || []).forEach((category) => {
            const selected = String(category.id) === String(state.categoryId) ? ' selected' : '';
            categoryMarkup.push('<option value="' + String(category.id) + '"' + selected + '>' + String(category.name) + '</option>');
        });
        categorySelect.innerHTML = categoryMarkup.join('');

        const tags = blogOptions[blogId].tags || [];
        if (tags.length === 0) {
            tagsContainer.innerHTML = '';
            tagsFieldset.hidden = true;
            return;
        }

        tagsFieldset.hidden = false;
        tagsContainer.innerHTML = tags.map((tag) => {
            const checked = selectedTags.has(Number(tag.id)) ? ' checked' : '';
            return '<label style="display:inline-block;margin-right:1rem;font-weight:normal">'
                + '<input type="checkbox" name="tags[]" value="' + String(tag.id) + '"' + checked + '>'
                + ' ' + String(tag.name)
                + '</label>';
        }).join('');

        const selectedBlog = blogMetaById[String(blogId)] || null;
        if (contextName && selectedBlog) {
            contextName.textContent = selectedBlog.name;
        }
        if (redirectInput) {
            redirectInput.value = '<?= h(BASE_URL . '/admin/blog.php') ?>' + '?blog=' + encodeURIComponent(String(blogId));
        }
        if (categoryLink) {
            categoryLink.href = '<?= h(BASE_URL . '/admin/blog_cats.php?blog_id=') ?>' + encodeURIComponent(String(blogId));
        }
        if (tagLink) {
            tagLink.href = '<?= h(BASE_URL . '/admin/blog_tags.php?blog_id=') ?>' + encodeURIComponent(String(blogId));
        }
        if (publicLink && selectedBlog) {
            publicLink.href = selectedBlog.publicUrl;
        }
    };

    slugInput?.addEventListener('input', function () {
        slugManual = this.value.trim() !== '';
    });

    titleInput?.addEventListener('input', function () {
        if (slugManual || !slugInput) {
            return;
        }
        slugInput.value = slugify(this.value);
    });

    if (blogSelect) {
        blogSelect.addEventListener('change', function () {
            rememberCurrentSelections();
            renderBlogOptions(this.value);
        });
    }

    categorySelect?.addEventListener('change', rememberCurrentSelections);
    tagsContainer?.addEventListener('change', rememberCurrentSelections);
})();
</script>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script nonce="<?= cspNonce() ?>">
(function () {
    const textarea = document.getElementById('content');
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem;min-height:300px';
    textarea.parentNode.insertBefore(wrapper, textarea);
    textarea.style.display = 'none';

    const quill = new Quill(wrapper, {
        theme: 'snow',
        modules: { toolbar: [
            [{ header: [2, 3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'code-block'],
            ['link', 'image'],
            ['clean']
        ] }
    });

    quill.root.innerHTML = textarea.value;

    textarea.closest('form')?.addEventListener('submit', function () {
        textarea.value = quill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
