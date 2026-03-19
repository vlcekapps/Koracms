<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id  = inputInt('get', 'id');

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_pages WHERE id = ?");
    $stmt->execute([$id]);
    $page = $stmt->fetch();
    if (!$page) {
        header('Location: ' . BASE_URL . '/admin/pages.php');
        exit;
    }
} else {
    $page = ['id' => null, 'title' => '', 'slug' => '', 'content' => '',
             'is_published' => 0, 'show_in_nav' => 0, 'nav_order' => 1];
}

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$pageTitle  = $id ? 'Upravit stránku' : 'Nová stránka';
adminHeader($pageTitle);
?>

<form method="post" action="<?= BASE_URL ?>/admin/page_save.php">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Vlastnosti stránky</legend>

    <label for="title">Název *</label>
    <input type="text" id="title" name="title" required aria-required="true"
           value="<?= h($page['title']) ?>">

    <label for="slug">Slug (URL) *</label>
    <input type="text" id="slug" name="slug" required aria-required="true"
           pattern="[a-z0-9\-]+" title="Pouze malá písmena, číslice a pomlčky"
           value="<?= h($page['slug']) ?>">
    <small>Automaticky vygenerován z názvu. Pouze malá písmena, číslice a pomlčky.</small>

    <label for="content">Obsah (HTML)</label>
    <textarea id="content" name="content"><?= h($page['content']) ?></textarea>

    <label style="font-weight:normal; margin-top:1rem">
      <input type="checkbox" name="is_published" value="1"
             <?= $page['is_published'] ? 'checked' : '' ?>>
      Publikováno
    </label>

    <label style="font-weight:normal; margin-top:.5rem">
      <input type="checkbox" name="show_in_nav" value="1"
             <?= $page['show_in_nav'] ? 'checked' : '' ?>>
      Zobrazit v navigaci
    </label>

    <label for="nav_order">Pořadí v navigaci</label>
    <input type="number" id="nav_order" name="nav_order" min="1" style="width:8rem"
           value="<?= (int)$page['nav_order'] ?>">

    <div style="margin-top:1.5rem">
      <button type="submit" class="btn">Uložit</button>
      <a href="<?= BASE_URL ?>/admin/pages.php" style="margin-left:1rem">Zrušit</a>
    </div>
  </fieldset>
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
    quill.root.innerHTML = ta.value;
    ta.closest('form').addEventListener('submit', function () {
        ta.value = quill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<script>
document.getElementById('title').addEventListener('input', function () {
    const slug = this.value
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
    document.getElementById('slug').value = slug;
});
</script>

<?php adminFooter(); ?>
