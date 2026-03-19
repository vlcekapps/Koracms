<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo     = db_connect();
$id      = inputInt('get', 'id');
$article = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_articles WHERE id = ?");
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) { header('Location: blog.php'); exit; }
}

$categories = $pdo->query("SELECT id, name FROM cms_categories ORDER BY name")->fetchAll();

$allTags = [];
$articleTagIds = [];
try {
    $allTags = $pdo->query("SELECT id, name FROM cms_tags ORDER BY name")->fetchAll();
    if ($id !== null) {
        $ts = $pdo->prepare("SELECT tag_id FROM cms_article_tags WHERE article_id = ?");
        $ts->execute([$id]);
        $articleTagIds = array_column($ts->fetchAll(), 'tag_id');
    }
} catch (\PDOException $e) {}

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';

// Formátování publish_at pro datetime-local input
$publishAtInput = '';
if (!empty($article['publish_at'])) {
    $publishAtInput = date('Y-m-d\TH:i', strtotime($article['publish_at']));
}

adminHeader($article ? 'Upravit článek' : 'Přidat článek');
?>

<form method="post" action="blog_save.php" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($article): ?>
    <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
  <?php endif; ?>

  <?php if ($article && !empty($article['author_id'])): ?>
    <?php
    // Načteme jméno autora
    try {
        $authorStmt = $pdo->prepare("SELECT first_name, last_name, nickname, email FROM cms_users WHERE id = ?");
        $authorStmt->execute([$article['author_id']]);
        $authorRow = $authorStmt->fetch();
        if ($authorRow) {
            $authorName = $authorRow['nickname'] !== '' ? $authorRow['nickname']
                        : trim($authorRow['first_name'] . ' ' . $authorRow['last_name']);
            if ($authorName === '') $authorName = $authorRow['email'];
        } else {
            $authorName = '–';
        }
    } catch (\PDOException $e) { $authorName = '–'; }
    ?>
    <p style="color:#555;font-size:.9rem;margin-bottom:1rem">
      Autor: <strong><?= h($authorName) ?></strong>
    </p>
  <?php elseif (!$article): ?>
    <p style="color:#555;font-size:.9rem;margin-bottom:1rem">
      Autor: <strong><?= h(currentUserDisplayName()) ?></strong>
    </p>
  <?php endif; ?>

  <label for="title">Titulek <span aria-hidden="true">*</span></label>
  <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
         value="<?= h($article['title'] ?? '') ?>">

  <label for="category_id">Kategorie</label>
  <select id="category_id" name="category_id">
    <option value="">– bez kategorie –</option>
    <?php foreach ($categories as $cat): ?>
      <option value="<?= (int)$cat['id'] ?>"
        <?= ((int)($article['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
        <?= h($cat['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <?php if (!empty($allTags)): ?>
  <fieldset style="margin-top:1rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Tagy</legend>
    <?php foreach ($allTags as $t): ?>
      <label style="display:inline-block;margin-right:1rem;font-weight:normal">
        <input type="checkbox" name="tags[]" value="<?= (int)$t['id'] ?>"
               <?= in_array((int)$t['id'], $articleTagIds, true) ? 'checked' : '' ?>>
        <?= h($t['name']) ?>
      </label>
    <?php endforeach; ?>
  </fieldset>
  <?php endif; ?>

  <label for="perex">Perex (krátký úvod)</label>
  <textarea id="perex" name="perex" rows="3"><?= h($article['perex'] ?? '') ?></textarea>

  <label for="content">Text článku <span aria-hidden="true">*</span></label>
  <textarea id="content" name="content" rows="15" required aria-required="true"><?= h($article['content'] ?? '') ?></textarea>

  <label for="image">
    Náhledový obrázek
    <?php if (!empty($article['image_file'])): ?>
      <small>(aktuální: <a href="<?= BASE_URL ?>/uploads/articles/<?= rawurlencode($article['image_file']) ?>"
             target="_blank"><?= h($article['image_file']) ?></a>)</small>
    <?php endif; ?>
  </label>
  <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
  <?php if (!empty($article['image_file'])): ?>
    <label style="font-weight:normal;margin-top:.3rem">
      <input type="checkbox" name="image_delete" value="1"> Smazat stávající obrázek
    </label>
  <?php endif; ?>

  <label for="publish_at">Plánované publikování <small>(prázdné = ihned)</small></label>
  <input type="datetime-local" id="publish_at" name="publish_at"
         style="width:auto" value="<?= h($publishAtInput) ?>">

  <fieldset style="margin-top:1.5rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>SEO / Open Graph <small>(nepovinné – ponechte prázdné pro automatické hodnoty)</small></legend>
    <label for="meta_title">Meta titulek</label>
    <input type="text" id="meta_title" name="meta_title" maxlength="160"
           value="<?= h($article['meta_title'] ?? '') ?>">

    <label for="meta_description">Meta popis</label>
    <textarea id="meta_description" name="meta_description" rows="2"
              style="min-height:0"><?= h($article['meta_description'] ?? '') ?></textarea>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit"><?= $article ? 'Uložit změny' : 'Přidat článek' ?></button>
    <?php if ($article && !empty($article['preview_token'])): ?>
      <a href="<?= BASE_URL ?>/blog/article.php?id=<?= (int)$article['id'] ?>&preview=<?= h($article['preview_token']) ?>"
         target="_blank" style="margin-left:1rem">Náhled</a>
    <?php elseif ($article): ?>
      <small style="margin-left:1rem;color:#666">(Uložte pro aktivaci odkazu „Náhled")</small>
    <?php endif; ?>
    <a href="blog.php" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
(function () {
    const ta = document.getElementById('content');
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem';
    wrapper.style.minHeight = '300px';
    ta.parentNode.insertBefore(wrapper, ta);
    ta.style.display = 'none';

    const quill = new Quill(wrapper, {
        theme: 'snow',
        modules: { toolbar: [
            [{ header: [2, 3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'code-block'],
            ['link', 'image'],
            ['clean']
        ]}
    });

    // Načteme existující obsah
    quill.root.innerHTML = ta.value;

    // Při odeslání formuláře synchronizujeme obsah
    ta.closest('form').addEventListener('submit', function () {
        ta.value = quill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
