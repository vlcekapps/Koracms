<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id = inputInt('get', 'id');
$article = null;

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

$categories = $pdo->query("SELECT id, name FROM cms_categories ORDER BY name")->fetchAll();
$allTags = [];
$articleTagIds = [];
try {
    $allTags = $pdo->query("SELECT id, name FROM cms_tags ORDER BY name")->fetchAll();
    if ($id !== null) {
        $tagStmt = $pdo->prepare("SELECT tag_id FROM cms_article_tags WHERE article_id = ?");
        $tagStmt->execute([$id]);
        $articleTagIds = array_column($tagStmt->fetchAll(), 'tag_id');
    }
} catch (\PDOException $e) {
}

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$err = trim($_GET['err'] ?? '');
$publishAtInput = '';
if (!empty($article['publish_at'])) {
    $publishAtInput = date('Y-m-d\TH:i', strtotime((string)$article['publish_at']));
}

adminHeader($article ? 'Upravit článek' : 'Přidat článek');
?>

<?php if ($err === 'slug'): ?>
  <p role="alert" class="error" id="form-error">Slug článku je povinný a musí být unikátní.</p>
<?php endif; ?>

<form method="post" action="blog_save.php" enctype="multipart/form-data" novalidate<?= $err === 'slug' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($article): ?>
    <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
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
    <small id="blog-slug-help" class="field-help">Používejte malá písmena, číslice a pomlčky.</small>

    <label for="category_id">Kategorie</label>
    <select id="category_id" name="category_id">
      <option value="">– bez kategorie –</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>" <?= ((int)($article['category_id'] ?? 0) === (int)$category['id']) ? 'selected' : '' ?>>
          <?= h($category['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </fieldset>

  <?php if (!empty($allTags)): ?>
  <fieldset style="margin-top:1rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Štítky článku</legend>
    <?php foreach ($allTags as $tag): ?>
      <label style="display:inline-block;margin-right:1rem;font-weight:normal">
        <input type="checkbox" name="tags[]" value="<?= (int)$tag['id'] ?>"
               <?= in_array((int)$tag['id'], $articleTagIds, true) ? 'checked' : '' ?>>
        <?= h($tag['name']) ?>
      </label>
    <?php endforeach; ?>
  </fieldset>
  <?php endif; ?>

  <fieldset>
    <legend>Text článku</legend>

    <label for="perex">Perex (krátký úvod)</label>
    <textarea id="perex" name="perex" rows="3"><?= h($article['perex'] ?? '') ?></textarea>

    <label for="content">Text článku <span aria-hidden="true">*</span></label>
    <textarea id="content" name="content" rows="15" required aria-required="true"<?= !$useWysiwyg ? ' aria-describedby="blog-content-help"' : '' ?>><?= h($article['content'] ?? '') ?></textarea>
    <?php if (!$useWysiwyg): ?><small id="blog-content-help" class="field-help">Podporuje HTML i Markdown syntaxi.</small><?php endif; ?>

    <label for="image">Náhledový obrázek</label>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp"
           aria-describedby="<?= !empty($article['image_file']) ? 'blog-image-current' : 'blog-image-help' ?>">
    <?php if (!empty($article['image_file'])): ?>
      <small id="blog-image-current" class="field-help">Aktuální obrázek: <a href="<?= BASE_URL ?>/uploads/articles/<?= rawurlencode((string)$article['image_file']) ?>"
             target="_blank" rel="noopener noreferrer"><?= h((string)$article['image_file']) ?></a>.</small>
    <?php else: ?>
      <small id="blog-image-help" class="field-help">Volitelné pole pro úvodní náhled článku.</small>
    <?php endif; ?>
    <?php if (!empty($article['image_file'])): ?>
      <label style="font-weight:normal;margin-top:.3rem">
        <input type="checkbox" name="image_delete" value="1"> Smazat stávající obrázek
      </label>
    <?php endif; ?>

    <label for="publish_at">Plánované publikování</label>
    <input type="datetime-local" id="publish_at" name="publish_at" aria-describedby="blog-publish-at-help"
           style="width:auto" value="<?= h($publishAtInput) ?>">
    <small id="blog-publish-at-help" class="field-help">Prázdné pole znamená publikování ihned.</small>
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

  <div style="margin-top:1.5rem">
    <button type="submit"><?= $article ? 'Uložit změny' : 'Přidat článek' ?></button>
    <a href="blog.php" style="margin-left:1rem">Zrušit</a>
    <?php if ($article && !empty($article['preview_token'])): ?>
      <a href="<?= h(articlePreviewPath($article)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Náhled</a>
    <?php elseif ($article): ?>
      <small style="margin-left:1rem;color:#666">(Uložte pro aktivaci odkazu „Náhled“)</small>
    <?php endif; ?>
  </div>
</form>

<script>
(function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    let slugManual = <?= $article && !empty($article['slug']) ? 'true' : 'false' ?>;

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    slugInput?.addEventListener('input', function () {
        slugManual = this.value.trim() !== '';
    });

    titleInput?.addEventListener('input', function () {
        if (slugManual || !slugInput) {
            return;
        }
        slugInput.value = slugify(this.value);
    });
})();
</script>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
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
